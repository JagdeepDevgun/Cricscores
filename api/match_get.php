<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/_helpers.php'; 

// PERFORMANCE: Close session immediately.
if (session_id()) session_write_close();

header('Content-Type: application/json');

$match_id = (int)($_GET['match_id'] ?? 0);
$include_balls = (int)($_GET['include_balls'] ?? 0);

if ($match_id <= 0) { 
    http_response_code(400); 
    echo json_encode(['error' => 'match_id required']); 
    exit; 
}

// --- HELPER: Get Scorecard ---
function get_scorecard(PDO $pdo, int $innings_id) {
    $bat = $pdo->prepare("
        SELECT 
            p.id, p.name, p.is_captain, 
            SUM(CASE WHEN b.striker_id = p.id THEN b.runs_bat ELSE 0 END) as runs, 
            COUNT(CASE WHEN b.striker_id = p.id THEN 1 ELSE NULL END) as balls, 
            SUM(CASE WHEN b.striker_id = p.id AND b.runs_bat=4 THEN 1 ELSE 0 END) as fours, 
            SUM(CASE WHEN b.striker_id = p.id AND b.runs_bat=6 THEN 1 ELSE 0 END) as sixes, 
            MAX(CASE 
                WHEN b.is_wicket=1 AND p.id = COALESCE(b.wicket_player_out_id, b.striker_id)
                THEN b.wicket_type 
                ELSE NULL 
            END) as dismissal,
            MIN(b.seq) as entry_seq
        FROM ball_events b
        JOIN players p ON (p.id = b.striker_id OR p.id = b.non_striker_id)
        WHERE b.innings_id = ?
        GROUP BY p.id
        ORDER BY entry_seq ASC
    ");
    $bat->execute([$innings_id]);
    
    // UPDATED BOWLING QUERY: Includes Wides and No-Balls
    $bowl = $pdo->prepare("
        SELECT p.id, p.name, p.is_captain,
               COUNT(CASE WHEN b.is_legal=1 THEN 1 END) as legal_balls,
               COUNT(CASE WHEN b.extras_type='wd' THEN 1 END) as wides,    
               COUNT(CASE WHEN b.extras_type='nb' THEN 1 END) as no_balls, 
               SUM(b.runs_bat + b.extras_runs) as runs_conceded,
               COUNT(CASE WHEN b.is_wicket=1 AND b.wicket_type != 'run out' THEN 1 END) as wickets
        FROM ball_events b
        JOIN players p ON p.id = b.bowler_id
        WHERE b.innings_id = ?
        GROUP BY p.id
        ORDER BY MIN(b.seq) ASC
    ");
    $bowl->execute([$innings_id]);
    
    return ['batsmen' => $bat->fetchAll(), 'bowlers' => $bowl->fetchAll()];
}

// --- HELPER: Detailed Data ---
function get_detailed_data(PDO $pdo, int $innings_id) {
    $sql = "SELECT b.*, 
                   pb.name as bowler_name, 
                   ps.name as striker_name,
                   po.name as player_out_name
            FROM ball_events b 
            LEFT JOIN players pb ON pb.id = b.bowler_id 
            LEFT JOIN players ps ON ps.id = b.striker_id 
            LEFT JOIN players po ON po.id = b.wicket_player_out_id
            WHERE b.innings_id=? 
            ORDER BY b.seq ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$innings_id]);
    $balls = $stmt->fetchAll();

    $commentary = [];
    $graphData = [ ['x'=>0, 'y'=>0] ]; 
    $wicketPoints = [];
    $oversHistory = [];
    $fow = [];
    
    $totalRuns = 0;
    $legalBalls = 0;
    $wicketsDown = 0;
    $p_runs = 0; $p_balls = 0;
    
    foreach ($balls as $b) {
        $runs = (int)$b['runs_bat'] + (int)$b['extras_runs'];
        $totalRuns += $runs;
        $p_runs += $runs;
        $p_balls++; 

        $is_legal = (int)$b['is_legal'];
        if ($is_legal) $legalBalls++;
        
        if ($is_legal && $legalBalls % 6 === 0) {
            $graphData[] = ['x' => $legalBalls/6, 'y' => $totalRuns];
        }

        if ($b['is_wicket']) {
            $wicketsDown++;
            $wX = ($legalBalls > 0) ? (intdiv($legalBalls-1, 6) + (($legalBalls-1)%6 + 1)/6.0) : 0;
            if ($is_legal && $legalBalls%6==0) $wX = $legalBalls/6;
            
            $wicketPoints[] = ['x' => $wX, 'y' => $totalRuns, 'desc' => $b['wicket_type']];
            
            $pName = $b['player_out_name'];
            if (!$pName && $b['striker_id']) $pName = $b['striker_name'];
            
            $fow[] = [
                'score' => $totalRuns, 'wicket' => $wicketsDown, 'player' => $pName ?: 'Unknown',
                'partnership_runs' => $p_runs, 'partnership_balls' => $p_balls,
                'over' => intdiv(max(0,$legalBalls-1), 6) . '.' . (max(0,$legalBalls-1)%6 + 1)
            ];
            $p_runs = 0; $p_balls = 0;
        }

        $currentOverIndex = intdiv(max(0, $legalBalls - ($is_legal?1:0)), 6) + 1;
        if (!isset($oversHistory[$currentOverIndex])) {
            $oversHistory[$currentOverIndex] = ['over'=>$currentOverIndex, 'bowler'=>$b['bowler_name']??'Unknown', 'balls'=>[]];
        }
        $lbl = (string)$b['runs_bat'];
        if ($b['extras_type']) {
            $lbl = strtoupper($b['extras_type']);
            if ($b['extras_runs'] > 1 && !in_array($b['extras_type'],['wd','nb'])) $lbl = $b['extras_runs'].$lbl;
        }
        if ($b['is_wicket']) $lbl .= 'W';
        $oversHistory[$currentOverIndex]['balls'][] = ['label'=>$lbl, 'is_legal'=>$is_legal];

        // Commentary
        $bowler = $b['bowler_name'] ?? 'Unknown';
        $batter = $b['striker_name'] ?? 'Unknown';
        $prefix = "$bowler to $batter, ";

        $eventText = "";
        if ($b['is_wicket']) $eventText = "<b>WICKET!</b> " . ($b['wicket_type'] ?? 'Out');
        elseif ($b['runs_bat'] == 4) $eventText = "<b>FOUR!</b>";
        elseif ($b['runs_bat'] == 6) $eventText = "<b>SIX!</b>";
        elseif ($b['extras_type']) $eventText = strtoupper($b['extras_type']) . " (" . $b['extras_runs'] . ")";
        else $eventText = ($runs === 0 ? "no run" : "$runs runs");
        
        $txt = $prefix . $eventText;
        
        $ovStr = intdiv(max(0,$legalBalls-1), 6) . '.' . (max(0,$legalBalls-1)%6 + 1);
        
        // ADDED: Pass all raw details so frontend can open edit modal
        $commentary[] = [
            'id' => (int)$b['id'], // Critical for Editing
            'over' => $ovStr, 
            'text' => $txt, 
            'runs' => $runs, // Total runs on this ball
            'runs_bat' => (int)$b['runs_bat'],
            'extras_type' => $b['extras_type'],
            'extras_runs' => (int)$b['extras_runs'],
            'is_wicket' => (int)$b['is_wicket'],
            'wicket_type' => $b['wicket_type']
        ];
    }
    
    if ($legalBalls % 6 !== 0) {
        $lastX = intdiv($legalBalls, 6) + (($legalBalls % 6) / 6.0);
        $graphData[] = ['x' => $lastX, 'y' => $totalRuns];
    }

    return [
        'comm' => array_reverse($commentary), 'graph' => $graphData, 'wickets' => $wicketPoints,
        'overs_history' => array_values($oversHistory), 'last_ball' => end($balls),
        'fow' => $fow, 'current_partnership' => ['runs'=>$p_runs, 'balls'=>$p_balls]
    ];
}

