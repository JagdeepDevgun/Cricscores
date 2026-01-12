<?php
require_once __DIR__ . '/../db.php';

$pid = (int)($_GET['id'] ?? 0);
if(!$pid) die("Invalid Player");

// 1. Get Player Name (Identity)
$stmt = $pdo->prepare("SELECT name FROM players WHERE id=?");
$stmt->execute([$pid]);
$pName = $stmt->fetchColumn();

if (!$pName) die("Player not found");

// 2. Find ALL IDs for this player (to aggregate global stats)
$idsStmt = $pdo->prepare("SELECT id FROM players WHERE name=?");
$idsStmt->execute([$pName]);
$allIds = $idsStmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($allIds)) die("No data found");

$inClause = implode(',', array_map('intval', $allIds));

// 3. Global Batting Stats
$batSql = "
    SELECT 
        COUNT(DISTINCT m.id) as matches, 
        SUM(b.runs_bat) as runs, 
        COUNT(b.id) as balls, 
        SUM(CASE WHEN b.runs_bat=4 THEN 1 ELSE 0 END) as fours, 
        SUM(CASE WHEN b.runs_bat=6 THEN 1 ELSE 0 END) as sixes
    FROM ball_events b 
    JOIN innings i ON b.innings_id=i.id 
    JOIN matches m ON i.match_id=m.id 
    WHERE b.striker_id IN ($inClause)
";
$bStats = $pdo->query($batSql)->fetch(PDO::FETCH_ASSOC);

// 4. Batting History for Charts & Milestones
$histSql = "
    SELECT SUM(b.runs_bat) as runs, 
           MAX(CASE WHEN b.is_wicket=1 AND b.wicket_player_out_id IN ($inClause) THEN 1 ELSE 0 END) as is_out
    FROM ball_events b 
    WHERE b.striker_id IN ($inClause)
    GROUP BY b.innings_id
";
$hist = $pdo->query($histSql)->fetchAll(PDO::FETCH_ASSOC);

$hs = 0; $fifties = 0; $hundreds = 0; $innings_count = 0; $not_outs = 0;

foreach($hist as $h) {
    $r = (int)$h['runs'];
    if($r > $hs) $hs = $r;
    if($r >= 50 && $r < 100) $fifties++;
    if($r >= 100) $hundreds++;
    if($h['is_out'] == 0) $not_outs++;
    $innings_count++;
}
$bStats['hs'] = $hs;

// 5. Global Bowling Stats (Updated for WD/NB)
$bowlSql = "
    SELECT 
        COUNT(DISTINCT m.id) as matches,
        SUM(CASE WHEN b.is_wicket=1 AND b.wicket_type != 'run out' THEN 1 ELSE 0 END) as wickets, 
        COUNT(CASE WHEN b.is_legal=1 THEN 1 END) as legal_balls, 
        SUM(b.runs_bat + b.extras_runs) as runs_conceded,
        COUNT(CASE WHEN b.extras_type='wd' THEN 1 END) as wides,
        COUNT(CASE WHEN b.extras_type='nb' THEN 1 END) as no_balls
    FROM ball_events b 
    JOIN innings i ON b.innings_id=i.id 
    JOIN matches m ON i.match_id=m.id 
    WHERE b.bowler_id IN ($inClause)
";
$oStats = $pdo->query($bowlSql)->fetch(PDO::FETCH_ASSOC);

// 6. Bowling Milestones & BBI (Best Bowling Innings)
$bowlHistSql = "
    SELECT 
        COUNT(CASE WHEN is_wicket=1 AND wicket_type != 'run out' THEN 1 END) as wkts,
        SUM(runs_bat + extras_runs) as runs
    FROM ball_events
    WHERE bowler_id IN ($inClause)
    GROUP BY innings_id
";
$bowlHist = $pdo->query($bowlHistSql)->fetchAll(PDO::FETCH_ASSOC);

$best_wkts = 0; $best_runs = 1000;
$w3 = 0; $w5 = 0;

foreach($bowlHist as $bh) {
    $w = (int)$bh['wkts'];
    $r = (int)$bh['runs'];
    
    // Check Best Bowling (More wickets, or same wickets for less runs)
    if ($w > $best_wkts) { $best_wkts = $w; $best_runs = $r; }
    elseif ($w == $best_wkts && $r < $best_runs) { $best_runs = $r; }
    
    if ($w >= 3) $w3++;
    if ($w >= 5) $w5++;
}
$bbi = ($best_wkts > 0) ? "$best_wkts/$best_runs" : "-";

// 7. Global Awards
$momSql = "SELECT COUNT(*) FROM matches WHERE man_of_match_id IN ($inClause)";
$momCount = $pdo->query($momSql)->fetchColumn();

// 8. Teams
$teamSql = "
    SELECT DISTINCT t.name as team_name, t.icon as team_icon, tr.name as tour_name 
    FROM players p 
    JOIN teams t ON p.team_id=t.id 
    JOIN tournaments tr ON t.tournament_id=tr.id 
    WHERE p.name = ?
    ORDER BY tr.id DESC
