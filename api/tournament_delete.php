<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo); // Ensures only admin can access
header('Content-Type: application/json');

$id = (int)($_POST['tournament_id'] ?? 0);
if ($id <= 0) { 
    http_response_code(400); 
    echo json_encode(['error' => 'Invalid tournament ID']); 
    exit; 
}

try {
    $pdo->beginTransaction();
    
    // 1. Get all matches in this tournament to clean up their data
    $mIds = $pdo->query("SELECT id FROM matches WHERE tournament_id=$id")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($mIds)) {
        $mList = implode(',', $mIds);
        // Delete balls
        $pdo->exec("DELETE FROM ball_events WHERE innings_id IN (SELECT id FROM innings WHERE match_id IN ($mList))");
        // Delete innings
        $pdo->exec("DELETE FROM innings WHERE match_id IN ($mList)");
        // Delete matches
        $pdo->exec("DELETE FROM matches WHERE tournament_id=$id");
    }

    // 2. Delete Teams & Players
    $tIds = $pdo->query("SELECT id FROM teams WHERE tournament_id=$id")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($tIds)) {
        $tList = implode(',', $tIds);
        $pdo->exec("DELETE FROM players WHERE team_id IN ($tList)");
        $pdo->exec("DELETE FROM teams WHERE tournament_id=$id");
    }

    // 3. Delete Tournament
    $stmt = $pdo->prepare("DELETE FROM tournaments WHERE id=?");
    $stmt->execute([$id]);
    
    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
}
