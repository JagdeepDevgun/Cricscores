<?php
// api/match_set_mom.php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php'; // Corrected path: auth.php is in the same directory

// Ensure the user is logged in before allowing updates
require_login($pdo);

header('Content-Type: application/json');

// Get the POST data from the Man of the Match selection
$match_id = (int)($_POST['match_id'] ?? 0);
$player_id = (int)($_POST['player_id'] ?? 0);

if ($match_id > 0 && $player_id > 0) {
    try {
        // Update the match record with the selected player's ID
        $stmt = $pdo->prepare("UPDATE matches SET man_of_match_id = ? WHERE id = ?");
        $stmt->execute([$player_id, $match_id]);
        
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Match ID or Player ID']);
}
?>