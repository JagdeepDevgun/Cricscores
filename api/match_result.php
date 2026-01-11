<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$match_id = (int)($_POST['match_id'] ?? 0);
$result = trim($_POST['result'] ?? ''); // 'A','B','tie','nr'

if ($match_id <= 0 || $result === '') { 
    http_response_code(400); 
    echo json_encode(['error' => 'match_id and result required']); 
    exit; 
}

$stmt = $pdo->prepare("SELECT * FROM matches WHERE id=?");
$stmt->execute([$match_id]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$m) { 
    http_response_code(404); 
    echo json_encode(['error' => 'Match not found']); 
    exit; 
}

$winner = null;
if ($result === 'A') $winner = (int)$m['team_a_id'];
else if ($result === 'B') $winner = (int)$m['team_b_id'];
else if ($result === 'tie' || $result === 'nr') $winner = null;
else { 
    http_response_code(400); 
    echo json_encode(['error' => 'Invalid result']); 
    exit; 
}

// Update Match: Set status to 'completed' regardless of result type
$up = $pdo->prepare("UPDATE matches SET status='completed', winner_team_id=?, result_type=? WHERE id=?");
$up->execute([$winner, $result, $match_id]);

echo json_encode(['ok' => true]);
?>