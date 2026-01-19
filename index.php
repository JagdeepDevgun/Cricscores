<?php
require_once __DIR__ . '/db.php';

$user = null;
if (file_exists(__DIR__ . '/api/auth.php')) {
  require_once __DIR__ . '/api/auth.php';
  if (function_exists('auth_user')) $user = auth_user($pdo);
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$tournaments = $pdo->query("SELECT id, name FROM tournaments ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$selectedTid = (int)($_GET['tournament_id'] ?? 0);
if ($selectedTid <= 0 && count($tournaments) > 0) $selectedTid = (int)$tournaments[0]['id'];

$tab = strtolower(trim((string)($_GET['tab'] ?? 'all')));
if (!in_array($tab, ['all','live','completed'], true)) $tab = 'all';

$matches = [];
if ($selectedTid > 0) {
  $sql = "
    SELECT m.*, ta.name AS team_a_name, tb.name AS team_b_name
    FROM matches m
    JOIN teams ta ON ta.id = m.team_a_id
    JOIN teams tb ON tb.id = m.team_b_id
    WHERE m.tournament_id = ?
    ORDER BY
      CASE m.status
        WHEN 'live' THEN 0
        WHEN 'awaiting_super_over' THEN 1
        WHEN 'completed' THEN 2
        ELSE 3
      END,
      m.id DESC
  ";
  $st = $pdo->prepare($sql);
  $st->execute([$selectedTid]);
  $matches = $st->fetchAll(PDO::FETCH_ASSOC);

  if ($tab === 'live') {
    $matches = array_values(array_filter($matches, fn($m) => $m['status'] === 'live' || $m['status'] === 'awaiting_super_over'));
  } elseif ($tab === 'completed') {
    $matches = array_values(array_filter($matches, fn($m) => $m['status'] === 'completed'));
  }
}

function result_text($m){
  if (($m['status'] ?? '') !== 'completed') return '';
  $rt = $m['result_type'] ?? null;
  if ($rt === 'tie') return 'MATCH TIED';
  if ($rt === 'nr')  return 'NO RESULT';
  if ($rt === 'A')   return h($m['team_a_name']) . ' WON';
  if ($rt === 'B')   return h($m['team_b_name']) . ' WON';
  return 'COMPLETED';
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="/style.css"/>
  <title>CricScore LIVE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />

  <style>
    .row { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:20px; margin-top:20px; }
    @media (max-width:900px){ .grid{ grid-template-columns:repeat(2, 1fr); } }
    @media (max-width:600px){ .grid{ grid-template-columns:1fr; } }
    
    .match-card {
        display: block;
        background: #fff;
        border: 2px solid #2c3e50;
        border-radius: 6px;
        padding: 15px;
        text-decoration: none;
        color: inherit;
        box-shadow: 3px 3px 0px rgba(44, 62, 80, 0.1);
        transition: transform 0.1s;
        height: 100%;
    }
    .match-card:hover {
        transform: translateY(-2px);
        box-shadow: 5px 5px 0px #2c3e50;
        border-color: #00bcd4;
    }
    .match-head { display:flex; justify-content:space-between; gap:10px; align-items:center; margin-bottom:10px; }
    .badge { font-size:11px; font-weight:700; padding:4px 8px; border:1px solid #2c3e50; border-radius:4px; text-transform:uppercase; background:#eee; }
    .badge.live { background: #ffeb3b; color: #000; border-color: #000; box-shadow: 2px 2px 0px rgba(0,0,0,0.1); }
    .teams { font-size:16px; font-weight:800; margin-bottom:5px; }
    .res { margin-top:10px; font-weight:700; color: #00bcd4; font-family: 'Courier New', monospace; }
  </style>
<link rel="manifest" href="/manifest.json">
<link rel="icon" type="image/png" href="/assets/logo.png">
<link rel="apple-touch-icon" href="/assets/icon-192.png">
<meta name="theme-color" content="#2c3e50">

<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch((err) => console.log('SW Registration Failed', err));
  }
</script>
</head>
<body>

<div class="home-wrap">
  <div class="topbar">
    <div class="brand">
      <a href="/" class="brand-link">
       <img src="../assets/logo.png" alt="Logo">
      </a>
    </div>
    <div class="right-actions">
      <a class="btn" href="/pages/players.php">Global Stats</a>
      
      <?php if ($user): ?>
        <a class="btn" href="/pages/commentary_manager.php" style="background:#fff3e0; color:#e65100;">🎙️ Commentary</a>
        <a class="btn" href="/api/tournament_create.php">+ Tournament</a>
        <a class="btn" href="/pages/settings.php">⚙️ Settings</a>
        <a class="btn danger" href="#" id="logoutBtn">Logout</a>
      <?php else: ?>
        <a class="btn" href="/pages/login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="row">
      <div style="flex:1;">
        <div class="muted" style="font-size:12px;margin-bottom:4px;">Select Tournament</div>
        <select id="tournamentSel">
          <?php if (count($tournaments) === 0): ?>
            <option value="">No tournaments</option>
          <?php else: ?>
            <?php foreach($tournaments as $t): ?>
              <option value="<?= (int)$t['id'] ?>" <?= ((int)$t['id'] === $selectedTid) ? 'selected' : '' ?>>
                <?= h($t['name']) ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
      </div>

      <?php if ($selectedTid > 0): ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a class="btn" href="/pages/tournament.php?id=<?= (int)$selectedTid ?>">Points Table</a>
            <?php if ($user): ?>
            <a class="btn" href="/pages/tournament.php?id=<?= (int)$selectedTid ?>#admin">Manage</a>
            <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="tabs" style="margin-top:20px;">
      <a class="tab <?= $tab==='all'?'active':'' ?>" href="?tournament_id=<?= (int)$selectedTid ?>&tab=all">All Matches</a>
      <a class="tab <?= $tab==='live'?'active':'' ?>" href="?tournament_id=<?= (int)$selectedTid ?>&tab=live">Live</a>
      <a class="tab <?= $tab==='completed'?'active':'' ?>" href="?tournament_id=<?= (int)$selectedTid ?>&tab=completed">Completed</a>
    </div>
  </div>

  <?php if ($selectedTid <= 0): ?>
    <div class="card" style="text-align:center;">
      <h3>No tournaments yet</h3>
      <p class="muted">Login and create using <b>+ Tournament</b>.</p>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php if (count($matches) === 0): ?>
        <div class="card" style="grid-column:1/-1; text-align:center;">
          <b>No matches found</b>
          <div class="muted" style="margin-top:6px;">This tournament has no matches yet.</div>
        </div>
      <?php else: ?>
        <?php foreach($matches as $m): ?>
          <?php
            $status = $m['status'] ?? 'scheduled';
            $badgeText = strtoupper($status);
            if ($status === 'awaiting_super_over') $badgeText = 'TIED (SUPER OVER?)';
            $res = result_text($m);
          ?>
          <a class="match-card" href="/pages/match.php?id=<?= (int)$m['id'] ?>">
            <div class="match-head">
              <span class="badge <?= ($status==='live' || $status==='awaiting_super_over') ? 'live':'' ?>">
                <?= h($badgeText) ?>
              </span>
              <span class="muted" style="font-size:12px;">#<?= (int)$m['id'] ?></span>
            </div>

            <div class="teams"><?= h($m['team_a_name']) ?> <span class="muted" style="font-weight:400;font-size:12px;">vs</span> <?= h($m['team_b_name']) ?></div>

            <div class="muted" style="font-size:12px;">
              <?= (int)($m['overs_limit'] ?? 0) ?> Overs
              <?php if (!empty($m['super_over'])): ?> • Super Over<?php endif; ?>
            </div>

            <?php if ($res !== ''): ?>
              <div class="res"><?= h($res) ?></div>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<script>
  const sel = document.getElementById('tournamentSel');
  if (sel) {
    sel.addEventListener('change', () => {
      const tid = sel.value || '';
      const tab = <?= json_encode($tab) ?>;
      if (!tid) return;
      location.href = `?tournament_id=${encodeURIComponent(tid)}&tab=${encodeURIComponent(tab)}`;
    });
  }

  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      try { await fetch('/api/logout.php', { credentials:'same-origin' }); } catch(e){}
      location.href = '/';
    });
  }
</script>
</body>
</html>