<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo); // Ensure user is logged in

header('Content-Type: application/json');

$oldPass = $_POST['old_password'] ?? '';
$newPass = $_POST['new_password'] ?? '';
$userId = $_SESSION['user_id'];

if (!$oldPass || !$newPass) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

try {
    // 1. Fetch current password hash
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentHash = $stmt->fetchColumn();

    // 2. Verify old password
    if (!password_verify($oldPass, $currentHash)) {
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'Incorrect old password']);
        exit;
    }

    // 3. Hash new password and update
    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $update->execute([$newHash, $userId]);

    echo json_encode(['ok' => true, 'message' => 'Password updated successfully']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>
