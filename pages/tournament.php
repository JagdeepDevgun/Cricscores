<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../api/auth.php';
$id = (int)($_GET['id'] ?? 0);
$user = auth_user($pdo);

// Fetch Tournament
$t = $pdo->prepare('SELECT * FROM tournaments WHERE id=?');
$t->execute([$id]);
$tour = $t->fetch(PDO::FETCH_ASSOC);
if (!$tour) die('Tournament not found');

// Fetch Teams
$teamsStmt = $pdo->prepare('SELECT * FROM teams WHERE tournament_id=? ORDER BY name');
$teamsStmt->execute([$id]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Players grouped by Team
$playersByTeam = [];
$pStmt = $pdo->prepare("SELECT p.*, t.name as team_name FROM players p JOIN teams t ON p.team_id = t.id WHERE t.tournament_id=? ORDER BY p.name");
$pStmt->execute([$id]);
while($r = $pStmt->fetch(PDO::FETCH_ASSOC)){
    $playersByTeam[$r['team_id']][] = $r;
}

// Fetch Matches
$matchesStmt = $pdo->prepare("
    SELECT m.*, 
           ta.name as teamA, ta.short_name as teamA_short, ta.icon as teamA_icon,
           tb.name as teamB, tb.short_name as teamB_short, tb.icon as teamB_icon 
    FROM matches m 
    JOIN teams ta ON ta.id=m.team_a_id 
    JOIN teams tb ON tb.id=m.team_b_id 
    WHERE m.tournament_id=? 
    ORDER BY m.id DESC
");
$matchesStmt->execute([$id]);
$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

$defOvers = (int)($tour['default_overs'] ?? 20);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="../style.css"/>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
  <title><?= htmlspecialchars($tour['name']) ?></title>
  <style>
    /* Stats & Layout */
    .stat-card { display: flex; flex-direction: column; padding: 12px; border-bottom: 1px solid #2a3147; font-size: 14px; }
    .stat-row { display: flex; justify-content: space-between; align-items: center; }
    .stat-meta { font-size: 12px; color: #888; margin-top: 4px; display: flex; gap: 10px; flex-wrap: wrap; }
    .stat-val { font-weight: 700; color: #000; font-size: 16px; white-space: nowrap; }
    
    .orange-cap { border: 2px solid #ff9800; background: rgba(255, 152, 0, 0.1); border-radius: 8px; margin-bottom: 8px; position: relative; }
    .orange-cap::after { content: '👑 ORANGE CAP'; position: absolute; top: -10px; right: 10px; background: #ff9800; color: #000; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 4px; }
    
    .purple-cap { border: 2px solid #9c27b0; background: rgba(156, 39, 176, 0.1); border-radius: 8px; margin-bottom: 8px; position: relative; }
    .purple-cap::after { content: '👑 PURPLE CAP'; position: absolute; top: -10px; right: 10px; background: #9c27b0; color: #fff; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 4px; }

    /* Match List Item */
    .match-item { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px; border-bottom: 1px solid #222; background: rgba(255,255,255,0.02); border-radius: 8px; margin-bottom: 8px; }
    .match-link { flex: 1; text-decoration: none; color: inherit; display: block; font-size: 15px; }
    .match-link:hover { color: #64b5f6; }
    
    /* Buttons */
    .del-btn { width: auto !important; min-width: 32px; height: 32px; background: rgba(255, 82, 82, 0.1); color: #ff5252; border: 1px solid #ff5252; border-radius: 8px; padding: 0; cursor: pointer; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; }
    .del-btn:hover { background: #ff5252; color: #fff; }
    
    .edit-btn { width: auto !important; min-width: 32px; height: 32px; background: rgba(33, 150, 243, 0.1); color: #64b5f6; border: 1px solid #2196f3; border-radius: 8px; padding: 0; cursor: pointer; font-size: 14px; margin-left: 8px; display: inline-flex; align-items: center; justify-content: center; }
    .edit-btn:hover { background: #2196f3; color: #fff; }

    /* Tables */
    .stat-table { width:100%; border-collapse:collapse; font-size:13px; margin-top:10px; }
    .stat-table th { text-align:left; background:#1e2330; padding:12px 8px; color:#90a4ae; border-bottom:1px solid #333; white-space: nowrap; cursor: pointer; user-select: none; }
    .stat-table th:hover { color: #fff; background: #2a3147; }
    .stat-table td { padding:10px 8px; border-bottom:1px solid #222; }
    
    .search-box { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #333; background: #111; color: #fff; margin-bottom: 10px; }

    /* Regulars Box */
    .regulars-box { max-height: 150px; overflow-y: auto; background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; margin-bottom: 10px; border: 1px solid #333; display: none; }
    .reg-chip { display: inline-flex; align-items: center; background: #232836; border-radius: 12px; margin: 3px; font-size: 12px; border: 1px solid #444; color: #ccc; overflow: hidden; }
    .reg-name { padding: 4px 8px; cursor: pointer; }
    .reg-name:hover { background: #2a3147; color: #fff; }
    .reg-del { padding: 4px 8px; cursor: pointer; background: rgba(255, 82, 82, 0.1); color: #ff5252; border-left: 1px solid #444; }
    .reg-del:hover { background: #ff5252; color: #fff; }
    
    /* Modal */
    .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 1000; display: none; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
    .modal { background: #1e2330; padding: 25px; border-radius: 16px; width: 90%; max-width: 400px; border: 1px solid #444; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
    
    /* Responsive Forms */
    .form-row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
    .form-row input, .form-row select { margin: 0; }
    
    @media (max-width: 600px) {
        .form-row button { width: 100%; margin-top: 5px; }
    }
  </style>
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

<div id="modal-edit-tour" class="modal-backdrop">
  <div class="modal">
    <h3>Edit Tournament Name</h3>
    <input type="hidden" id="edit-tr-id" value="<?= $id ?>">
    <label class="muted" style="font-size:12px;">Name</label>
    <input id="edit-tr-name" value="<?= htmlspecialchars($tour['name']) ?>" style="margin-bottom:20px;">
    <div style="display:flex; gap:10px;">
        <button onclick="saveTournamentEdit()" style="flex:1;">Save</button>
        <button class="danger" onclick="document.getElementById('modal-edit-tour').style.display='none'" style="flex:1;">Cancel</button>
    </div>
  </div>
</div>

<div id="modal-edit-player" class="modal-backdrop">
  <div class="modal">
    <h3>Edit Player</h3>
    <input type="hidden" id="edit-pid">
    
    <label class="muted" style="font-size:12px;">Name</label>
    <input id="edit-name" style="margin-bottom:15px;">
    
    <label class="muted" style="font-size:12px;">Move to Team</label>
    <select id="edit-team" style="margin-bottom:15px;">
        <?php foreach($teams as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option><?php endforeach; ?>
    </select>
    
    <label style="display:flex; align-items:center; gap:8px; margin-bottom:20px; background:rgba(255,255,255,0.05); padding:10px; border-radius:8px;">
        <input type="checkbox" id="edit-captain" style="width:auto; margin:0;"> 
        <span>Is Captain?</span>
    </label>
    
    <div style="display:flex; gap:10px;">
        <button onclick="savePlayerEdit()" style="flex:1;">Save</button>
        <button class="danger" onclick="document.getElementById('modal-edit-player').style.display='none'" style="flex:1;">Cancel</button>
    </div>
  </div>
</div>

<div id="modal-edit-team" class="modal-backdrop">
  <div class="modal">
    <h3>Edit Team</h3>
    <input type="hidden" id="edit-tid">
    
    <label class="muted" style="font-size:12px;">Icon</label>
    <select id="edit-ticon" style="margin-bottom:10px;">
        <option value="shield">🛡 Shield</option>
        <option value="sports_cricket">🏏 Bat</option>
        <option value="bolt">⚡ Bolt</option>
        <option value="stars">✨ Star</option>
        <option value="pets">🦁 Animal</option>
        <option value="local_fire_department">🔥 Fire</option>
    </select>

    <label class="muted" style="font-size:12px;">Team Name</label>
    <input id="edit-tname" style="margin-bottom:10px;">
    
    <label class="muted" style="font-size:12px;">Short Name</label>
    <input id="edit-tshort" style="margin-bottom:20px;">
    
    <div style="display:flex; gap:10px;">
        <button onclick="saveTeamEdit()" style="flex:1;">Save</button>
        <button class="danger" onclick="document.getElementById('modal-edit-team').style.display='none'" style="flex:1;">Cancel</button>
    </div>
  </div>
</div>

<div class="wrap">
  <div class="topbar">
    <div class="brand">
      <a href="../index.php" class="brand-link">
       <img src="../assets/logo.png" alt="Logo"
          style="height:64px; filter:drop-shadow(0 0 20px rgba(99,102,241,0.5));">
      </a>
    </div>
    <div class="top-actions"><?php if ($user): ?><a class="chip" href="#" onclick="doLogout()">Logout</a><?php else: ?><a class="chip" href="login.php">Login</a><?php endif; ?></div>
  </div>

  <div style="display:flex; align-items:center; gap:10px; margin-bottom:15px;">
      <h1 style="margin:0; font-size:24px;"><?= htmlspecialchars($tour['name']) ?></h1>
      <?php if($user): ?>
      <button class="edit-btn" onclick="openEditTournament()" style="background:none; border:none; color:#888;">
          <span class="material-symbols-outlined">edit</span>
      </button>
      <?php endif; ?>
  </div>

  <div class="card">
    <h2 style="font-size:18px;">Tools</h2>
    <div class="btnrow">
      <?php if ($user): ?>
        <button type="button" onclick="generateFixtures()">Generate Fixtures</button>
        <a class="btnlink" href="../api/backup.php" target="_blank" style="text-align:center;">Backup Data</a>
        <button type="button" class="danger" onclick="deleteTournament()">Delete Tournament</button>
      <?php endif; ?>
      <a class="btnlink" href="points.php?id=<?= $id ?>">Points Table</a>
    </div>
  </div>

  <div class="card">
    <h2 style="font-size:18px;">Stats Leaderboard</h2>
    <div class="tabs">
        <div class="tab active" onclick="showStat('bat', this)">Batting</div>
        <div class="tab" onclick="showStat('bowl', this)">Bowling</div>
        <div class="tab" onclick="showStat('sixes', this)">6s</div>
        <div class="tab" onclick="showStat('fours', this)">4s</div>
        <div class="tab" onclick="showStat('all', this)">All (Local)</div>
        <div class="tab" onclick="showStat('global', this)">Global Stats</div>
    </div>
    
    <input type="text" id="playerSearch" class="search-box" style="display:none;" placeholder="Search players..." onkeyup="renderTable()">
    
    <div id="stat-box" style="margin-top:10px;">Loading stats...</div>
  </div>

  <div class="grid">
    <div class="card">
      <h2 style="font-size:18px;">Teams & Players</h2>
      <?php if ($user): ?>
        
        <form id="addTeamForm" onsubmit="addTeamJS(event)" style="border-bottom:1px solid #333; padding-bottom:15px; margin-bottom:20px;">
          <label class="muted" style="font-size:12px;">Add New Team</label>
          <input type="hidden" name="tournament_id" value="<?= $id ?>">
          <div class="form-row">
            <select name="icon" style="width:70px; padding:12px;">
                <option value="shield">🛡</option>
                <option value="sports_cricket">🏏</option>
                <option value="bolt">⚡</option>
                <option value="stars">✨</option>
                <option value="pets">🦁</option>
                <option value="local_fire_department">🔥</option>
            </select>
            <input type="text" name="names" placeholder="Full Name (e.g. Mumbai Indians)" required style="flex:2;">
            <input type="text" name="short_name" placeholder="Short (MI)" maxlength="4" style="flex:1;">
            <button style="width:auto;">Add</button>
          </div>
        </form>

        <form id="addPlayerForm" onsubmit="addPlayersJS(event)" style="margin-bottom:20px; border-bottom:1px solid #333; padding-bottom:15px;">
            <label class="muted" style="font-size:12px;">Add Players to Team</label>
            <div class="form-row">
                <select id="sel-team-add" name="team_id" required style="flex:1;"><option value="">-- Select Team --</option><?php foreach($teams as $tm): ?><option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option><?php endforeach; ?></select>
                <button type="button" onclick="toggleRegulars()" style="width:auto; padding:10px 15px; font-size:12px;">+ Regulars</button>
            </div>
            
            <div id="regulars-box" class="regulars-box">Loading Regulars...</div>
            
            <div class="form-row" style="align-items:flex-start;">
                <textarea name="names" id="p-names" rows="2" placeholder="Names (one per line)" required style="flex:1;"></textarea>
                <label style="display:flex; flex-direction:column; align-items:center; font-size:11px; color:#888;">
                    <input type="checkbox" name="is_captain" value="1" style="width:20px; height:20px; margin-bottom:2px;"> 
                    Captain?
                </label>
            </div>
            
            <div class="form-row">
                <button class="secondary" style="flex:1;">Add to Team</button>
                <button type="button" onclick="saveAsRegular()" style="background:#4caf50; flex:1;">Save as Regular</button>
            </div>
        </form>
        
        <form method="post" action="../api/match_create.php" onsubmit="return submitForm(event,this, res=>{ location.href='match.php?id='+res.match_id; })" style="margin-bottom:15px; border-bottom:1px solid #333; padding-bottom:15px;">
            <input type="hidden" name="tournament_id" value="<?= $id ?>">
            <label class="muted" style="font-size:12px;">Create Match</label>
            <div class="form-row">
                <select name="team_a_id" required style="flex:1;"><option value="">Team A</option><?php foreach($teams as $tm): ?><option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option><?php endforeach; ?></select>
                <div style="padding:10px; color:#888;">vs</div>
                <select name="team_b_id" required style="flex:1;"><option value="">Team B</option><?php foreach($teams as $tm): ?><option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option><?php endforeach; ?></select>
            </div>
            
            <select name="batting_first_team_id" required style="margin-bottom:10px;"><option value="">Batting First (Toss)</option><?php foreach($teams as $tm): ?><option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option><?php endforeach; ?></select>
            
            <div class="form-row">
                <input type="number" name="overs_limit" value="<?= $defOvers ?>" placeholder="Overs" style="width:80px;">
                <label style="display:flex; align-items:center; gap:5px; font-size:13px; color:#ffd54f; flex:1;">
                    <input type="checkbox" name="is_final" value="1" style="width:auto;"> Final?
                </label>
                <button style="margin-left:auto;">Start Match</button>
            </div>
        </form>
      <?php endif; ?>
      
      <div id="teams-list-container">
      <?php foreach($teams as $tm): ?>
        <div style="margin-top:20px; background:rgba(0,0,0,0.9); border-radius:12px; padding:10px; border:1px solid rgba(255,255,255,0.05);" id="team-block-<?= $tm['id'] ?>">
            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-outlined" style="font-size:20px; color:#64b5f6;"><?= $tm['icon'] ?? 'shield' ?></span>
                    <div>
                        <div style="font-weight:700; color:#fff;"><?= htmlspecialchars($tm['name']) ?></div>
                        <div style="font-size:11px; color:#888;"><?= htmlspecialchars($tm['short_name'] ?? '') ?></div>
                    </div>
                </div>
                <?php if($user): ?>
                    <div style="display:flex;">
                        <button class="edit-btn" onclick="openEditTeam(<?= $tm['id'] ?>, '<?= htmlspecialchars(addslashes($tm['name'])) ?>', '<?= htmlspecialchars($tm['short_name'] ?? '') ?>', '<?= htmlspecialchars($tm['icon'] ?? 'shield') ?>')">
                            <span class="material-symbols-outlined">edit</span>
                        </button>
                        <button class="del-btn" onclick="deleteTeam(<?= $tm['id'] ?>)">
                            <span class="material-symbols-outlined">delete</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div id="team-players-<?= $tm['id'] ?>" style="border-top:1px solid rgba(255,255,255,0.05); padding-top:5px;">
            <?php if(isset($playersByTeam[$tm['id']])): ?>
                <?php foreach($playersByTeam[$tm['id']] as $p): ?>
                    <div id="p-row-<?= $p['id'] ?>" style="display:flex; justify-content:space-between; align-items:center; padding: 8px 4px; border-bottom: 1px solid rgba(255,255,255,0.02);">
                        <a href="player.php?id=<?= $p['id'] ?>" class="muted" style="text-decoration:none; color:inherit; font-size:14px;">
                            <?= htmlspecialchars($p['name']) ?>
                            <?php if(isset($p['is_captain']) && $p['is_captain']) echo '<span style="color:#ffd54f; font-weight:bold; font-size:11px; margin-left:4px; border:1px solid #ffd54f; border-radius:4px; padding:0 4px;">C</span>'; ?>
                        </a>
                        <?php if($user): ?>
                            <div style="display:flex; align-items:center;">
                                <button class="edit-btn" style="width:24px; height:24px; min-width:24px;" onclick="openEditPlayer(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $tm['id'] ?>, <?= isset($p['is_captain']) ? $p['is_captain'] : 0 ?>)">
                                    <span class="material-symbols-outlined" style="font-size:14px;">edit</span>
                                </button>
                                <button class="del-btn" style="width:24px; height:24px; min-width:24px;" onclick="deletePlayer(<?= $p['id'] ?>)">
                                    <span class="material-symbols-outlined" style="font-size:14px;">delete</span>
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="muted" style="font-size:12px; padding:5px;">No players added yet.</div>
            <?php endif; ?>
            </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <h2 style="font-size:18px;">Matches</h2>
      <div>
        <?php foreach($matches as $m): ?>
          <div class="match-item">
            <a class="match-link" href="match.php?id=<?= (int)$m['id'] ?>">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span class="material-symbols-outlined" style="font-size:16px; color:#aaa;"><?= $m['teamA_icon'] ?: 'shield' ?></span>
                    <b><?= htmlspecialchars($m['teamA_short'] ?: $m['teamA']) ?></b>
                    <span class="muted">vs</span>
                    <b><?= htmlspecialchars($m['teamB_short'] ?: $m['teamB']) ?></b>
                    <span class="material-symbols-outlined" style="font-size:16px; color:#aaa;"><?= $m['teamB_icon'] ?: 'shield' ?></span>
                </div>
                <div class="muted" style="font-size:11px; margin-top:4px;"><?= $m['status'] === 'completed' ? 'Completed' : 'Live' ?> &bull; <?= (int)$m['overs_limit'] ?> overs</div>
            </a>
            <?php if ($user): ?>
                <button class="del-btn" onclick="deleteMatch(<?= (int)$m['id'] ?>)" title="Delete Match">
                    <span class="material-symbols-outlined" style="font-size:16px;">delete</span>
                </button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
let localStats = null, globalStats = null, currentTab = 'bat', sort = { col:'runs', asc:false };

async function loadStats() {
    const r = await fetch(`../api/stats.php?tournament_id=<?= $id ?>`);
    localStats = await r.json();
    showStat('bat', document.querySelector('.tab.active'));
}
async function loadGlobalStats() {
    if(globalStats) return;
    const r = await fetch('../api/stats_global.php');
    globalStats = await r.json();
}
function showStat(type, el) {
    if(el) { document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active')); el.classList.add('active'); }
    currentTab = type;
    const search = document.getElementById('playerSearch');
    if (type === 'all' || type === 'global') {
        search.style.display = 'block'; search.value = '';
        if (type === 'global') loadGlobalStats().then(renderTable); else renderTable();
    } else {
        search.style.display = 'none'; renderCardList(type);
    }
}
function renderCardList(type) {
    const box = document.getElementById('stat-box');
    let data = [];
    if(type==='bat') data=localStats.batsmen; else if(type==='bowl') data=localStats.bowlers;
    else if(type==='sixes') data=localStats.most_sixes; else if(type==='fours') data=localStats.most_fours;
    if(!data||data.length===0) { box.innerHTML='<div class="muted">No stats.</div>'; return; }
    box.innerHTML = data.map((p,i) => {
        let meta='', val='', cap='';
        if(type==='bat') { if(i===0) cap='orange-cap'; val=`${p.runs} <span style="font-size:11px;color:#888">Runs</span>`; meta=`Mt:${p.matches} SR:${p.sr}`; }
        else if(type==='bowl') { if(i===0) cap='purple-cap'; val=`${p.wickets} <span style="font-size:11px;color:#888">Wkts</span>`; meta=`Mt:${p.matches} Ec:${p.econ}`; }
        else { val=p.count; meta=p.team; }
        return `<div class="stat-card ${cap}"><div class="stat-row"><div><div style="font-weight:600;"><a href="player.php?id=${p.id}" style="color:inherit;text-decoration:none;">${i+1}. ${p.name}</a></div><div class="stat-meta">${meta}</div></div><div class="stat-val">${val}</div></div></div>`;
    }).join('');
}
function renderTable() {
    const box = document.getElementById('stat-box');
    let d = (currentTab === 'global') ? (globalStats || []) : (localStats.all_players || []);
    const q = document.getElementById('playerSearch').value.toLowerCase();
    if(q) d = d.filter(p => p.name.toLowerCase().includes(q) || (p.team && p.team.toLowerCase().includes(q)));
    d.sort((a,b) => { let v1=a[sort.col], v2=b[sort.col]; if(sort.col==='name' || sort.col==='team') return sort.asc ? v1.localeCompare(v2) : v2.localeCompare(v1); return sort.asc ? (v1-v2) : (v2-v1); });
    if(d.length===0) { box.innerHTML='<div class="muted">No players.</div>'; return; }
    const th = (k,l) => `<th onclick="sortTable('${k}')">${l} ${sort.col===k ? (sort.asc?'↑':'↓') : ''}</th>`;
    let h = `<div style="overflow-x:auto;"><table class="stat-table"><thead><tr>${th('name','Player')}${currentTab!=='global' ? th('team','Team') : ''}${th('matches','Mat')}${th('runs','Runs')}${th('sr','SR')}${th('fours','4s')}${th('sixes','6s')}${th('wickets','Wkts')}${th('econ','Econ')}</tr></thead><tbody>`;
    d.forEach(p => { h += `<tr><td><a href="player.php?id=${p.id}" style="color:inherit;text-decoration:none;"><b>${p.name}</b></a></td>${currentTab!=='global' ? `<td>${p.team}</td>` : ''}<td>${p.matches}</td><td>${p.runs}</td><td>${p.sr}</td><td>${p.fours}</td><td>${p.sixes}</td><td>${p.wickets}</td><td>${p.econ}</td></tr>`; });
    h += `</tbody></table></div>`;
    box.innerHTML = h;
}
function sortTable(key) { if (sort.col === key) sort.asc = !sort.asc; else { sort.col = key; sort.asc = false; if(key==='name'||key==='team') sort.asc=true; } renderTable(); }

// --- Player Management ---
function openEditPlayer(pid, name, tid, isCap) { 
    document.getElementById('edit-pid').value = pid; 
    document.getElementById('edit-name').value = name; 
    document.getElementById('edit-team').value = tid; 
    document.getElementById('edit-captain').checked = (isCap == 1);
    document.getElementById('modal-edit-player').style.display = 'flex'; 
}
async function savePlayerEdit() { 
    const fd = new FormData(); 
    fd.append('player_id', document.getElementById('edit-pid').value); 
    fd.append('name', document.getElementById('edit-name').value); 
    fd.append('team_id', document.getElementById('edit-team').value);
    fd.append('is_captain', document.getElementById('edit-captain').checked ? 1 : 0);
    const r = await fetch('../api/player_edit.php', {method:'POST', body:fd}); 
    if(r.ok) location.reload(); else alert('Failed'); 
}
async function deletePlayer(pid) { 
    if(!confirm("Delete this player?")) return; 
    const fd = new FormData(); fd.append('player_id', pid); 
    const r = await fetch('../api/player_delete.php', {method:'POST', body:fd}); 
    const j = await r.json();
    if(r.ok) document.getElementById('p-row-'+pid).remove(); 
    else alert(j.error || 'Failed to delete'); 
}

// --- Team Management ---
function openEditTeam(tid, name, short, icon) { 
    document.getElementById('edit-tid').value = tid; 
    document.getElementById('edit-tname').value = name;
    document.getElementById('edit-tshort').value = short || '';
    document.getElementById('edit-ticon').value = icon || 'shield';
    document.getElementById('modal-edit-team').style.display = 'flex'; 
}
async function saveTeamEdit() { 
    const fd = new FormData(); 
    fd.append('team_id', document.getElementById('edit-tid').value); 
    fd.append('name', document.getElementById('edit-tname').value);
    fd.append('short_name', document.getElementById('edit-tshort').value);
    fd.append('icon', document.getElementById('edit-ticon').value);
    const r = await fetch('../api/team_edit.php', {method:'POST', body:fd}); 
    if(r.ok) location.reload(); else alert('Failed to update team'); 
}
async function deleteTeam(tid) {
    if(!confirm("Delete this Team? WARNING: This will delete all players in the team.")) return;
    const fd = new FormData(); fd.append('team_id', tid);
    const r = await fetch('../api/team_delete.php', {method:'POST', body:fd});
    const j = await r.json();
    if(r.ok) {
        document.getElementById('team-block-'+tid).remove();
        const opts = document.querySelectorAll(`option[value="${tid}"]`);
        opts.forEach(o => o.remove());
    } else {
        alert(j.error || 'Failed to delete team');
    }
}

// --- Tournament Edit ---
function openEditTournament() { document.getElementById('modal-edit-tour').style.display = 'flex'; }
async function saveTournamentEdit() {
    const fd = new FormData();
    fd.append('tournament_id', document.getElementById('edit-tr-id').value);
    fd.append('name', document.getElementById('edit-tr-name').value);
    const r = await fetch('../api/tournament_edit.php', {method:'POST', body:fd});
    if(r.ok) location.reload();
    else alert('Failed to update tournament');
}

// --- ADD PLAYERS JS ---
async function addPlayersJS(e) {
    e.preventDefault();
    const form = e.target;
    const r = await fetch('../api/player_add.php', {method:'POST', body:new FormData(form)});
    const j = await r.json();
    if(!r.ok) { alert(j.error); return; }
    location.reload();
}

// --- Add Team JS ---
async function addTeamJS(e) {
    e.preventDefault();
    const form = e.target;
    const r = await fetch('../api/team_add.php', {method:'POST', body:new FormData(form)});
    const j = await r.json();
    if(!r.ok) { alert(j.error); return; }
    location.reload();
}

async function toggleRegulars() { const box = document.getElementById('regulars-box'); if(box.style.display === 'block') { box.style.display='none'; return; } const r = await fetch('../api/regular_players.php?action=list'); const d = await r.json(); box.innerHTML = d.map(p => `
    <div class="reg-chip">
        <span class="reg-name" onclick="addRegName('${p.name}')">${p.name}</span>
        <span class="reg-del" onclick="deleteRegular(${p.id})">&times;</span>
    </div>
`).join('') || '<div class="muted">No regulars saved.</div>'; box.style.display = 'block'; }
function addRegName(name) { const ta = document.getElementById('p-names'); ta.value = ta.value ? ta.value + '\n' + name : name; }
async function saveAsRegular() { const names = document.getElementById('p-names').value.split('\n'); for(let n of names) { if(n.trim()) { const fd = new FormData(); fd.append('action','add'); fd.append('name',n.trim()); await fetch('../api/regular_players.php', {method:'POST', body:fd}); } } alert('Saved!'); toggleRegulars(); }
async function deleteRegular(id) { if(!confirm("Remove from Regulars?")) return; const fd = new FormData(); fd.append('action','delete'); fd.append('id', id); await fetch('../api/regular_players.php', {method:'POST', body:fd}); toggleRegulars(); }

async function doLogout(){ await fetch('../api/logout.php',{method:'POST'}); location.href='../index.php'; }
async function submitForm(e, form, cb){ e.preventDefault(); const r=await fetch(form.action,{method:'POST',body:new FormData(form)}); const j=await r.json(); if(!r.ok){alert(j.error);return;} cb(j); }
async function generateFixtures(){ const o=prompt('Overs?', '<?= $defOvers ?>'); if(o) { const fmt=prompt("1=Single,2=Double,3=Knockout","1"); let t='single'; if(fmt=='2')t='double'; else if(fmt=='3')t='knockout'; const fd=new FormData(); fd.append('tournament_id','<?= $id ?>'); fd.append('overs_limit',o); fd.append('type',t); await fetch('../api/fixtures_generate.php',{method:'POST',body:fd}); location.reload(); }}
async function deleteMatch(mid){ if(confirm("Delete match?")) { const fd=new FormData(); fd.append('match_id',mid); await fetch('../api/match_delete.php',{method:'POST',body:fd}); location.reload(); }}
async function deleteTournament(){ if(confirm("Delete Tournament?")) { const fd=new FormData(); fd.append('tournament_id',<?= $id ?>); await fetch('../api/tournament_delete.php',{method:'POST',body:fd}); location.href='../index.php'; }}

loadStats();
</script>
</body>
</html>