<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

$tid = (int)($_GET['tournament_id'] ?? 0);
if ($tid <= 0) { echo json_encode([]); exit; }

// 1. Top Batsmen
$batSql = "
  SELECT p.id, p.name, t.name as team,
    COUNT(DISTINCT m.id) as matches,
    SUM(b.runs_bat) as runs,
    COUNT(b.id) as balls,
    SUM(CASE WHEN b.runs_bat=4 THEN 1 ELSE 0 END) as fours,
    SUM(CASE WHEN b.runs_bat=6 THEN 1 ELSE 0 END) as sixes
  FROM ball_events b
  JOIN innings i ON i.id = b.innings_id
  JOIN matches m ON m.id = i.match_id
  JOIN players p ON p.id = b.striker_id
  JOIN teams t ON t.id = p.team_id
  WHERE m.tournament_id = ?
  GROUP BY p.id
  HAVING p.name IS NOT NULL AND p.name != ''
  ORDER BY runs DESC LIMIT 10
";
$batStmt = $pdo->prepare($batSql);
$batStmt->execute([$tid]);
$batsmen = $batStmt->fetchAll(PDO::FETCH_ASSOC);

foreach($batsmen as &$b) {
    $b['sr'] = ($b['balls'] > 0) ? round(($b['runs'] / $b['balls']) * 100, 1) : 0.0;
}
unset($b); // Safety unset

// 2. Top Bowlers (FIX: Added LOWER() check for run out)
$bowlSql = "
  SELECT p.id, p.name, t.name as team,
    COUNT(DISTINCT m.id) as matches,
    COUNT(CASE WHEN b.is_wicket=1 AND LOWER(b.wicket_type) != 'run out' THEN 1 END) as wickets,
    COUNT(CASE WHEN b.is_legal=1 THEN 1 END) as legal_balls,
    COUNT(CASE WHEN b.is_legal=1 AND b.runs_bat=0 AND b.extras_runs=0 THEN 1 END) as dots,
    SUM(b.runs_bat + b.extras_runs) as runs_conceded
  FROM ball_events b
  JOIN innings i ON i.id = b.innings_id
  JOIN matches m ON m.id = i.match_id
  JOIN players p ON p.id = b.bowler_id
  JOIN teams t ON t.id = p.team_id
  WHERE m.tournament_id = ?
  GROUP BY p.id
  HAVING p.name IS NOT NULL AND p.name != ''
  ORDER BY wickets DESC, runs_conceded ASC LIMIT 10
";
$bowlStmt = $pdo->prepare($bowlSql);
$bowlStmt->execute([$tid]);
$bowlers = $bowlStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate 3-Wicket Hauls separately (FIX: Added LOWER() check)
$threesSql = "
  SELECT bowler_id, COUNT(*) as count
  FROM (
    SELECT b.bowler_id, COUNT(*) as w
    FROM ball_events b
    JOIN innings i ON i.id = b.innings_id
    JOIN matches m ON m.id = i.match_id
    WHERE m.tournament_id = ? AND b.is_wicket=1 AND LOWER(b.wicket_type) != 'run out'
    GROUP BY m.id, b.bowler_id
    HAVING w >= 3
  )
  GROUP BY bowler_id
";
$threesStmt = $pdo->prepare($threesSql);
$threesStmt->execute([$tid]);
$threesMap = $threesStmt->fetchAll(PDO::FETCH_KEY_PAIR);

foreach($bowlers as &$b) {
    $overs = $b['legal_balls'] / 6;
    $b['econ'] = ($overs > 0) ? round($b['runs_conceded'] / $overs, 2) : 0.0;
    $b['w3'] = $threesMap[$b['id']] ?? 0;
}
unset($b); 

// 3. Highest Sixes
$sixSql = "
  SELECT p.id, p.name, t.name as team, SUM(CASE WHEN b.runs_bat=6 THEN 1 ELSE 0 END) as count
  FROM ball_events b
  JOIN innings i ON i.id = b.innings_id
  JOIN matches m ON m.id = i.match_id
  JOIN players p ON p.id = b.striker_id
  JOIN teams t ON t.id = p.team_id
  WHERE m.tournament_id = ?
  GROUP BY p.id
  HAVING count > 0 AND p.name IS NOT NULL
  ORDER BY count DESC LIMIT 5
";
$sixStmt = $pdo->prepare($sixSql);
$sixStmt->execute([$tid]);