";
$tStmt = $pdo->prepare($teamSql);
$tStmt->execute([$pName]);
$teamsList = $tStmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Charts Data
$chartSql = "
    SELECT m.id, SUM(b.runs_bat) as runs, 
           MAX(CASE WHEN b.is_wicket=1 AND b.wicket_player_out_id IN ($inClause) THEN 1 ELSE 0 END) as is_out
    FROM ball_events b 
    JOIN innings i ON b.innings_id=i.id 
    JOIN matches m ON i.match_id=m.id 
    WHERE b.striker_id IN ($inClause)
    GROUP BY m.id, i.innings_no
    ORDER BY m.id ASC LIMIT 20
";
$chartData = $pdo->query($chartSql)->fetchAll(PDO::FETCH_ASSOC);
$graphLabels = []; $graphRuns = []; $graphColors = [];
foreach($chartData as $cd) {
    $graphLabels[] = "Match " . $cd['id'];
    $graphRuns[] = (int)$cd['runs'];
    $graphColors[] = ($cd['is_out'] == 1) ? '#ff5252' : '#00e676';
}

// Calculations
$totalMatches = max((int)$bStats['matches'], (int)$oStats['matches']);

// Batting Metrics
$outs = $innings_count - $not_outs;
$bat_avg = ($outs > 0) ? round($bStats['runs'] / $outs, 2) : (int)$bStats['runs'];
$bat_sr = ($bStats['balls'] > 0) ? round(($bStats['runs'] / $bStats['balls']) * 100, 1) : 0;

