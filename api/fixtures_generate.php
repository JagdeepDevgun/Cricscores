<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$tournament_id = (int)($_POST['tournament_id'] ?? 0);
$overs = (int)($_POST['overs_limit'] ?? 20);
$type = $_POST['type'] ?? ''; // 'single', 'double', 'knockout'

if ($tournament_id <= 0) { http_response_code(400); echo json_encode(['error'=>'tournament_id required']); exit; }

$tour = $pdo->prepare("SELECT * FROM tournaments WHERE id=?");
$tour->execute([$tournament_id]);
$t = $tour->fetch(PDO::FETCH_ASSOC);
if (!$t) { http_response_code(404); echo json_encode(['error'=>'Tournament not found']); exit; }

// Fallback if type wasn't passed (use tournament default)
if (empty($type)) {
    if ($t['type'] === 'knockout') $type = 'knockout';
    else $type = 'single';
}

$teamsStmt = $pdo->prepare("SELECT id,name FROM teams WHERE tournament_id=? ORDER BY name");
$teamsStmt->execute([$tournament_id]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($teams) < 2) { http_response_code(400); echo json_encode(['error'=>'Add at least 2 teams']); exit; }

$created = 0;

if ($type === 'single') {
  // SINGLE ROUND ROBIN: Each team plays every other team ONCE (Triangular loop)
  for ($i=0; $i<count($teams); $i++) {
    for ($j=$i+1; $j<count($teams); $j++) {
      $a = (int)$teams[$i]['id'];
      $b = (int)$teams[$j]['id'];
      $stmt = $pdo->prepare("INSERT INTO matches(tournament_id, team_a_id, team_b_id, overs_limit, status) VALUES(?,?,?,?, 'scheduled')");
      $stmt->execute([$tournament_id, $a, $b, $overs]);
      $created++;
    }
  }
} 
else if ($type === 'double') {
  // DOUBLE ROUND ROBIN: Each team plays every other team TWICE (Full loop)
  // Generates A vs B (Home) and B vs A (Away)
  for ($i=0; $i<count($teams); $i++) {
    for ($j=0; $j<count($teams); $j++) {
      if ($i === $j) continue; // Don't play self
      $a = (int)$teams[$i]['id'];
      $b = (int)$teams[$j]['id'];
      $stmt = $pdo->prepare("INSERT INTO matches(tournament_id, team_a_id, team_b_id, overs_limit, status) VALUES(?,?,?,?, 'scheduled')");
      $stmt->execute([$tournament_id, $a, $b, $overs]);
      $created++;
    }
  }
}
else {
  // KNOCKOUT: Single elimination bracket (Top vs Bottom seeding)
  $ids = array_map(fn($x)=> (int)$x['id'], $teams);
  $n = count($ids);
  $i = 0; $j = $n - 1;
  while ($i < $j) {
    $a = $ids[$i];
    $b = $ids[$j];
    $stmt = $pdo->prepare("INSERT INTO matches(tournament_id, team_a_id, team_b_id, overs_limit, status) VALUES(?,?,?,?, 'scheduled')");
    $stmt->execute([$tournament_id, $a, $b, $overs]);
    $created++;
    $i++; $j--;
  }
}

echo json_encode(['ok'=>true,'created'=>$created,'type'=>$type]);