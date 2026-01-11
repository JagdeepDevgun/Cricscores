<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$match_id = (int)($_POST['match_id'] ?? 0);
$bat_first = (int)($_POST['batting_first_team_id'] ?? 0);
if ($match_id<=0 || $bat_first<=0) { http_response_code(400); echo json_encode(['error'=>'match_id and batting_first_team_id required']); exit; }

$stmt = $pdo->prepare("SELECT * FROM matches WHERE id=?");
$stmt->execute([$match_id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$m) { http_response_code(404); echo json_encode(['error'=>'Match not found']); exit; }

$teamA = (int)$m['team_a_id'];
$teamB = (int)$m['team_b_id'];
if ($bat_first !== $teamA && $bat_first !== $teamB) { http_response_code(400); echo json_encode(['error'=>'Batting team must be Team A or Team B']); exit; }

$bowling = ($bat_first === $teamA) ? $teamB : $teamA;

// If innings already exists, don't duplicate
$chk = $pdo->prepare("SELECT COUNT(*) FROM innings WHERE match_id=? AND innings_no=1");
$chk->execute([$match_id]);
if ((int)$chk->fetchColumn() === 0) {
  $ins = $pdo->prepare("INSERT INTO innings(match_id, innings_no, batting_team_id, bowling_team_id, completed) VALUES(?,?,?,?,0)");
  $ins->execute([$match_id, 1, $bat_first, $bowling]);
}

$pdo->prepare("UPDATE matches SET status='live' WHERE id=?")->execute([$match_id]);
echo json_encode(['ok'=>true]);
