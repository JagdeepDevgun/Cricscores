<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$id = (int)($_POST['player_id'] ?? 0);

if ($id <= 0) { 
    echo json_encode(['error' => 'Invalid ID']); 
    exit; 
}

try {
    // 1. Check if player has any match history
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM ball_events 
        WHERE striker_id = ? OR non_striker_id = ? OR bowler_id = ?
    ");
    $check->execute([$id, $id, $id]);
    $count = $check->fetchColumn();

    if ($count > 0) {
        // STOP DELETE: Player has history
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete: This player has match stats.']);
        exit;
    }

    // 2. Safe to delete
    $stmt = $pdo->prepare("DELETE FROM players WHERE id=?");
    $stmt->execute([$id]);
    
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>