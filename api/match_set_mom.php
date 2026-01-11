<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$match_id = (int)$_POST['match_id'];
$player_id = (int)$_POST['player_id'];

if ($match_id > 0 && $player_id > 0) {
    $stmt = $pdo->prepare("UPDATE matches SET man_of_match_id = ? WHERE id = ?");
    $stmt->execute([$player_id, $match_id]);
    echo json_encode(['ok' => true]);
} else {
    echo json_encode(['error' => 'Invalid ID']);
}
?>
