<?php
// api/backup.php

// 1. Security Check
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    die("Access Denied. Please login.");
}

// 2. Define Paths
$dbPath = __DIR__ . '/../cric.db';
$backupDir = __DIR__ . '/../data/'; 

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0775, true);
}

// 3. Source Check
if (!file_exists($dbPath)) {
    http_response_code(404);
    die("Error: Database file not found.");
}

// 4. Create backup file (Direct copy is safe when not in WAL mode)
$backupFileName = 'cric_backup_' . date('Y-m-d_H-i-s') . '.db';
$destPath = $backupDir . $backupFileName;

if (!copy($dbPath, $destPath)) {
    die("Error: Failed to create backup file.");
}

// 5. Force Download Headers
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $backupFileName . '"');
header('Content-Length: ' . filesize($destPath));

while (ob_get_level()) { ob_end_clean(); }
readfile($destPath);
exit;
?>