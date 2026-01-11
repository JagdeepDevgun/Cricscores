<?php
// api/auth.php
// Session + helper functions for login/logout + user lookup

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function auth_user(PDO $pdo): ?array {
  $uid = (int)($_SESSION['user_id'] ?? 0);
  if ($uid <= 0) return null;

  $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id=?');
  $stmt->execute([$uid]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  return $u ?: null;
}

function is_logged_in(): bool {
  return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function require_login() {
  if (!is_logged_in()) {
    http_response_code(403);
    exit('Unauthorized');
  }
}
