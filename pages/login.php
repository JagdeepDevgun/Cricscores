<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../api/auth.php';

$user = auth_user($pdo);
if ($user) {
  header('Location: ../index.php');
  exit;
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <link rel="stylesheet" href="../style.css" />
  <title>Login - Cric Score</title>
<link rel="manifest" href="/manifest.json">
<link rel="icon" type="image/png" href="/assets/logo.png">
<link rel="apple-touch-icon" href="/assets/icon-192.png">
<meta name="theme-color" content="#2c3e50">
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
  }
</script>
</head>

<body>

  <div class="main-wrapper"
    style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:80vh;">

    <div style="text-align:center; margin-bottom:32px;">
      <a href="../index.php"
        style="display:inline-flex; align-items:center; gap:12px; font-size:2rem; font-weight:800; color:white; text-decoration:none;">
        <img src="../assets/logo.png" alt="Logo"
          style="height:64px; filter:drop-shadow(0 0 20px rgba(99,102,241,0.5));">
        <span>CRIC<span style="font-weight:400; opacity:0.8;">SCORE</span></span>
      </a>
    </div>

    <div class="glass-card animate-enter" style="width:100%; max-width:400px; padding:40px;">
      <h2 style="text-align:center; margin-bottom:24px; font-size:1.8rem;">Welcome Back</h2>

      <form id="loginForm">
        <label>Username</label>
        <input name="username" placeholder="Enter username" required style="margin-bottom:20px;">

        <label>Password</label>
        <input type="password" name="password" placeholder="Enter password" required style="margin-bottom:32px;">

        <button type="submit" class="btn" style="width:100%;">
          <span class="material-symbols-rounded">login</span> Login
        </button>
      </form>

      <div id="err"
        style="color: #ef4444; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 12px; border-radius: 8px; margin-top: 20px; display:none; text-align: center; font-size: 0.9rem;">
      </div>
    </div>

    <div style="margin-top:24px; color:var(--text-muted);">
      <a href="../index.php">← Back to Home</a>
    </div>

  </div>

  <script>
    const form = document.getElementById('loginForm');
    const err = document.getElementById('err');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      err.style.display = 'none';
      const btn = form.querySelector('button');
      const oldTxt = btn.innerHTML;
      btn.innerHTML = 'Logging in...';
      btn.disabled = true;

      const fd = new FormData(form);
      try {
        const r = await fetch('../api/login.php', { method: 'POST', body: fd });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) {
          err.textContent = j.error || 'Login failed';
          err.style.display = 'block';
          btn.innerHTML = oldTxt;
          btn.disabled = false;
          return;
        }
        location.href = '../index.php';
      } catch (e) {
        err.textContent = 'Connection error';
        err.style.display = 'block';
        btn.innerHTML = oldTxt;
        btn.disabled = false;
      }
    });
  </script>
</body>

</html>