<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '') {
  http_response_code(400);
  echo json_encode(['error' => 'username and password required']);
  exit;
}

$stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username=?');
$stmt->execute([$username]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u || !password_verify($password, $u['password_hash'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Invalid credentials']);
  exit;
}

$_SESSION['user_id'] = (int)$u['id'];
echo json_encode(['ok' => true]);
