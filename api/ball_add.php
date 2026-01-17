<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

// --- INTERNAL HELPER (No External Deps) ---
function calc_totals($pdo, $inn_id) {
    $stmt = $pdo->prepare("SELECT COUNT(CASE WHEN is_legal=1 THEN 1 END) as legal, SUM(runs_bat+extras_runs) as runs, SUM(CASE WHEN is_wicket=1 THEN 1 END) as wkts FROM ball_events WHERE innings_id=?");
    $stmt->execute([$inn_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$innings_id  = (int)($_POST['innings_id'] ?? 0);
$runs_bat    = (int)($_POST['runs_bat'] ?? 0);
$extras_type = trim($_POST['extras_type'] ?? '');
$extras_runs = (int)($_POST['extras_runs'] ?? 0);
$is_wicket   = (int)($_POST['is_wicket'] ?? 0);
$wicket_type = trim($_POST['wicket_type'] ?? '');

if ($is_wicket && (stripos($wicket_type, 'run') !== false && stripos($wicket_type, 'out') !== false)) {
    $wicket_type = 'run out';
}

$striker_id = (int)($_POST['striker_id'] ?? 0);
$non_striker_id = (int)($_POST['non_striker_id'] ?? 0);
$bowler_id = (int)($_POST['bowler_id'] ?? 0);
$wicket_player_out_id = (int)($_POST['wicket_player_out_id'] ?? 0); 

if ($innings_id <= 0) { http_response_code(400); echo json_encode(['error' => 'innings_id required']); exit; }

// 1. Fetch Meta
$meta = $pdo->prepare("SELECT i.*, m.status AS match_status, m.overs_limit AS match_overs, m.wickets_limit AS match_wickets FROM innings i JOIN matches m ON m.id=i.match_id WHERE i.id=?");
$meta->execute([$innings_id]);
$inn = $meta->fetch(PDO::FETCH_ASSOC);

if (!$inn) { http_response_code(404); echo json_encode(['error'=>'Innings not found']); exit; }

// 2. Reset Status if needed
if ((int)$inn['completed'] === 1 || $inn['match_status'] === 'completed') {
    $pdo->prepare("UPDATE innings SET completed=0 WHERE id=?")->execute([$innings_id]);
    $pdo->prepare("UPDATE matches SET status='live', winner_team_id=NULL, result_type=NULL WHERE id=?")->execute([$inn['match_id']]);
}

$is_legal = ($extras_type === 'wd' || $extras_type === 'nb') ? 0 : 1;
$stmt = $pdo->prepare('SELECT COALESCE(MAX(seq),0)+1 FROM ball_events WHERE innings_id=?');
$stmt->execute([$innings_id]);
$next_seq = (int)$stmt->fetchColumn();

// 3. Insert
$stmt = $pdo->prepare("INSERT INTO ball_events(innings_id, seq, striker_id, non_striker_id, bowler_id, runs_bat, extras_type, extras_runs, is_wicket, wicket_type, wicket_player_out_id, is_legal) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
$stmt->execute([$innings_id, $next_seq, ($striker_id?:null), ($non_striker_id?:null), ($bowler_id?:null), $runs_bat, ($extras_type!==''?$extras_type:null), $extras_runs, $is_wicket?1:0, ($wicket_type!==''?$wicket_type:null), ($wicket_player_out_id?:null), $is_legal]);

// 4. Update Target (Logic)
if ((int)$inn['innings_no'] === 1) {
    $t = calc_totals($pdo, $innings_id);
    $newTarget = $t['runs'] + 1;
    $pdo->prepare("UPDATE innings SET target=? WHERE match_id=? AND innings_no=2")->execute([$newTarget, $inn['match_id']]);
}

// 5. Auto End Check
$tot = calc_totals($pdo, $innings_id);
$oversLimit = (isset($inn['overs_limit_override']) && $inn['overs_limit_override'] !== null) ? (int)$inn['overs_limit_override'] : (int)$inn['match_overs'];
$ballLimit = $oversLimit * 6;
$wicketLimit = (int)$inn['match_wickets'] > 0 ? (int)$inn['match_wickets'] : 10;

$autoEnd = false;
$reason = null;

if ($tot['wkts'] >= $wicketLimit) { $autoEnd = true; $reason = 'all_out'; }
if ($tot['legal'] >= $ballLimit) { $autoEnd = true; $reason = 'overs_limit'; }

$innNo = (int)$inn['innings_no'];
if (($innNo === 2 || $innNo === 4) && $inn['target'] !== null) {
  if ($tot['runs'] >= (int)$inn['target']) { $autoEnd = true; $reason = 'target_reached'; }
}

if ($autoEnd) {
  require_once __DIR__ . '/innings_complete.php'; // Reuse logic if possible, or trigger completion
  complete_innings($pdo, $innings_id);
  echo json_encode(['ok'=>true, 'auto_end'=>true, 'auto_end_reason'=>$reason, 'advance'=>true]);
  exit;
}

echo json_encode(['ok'=>true]);
?>