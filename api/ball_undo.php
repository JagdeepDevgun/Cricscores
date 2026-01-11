<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/_helpers.php'; // Required for totals calc
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$innings_id = (int)($_POST['innings_id'] ?? 0);
if ($innings_id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'innings_id required']);
  exit;
}

// 1. Find last ball
$stmt = $pdo->prepare('SELECT id FROM ball_events WHERE innings_id=? ORDER BY seq DESC LIMIT 1');
$stmt->execute([$innings_id]);
$id = $stmt->fetchColumn();

if (!$id) {
  echo json_encode(['ok' => true, 'msg' => 'Nothing to undo']);
  exit;
}

// 2. Delete it
$del = $pdo->prepare('DELETE FROM ball_events WHERE id=?');
$del->execute([$id]);

// 3. Reset Completion (Unlock Innings & Match)
$pdo->prepare("UPDATE innings SET completed=0 WHERE id=?")->execute([$innings_id]);
$pdo->prepare("
    UPDATE matches 
    SET status='live', winner_team_id=NULL, result_type=NULL 
    WHERE id=(SELECT match_id FROM innings WHERE id=?)
")->execute([$innings_id]);

// 4. Update Target (If 1st Innings modified)
$meta = $pdo->prepare("SELECT innings_no, match_id FROM innings WHERE id=?");
$meta->execute([$innings_id]);
$inn = $meta->fetch(PDO::FETCH_ASSOC);

if ($inn && (int)$inn['innings_no'] === 1) {
    $newTotal = innings_totals($pdo, $innings_id)['runs'];
    $newTarget = $newTotal + 1;
    $pdo->prepare("UPDATE innings SET target=? WHERE match_id=? AND innings_no=2")->execute([$newTarget, $inn['match_id']]);
}

echo json_encode(['ok' => true]);
?>