// Bowling Metrics
$overs = $oStats['legal_balls'] / 6;
$bowl_econ = ($overs > 0) ? round($oStats['runs_conceded'] / $overs, 2) : 0;
$bowl_avg = ($oStats['wickets'] > 0) ? round($oStats['runs_conceded'] / $oStats['wickets'], 2) : 0;
$bowl_sr = ($oStats['wickets'] > 0) ? round($oStats['legal_balls'] / $oStats['wickets'], 1) : 0;

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
    <link rel="stylesheet" href="../style.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
    <title><?= htmlspecialchars($pName) ?> - Profile</title>
    <style>
        .profile-header { 
            text-align: center; 
            padding: 30px 20px; 
            margin-bottom: 20px;
            background-image: repeating-linear-gradient(transparent, transparent 29px, #ccc 30px);
            background-color: var(--paper);
            border-bottom: 2px solid var(--ink);
        }
        .avatar { margin-bottom: 10px; display: inline-block; }
        .p-name { margin: 0; }
        
        .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        
        .stat-box { 
            background: #fff; 
            padding: 15px; 
            border: 2px solid var(--ink); 
            border-radius: 4px; 
            text-align: center; 
            box-shadow: 3px 3px 0px rgba(0,0,0,0.1);
            position: relative;
        }
        .stat-box::before {
            content: ''; position: absolute; top: -8px; left: 50%; transform: translateX(-50%);
            width: 40px; height: 12px; background: rgba(255,255,255,0.7); border: 1px solid #ccc;
        }

        .stat-label { font-size: 11px; color: var(--ink-light); text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
        .stat-val { font-size: 24px; font-weight: 900; color: var(--ink); margin-top: 5px; font-family: 'Courier New', monospace; }
        
        .section-title { 
            display: flex; align-items: center; gap: 8px; 
            font-size: 18px; font-weight: 800; color: var(--ink); 
            margin-bottom: 15px; 
            border-bottom: 3px solid var(--pop-yellow); 
            display: inline-block;
            padding-right: 20px;
        }
        
        .team-list-item { 
            display: flex; align-items: center; gap: 15px; 
            padding: 12px; 
            background: #fff; 
            border: 2px solid var(--ink); 
            border-radius: 6px; 
            margin-bottom: 10px;
            box-shadow: 2px 2px 0px rgba(0,0,0,0.05);
        }
        .team-icon { font-size: 24px; color: var(--pop-cyan); }
        .team-name { font-weight: 800; color: var(--ink); font-size: 16px; }
        .tour-name { font-size: 12px; color: var(--ink-light); font-family: 'Courier New'; }

        @media(max-width: 600px) {
            .stat-grid { grid-template-columns: 1fr 1fr; }
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
<div class="wrap">
    <div class="topbar">
        <div class="brand">
            <a href="../index.php">
             <img src="../assets/logo.png" alt="Logo" style="height:40px;">
            </a>
        </div>
        <div class="top-actions"><a class="btn" href="javascript:history.back()">← Back</a></div>
    </div>
    
    <div class="profile-header">
        <div class="avatar">
            <span class="material-symbols-outlined" style="font-size:64px; color:var(--ink);">account_circle</span>
        </div>
        <h1><?= htmlspecialchars($pName) ?></h1>
        <div class="muted">Career Statistics</div>
    </div>

    <?php if(count($graphRuns) > 0): ?>
    <div class="card">
        <div class="section-title">Form Guide (Last 20 Innings)</div>
        <div style="height: 250px; width: 100%;">
            <canvas id="batChart"></canvas>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="section-title">
            <span class="material-symbols-outlined">sports_cricket</span> Batting
        </div>
        <div class="stat-grid">
            <div class="stat-box"><div class="stat-label">Matches</div><div class="stat-val"><?= $totalMatches ?></div></div>
            <div class="stat-box"><div class="stat-label">Runs</div><div class="stat-val"><?= (int)$bStats['runs'] ?></div></div>
            <div class="stat-box"><div class="stat-label">Average</div><div class="stat-val"><?= $bat_avg ?></div></div>
            <div class="stat-box"><div class="stat-label">Highest</div><div class="stat-val"><?= (int)$bStats['hs'] ?></div></div>
            <div class="stat-box"><div class="stat-label">Strike Rate</div><div class="stat-val"><?= $bat_sr ?></div></div>
            <div class="stat-box"><div class="stat-label">50s / 100s</div><div class="stat-val"><?= $fifties ?> / <?= $hundreds ?></div></div>
            <div class="stat-box"><div class="stat-label">Not Outs</div><div class="stat-val"><?= $not_outs ?></div></div>
            <div class="stat-box"><div class="stat-label">Fours</div><div class="stat-val" style="color:var(--pop-cyan)"><?= (int)$bStats['fours'] ?></div></div>
            <div class="stat-box"><div class="stat-label">Sixes</div><div class="stat-val" style="color:var(--pop-yellow); text-shadow:1px 1px 0 #000;"><?= (int)$bStats['sixes'] ?></div></div>
        </div>
    </div>

    <div class="card">
        <div class="section-title" style="border-color:var(--pop-cyan);">
            <span class="material-symbols-outlined">sports_baseball</span> Bowling
        </div>
        <div class="stat-grid">
            <div class="stat-box"><div class="stat-label">Wickets</div><div class="stat-val" style="color:var(--pop-red)"><?= (int)$oStats['wickets'] ?></div></div>
            <div class="stat-box"><div class="stat-label">Best (BBI)</div><div class="stat-val"><?= $bbi ?></div></div>
            <div class="stat-box"><div class="stat-label">Economy</div><div class="stat-val"><?= $bowl_econ ?></div></div>
            
            <div class="stat-box"><div class="stat-label">Average</div><div class="stat-val"><?= $bowl_avg ?></div></div>
            <div class="stat-box"><div class="stat-label">Strike Rate</div><div class="stat-val"><?= $bowl_sr ?></div></div>
            <div class="stat-box"><div class="stat-label">3w / 5w</div><div class="stat-val"><?= $w3 ?> / <?= $w5 ?></div></div>
            
            <div class="stat-box"><div class="stat-label">Overs</div><div class="stat-val"><?= round($overs, 1) ?></div></div>
            <div class="stat-box"><div class="stat-label">WD / NB</div><div class="stat-val"><?= (int)$oStats['wides'] ?> / <?= (int)$oStats['no_balls'] ?></div></div>
            <div class="stat-box"><div class="stat-label">Runs Conc.</div><div class="stat-val"><?= (int)$oStats['runs_conceded'] ?></div></div>
        </div>
    </div>

    <?php if($momCount > 0): ?>
    <div class="card">
        <div class="section-title" style="border-color:var(--pop-purple);">
            <span class="material-symbols-outlined">emoji_events</span> Awards
        </div>
        <div class="stat-grid" style="grid-template-columns: 1fr;">
            <div class="stat-box" style="background:var(--pop-yellow); border-color:black;">
                <div class="stat-label" style="color:black;">Man of the Match</div>
                <div class="stat-val" style="color:black;"><?= (int)$momCount ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="section-title">
            <span class="material-symbols-outlined">groups</span> Teams
        </div>
        <?php if(count($teamsList) > 0): ?>
            <?php foreach($teamsList as $tm): ?>
            <div class="team-list-item">
                <span class="material-symbols-outlined team-icon"><?= $tm['team_icon'] ?: 'shield' ?></span>
                <div class="team-info">
                    <span class="team-name"><?= htmlspecialchars($tm['team_name']) ?></span>
                    <span class="tour-name"><?= htmlspecialchars($tm['tour_name']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="muted">No teams found.</div>
        <?php endif; ?>
    </div>

</div>

<script>
    const ctx = document.getElementById('batChart');
    if(ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($graphLabels) ?>,
                datasets: [{
                    label: 'Runs',
                    data: <?= json_encode($graphRuns) ?>,
                    borderColor: '#2c3e50',
                    borderWidth: 2,
                    tension: 0.4,
                    pointBackgroundColor: <?= json_encode($graphColors) ?>,
                    pointBorderColor: '#2c3e50',
                    pointRadius: 6,
                    borderDash: [],
                    fill: true,
                    backgroundColor: 'rgba(0, 188, 212, 0.1)' 
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#eee', borderDash: [5, 5] }, ticks: { font: { family: "'Courier New', monospace" } } },
                    x: { display: false }
                }
            }
        });
    }
</script>
</body>
</html>