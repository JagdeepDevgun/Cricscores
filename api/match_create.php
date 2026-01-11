<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$tid = (int)($_POST['tournament_id'] ?? 0);
$team_a = (int)($_POST['team_a_id'] ?? 0);
$team_b = (int)($_POST['team_b_id'] ?? 0);
$bat_first = (int)($_POST['batting_first_team_id'] ?? 0);
$overs = (int)($_POST['overs_limit'] ?? 20);
$is_final = (int)($_POST['is_final'] ?? 0); // NEW

if ($tid<=0 || $team_a<=0 || $team_b<=0 || $bat_first<=0) {
    echo json_encode(['error' => 'Invalid input']); exit;
}

try {
    $pdo->beginTransaction();
    
    // Create Match with is_final flag
    $stmt = $pdo->prepare("INSERT INTO matches (tournament_id, team_a_id, team_b_id, toss_winner_team_id, toss_decision, overs_limit, is_final, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'live')");
    // Assuming batting first team won toss and chose bat for simplicity, or just setting up the game state
    $stmt->execute([$tid, $team_a, $team_b, $bat_first, 'bat', $overs, $is_final]);
    $match_id = $pdo->lastInsertId();

    // Create Innings 1
    $i1 = $pdo->prepare("INSERT INTO innings (match_id, innings_no, batting_team_id, bowling_team_id) VALUES (?, 1, ?, ?)");
    $i1->execute([$match_id, $bat_first, ($bat_first == $team_a ? $team_b : $team_a)]);

    // Create Innings 2
    $i2 = $pdo->prepare("INSERT INTO innings (match_id, innings_no, batting_team_id, bowling_team_id) VALUES (?, 2, ?, ?)");
    $i2->execute([$match_id, ($bat_first == $team_a ? $team_b : $team_a), $bat_first]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'match_id' => $match_id]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
?>