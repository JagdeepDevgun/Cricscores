<?php
// /api/tournament_create.php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function table_columns(PDO $pdo, string $table): array {
  $cols = [];
  try {
    $st = $pdo->query("PRAGMA table_info($table)");
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
      $cols[$r['name']] = true;
    }
  } catch (Throwable $e) {}
  return $cols;
}

function json_out(int $code, array $payload){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload);
  exit;
}

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
$isSqlite = ($driver === 'sqlite');

// ---------------------------------------------
// CREATE TOURNAMENT POST
// ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['_action'])) {
  $name = trim((string)($_POST['name'] ?? ''));
  $type = trim((string)($_POST['type'] ?? 'round_robin'));

  if ($name === '') json_out(400, ['error' => 'Tournament name is required']);
  if (!in_array($type, ['round_robin','knockout'], true)) $type = 'round_robin';

  $win  = (int)($_POST['win_points'] ?? 2);
  $tie  = (int)($_POST['tie_points'] ?? 1);
  $nr   = (int)($_POST['nr_points'] ?? 1);
  $loss = (int)($_POST['loss_points'] ?? 0);
  
  // New Configs
  $defOvers = (int)($_POST['default_overs'] ?? 20);
  $defWickets = (int)($_POST['default_wickets'] ?? 10);

  $cols = table_columns($pdo, 'tournaments');

  $data = [
    'name' => $name,
    'type' => $type,
    'win_points' => $win,
    'tie_points' => $tie,
    'nr_points' => $nr,
    'loss_points' => $loss,
    'default_overs' => $defOvers,
    'default_wickets' => $defWickets,
  ];

  $insCols = [];
  $insVals = [];
  $params  = [];

  foreach ($data as $k => $v) {
    if (isset($cols[$k])) {
      $insCols[] = $k;
      $insVals[] = '?';
      $params[]  = $v;
    }
  }

  try {
    $sql = "INSERT INTO tournaments (" . implode(',', $insCols) . ") VALUES (" . implode(',', $insVals) . ")";
    $st = $pdo->prepare($sql);
    $st->execute($params);

    $id = (int)$pdo->lastInsertId();
    json_out(200, ['id' => $id, 'ok' => true]);
  } catch (Throwable $e) {
    json_out(500, ['error' => 'Tournament create failed: '.$e->getMessage()]);
  }
}

// ---------------------------------------------
// Load tournament for Add Teams UI
// ---------------------------------------------
$tid = (int)($_GET['id'] ?? 0);
$tournament = null;
$teams = [];

if ($tid > 0) {
  $st = $pdo->prepare("SELECT * FROM tournaments WHERE id=?");
  $st->execute([$tid]);
  $tournament = $st->fetch(PDO::FETCH_ASSOC);

  if ($tournament) {
    $st2 = $pdo->prepare("SELECT id,name FROM teams WHERE tournament_id=? ORDER BY id ASC");
    $st2->execute([$tid]);
    $teams = $st2->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $tid = 0;
  }
}