// 4. Highest Fours
$fourSql = "
  SELECT p.id, p.name, t.name as team, SUM(CASE WHEN b.runs_bat=4 THEN 1 ELSE 0 END) as count
  FROM ball_events b
  JOIN innings i ON i.id = b.innings_id
  JOIN matches m ON m.id = i.match_id
  JOIN players p ON p.id = b.striker_id
  JOIN teams t ON t.id = p.team_id
  WHERE m.tournament_id = ?
  GROUP BY p.id
  HAVING count > 0 AND p.name IS NOT NULL
  ORDER BY count DESC LIMIT 5
";
$fourStmt = $pdo->prepare($fourSql);
$fourStmt->execute([$tid]);

// 5. All Players (Merged Data)
$allSql = "
    SELECT p.id, p.name, t.name as team
    FROM players p
    JOIN teams t ON t.id = p.team_id
    WHERE t.tournament_id = ?
    ORDER BY t.name, p.name
";
$allStmt = $pdo->prepare($allSql);
$allStmt->execute([$tid]);
$allPlayersRaw = $allStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper queries for aggregates per player
$allStatsBat = $pdo->prepare("
    SELECT p.id, COUNT(DISTINCT m.id) as matches, SUM(b.runs_bat) as runs, COUNT(b.id) as balls,
           SUM(CASE WHEN b.runs_bat=4 THEN 1 ELSE 0 END) as fours,
           SUM(CASE WHEN b.runs_bat=6 THEN 1 ELSE 0 END) as sixes
    FROM ball_events b JOIN innings i ON i.id=b.innings_id JOIN matches m ON m.id=i.match_id JOIN players p ON p.id=b.striker_id
    WHERE m.tournament_id=? GROUP BY p.id
");
$allStatsBat->execute([$tid]);
$batMap = [];
while($r = $allStatsBat->fetch(PDO::FETCH_ASSOC)) $batMap[$r['id']] = $r;

// (FIX: Added LOWER() check)
$allStatsBowl = $pdo->prepare("
    SELECT p.id, COUNT(DISTINCT m.id) as matches, 
           COUNT(CASE WHEN b.is_wicket=1 AND LOWER(b.wicket_type) != 'run out' THEN 1 END) as wickets,
           COUNT(CASE WHEN b.is_legal=1 THEN 1 END) as legal_balls,
           COUNT(CASE WHEN b.is_legal=1 AND b.runs_bat=0 AND b.extras_runs=0 THEN 1 END) as dots,
           SUM(b.runs_bat + b.extras_runs) as runs_conceded
    FROM ball_events b JOIN innings i ON i.id=b.innings_id JOIN matches m ON m.id=i.match_id JOIN players p ON p.id=b.bowler_id
    WHERE m.tournament_id=? GROUP BY p.id
");
$allStatsBowl->execute([$tid]);
$bowlMap = [];
while($r = $allStatsBowl->fetch(PDO::FETCH_ASSOC)) $bowlMap[$r['id']] = $r;

$finalList = [];
foreach($allPlayersRaw as $p) {
    if (empty($p['name'])) continue; 
    
    $pid = $p['id'];
    $b = $batMap[$pid] ?? ['matches'=>0,'runs'=>0,'balls'=>0,'fours'=>0,'sixes'=>0];
    $bo = $bowlMap[$pid] ?? ['matches'=>0,'wickets'=>0,'legal_balls'=>0,'dots'=>0,'runs_conceded'=>0];
    
    $matches = max($b['matches'], $bo['matches']);
    if($matches === 0) continue; 

    $sr = ($b['balls'] > 0) ? round(($b['runs']/$b['balls'])*100, 1) : 0;
    $overs = $bo['legal_balls'] / 6;
    $econ = ($overs > 0) ? round($bo['runs_conceded']/$overs, 2) : 0;

    $finalList[] = [
        'id' => $pid,
        'name' => $p['name'],
        'team' => $p['team'],
        'matches' => $matches,
        'runs' => $b['runs'],
        'sr' => $sr,
        'fours' => $b['fours'],
        'sixes' => $b['sixes'],
        'wickets' => $bo['wickets'],
        'econ' => $econ,
        'dots' => $bo['dots']
    ];
}

echo json_encode([
  'batsmen' => $batsmen,
  'bowlers' => $bowlers,
  'most_sixes' => $sixStmt->fetchAll(PDO::FETCH_ASSOC),
  'most_fours' => $fourStmt->fetchAll(PDO::FETCH_ASSOC),
  'all_players' => $finalList
]);
?>