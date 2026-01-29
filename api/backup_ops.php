<?php
// api/backup_ops.php
require_once __DIR__ . '/../db.php';

session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$dbFile = __DIR__ . '/../cric.db';
// Centralized backup directory
$backupDir = __DIR__ . '/../data/backups/'; 

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
    chmod($backupDir, 0775);
}

// 1. LIST ACTION (Required for settings.php to show the table)
if ($action === 'list') {
    $files = glob($backupDir . "*.db");
    $list = [];
    foreach ($files as $f) {
        $list[] = [
            'name' => basename($f),
            'size' => round(filesize($f) / 1024, 2) . ' KB',
            'date' => date("Y-m-d H:i:s", filemtime($f))
        ];
    }
    usort($list, function($a, $b) { return strcmp($b['date'], $a['date']); });
    header('Content-Type: application/json');
    echo json_encode($list);
    exit;
}

// 2. CREATE BACKUP
elseif ($action === 'create') {
    try {
        $filename = 'cric_backup_' . date('Y-m-d_H-i-s') . '.db';
        $dest = $backupDir . $filename;
        if (copy($dbFile, $dest)) {
            echo json_encode(['success' => true, 'file' => $filename]);
        } else {
            throw new Exception("Failed to copy database file.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// 3. DELETE BACKUP
elseif ($action === 'delete') {
    $file = basename($_POST['file'] ?? '');
    $path = $backupDir . $file;
    if ($file && file_exists($path)) {
        unlink($path);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
    }
}

// 4. RESTORE BACKUP
elseif ($action === 'restore') {
    $file = basename($_POST['file'] ?? '');
    $source = $backupDir . $file;
    if ($file && file_exists($source)) {
        $safetyBackup = $backupDir . 'pre_restore_' . date('Y-m-d_H-i-s') . '.db';
        copy($dbFile, $safetyBackup);
        $pdo = null; 
        if (copy($source, $dbFile)) {
            session_destroy();
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Restore failed.']);
        }
    }
}
?>