// --- MAIN LOGIC ---
$stmt = $pdo->prepare("
    SELECT m.*, 
           ta.name AS team_a, ta.short_name AS team_a_short, ta.icon AS team_a_icon,
           tb.name AS team_b, tb.short_name AS team_b_short, tb.icon AS team_b_icon,
           p.name as mom_name 
    FROM matches m 
    JOIN teams ta ON ta.id=m.team_a_id 
    JOIN teams tb ON tb.id=m.team_b_id 
    LEFT JOIN players p ON p.id = m.man_of_match_id 
    WHERE m.id=?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch();
if (!$match) { echo json_encode(['error'=>'Match not found']); exit; }

$innStmt = $pdo->prepare("SELECT * FROM innings WHERE match_id=? ORDER BY innings_no ASC");
$innStmt->execute([$match_id]);
$innings = $innStmt->fetchAll();

$pStmt = $pdo->prepare("SELECT id, name, team_id, is_captain FROM players WHERE team_id IN (?,?) ORDER BY name");
$pStmt->execute([$match['team_a_id'], $match['team_b_id']]);
$players = $pStmt->fetchAll();

$out = ['match'=>$match, 'innings'=>[], 'players'=>$players];
$inn1 = null; $inn2 = null;

foreach ($innings as $i) {
    // This will now use the optimized cache logic
    $stats = innings_totals($pdo, (int)$i['id']);
    
    $ballStmt = $pdo->prepare('SELECT runs_bat, extras_type, extras_runs, is_wicket FROM ball_events WHERE innings_id=? ORDER BY seq ASC');
    $ballStmt->execute([$i['id']]);
    $allBalls = $ballStmt->fetchAll();
    
    $recent = [];
    foreach(array_slice($allBalls, -12) as $b){
        $lbl = (string)$b['runs_bat'];
        if($b['extras_type']) $lbl = strtoupper($b['extras_type']);
        if($b['is_wicket']) $lbl .= 'W';
        $recent[] = $lbl;
    }

    $details = get_detailed_data($pdo, (int)$i['id']);
    $scorecard = get_scorecard($pdo, (int)$i['id']);
    
    $bt = $pdo->query('SELECT name FROM teams WHERE id='.(int)$i['batting_team_id'])->fetchColumn() ?: 'Unknown Team';
    $crr = ($stats['overs_float'] > 0) ? round($stats['runs'] / $stats['overs_float'], 2) : 0.00;

    $innData = [
        'id'=>(int)$i['id'], 'innings_no'=>(int)$i['innings_no'],
        'batting_team_id'=>(int)$i['batting_team_id'], 'batting_team'=>(string)$bt,
        'target'=>($i['target'] !== null ? (int)$i['target'] : null),
        'completed'=>(int)$i['completed'],
        'summary'=> array_merge($stats, ['recent_balls' => $recent, 'rr' => $crr]),
        'last_ball' => $details['last_ball'],
        'graph_data' => $details['graph'],
        'wickets_data' => $details['wickets'],
        'overs_history' => $details['overs_history'],
        'scorecard' => $scorecard,
        'commentary' => $details['comm'],
        'fow' => $details['fow'],
        'current_partnership' => $details['current_partnership']
    ];
    
    $out['innings'][] = $innData;
    if ($i['innings_no'] == 1) $inn1 = $innData;
    if ($i['innings_no'] == 2) $inn2 = $innData;
}

if ($match['status'] === 'awaiting_super_over') {
    $out['match']['result_text'] = "MATCH TIED (Super Over?)";
    $out['match']['result_type'] = 'tie';
} 
elseif ($match['status'] === 'completed') {
    if ($match['result_type'] === 'tie') {
        $out['match']['result_text'] = "MATCH TIED";
    } elseif ($match['result_type'] === 'nr') {
        $out['match']['result_text'] = "NO RESULT";
    } elseif ($match['winner_team_id']) {
        $wID = (int)$match['winner_team_id'];
        $text = "";
        
        if ((int)$match['super_over'] === 1) {
            $wName = ($wID == $match['team_a_id']) ? $match['team_a'] : $match['team_b'];
            $text = "$wName won (Super Over)";
        } elseif ($inn1 && $inn2) {
            if ($wID == $inn1['batting_team_id']) {
                $margin = $inn1['summary']['runs'] - $inn2['summary']['runs'];
                $text = "{$inn1['batting_team']} won by $margin runs";
            } else {
                $wktLimit = (int)$match['wickets_limit'] > 0 ? (int)$match['wickets_limit'] : 10;
                $margin = $wktLimit - $inn2['summary']['wkts'];
                $text = "{$inn2['batting_team']} won by $margin wickets";
            }
        }
        if (!$text) { 
            $wName = ($wID == $match['team_a_id']) ? $match['team_a'] : $match['team_b']; 
            $text = "$wName won"; 
        }
        $out['match']['result_text'] = $text;
    }
}

$out['chase'] = null;
foreach ($out['innings'] as $inx) {
  if (($inx['innings_no'] === 2 || $inx['innings_no'] === 4) && $match['status'] !== 'completed') {
    $target = $inx['target'];
    if($target !== null) {
        $runs = (int)$inx['summary']['runs'];
        $legal = (int)$inx['summary']['legal_balls'];
        $oversLimit = ($innings[array_search($inx['id'], array_column($innings, 'id'))]['overs_limit_override'] ?? $match['overs_limit']);
        $remBalls = max(0, ((int)$oversLimit * 6) - $legal);
        $reqRuns = max(0, $target - $runs);
        $rrr = ($remBalls > 0) ? round($reqRuns / ($remBalls/6), 2) : 0.00;
        $out['chase'] = ['innings_no'=>$inx['innings_no'], 'target'=>$target, 'required_runs'=>$reqRuns, 'remaining_balls'=>$remBalls, 'required_rr'=>$rrr];
    }
  }
}

echo json_encode($out);
?>