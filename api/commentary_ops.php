<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

// Auth Check
$user = auth_user($pdo);
if (!$user && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Ensure DB Table Exists
$pdo->exec("CREATE TABLE IF NOT EXISTS commentary (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    trigger_event TEXT NOT NULL, 
    context_tag TEXT NOT NULL DEFAULT 'default',
    text_template TEXT NOT NULL,
    is_user_added INTEGER DEFAULT 0
)");

$action = $_POST['action'] ?? ($_GET['action'] ?? 'list');

// --- LIST ALL ---
if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM commentary ORDER BY id DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- ADD NEW ---
if ($action === 'add') {
    $trigger = $_POST['trigger'] ?? '';
    $context = $_POST['context'] ?? 'default';
    $text = trim($_POST['text'] ?? '');
    
    // FIX: Check explicitly for empty string so "0" (Dot Ball) is accepted
    if ($trigger !== '' && $text !== '') {
        $stmt = $pdo->prepare("INSERT INTO commentary (trigger_event, context_tag, text_template, is_user_added) VALUES (?, ?, ?, 1)");
        $stmt->execute([$trigger, $context, $text]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['error' => 'Missing fields']);
    }
    exit;
}

// --- EDIT EXISTING ---
if ($action === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $trigger = $_POST['trigger'] ?? '';
    $context = $_POST['context'] ?? '';
    $text = trim($_POST['text'] ?? '');
    
    // FIX: Check explicitly for empty string
    if ($id && $trigger !== '' && $text !== '') {
        $stmt = $pdo->prepare("UPDATE commentary SET trigger_event=?, context_tag=?, text_template=? WHERE id=?");
        $stmt->execute([$trigger, $context, $text, $id]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['error' => 'Invalid data']);
    }
    exit;
}

// --- DELETE ---
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("DELETE FROM commentary WHERE id=?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
    }
    exit;
}
?>