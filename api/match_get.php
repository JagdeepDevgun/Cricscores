<?php
require_once __DIR__ . '/../db.php';
// We do not rely on _helpers.php to ensure we get fresh, uncached data

if (session_id()) session_write_close();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$match_id = (int)($_GET['match_id'] ?? 0);
if ($match_id <= 0) { http_response_code(400); echo json_encode(['error' => 'match_id required']); exit; }

// ==========================================
// 1. INTERNAL HELPER: Fresh Innings Totals
// ==========================================
function calculate_innings_totals(PDO $pdo, int $innings_id) {
    $sql = "SELECT 
            COUNT(CASE WHEN is_legal=1 THEN 1 END) as legal_balls,
            SUM(runs_bat + extras_runs) as runs,
            SUM(CASE WHEN is_wicket=1 THEN 1 END) as wickets
            FROM ball_events WHERE innings_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$innings_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    $legal = (int)($res['legal_balls'] ?? 0);
    $runs = (int)($res['runs'] ?? 0);
    $wkts = (int)($res['wickets'] ?? 0);
    $overs = floor($legal / 6) . '.' . ($legal % 6);
    $overs_dec = $legal > 0 ? $legal / 6 : 0;
    $rr = $overs_dec > 0 ? number_format($runs / $overs_dec, 2) : "0.00";

    return [
        'runs' => $runs, 'wickets' => $wkts, 'wkts' => $wkts,
        'legal_balls' => $legal, 'overs' => $overs, 'overs_text' => $overs,
        'run_rate' => $rr, 'rr' => $rr
    ];
}

// ==========================================
// 2. COMMENTARY HELPERS
// ==========================================
function get_commentary_library(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT trigger_event, context_tag, text_template FROM commentary");
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { return []; }
    $lib = [];
    foreach ($raw as $row) { $lib[$row['trigger_event']][$row['context_tag']][] = $row['text_template']; }
    return $lib;
}

function pick_commentary($lib, $trigger, $context, $seed_index) {
    if (isset($lib[$trigger][$context]) && !empty($lib[$trigger][$context])) {
        $pool = $lib[$trigger][$context]; return $pool[$seed_index % count($pool)];
    }
    if (isset($lib[$trigger]['default']) && !empty($lib[$trigger]['default'])) {
        $pool = $lib[$trigger]['default']; return $pool[$seed_index % count($pool)];
    }
    // Fallback if specific trigger not found (try standard text)
    return ""; 
}

