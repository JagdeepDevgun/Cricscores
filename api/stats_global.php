<?php
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

// 1. Global Batting
$batSql = "
  SELECT MAX(p.id) as id, p.name, 
    COUNT(DISTINCT m.id) as matches,
    SUM(b.runs_bat) as runs,
    COUNT(b.id) as balls,
    SUM(CASE WHEN b.runs_bat=4 THEN 1 ELSE 0 END) as fours,
    SUM(CASE WHEN b.runs_bat=6 THEN 1 ELSE 0 END) as sixes
  FROM ball_events b
  JOIN innings i ON i.id = b.innings_id
  JOIN matches m ON m.id = i.match_id
  JOIN players p ON p.id = b.striker_id
  GROUP BY p.name
  HAVING p.name IS NOT NULL AND p.name != ''
";
$batStats = [];
$stmt = $pdo->query($batSql);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $r['sr'] = ($r['balls'] > 0) ? round(($r['runs'] / $r['balls']) * 100, 1) : 0;
    $batStats[$r['name']] = $r;
}

// 2. Global Bowling (FIX: Added LOWER() check)
$bowlSql = "
  SELECT MAX(p.id) as id, p.name,
    COUNT(DISTINCT m.id) as matches,
    COUNT(CASE WHEN b.is_wicket=1 AND LOWER(b.wicket_type) != 'run out' THEN 1 END) as wickets,
    COUNT(CASE WHEN b.is_legal=1 THEN 1 END) as legal_balls,
    COUNT(CASE WHEN b.is_legal=1 AND b.runs_bat=0 AND b.extras_runs=0 THEN 1 END) as dots,
    SUM(b.runs_bat + b.extras_runs) as runs_conceded
  FROM ball_events b
  JOIN innings i ON i.id = b.innings_id
  JOIN matches m ON m.id = i.match_id
  JOIN players p ON p.id = b.bowler_id
  GROUP BY p.name
  HAVING p.name IS NOT NULL AND p.name != ''
";
$bowlStats = [];
$stmt = $pdo->query($bowlSql);
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $overs = $r['legal_balls'] / 6;
    $r['econ'] = ($overs > 0) ? round($r['runs_conceded'] / $overs, 2) : 0;
    $bowlStats[$r['name']] = $r;
}

// 3. Merge
$allNames = array_unique(array_merge(array_keys($batStats), array_keys($bowlStats)));
$final = [];

foreach($allNames as $name) {
    $b = $batStats[$name] ?? ['id'=>0, 'matches'=>0, 'runs'=>0, 'balls'=>0, 'sr'=>0, 'fours'=>0, 'sixes'=>0];
    $o = $bowlStats[$name] ?? ['id'=>0, 'matches'=>0, 'wickets'=>0, 'econ'=>0, 'dots'=>0];
    
    // Pick the best available ID
    $id = ($b['id'] > 0) ? $b['id'] : $o['id'];
    $matches = max($b['matches'], $o['matches']);
    
    $final[] = [
        'id' => $id, // Passing ID to frontend
        'name' => $name,
        'matches' => $matches,
        'runs' => $b['runs'],
        'sr' => $b['sr'],
        'fours' => $b['fours'],
        'sixes' => $b['sixes'],
        'wickets' => $o['wickets'],
        'econ' => $o['econ']
    ];
}

echo json_encode($final);
?>