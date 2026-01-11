<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$id = (int)($_POST['team_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

try {
    // 1. Check if team has played any matches
    $chk = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE team_a_id=? OR team_b_id=?");
    $chk->execute([$id, $id]);
    if ($chk->fetchColumn() > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete: Team has played matches.']);
        exit;
    }

    // 2. Delete players associated with this team
    $pdo->prepare("DELETE FROM players WHERE team_id=?")->execute([$id]);

    // 3. Delete the team
    $pdo->prepare("DELETE FROM teams WHERE id=?")->execute([$id]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
