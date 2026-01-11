<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$tid = (int)($_POST['tournament_id'] ?? 0);
$name = trim($_POST['names'] ?? ''); // 'names' matches the form input name
$short = trim($_POST['short_name'] ?? '');
$icon = trim($_POST['icon'] ?? 'shield');

if ($tid <= 0 || empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid Name or Tournament']);
    exit;
}

// Auto-generate short name if empty (e.g., "Mumbai Indians" -> "MUM")
if (empty($short)) {
    $short = strtoupper(substr($name, 0, 3));
}

try {
    $stmt = $pdo->prepare("INSERT INTO teams (name, short_name, icon, tournament_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $short, $icon, $tid]);
    
    // Return all teams to update the UI immediately
    $all = $pdo->prepare("SELECT * FROM teams WHERE tournament_id=? ORDER BY name");
    $all->execute([$tid]);
    
    echo json_encode(['ok' => true, 'teams' => $all->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>