// ==========================================
// 3. SCORECARD ENGINE
// ==========================================
function get_scorecard(PDO $pdo, int $innings_id) {
    $sc = ['batsmen' => [], 'bowlers' => [], 'extras' => ['total'=>0]];
    
    // BATSMEN
    $batSql = "SELECT p.id, p.name, p.is_captain, SUM(b.runs_bat) as runs, COUNT(CASE WHEN b.extras_type != 'wd' THEN 1 END) as balls, SUM(CASE WHEN b.runs_bat=4 THEN 1 ELSE 0 END) as fours, SUM(CASE WHEN b.runs_bat=6 THEN 1 ELSE 0 END) as sixes, MAX(CASE WHEN b.is_wicket=1 AND b.wicket_player_out_id=p.id THEN b.wicket_type ELSE NULL END) as dismissal, MAX(CASE WHEN b.is_wicket=1 AND b.wicket_player_out_id=p.id THEN pb.name ELSE NULL END) as bowler_name FROM ball_events b JOIN players p ON p.id = b.striker_id LEFT JOIN players pb ON pb.id = b.bowler_id WHERE b.innings_id = ? GROUP BY p.id";
    $stmt = $pdo->prepare($batSql); $stmt->execute([$innings_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $bat) {
        $desc = null; 
        if ($bat['dismissal']) {
            $d = $bat['dismissal'];
            if ($d == 'bowled' || $d == 'lbw') $desc = "b " . $bat['bowler_name'];
            elseif (stripos($d, 'run') !== false) $desc = "Run Out";
            else $desc = "c & b " . $bat['bowler_name'];
        }
        $sc['batsmen'][] = array_merge($bat, ['dismissal'=>$desc]);
    }
    
    // BOWLERS
    $bowlSql = "SELECT p.id, p.name, COUNT(CASE WHEN b.is_legal=1 THEN 1 END) as legal_balls, SUM(b.runs_bat + b.extras_runs) as runs_conceded, SUM(CASE WHEN b.is_wicket=1 AND b.wicket_type != 'run out' THEN 1 ELSE 0 END) as wickets, COUNT(CASE WHEN b.extras_type='wd' THEN 1 END) as wides, COUNT(CASE WHEN b.extras_type='nb' THEN 1 END) as no_balls FROM ball_events b JOIN players p ON p.id = b.bowler_id WHERE b.innings_id = ? GROUP BY p.id";
    $stmt = $pdo->prepare($bowlSql); $stmt->execute([$innings_id]);
    $sc['bowlers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // EXTRAS
    $exSql = "SELECT extras_type, SUM(extras_runs) as runs FROM ball_events WHERE innings_id = ? AND extras_runs > 0 GROUP BY extras_type";
    $stmt = $pdo->prepare($exSql); $stmt->execute([$innings_id]);
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $sc['extras']['total'] += $r['runs']; $sc['extras'][$r['extras_type']] = $r['runs']; }
    
    return $sc;
}

// ==========================================
// 4. DETAILED DATA & LOGIC ENGINE
// ==========================================
function get_detailed_data(PDO $pdo, int $innings_id, ?int $target_run_count = null) {
    $sql = "SELECT b.*, pb.name as bowler_name, ps.name as striker_name, pns.name as non_striker_name, po.name as player_out_name 
            FROM ball_events b 
            LEFT JOIN players pb ON pb.id = b.bowler_id 
            LEFT JOIN players ps ON ps.id = b.striker_id 
            LEFT JOIN players pns ON pns.id = b.non_striker_id 
            LEFT JOIN players po ON po.id = b.wicket_player_out_id
            WHERE b.innings_id=? ORDER BY b.seq ASC";
    $stmt = $pdo->prepare($sql); $stmt->execute([$innings_id]);
    $balls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $c_lib = get_commentary_library($pdo);
    $commentary = []; $graphData = [['x'=>0, 'y'=>0]]; $wicketPoints = []; $fow = []; $oversHistory = [];
    
    $totalRuns = 0; $wicketsDown = 0; $legalBalls = 0; $thisOverRuns = 0;
    
    // Partnership Calculation
    $p_runs = 0; $p_balls = 0;
    $prev_p_runs = 0; // To track milestone crossing
    
    $playerScores = []; $playerBalls = [];
    
    $consecutiveDots = 0; $consecutiveBoundaries = 0;
    $consecutiveWickets = 0;
    $winningBallIndex = -1;

    foreach ($balls as $k => $b) {
        $runs_bat = (int)$b['runs_bat'];
        $extras = (int)$b['extras_runs'];
        $total_ball_runs = $runs_bat + $extras;
        $totalRuns += $total_ball_runs;
        $thisOverRuns += $total_ball_runs;
        $is_legal = (int)$b['is_legal'];
        if ($is_legal) $legalBalls++;
        if ($b['is_wicket']) $wicketsDown++;

        // Track Scores & Partnership
        $prev_p_runs = $p_runs; // Save previous partnership score
        if ($b['striker_id']) {
            $sid = $b['striker_id'];
            if(!isset($playerScores[$sid])) { $playerScores[$sid] = 0; $playerBalls[$sid] = 0; }
            $prevScore = $playerScores[$sid];
            $playerScores[$sid] += $runs_bat;
            $newScore = $playerScores[$sid];
            if ($b['extras_type'] !== 'wd') { $playerBalls[$sid]++; $p_balls++; }
            $p_runs += $total_ball_runs;
        }

        // --- CONTEXT LOGIC ---
        // Basic Stats
        if ($total_ball_runs == 0 && $is_legal) { $consecutiveDots++; $consecutiveBoundaries = 0; }
        elseif ($runs_bat >= 4) { $consecutiveBoundaries++; $consecutiveDots = 0; }
        else { $consecutiveDots = 0; $consecutiveBoundaries = 0; }
        
        // Hattrick Check (Consecutive wickets)
        if ($b['is_wicket']) { $consecutiveWickets++; } else { $consecutiveWickets = 0; }

        // Win Detection
        $is_win_chase = false;
        if ($target_run_count && $totalRuns >= $target_run_count && $winningBallIndex == -1) { $is_win_chase = true; $winningBallIndex = $k; }
        $is_win_defend = false;
        if ($target_run_count && ($wicketsDown == 10 || ($legalBalls == 120)) && $totalRuns < $target_run_count && $winningBallIndex == -1) { $is_win_defend = true; $winningBallIndex = $k; }
        $is_close_finish = false;
        if ($target_run_count) {
            $runsNeeded = $target_run_count - ($totalRuns - $total_ball_runs);
            $ballsLeft = 120 - ($legalBalls - ($is_legal?1:0));
            if ($ballsLeft <= 6 && $runsNeeded <= 12 && $runsNeeded > 0) $is_close_finish = true;
        }

        // --- COMMENTARY GENERATION ---
        $mainText = ""; $subText = "";
        
        if ($is_win_chase) {
             $trigger = 'win_chase';
             $ctx = ($runs_bat == 4) ? 'boundary' : (($runs_bat == 6) ? 'six' : 'default');
             if ($is_close_finish) $ctx = 'close_finish';
             $mainText = pick_commentary($c_lib, $trigger, $ctx, $b['id']);
        } 
        elseif ($is_win_defend) {
             $mainText = pick_commentary($c_lib, 'win_defend', $is_close_finish ? 'close_finish' : 'default', $b['id']);
        }
        elseif ($b['is_wicket']) {
             // Wicket Logic
             $wt = strtolower($b['wicket_type']);
             // Normalize triggers (e.g. "Run Out" -> "out_run out", "Hit Wicket" -> "out_hit_wicket")
             if(strpos($wt, 'catch')!==false) $trigger = 'out_caught';
             elseif(strpos($wt, 'run')!==false) $trigger = 'out_run out';
             elseif(strpos($wt, 'hit')!==false) $trigger = 'out_hit_wicket';
             elseif(strpos($wt, 'stump')!==false) $trigger = 'out_stumped';
             else $trigger = 'out_' . $wt;

             // Contexts
             $ctx = $is_close_finish ? 'close_finish' : 'default';
             if ($ctx == 'default' && $consecutiveDots >= 3) $ctx = 'pressure_dots';
             
             $mainText = pick_commentary($c_lib, $trigger, $ctx, $b['id']);
             if(!$mainText) $mainText = "Wicket! " . ucwords($wt); // Fallback

             // Hat-trick check
             if ($consecutiveWickets >= 3) {
                 $subText .= "<br><b style='color:#d32f2f; animation:blink 1s infinite;'>??? " . pick_commentary($c_lib, 'milestone_hattrick', 'default', $b['id']) . "</b>";
             }
             
             $outPlayerName = $b['player_out_name'] ?: 'Batter';
             $fow[] = ['score'=>$totalRuns, 'wicket'=>$wicketsDown, 'player'=>$outPlayerName, 'over'=>floor($legalBalls/6).".".($legalBalls%6)];
             $wicketPoints[] = ['x'=>$legalBalls/6, 'y'=>$totalRuns];
             $p_runs = 0; $p_balls = 0; // Reset partnership
        } 
        elseif ($b['extras_type']) {
             $mainText = pick_commentary($c_lib, 'extra_'.strtolower($b['extras_type']), 'default', $b['id']);
             if(!$mainText) $mainText = "Extra " . strtoupper($b['extras_type']);
        } 
        else {
             $trigger = (string)$runs_bat;
             $ctx = $is_close_finish ? 'close_finish' : 'default';
             if ($ctx == 'default' && $consecutiveBoundaries >= 2 && ($runs_bat==4||$runs_bat==6)) $ctx = 'back_to_back';
             
             $mainText = pick_commentary($c_lib, $trigger, $ctx, $b['id']);
             if(!$mainText) $mainText = ($runs_bat==0) ? "No run." : "$runs_bat run(s).";

             // Milestones
             if (isset($newScore)) {
                if ($prevScore < 50 && $newScore >= 50) $subText .= "<br><b style='color:#ff9800;'>? " . pick_commentary($c_lib, 'milestone_50', 'default', $b['id']) . "</b>";
                elseif ($prevScore < 100 && $newScore >= 100) $subText .= "<br><b style='color:#e91e63;'>? " . pick_commentary($c_lib, 'milestone_100', 'default', $b['id']) . "</b>";
             }
             
             // Partnership Milestones
             if ($prev_p_runs < 50 && $p_runs >= 50) $subText .= "<br><span style='color:#00bcd4; font-weight:bold;'>?? " . pick_commentary($c_lib, 'partnership_50', 'default', $b['id']) . "</span>";
             elseif ($prev_p_runs < 100 && $p_runs >= 100) $subText .= "<br><span style='color:#00bcd4; font-weight:bold;'>?? " . pick_commentary($c_lib, 'partnership_100', 'default', $b['id']) . "</span>";
        }

        $bowlerName = $b['bowler_name'] ?? 'Bowler';
        $batterName = $b['striker_name'] ?? 'Batter';
        $finalHtml = "<b>$bowlerName</b> to <b>$batterName</b>, " . $mainText . $subText;

        if($is_close_finish && !$is_win_chase && !$is_win_defend) $finalHtml = "<span style='color:#d32f2f;'>CRUNCH TIME!</span> " . $finalHtml;

        $ovDisplay = intdiv(max(0, $legalBalls-1), 6) . '.' . (max(0, $legalBalls-1)%6 + 1);
        $commentary[] = [
            'id' => $b['id'], 'over' => $ovDisplay, 'text' => $finalHtml,
            'runs' => $total_ball_runs, 'runs_bat' => $runs_bat, 'extras_type' => $b['extras_type'], 'extras_runs' => $extras,
            'is_wicket' => (int)$b['is_wicket'], 'type' => 'ball'
        ];

        // End of Over
        $currOver = intdiv(max(0, $legalBalls - ($is_legal?1:0)), 6) + 1;
        if (!isset($oversHistory[$currOver])) $oversHistory[$currOver] = ['over'=>$currOver, 'bowler'=>$bowlerName, 'balls'=>[]];
        $lbl = (string)$runs_bat;
        if($b['extras_type']) { $lbl = strtoupper($b['extras_type']); if($extras>1 && !in_array($b['extras_type'],['wd','nb'])) $lbl=$extras.$lbl; }
        if($b['is_wicket']) $lbl .= 'W';
        $oversHistory[$currOver]['balls'][] = ['id'=>$b['id'], 'label'=>$lbl, 'is_legal'=>$is_legal, 'runs_bat'=>$runs_bat, 'extras_type'=>$b['extras_type'], 'wicket_type'=>$b['wicket_type']];

        if ($is_legal && $legalBalls % 6 === 0) {
            $graphData[] = ['x' => $legalBalls/6, 'y' => $totalRuns];
            $batStrParts = [];
            if ($b['striker_id']) {
                $sId = $b['striker_id']; $sRun = $playerScores[$sId]??0; $sBalls = $playerBalls[$sId]??0;
                $batStrParts[] = "<b>{$b['striker_name']}</b> $sRun($sBalls)*";
            }
            if ($b['non_striker_id']) {
                $nsId = $b['non_striker_id']; $nsRun = $playerScores[$nsId]??0; $nsBalls = $playerBalls[$nsId]??0;
                $batStrParts[] = "{$b['non_striker_name']} $nsRun($nsBalls)";
            }
            $batStr = implode(" | ", $batStrParts);

            $commentary[] = [
                'type' => 'over_end', 'id' => 'ov_'.$currOver, 'over' => "END OF OVER " . ($legalBalls/6),
                'text' => "Runs conceded: <b>$thisOverRuns</b>", 'score' => "<b>$totalRuns / $wicketsDown</b>",
                'batsmen' => $batStr, 'partnership' => "P'SHIP: $p_runs ($p_balls)", 'this_over_runs' => $thisOverRuns
            ];
            $thisOverRuns = 0;
        }
    }

    return [
        'comm' => array_reverse($commentary), 'graph' => $graphData, 'wickets' => $wicketPoints,
        'fow' => $fow, 'partnership' => ['runs' => $p_runs, 'balls' => $p_balls],
        'overs_history' => array_values($oversHistory), 'last_ball' => end($balls)
    ];
}

// ==========================================
// 5. MAIN EXECUTION
// ==========================================
$stmt = $pdo->prepare("
    SELECT m.*, 
           ta.name AS team_a, 
           tb.name AS team_b,
           pmom.name AS mom_name 
    FROM matches m 
    JOIN teams ta ON ta.id = m.team_a_id 
    JOIN teams tb ON tb.id = m.team_b_id 
    LEFT JOIN players pmom ON pmom.id = m.man_of_match_id
    WHERE m.id = ?
");
$stmt->execute([$match_id]);
$match = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$match) { echo json_encode(['error' => 'Match not found']); exit; }

if ($match['status'] === 'awaiting_super_over') { $match['result_text'] = "MATCH TIED (Super Over?)"; $match['result_type'] = 'tie'; } 
elseif ($match['status'] === 'completed' && $match['winner_team_id']) {
    $wName = ((int)$match['winner_team_id'] == $match['team_a_id']) ? $match['team_a'] : $match['team_b'];
    $match['result_text'] = "$wName won";
}

$pStmt = $pdo->prepare("SELECT id, name, team_id, is_captain FROM players WHERE team_id IN (?,?) ORDER BY name");
$pStmt->execute([$match['team_a_id'], $match['team_b_id']]);
$players = $pStmt->fetchAll(PDO::FETCH_ASSOC);

$innStmt = $pdo->prepare("SELECT * FROM innings WHERE match_id=? ORDER BY innings_no ASC");
$innStmt->execute([$match_id]);
$innings = $innStmt->fetchAll(PDO::FETCH_ASSOC);

$target = null; $firstInnRuns = 0;
foreach($innings as $i) { if($i['innings_no'] == 1) { $s = calculate_innings_totals($pdo, (int)$i['id']); $firstInnRuns = $s['runs']; } }
$target = $firstInnRuns ? ($firstInnRuns + 1) : null;

$output = ['match' => $match, 'players' => $players, 'innings' => [], 'chase' => null];

foreach ($innings as $i) {
    $totals = calculate_innings_totals($pdo, (int)$i['id']);
    $chaseTarget = ($i['innings_no'] == 2) ? $target : null;
    $details = get_detailed_data($pdo, (int)$i['id'], $chaseTarget);
    $scorecard = get_scorecard($pdo, (int)$i['id']);
    $bt = ((int)$i['batting_team_id'] == $match['team_a_id']) ? $match['team_a'] : $match['team_b'];
    
    $recent = []; foreach($details['overs_history'] as $oh) { foreach($oh['balls'] as $rb) { $recent[]=$rb['label']; } }
    $recent = array_slice($recent, -12);

    $innData = [
        'id' => (int)$i['id'], 'innings_no' => (int)$i['innings_no'], 'batting_team' => $bt, 'batting_team_id' => (int)$i['batting_team_id'],
        'completed' => (int)$i['completed'], 'summary' => array_merge($totals, ['recent_balls' => array_reverse($recent)]),
        'target' => ($i['innings_no'] == 2) ? $target : null, 'commentary' => $details['comm'], 
        'graph_data' => $details['graph'], 'wickets_data' => $details['wickets'], 'fow' => $details['fow'], 
        'current_partnership' => $details['partnership'], 'scorecard' => $scorecard, 'overs_history' => $details['overs_history'], 'last_ball' => $details['last_ball']
    ];
    $output['innings'][] = $innData;

    if (($i['innings_no'] === 2 || $i['innings_no'] === 4) && $match['status'] !== 'completed' && $target) {
        $runs = $totals['runs']; $legal = $totals['legal_balls'];
        $remBalls = max(0, ((int)$match['overs_limit'] * 6) - $legal); $reqRuns = max(0, $target - $runs);
        $rrr = ($remBalls > 0) ? round($reqRuns / ($remBalls/6), 2) : 0.00;
        $output['chase'] = ['innings_no'=>$i['innings_no'], 'target'=>$target, 'required_runs'=>$reqRuns, 'remaining_balls'=>$remBalls, 'required_rr'=>$rrr];
    }
}
echo json_encode($output);
?>