<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$tid = (int)$_POST['team_id'];
$namesRaw = $_POST['names'];
$is_captain = (int)($_POST['is_captain'] ?? 0);

if ($tid <= 0 || empty($namesRaw)) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$names = explode("\n", $namesRaw);
$added = [];

$stmt = $pdo->prepare("INSERT INTO players (name, team_id, is_captain) VALUES (?, ?, ?)");

foreach ($names as $name) {
    $name = trim($name);
    if (!empty($name)) {
        $stmt->execute([$name, $tid, $is_captain]);
        $lid = $pdo->lastInsertId();
        $added[] = ['id' => $lid, 'name' => $name, 'team_id' => $tid, 'is_captain' => $is_captain];
        
        // If adding multiple players, only mark the first one as captain (if selected)
        if ($is_captain) $is_captain = 0; 
    }
}

echo json_encode(['ok' => true, 'players' => $added]);
?>