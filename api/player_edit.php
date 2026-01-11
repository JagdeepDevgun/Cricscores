<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$id = (int)$_POST['player_id'];
$name = trim($_POST['name']);
$tid = (int)$_POST['team_id'];
$is_captain = (int)($_POST['is_captain'] ?? 0);

if ($id <= 0 || empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {
    // Basic update. In a more complex app, you might want to unset previous captains of the same team.
    $stmt = $pdo->prepare("UPDATE players SET name=?, team_id=?, is_captain=? WHERE id=?");
    $stmt->execute([$name, $tid, $is_captain, $id]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>