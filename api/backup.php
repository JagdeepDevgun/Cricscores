<?php
// api/backup.php

// 1. Security Check
session_start();
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    die("Access Denied. Please login.");
}

// 2. Define Database Path
// Adjust this if your folder structure is different. 
// Currently assumes api/ is one level deep, so ../data/cric.db is the path.
$dbPath = __DIR__ . '/../cric.db';

// 3. Check if file exists
if (!file_exists($dbPath)) {
    http_response_code(404);
    die("Error: Database file not found at: " . $dbPath);
}

// 4. Force Download Headers
$backupName = 'cric_backup_' . date('Y-m-d_H-i') . '.db';

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $backupName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($dbPath));

// 5. Clear output buffer (Crucial to prevent corrupted files)
while (ob_get_level()) {
    ob_end_clean();
}

// 6. Send File
readfile($dbPath);
exit;
?>
