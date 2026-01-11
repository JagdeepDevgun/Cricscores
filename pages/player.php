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
// We group by Name to treat "MS Dhoni" in IPL 2024 and IPL 2025 as the same person.
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

// 4. Global Highest Score
$hsSql = "
    SELECT MAX(inning_score) 
    FROM (
        SELECT SUM(runs_bat) as inning_score 
        FROM ball_events 
        WHERE striker_id IN ($inClause)
        GROUP BY innings_id
    ) as scores
";
$hs = $pdo->query($hsSql)->fetchColumn();
$bStats['hs'] = $hs ? $hs : 0;

// 5. Global Bowling Stats
$bowlSql = "
    SELECT 
        COUNT(DISTINCT m.id) as matches,
        SUM(CASE WHEN b.is_wicket=1 AND b.wicket_type != 'run out' THEN 1 ELSE 0 END) as wickets, 
        COUNT(CASE WHEN b.is_legal=1 THEN 1 END) as legal_balls, 
        SUM(b.runs_bat + b.extras_runs) as runs_conceded 
    FROM ball_events b 
    JOIN innings i ON b.innings_id=i.id 
    JOIN matches m ON i.match_id=m.id 
    WHERE b.bowler_id IN ($inClause)
";
$oStats = $pdo->query($bowlSql)->fetch(PDO::FETCH_ASSOC);

// Matches Played (Max of batted vs bowled matches to capture participation)
$totalMatches = max((int)$bStats['matches'], (int)$oStats['matches']);

// 6. Global Awards (Man of the Match)
$momSql = "SELECT COUNT(*) FROM matches WHERE man_of_match_id IN ($inClause)";
$momCount = $pdo->query($momSql)->fetchColumn();

// 7. Fetch Teams & Tournaments History
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

// 8. Chart Data: Runs History
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
$graphLabels = [];
$graphRuns = [];
$graphColors = [];
foreach($chartData as $cd) {
    $graphLabels[] = "Match " . $cd['id'];
    $graphRuns[] = (int)$cd['runs'];
    // Red point if out, Green if not out
    $graphColors[] = ($cd['is_out'] == 1) ? '#ff5252' : '#00e676';
}

// Calculations
$sr = ($bStats['balls'] > 0) ? round(($bStats['runs'] / $bStats['balls']) * 100, 1) : 0;
$overs = $oStats['legal_balls'] / 6;
$econ = ($overs > 0) ? round($oStats['runs_conceded'] / $overs, 2) : 0;
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
        /* PROFILE HEADER */
        .profile-header { 
            text-align: center; 
            padding: 30px 20px; 
            margin-bottom: 20px;
            /* Lined paper effect */
            background-image: repeating-linear-gradient(transparent, transparent 29px, #ccc 30px);
            background-color: var(--paper);
            border-bottom: 2px solid var(--ink);
        }
        .avatar { margin-bottom: 10px; display: inline-block; }
        .p-name { margin: 0; }
        
        /* STAT CARDS */
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
        /* Tape effect on stat box */
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
        
        /* LISTS */
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
            <div class="stat-box"><div class="stat-label">Highest</div><div class="stat-val"><?= (int)$bStats['hs'] ?></div></div>
            <div class="stat-box"><div class="stat-label">Strike Rate</div><div class="stat-val"><?= $sr ?></div></div>
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
            <div class="stat-box"><div class="stat-label">Economy</div><div class="stat-val"><?= $econ ?></div></div>
            <div class="stat-box"><div class="stat-label">Overs</div><div class="stat-val"><?= round($overs, 1) ?></div></div>
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
                    borderColor: '#2c3e50', // Ink color
                    borderWidth: 2,
                    tension: 0.4, // Hand-drawn feel
                    pointBackgroundColor: <?= json_encode($graphColors) ?>,
                    pointBorderColor: '#2c3e50',
                    pointRadius: 6,
                    borderDash: [],
                    fill: true,
                    backgroundColor: 'rgba(0, 188, 212, 0.1)' // Light cyan wash
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