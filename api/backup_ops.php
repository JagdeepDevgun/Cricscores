<?php
// api/backup_ops.php
require_once __DIR__ . '/../db.php'; // For DB connection (to run checkpoint) and auth check

session_start();
// Basic Auth Check
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$dbFile = __DIR__ . '/../cric.db';
$backupDir = __DIR__ . '/../backups/';

if (!is_dir($backupDir)) mkdir($backupDir, 0775, true);

// 1. CREATE BACKUP
if ($action === 'create') {
    try {
        // Run Checkpoint to flush WAL to main DB file before copying
        $pdo->exec("PRAGMA wal_checkpoint(FULL);");
        
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

// 2. DELETE BACKUP
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

// 3. RESTORE BACKUP
elseif ($action === 'restore') {
    $file = basename($_POST['file'] ?? '');
    $source = $backupDir . $file;
    
    if ($file && file_exists($source)) {
        // Safety: Backup current state before overwriting
        $safetyBackup = $backupDir . 'pre_restore_' . date('Y-m-d_H-i-s') . '.db';
        $pdo->exec("PRAGMA wal_checkpoint(FULL);"); // Checkpoint current before safety backup
        copy($dbFile, $safetyBackup);
        
        // Restore
        // We must close connection if possible, or assume file copy works in WAL mode (usually safe on Linux)
        $pdo = null; // Close current PHP connection
        
        if (copy($source, $dbFile)) {
            // Force Logout after restore
            session_destroy();
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to overwrite database file. Check permissions.']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Backup file not found']);
    }
}

// 4. DOWNLOAD BACKUP
elseif ($action === 'download') {
    $file = basename($_GET['file'] ?? '');
    $path = $backupDir . $file;
    
    if ($file && file_exists($path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    } else {
        die("File not found");
    }
}
