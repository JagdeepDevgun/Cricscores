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
            COUNT(CASE WHEN b.striker_id = p.id AND b.extras_type != 'wd' THEN 1 ELSE NULL END) as balls, 
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
    
    // UPDATED BOWLING QUERY
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

    $exStmt = $pdo->prepare("
        SELECT 
            SUM(extras_runs) as total,
            SUM(CASE WHEN extras_type='wd' THEN extras_runs ELSE 0 END) as wides,
            SUM(CASE WHEN extras_type='nb' THEN extras_runs ELSE 0 END) as no_balls,
            SUM(CASE WHEN extras_type='b' THEN extras_runs ELSE 0 END) as byes,
            SUM(CASE WHEN extras_type='lb' THEN extras_runs ELSE 0 END) as leg_byes
        FROM ball_events 
        WHERE innings_id = ?
    ");
    $exStmt->execute([$innings_id]);
    $extras = $exStmt->fetch(PDO::FETCH_ASSOC);
    
    return ['batsmen' => $bat->fetchAll(), 'bowlers' => $bowl->fetchAll(), 'extras' => $extras];
}

// --- HELPER: Detailed Data ---
function get_detailed_data(PDO $pdo, int $innings_id) {
    // EXPANDED COMMENTARY LIBRARY (Mixed Regular + IPL Style)
    $c_map = [
        '0' => [
            "Tight line and length — the batsman can only defend. No run.",
            "Good pressure from the bowler, absolutely nothing on offer.",
            "Dot ball! The bowler wins this round — crowd goes silent!",
            "No room, no run, pure domination from the bowler!"
        ],
        '1' => [
            "Just nudged into the gap and they’ll settle for a quick single.",
            "Soft hands, sharp running — one more added to the total.",
            "Busy cricket! Push and run — keeps the scoreboard ticking.",
            "Quick feet, sharp call — that’s smart batting!"
        ],
        '2' => [
            "Placed beautifully and they’ll come back for two comfortably.",
            "Good placement, excellent awareness — two runs taken.",
            "Split the field! Easy two and pressure back on the bowler.",
            "They’re running hard — turning ones into twos!"
        ],
        '3' => [
            "Driven into the deep and they’ll run hard for three.",
            "Long chase for the fielder — three runs completed.",
            "All hustle! They push for three — excellent commitment!",
            "Risky running but it pays off — three added!"
        ],
        '4' => [
            "Cracked! That races away to the boundary — four runs!",
            "Timed to perfection, no chance for the fielder — that’s four.",
            "BANG! Bullet to the fence — that’s classic IPL timing!",
            "Threaded the needle! Boundary finds the rope in no time!"
        ],
        '6' => [
            "That’s massive! Picked it up and launched it into the stands!",
            "Clean as a whistle — straight over the ropes for six!",
            "HUUUGE! That’s gone into orbit — IPL at its absolute best!",
            "DISAPPEARS! You won’t see that ball again!"
        ],
        'out' => [
            'bowled' => [
                "Cleaned him up! The stumps are shattered — what a delivery!",
                "Through the gate and gone! Absolute peach of a ball!",
                "TIMBER!!! Middle stump sent flying — absolute carnage!",
                "You miss, I hit — bowler strikes in brutal fashion!"
            ],
            'caught' => [
                "Edge and taken! Safe hands in the slips — he’s gone!",
                "Mistimed shot, straight to the fielder — catch taken!",
                "SKYED IT… TAKEN! Pressure does the damage!",
                "Went too hard, paid the price — safe hands complete the job!"
            ],
            'lbw' => [
                "Big appeal… finger goes up! He’s trapped right in front!",
                "Plumb in front of the stumps — no doubt about that decision.",
                "Huge shout… GIVEN! He’s dead in front — no escape!",
                "Trapped like a statue! That’s plumb!"
            ],
            'run out' => [
                "Direct hit! That’s brilliant fielding — he’s short of the crease!",
                "Big mix-up and he’s gone! Run out in dramatic fashion!",
                "DIRECT HIT! BOOM! He’s GONE — electric fielding!",
                "Chaos between the wickets — disaster strikes!"
            ],
            'stumped' => [
                "Lightning-quick hands from the keeper — stumped and gone!",
                "Beaten in flight, out of the crease — that’s a sharp stumping.",
                "OUT OF THE CREASE… GONE! Keeper lightning-fast!",
                "Fooled completely! That’s a beauty behind the stumps!"
            ],
            'hit wicket' => [
                "Oh dear! Lost his balance and knocked over the stumps — hit wicket!",
                "Back foot clips the bails — unlucky way to go!",
                "OH NO! Lost his shape — stumps disturbed!",
                "Pressure tells! He knocks over his own castle!"
            ],
            'caught and bowled' => [
                "Straight back to the bowler — caught and bowled!",
                "Reflex catch! The bowler does it all himself!",
                "STRAIGHT BACK! Reaction time: ZERO!",
                "One hand, one chance — taken brilliantly by the bowler!"
            ],
            'retired hurt' => [
                "He’s walking off in discomfort — retired hurt for now.",
                "Looks like an injury concern — he’ll leave the field.",
                "That doesn’t look good — big blow for the batting side.",
                "He’s walking off — concern written all over his face."
            ],
            'default' => [
                "Wicket fell! The batsman has to go.",
                "He's out! A big blow for the team.",
                "Gone! The fielding side celebrates.",
                "Wicket! That's the end of a fine innings."
            ]
        ],
        'extras' => [
            'wd' => [
                "That’s too wide — umpire stretches the arms.",
                "Lost control there, wide signaled.",
                "Way outside! That’s a gift — wide called!",
                "Lost the radar there — pressure showing!"
            ],
            'nb' => [
                "Overstepped! That’s a no-ball — free hit coming up!",
                "Foot fault — extra run and a free hit for the batsman.",
                "OVER THE LINE! No-ball — crowd roars for the free hit!",
                "Costly mistake! Free hit coming up — danger time!"
            ],
            'b' => [
                "Keeper misses it, and they’ll sneak a bye.",
                "Past everyone! Byes added to the total.",
                "Missed by everyone! They’ll pinch some byes!",
                "Chaos behind the stumps — bonus runs gifted!"
            ],
            'lb' => [
                "Off the pads and they’ll take a leg bye.",
                "No bat involved — leg byes signaled.",
                "Off the pads and they’re off — leg byes collected!",
                "Wrong place, wrong line — extras sneak through!"
            ]
        ],
        'milestone' => [
            '50' => [
                "And there it is! A well-deserved fifty!",
                "Raises the bat — fifty up in style!",
                "FIFTY UP! Explosive innings — crowd on its feet!",
                "Milestone smashed! That’s been box-office entertainment!"
            ],
            '100' => [
                "What a knock! Brings up a magnificent hundred!",
                "Standing ovation — a century to remember!",
                "A STUNNING HUNDRED! IPL HISTORY IN THE MAKING!",
                "HE DOES IT! Pure class, pure dominance — what a knock!"
            ],
            'hattrick' => [
                "Hat-trick ball… AND HE’S GOT HIM! HISTORY MADE!",
                "Unbelievable scenes! Three in three!",
                "HAT-TRICK BALL… YESSSS! THE STADIUM ERUPTS!",
                "UNREAL! THREE BALLS, THREE WICKETS — ABSOLUTE MADNESS!"
            ],
            'maiden' => [
                "Perfect over! Maiden completed — pressure building.",
                "Dot after dot — a textbook maiden.",
                "ICE-COLD OVER! Absolute squeeze — maiden completed!",
                "Bowler turns up the heat — not a single run conceded!"
            ]
        ]
    ];

    $sql = "SELECT b.*, 
                   pb.name as bowler_name, 
                   ps.name as striker_name,
                   pns.name as non_striker_name,
                   po.name as player_out_name
            FROM ball_events b 
            LEFT JOIN players pb ON pb.id = b.bowler_id 
            LEFT JOIN players ps ON ps.id = b.striker_id 
            LEFT JOIN players pns ON pns.id = b.non_striker_id 
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
    
    // Player Stats Tracking [id => [runs, balls, name]]
    $playerStats = [];
    $playerScores = []; // For milestones
    $consecutiveWickets = 0;
    $overRunsConceded = 0;
    
    foreach ($balls as $k => $b) {
        $runs = (int)$b['runs_bat'] + (int)$b['extras_runs'];
        $totalRuns += $runs;
        $p_runs += $runs;
        
        $is_legal = (int)$b['is_legal'];
        if ($is_legal) $legalBalls++;
        $overRunsConceded += $runs;

        // --- TRACK PLAYER STATS ---
        $sId = $b['striker_id'];
        $nsId = $b['non_striker_id'];
        
        if($sId && !isset($playerStats[$sId])) $playerStats[$sId] = ['name'=>$b['striker_name'], 'runs'=>0, 'balls'=>0];
        if($nsId && !isset($playerStats[$nsId])) $playerStats[$nsId] = ['name'=>$b['non_striker_name']?:'NS', 'runs'=>0, 'balls'=>0];
        
        // Milestone tracking prep
        $prevScore = isset($playerScores[$sId]) ? $playerScores[$sId] : 0;
        
        if ($sId) {
            $playerStats[$sId]['runs'] += (int)$b['runs_bat'];
            if ($b['extras_type'] !== 'wd') {
                $playerStats[$sId]['balls']++;
                $p_balls++; // Partnership balls exclude wides
            }
            $playerScores[$sId] = $playerStats[$sId]['runs'];
        }
        $newScore = $playerScores[$sId] ?? 0;

        // Graph & Wickets
        if ($is_legal && $legalBalls % 6 === 0) {
            $graphData[] = ['x' => $legalBalls/6, 'y' => $totalRuns];
        }

        if ($b['is_wicket']) {
            $wicketsDown++;
            $consecutiveWickets++;
            
            $wX = ($legalBalls > 0) ? (intdiv($legalBalls-1, 6) + (($legalBalls-1)%6 + 1)/6.0) : 0;
            if ($is_legal && $legalBalls%6==0) $wX = $legalBalls/6;
            $wicketPoints[] = ['x' => $wX, 'y' => $totalRuns, 'desc' => $b['wicket_type']];
            
            $pName = $b['player_out_name'] ?: ($b['striker_name'] ?: 'Unknown');
            $fow[] = [
                'score' => $totalRuns, 'wicket' => $wicketsDown, 'player' => $pName,
                'partnership_runs' => $p_runs, 'partnership_balls' => $p_balls,
                'over' => intdiv(max(0,$legalBalls-1), 6) . '.' . (max(0,$legalBalls-1)%6 + 1)
            ];
            $p_runs = 0; $p_balls = 0; // Reset Partnership
        } else {
            $consecutiveWickets = 0;
        }

        // Overs History
        $currentOverIndex = intdiv(max(0, $legalBalls - ($is_legal?1:0)), 6) + 1;
        if (!isset($oversHistory[$currentOverIndex])) {
            $oversHistory[$currentOverIndex] = ['over'=>$currentOverIndex, 'bowler'=>$b['bowler_name']??'Unknown', 'balls'=>[]];
            $overRunsConceded = $runs; 
        }
        $lbl = (string)$b['runs_bat'];
        if ($b['extras_type']) {
            $lbl = strtoupper($b['extras_type']);
            if ($b['extras_runs'] > 1 && !in_array($b['extras_type'],['wd','nb'])) $lbl = $b['extras_runs'].$lbl;
        }
        if ($b['is_wicket']) $lbl .= 'W';
        
        $oversHistory[$currentOverIndex]['balls'][] = [
            'id' => (int)$b['id'], 'label'=>$lbl, 'is_legal'=>$is_legal,
            'runs_bat' => (int)$b['runs_bat'], 'extras_type' => $b['extras_type'],
            'extras_runs' => (int)$b['extras_runs'], 'wicket_type' => $b['wicket_type']
        ];

        // --- COMMENTARY TEXT ---
        $bowler = $b['bowler_name'] ?? 'Unknown';
        $batter = $b['striker_name'] ?? 'Unknown';
        $prefix = "<b>$bowler</b> to <b>$batter</b>, ";
        $idx = (int)$b['id'] % 4; 
        
        $eventText = ""; $milestoneText = "";

        if ($b['is_wicket']) {
            $wt = strtolower($b['wicket_type'] ?? '');
            $pool = $c_map['out'][$wt] ?? $c_map['out']['default'];
            $eventText = $pool[$idx] ?? $pool[0];
            
            if($consecutiveWickets >= 3) {
                $mPool = $c_map['milestone']['hattrick'];
                $milestoneText = "<br><span style='color:#e91e63; font-weight:bold;'> " . ($mPool[$idx] ?? $mPool[0]) . "</span>";
            }
        } elseif ($b['extras_type']) {
            $et = strtolower($b['extras_type']);
            $pool = $c_map['extras'][$et] ?? ["Extras."];
            $eventText = $pool[$idx] ?? $pool[0];
        } else {
            $r = (string)$b['runs_bat'];
            $pool = $c_map[$r] ?? ["$r runs."];
            $eventText = $pool[$idx] ?? $pool[0];
            
            // Milestones
            if($prevScore < 50 && $newScore >= 50) {
                 $mPool = $c_map['milestone']['50'];
                 $milestoneText = "<br><span style='color:#ff9800; font-weight:bold;'> " . ($mPool[$idx] ?? $mPool[0]) . "</span>";
            } elseif($prevScore < 100 && $newScore >= 100) {
                 $mPool = $c_map['milestone']['100'];
                 $milestoneText = "<br><span style='color:#e91e63; font-weight:bold;'> " . ($mPool[$idx] ?? $mPool[0]) . "</span>";
            }
        }
        
        // Maiden Check
        if($is_legal && $legalBalls % 6 === 0 && $overRunsConceded === 0) {
             $mPool = $c_map['milestone']['maiden'];
             $milestoneText .= "<br><span style='color:#00bcd4; font-weight:bold;'> " . ($mPool[$idx] ?? $mPool[0]) . "</span>";
             $overRunsConceded = 0;
        }

        $ovStr = intdiv(max(0,$legalBalls-1), 6) . '.' . (max(0,$legalBalls-1)%6 + 1);
        
        $commentary[] = [
            'id' => (int)$b['id'],
            'type' => 'ball',
            'over' => $ovStr, 
            'text' => $prefix . $eventText . $milestoneText, 
            'runs' => $runs, 'runs_bat' => (int)$b['runs_bat'], 'is_wicket' => (int)$b['is_wicket'], 
            'extras_type' => $b['extras_type'], 'extras_runs' => (int)$b['extras_runs']
        ];

        // --- END OF OVER SUMMARY ---
        if ($is_legal && $legalBalls % 6 === 0) {
            // Determine active batsmen for summary by peeking ahead or using current state logic
            $activeS = $sId; $activeNS = $nsId;
            $nextBall = $balls[$k+1] ?? null;
            
            if ($nextBall) {
                $activeS = $nextBall['striker_id'];
                $activeNS = $nextBall['non_striker_id'];
            } elseif ($b['is_wicket']) {
                // Determine survivor if last ball was wicket and no new ball yet
                $outId = $b['wicket_player_out_id'];
                $activeS = ($outId == $sId) ? $nsId : $sId;
                $activeNS = 0; // Second spot is technically empty/new guy
            }
            
            $batsmenStr = [];
            if(isset($playerStats[$activeS])) $batsmenStr[] = "<b>{$playerStats[$activeS]['name']}</b> {$playerStats[$activeS]['runs']}({$playerStats[$activeS]['balls']})";
            if(isset($playerStats[$activeNS])) $batsmenStr[] = "<b>{$playerStats[$activeNS]['name']}</b> {$playerStats[$activeNS]['runs']}({$playerStats[$activeNS]['balls']})";
            
            $commSummary = [
                'type' => 'over_end',
                'id' => 'summ_'.$currentOverIndex,
                'over' => "END $currentOverIndex",
                'score' => "<b>$totalRuns/$wicketsDown</b>",
                'batsmen' => implode(" &bull; ", $batsmenStr),
                'partnership' => "Part: $p_runs ($p_balls)" 
            ];
            $commentary[] = $commSummary;
        }
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
$stmt = $pdo->prepare("SELECT m.*, ta.name AS team_a, ta.short_name AS team_a_short, ta.icon AS team_a_icon, tb.name AS team_b, tb.short_name AS team_b_short, tb.icon AS team_b_icon, p.name as mom_name FROM matches m JOIN teams ta ON ta.id=m.team_a_id JOIN teams tb ON tb.id=m.team_b_id LEFT JOIN players p ON p.id = m.man_of_match_id WHERE m.id=?");
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

if ($match['status'] === 'awaiting_super_over') { $out['match']['result_text'] = "MATCH TIED (Super Over?)"; $out['match']['result_type'] = 'tie'; } 
elseif ($match['status'] === 'completed') {
    if ($match['result_type'] === 'tie') $out['match']['result_text'] = "MATCH TIED";
    elseif ($match['result_type'] === 'nr') $out['match']['result_text'] = "NO RESULT";
    elseif ($match['winner_team_id']) {
        $wID = (int)$match['winner_team_id'];
        $wName = ($wID == $match['team_a_id']) ? $match['team_a'] : $match['team_b'];
        $out['match']['result_text'] = "$wName won";
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