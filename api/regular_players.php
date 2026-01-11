<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

// Auto-create table if missing
$pdo->exec("CREATE TABLE IF NOT EXISTS saved_players (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE)");

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// 1. LIST
if ($action === 'list') {
    $stmt = $pdo->query("SELECT * FROM saved_players ORDER BY name");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// 2. ADD
if ($action === 'add') {
    $name = trim($_POST['name'] ?? '');
    if (!$name) { 
        echo json_encode(['ok'=>false, 'error'=>'Name required']); 
        exit; 
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO saved_players (name) VALUES (?)");
        $stmt->execute([$name]);
        echo json_encode(['ok'=>true, 'id'=>$pdo->lastInsertId()]);
    } catch(Exception $e) {
        // Likely duplicate, just ignore
        echo json_encode(['ok'=>true, 'msg'=>'Already exists']);
    }
    exit;
}

// 3. DELETE
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $pdo->prepare("DELETE FROM saved_players WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]);
    exit;
}
?>