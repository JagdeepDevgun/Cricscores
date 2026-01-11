<?php
$id = (int)($_GET['id'] ?? 0);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="../style.css"/>
  <title>Points Table</title>
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
  <a href="tournament.php?id=<?= $id ?>">← Back</a>
  <h1>Points Table</h1>

  <div id="view" class="card">Loading…</div>
</div>

<script>
const tid = <?= $id ?>;

async function load(){
  const r = await fetch(`../api/points_table.php?tournament_id=${tid}`);
  const j = await r.json();
  if(!r.ok){ document.getElementById('view').innerText = j.error || 'Error'; return; }

  const t = j.tournament;
  const rows = j.table;

  const html = `
    <h2>${t.name}</h2>
    <div class="muted">Type: ${t.type} • Points: W=${t.win_points}, T=${t.tie_points}, NR=${t.nr_points}, L=${t.loss_points}</div>
    <div style="overflow:auto; margin-top:12px;">
      <table style="width:100%; border-collapse:collapse;">
        <thead>
          <tr>
            <th style="text-align:left; padding:8px; border-bottom:1px solid #2a3147;">Team</th>
            <th style="padding:8px; border-bottom:1px solid #2a3147;">P</th>
            <th style="padding:8px; border-bottom:1px solid #2a3147;">W</th>
            <th style="padding:8px; border-bottom:1px solid #2a3147;">L</th>
            <th style="padding:8px; border-bottom:1px solid #2a3147;">T</th>
            <th style="padding:8px; border-bottom:1px solid #2a3147;">NR</th>
            <th style="padding:8px; border-bottom:1px solid #2a3147;">NRR</th>
            <th style="padding:8px; border-bottom:1px solid #2a3147;">Pts</th>
          </tr>
        </thead>
        <tbody>
          ${rows.map(x=>`
            <tr>
              <td style="text-align:left; padding:8px; border-bottom:1px solid #1d2233;">${escapeHtml(x.team)}</td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #1d2233;">${x.P}</td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #1d2233;">${x.W}</td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #1d2233;">${x.L}</td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #1d2233;">${x.T}</td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #1d2233;">${x.NR}</td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #1d2233;">${Number(x.NRR).toFixed(3)}</td>
              <td style="text-align:center; padding:8px; border-bottom:1px solid #1d2233; font-weight:700;">${x.Pts}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    </div>
  `;
  document.getElementById('view').innerHTML = html;
}
function escapeHtml(s){
  return String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
}
load();
</script>
</body>
</html>
