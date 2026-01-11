<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../api/auth.php';
$user = auth_user($pdo);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="../style.css"/>
  <title>Global Player Stats</title>
  <style>
    .stat-table th { cursor: pointer; }
    .search-box { width: 100%; padding: 12px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: #fff; margin-bottom: 20px; font-size: 16px; }
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
<div class="wrap">
  <div class="topbar">
        <div class="brand">
		<a href="../index.php">
	 	 <img src="../assets/logo.png" alt="Logo"
          	 style="height:64px; filter:drop-shadow(0 0 20px rgba(99,102,241,0.5));">
		</a>
	</div>
    <div class="top-actions"><?php if ($user): ?><a class="chip" href="#" onclick="doLogout()">Logout</a><?php else: ?><a class="chip" href="login.php">Login</a><?php endif; ?></div>
  </div>

  <h1>Global Player Stats</h1>
  <div class="card">
    <input type="text" id="search" class="search-box" placeholder="Search player name..." onkeyup="render()">
    <div id="table-box">Loading...</div>
  </div>
</div>

<script>
let players = [];
let sortCol = 'runs';
let sortAsc = false;

async function load() {
    const r = await fetch('../api/stats_global.php');
    players = await r.json();
    render();
}

function render() {
    const q = document.getElementById('search').value.toLowerCase();
    let d = players.filter(p => p.name.toLowerCase().includes(q));
    
    d.sort((a,b) => {
        let v1 = a[sortCol], v2 = b[sortCol];
        if(sortCol === 'name') return sortAsc ? v1.localeCompare(v2) : v2.localeCompare(v1);
        return sortAsc ? (v1 - v2) : (v2 - v1);
    });

    const th = (k, l) => `<th onclick="doSort('${k}')">${l} ${sortCol===k ? (sortAsc?'↑':'↓') : ''}</th>`;

    let h = `<div style="overflow-x:auto;"><table class="stat-table"><thead><tr>
        ${th('name','Player')} ${th('matches','Mat')} ${th('runs','Runs')} ${th('sr','SR')} ${th('wickets','Wkts')} ${th('econ','Econ')}
    </tr></thead><tbody>`;
    
    d.forEach(p => {
        // FIXED: Added Link using p.id
        h += `<tr>
            <td><a href="player.php?id=${p.id}" style="color:inherit; text-decoration:none;"><b>${p.name}</b></a></td>
            <td>${p.matches}</td>
            <td>${p.runs}</td>
            <td>${p.sr}</td>
            <td>${p.wickets}</td>
            <td>${p.econ}</td>
        </tr>`;
    });
    h += `</tbody></table></div>`;
    document.getElementById('table-box').innerHTML = h;
}

function doSort(col) {
    if(sortCol === col) sortAsc = !sortAsc;
    else { sortCol = col; sortAsc = false; if(col==='name') sortAsc=true; }
    render();
}

async function doLogout(){ await fetch('../api/logout.php',{method:'POST'}); location.href='../index.php'; }
load();
</script>
</body>
</html>