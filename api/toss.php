<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$match_id = (int)($_POST['match_id'] ?? 0);
$winner = (int)($_POST['winner_team_id'] ?? 0);
$decision = trim($_POST['decision'] ?? ''); // 'bat' or 'bowl'

if ($match_id <= 0 || $winner <= 0 || !in_array($decision, ['bat','bowl'])) {
  http_response_code(400); echo json_encode(['error'=>'Invalid toss data']); exit;
}

$pdo->prepare("UPDATE matches SET toss_winner_team_id=?, toss_decision=? WHERE id=?")
    ->execute([$winner, $decision, $match_id]);

echo json_encode(['ok'=>true]);
