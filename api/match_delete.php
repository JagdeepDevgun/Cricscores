<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo); // Ensures only admin can access
header('Content-Type: application/json');

$id = (int)($_POST['match_id'] ?? 0);
if ($id <= 0) { 
    http_response_code(400); 
    echo json_encode(['error' => 'Invalid match ID']); 
    exit; 
}

try {
    $pdo->beginTransaction();

    // 1. Delete ball events linked to this match's innings
    $pdo->exec("DELETE FROM ball_events WHERE innings_id IN (SELECT id FROM innings WHERE match_id=$id)");

    // 2. Delete innings
    $pdo->exec("DELETE FROM innings WHERE match_id=$id");

    // 3. Delete match
    $stmt = $pdo->prepare("DELETE FROM matches WHERE id=?");
    $stmt->execute([$id]);

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
}
