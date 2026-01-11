<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$id = (int)($_POST['team_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$short = trim($_POST['short_name'] ?? '');
$icon = trim($_POST['icon'] ?? 'shield');

if ($id <= 0 || empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE teams SET name = ?, short_name = ?, icon = ? WHERE id = ?");
    $stmt->execute([$name, $short, $icon, $id]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>