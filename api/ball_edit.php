<?php
// api/ball_edit.php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$ball_id = (int)($_POST['ball_id'] ?? 0);
if ($ball_id <= 0) { http_response_code(400); echo json_encode(['error' => 'ball_id required']); exit; }

// Fetch existing ball to get innings_id
$old = $pdo->prepare("SELECT innings_id FROM ball_events WHERE id=?");
$old->execute([$ball_id]);
$innings_id = $old->fetchColumn();

if (!$innings_id) { http_response_code(404); echo json_encode(['error' => 'Ball not found']); exit; }

// Prepare Update Data
$runs_bat    = (int)($_POST['runs_bat'] ?? 0);
$extras_type = trim($_POST['extras_type'] ?? '');
$extras_runs = (int)($_POST['extras_runs'] ?? 0);
$is_wicket   = (int)($_POST['is_wicket'] ?? 0);
$wicket_type = trim($_POST['wicket_type'] ?? '');
$wicket_player_out_id = (int)($_POST['wicket_player_out_id'] ?? 0);

// Determine Legality
$is_legal = ($extras_type === 'wd' || $extras_type === 'nb') ? 0 : 1;

// Update the Ball
$sql = "UPDATE ball_events SET 
        runs_bat=?, extras_type=?, extras_runs=?, 
        is_wicket=?, wicket_type=?, wicket_player_out_id=?, is_legal=?
        WHERE id=?";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    $runs_bat, ($extras_type ?: null), $extras_runs, 
    $is_wicket, ($wicket_type ?: null), ($wicket_player_out_id ?: null), $is_legal,
    $ball_id
]);

// PERFORMANCE: Update the cached totals in 'innings' table
recalculate_innings_score($pdo, $innings_id);

// Update Target (if Innings 1)
$innStmt = $pdo->prepare("SELECT innings_no, match_id FROM innings WHERE id=?");
$innStmt->execute([$innings_id]);
$inn = $innStmt->fetch(PDO::FETCH_ASSOC);

if ($inn && (int)$inn['innings_no'] === 1) {
    // Get fresh totals using the new optimized cache
    $t1 = innings_totals($pdo, $innings_id); 
    $target = $t1['runs'] + 1;
    $pdo->prepare("UPDATE innings SET target=? WHERE match_id=? AND innings_no=2")->execute([$target, $inn['match_id']]);
}

echo json_encode(['ok' => true]);
?>