// ---------------------------------------------
// Add teams POST
// ---------------------------------------------
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['_action'] ?? '') === 'add_teams') {
  $tid = (int)($_POST['tournament_id'] ?? 0);

  if ($tid <= 0) {
    $err = 'Tournament not found.';
  } else {
    $raw = (array)($_POST['teams'] ?? []);
    $names = [];
    foreach ($raw as $r) {
      $r = trim((string)$r);
      if ($r !== '') $names[] = $r;
    }
    $names = array_values(array_unique($names));

    if (count($names) < 2) {
      $err = 'Add at least 2 teams.';
    } else {
      if ($isSqlite) $sql = "INSERT OR IGNORE INTO teams(tournament_id,name) VALUES(?,?)";
      else $sql = "INSERT IGNORE INTO teams(tournament_id,name) VALUES(?,?)";

      $ins = $pdo->prepare($sql);
      foreach ($names as $nm) $ins->execute([$tid, $nm]);

      header("Location: /pages/tournament.php?id=".$tid);
      exit;
    }
  }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="/style.css"/>
  <title>Create Tournament</title>
  <style>
    .wrap{ max-width:980px; margin:18px auto; padding:0 12px; }
    .card{ border:1px solid rgba(255,255,255,.12); border-radius:14px; padding:14px; margin-top:12px; }
    .row{ display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
    .btn{ display:inline-block; padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,.12); text-decoration:none; background:transparent; color:inherit; cursor:pointer; }
    input, select{ padding:10px 12px; border-radius:10px; width:100%; border:1px solid rgba(255,255,255,.12); background:transparent; color:inherit; box-sizing:border-box; }
    label{ font-size:13px; opacity:.85; }
    .two{ display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    @media (max-width:650px){ .two{ grid-template-columns:1fr; } }
    .err{ background:rgba(255,0,0,.12); border:1px solid rgba(255,0,0,.25); padding:10px; border-radius:12px; margin-top:12px; }
    .teamrow{ display:flex; gap:10px; margin-top:10px; align-items:center; flex-wrap:nowrap; }
    .teamrow input{ flex: 1 1 auto; width: auto !important; min-width: 320px; }
    .teamrow .btn{ min-width: 120px; text-align:center; white-space:nowrap; }
    @media (max-width:650px){ .teamrow{ flex-wrap:wrap; } .teamrow input{ min-width: 0; width:100% !important; } .teamrow .btn{ width:100%; min-width:0; } }
    input::placeholder{ color: rgba(255,255,255,.55); }
  </style>
</head>
<body>
<div class="wrap">

  <div class="row">
    <h2 style="margin:0;">+ Tournament</h2>
    <a class="btn" href="/">Home</a>
  </div>

  <?php if ($err): ?>
    <div class="err"><?= h($err) ?></div>
  <?php endif; ?>

  <?php if (!$tournament): ?>
    <div class="card">
      <h3 style="margin-top:0;">Create Tournament</h3>

      <form id="createForm" method="post" action="/api/tournament_create.php">
        <label>Tournament name</label>
        <input name="name" placeholder="Tournament name" required>

        <div style="height:10px;"></div>

        <label>Type</label>
        <select name="type">
          <option value="round_robin">Round Robin</option>
          <option value="knockout">Knockout</option>
        </select>

        <div style="height:12px;"></div>

        <div class="two">
          <div>
            <label>Win points</label>
            <input type="number" name="win_points" value="2" min="0">
          </div>
          <div>
            <label>Tie points</label>
            <input type="number" name="tie_points" value="1" min="0">
          </div>
        </div>
        <div class="two" style="margin-top:10px;">
          <div>
            <label>No Result points</label>
            <input type="number" name="nr_points" value="1" min="0">
          </div>
          <div>
            <label>Loss points</label>
            <input type="number" name="loss_points" value="0" min="0">
          </div>
        </div>

        <div style="height:16px;"></div>
        <h4 style="margin:0 0 8px 0; opacity:0.9;">Match Defaults</h4>
        <div class="two">
          <div>
            <label>Default Overs</label>
            <input type="number" name="default_overs" value="20" min="1">
          </div>
          <div>
            <label>Default Wickets</label>
            <input type="number" name="default_wickets" value="10" min="1">
          </div>
        </div>

        <button class="btn" style="width:100%; margin-top:12px;">Create</button>
      </form>
      <div id="createErr" class="err" style="display:none;"></div>
    </div>

    <script>
      function extractId(data, text){
        if (data && Number(data.id) > 0) return Number(data.id);
        const m = String(text).match(/(\d+)/);
        return m ? Number(m[1]) : 0;
      }

      document.getElementById('createForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const box = document.getElementById('createErr');
        box.style.display='none';
        const fd = new FormData(e.target);
        try {
          const r = await fetch(e.target.action, { method:'POST', body:fd });
          const text = await r.text();
          let data = null;
          try { data = JSON.parse(text); } catch(_) {}
          
          if(!r.ok){
            box.style.display='block';
            box.textContent = (data && data.error) ? data.error : text;
            return;
          }
          const tid = extractId(data, text);
          if(!tid) throw new Error('No ID');
          location.href = '/api/tournament_create.php?id=' + tid;
        } catch(err){
          box.style.display='block';
          box.textContent = 'Error: ' + err.message;
        }
      });
    </script>

  <?php else: ?>
    <div class="card">
      <h3 style="margin-top:0;">Add Teams — <?= h($tournament['name']) ?></h3>
      <p style="opacity:.85;margin-top:6px;">Add team names. (Minimum 2 teams)</p>
      <?php if (!empty($teams)): ?>
        <div style="margin:10px 0; opacity:.9;">
          <b>Existing:</b> <?= h(implode(', ', array_map(fn($x)=>$x['name'], $teams))) ?>
        </div>
      <?php endif; ?>

      <form method="post" id="teamsForm">
        <input type="hidden" name="_action" value="add_teams">
        <input type="hidden" name="tournament_id" value="<?= (int)$tournament['id'] ?>">
        <div id="teamsBox"></div>
        <div id="teamsErr" class="err" style="display:none;"></div>
        <div class="row" style="margin-top:12px; justify-content:flex-start;">
          <button type="button" class="btn" onclick="addTeam()">+ Add Team</button>
          <button type="submit" class="btn">Save Teams</button>
          <a class="btn" href="/pages/tournament.php?id=<?= (int)$tournament['id'] ?>">Skip</a>
        </div>
      </form>
    </div>
    <script>
      const box = document.getElementById('teamsBox');
      function addTeam(v=''){
        const d=document.createElement('div'); d.className='teamrow';
        d.innerHTML=`<input name="teams[]" placeholder="Team name" value="${v.replace(/"/g,'&quot;')}"><button type="button" class="btn" onclick="this.parentElement.remove()">Remove</button>`;
        box.appendChild(d);
      }
      addTeam(''); addTeam('');
      document.getElementById('teamsForm').addEventListener('submit', (e)=>{
        const n = Array.from(document.querySelectorAll('[name="teams[]"]')).map(i=>i.value.trim()).filter(x=>x);
        if(new Set(n).size < 2){ e.preventDefault(); document.getElementById('teamsErr').style.display='block'; document.getElementById('teamsErr').textContent='Add at least 2 teams.'; }
      });
    </script>
  <?php endif; ?>
</div>
</body>
</html>