<?php
ob_start();
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../db.php';
$id = (int)($_GET['id'] ?? 0);
$user = auth_user($pdo);
$canEdit = $user ? true : false;
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="../style.css"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
  <title>Match Center</title>
  <style>
    /* STICKY HEADER */
    .sticky-header { 
        position: fixed; top: 0; left: 0; right: 0; 
        background: #fff; border-bottom: 2px solid #2c3e50; 
        padding: 10px 16px; z-index: 999; 
        display: flex; align-items: center; justify-content: space-between; 
        transform: translateY(-120%); transition: transform 0.3s ease; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
    }
    .sticky-header.visible { transform: translateY(0); }
    .sticky-left { display: flex; flex-direction: column; }
    .sticky-team { font-size: 11px; font-weight: 800; color: #7f8c8d; text-transform: uppercase; }
    .sticky-score { font-size: 20px; font-weight: 900; color: #2c3e50; line-height: 1; font-family: 'Courier New', monospace; }
    .sticky-overs { font-size: 14px; font-weight: 600; color: #00bcd4; }
    .sticky-req { font-size: 12px; font-weight: 700; color: #ff5252; background: #fff0f0; padding:2px 6px; border-radius:4px; border:1px solid #ff5252; }
    
    /* SCORE DISPLAY */
    .score-display { 
        background: #fff; 
        border: 2px solid #2c3e50; 
        border-radius: 6px; 
        padding: 20px; 
        position: relative; 
        box-shadow: 5px 5px 0px rgba(44, 62, 80, 0.1);
        margin-bottom: 20px;
        background-image: radial-gradient(#ddd 1px, transparent 0);
        background-size: 12px 12px;
    }
    .score-big { font-size: 42px; font-weight: 900; font-family: 'Courier New', monospace; color: #2c3e50; letter-spacing: -2px; }
    
    /* TEAM STICKER */
    .team-sticker {
        background: #2c3e50; color: #fff;
        padding: 4px 12px; border-radius: 4px;
        font-weight: 800; text-transform: uppercase;
        font-size: 14px; letter-spacing: 1px;
        display: inline-block;
        transform: rotate(-1deg);
        box-shadow: 2px 2px 0px rgba(0,0,0,0.2);
    }

    /* SCORER KEYPAD */
    .scorer-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin-bottom: 15px; }
    .scorer-buttons button, .btn-edit-run { 
        height: 55px; width: 100%; border-radius: 6px; 
        font-weight: 900; font-size: 18px; font-family: 'Courier New', monospace;
        background: #fff; color: #2c3e50; border: 2px solid #2c3e50; box-shadow: 3px 3px 0px #ccc;
        cursor: pointer;
    }
    .scorer-buttons button:active, .btn-edit-run:active { transform: translate(2px, 2px); box-shadow: none; }
    
    /* COLOR KEYS */
    .btn-4 { color: #fff !important; background: #00bcd4 !important; border-color: #2c3e50 !important; }
    .btn-6 { color: #2c3e50 !important; background: #ffeb3b !important; border-color: #2c3e50 !important; }
    .btn-w { color: #fff !important; background: #ff5252 !important; border-color: #2c3e50 !important; }
    
    /* TABLES */
    .score-table th { background: #eee; color: #555; border: 1px solid #ccc; border-bottom: 2px solid #2c3e50; }
    .score-table td { border: 1px solid #eee; padding: 10px 8px; font-family: 'Courier New', monospace; }
    
    /* MISC */
    .result-banner { 
        background: #fff; border: 2px solid #2c3e50; color: #2c3e50; 
        padding: 15px; border-radius: 6px; text-align: center; font-weight: 800; 
        margin-bottom: 15px; box-shadow: 4px 4px 0px #00e676; 
    }
    .result-banner.tie { box-shadow: 4px 4px 0px #ffeb3b; }
    
    .chase-indicator { 
        background: #e3f2fd; border: 2px solid #2196f3; border-radius: 6px; 
        padding: 10px; margin-top: 10px; color: #0d47a1; 
    }

    /* COMMENTARY STYLES */
    .comm-row {
        display: flex;
        align-items: flex-start;
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        gap: 15px;
    }
    .comm-over {
        font-family: 'Courier New', monospace;
        font-weight: 800;
        color: #7f8c8d;
        font-size: 13px;
        min-width: 35px;
        padding-top: 8px;
    }
    .comm-ball {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        border: 2px solid #2c3e50;
        flex-shrink: 0;
        box-shadow: 2px 2px 0px rgba(0,0,0,0.1);
    }
    .comm-ball.normal { background: #fff; color: #2c3e50; }
    .comm-ball.four { background: #00bcd4; color: #fff; border-color: #008ba3; }
    .comm-ball.six { background: #ffeb3b; color: #000; border-color: #fbc02d; }
    .comm-ball.wicket { background: #ff5252; color: #fff; border-color: #d32f2f; }
    .comm-ball.extra { background: #ab47bc; color: #fff; border-color: #7b1fa2; font-size: 11px; }
    
    .comm-text { flex: 1; font-size: 14px; line-height: 1.5; color: #37474f; }
    
    /* SUMMARY ROW */
    .comm-summary {
        background: #fcfcfc;
        border-bottom: 2px solid #2c3e50;
        padding: 15px;
        font-size: 13px;
        color: #444;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .comm-summary-title { font-weight: 800; color: #2c3e50; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; display:flex; justify-content:space-between; }
    .comm-summary-stats { display: flex; justify-content: space-between; align-items: center; }
    .comm-summary-score { font-family: 'Courier New'; font-weight: 900; font-size: 18px; color:#2c3e50; }

    /* Modal */
    .modal-wrap { position:fixed; inset:0; background:rgba(255,255,255,0.95); z-index:2000; display:none; align-items:center; justify-content:center; }
    .modal-box { background:#fff; width:90%; max-width:400px; padding:25px; border-radius:8px; border:2px solid #2c3e50; box-shadow:8px 8px 0px #2c3e50; text-align:center; }
    
    /* WICKET MODAL GRID */
    .wicket-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; }
    .wicket-btn { padding: 15px; font-weight: 800; text-transform: uppercase; border: 2px solid #2c3e50; background: #fff; color: #2c3e50; border-radius: 6px; cursor: pointer; transition:0.1s; }
    .wicket-btn:hover { background: #2c3e50; color: #fff; transform: translateY(-2px); box-shadow: 3px 3px 0px rgba(0,0,0,0.2); }

    /* UPDATED: Player Select Grid with Swap Button */
    .player-select { 
        display: grid; 
        grid-template-columns: 1fr auto 1fr; /* Left (Striker) - Middle (Swap) - Right (NonStriker) */
        gap: 8px; 
        margin-bottom: 15px; 
        padding: 15px; 
        border: 2px dashed #ccc; 
        border-radius: 8px; 
        background: #fafafa; 
        align-items: center;
    }
    .player-select label { font-size: 11px; font-weight:700; text-transform:uppercase; color: #7f8c8d; }
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

<div id="sticky-header" class="sticky-header">
    <div id="sticky-left" class="sticky-left"></div>
    <div id="sticky-right" class="sticky-right"></div>
</div>

<div id="modal-bowler" class="modal-wrap">
  <div class="modal-box">
    <h3>End of Over</h3>
    <p class="muted">Who is bowling next?</p>
    <select id="new-bowler-select"></select>
    <button onclick="confirmNewBowler()" class="btn" style="width:100%; margin-top:15px; background:var(--pop-cyan); color:white;">Start Next Over</button>
  </div>
</div>

<div id="modal-batsman" class="modal-wrap">
  <div class="modal-box">
    <h3>Fall of Wicket</h3>
    <p class="muted">Who is the new batter?</p>
    <select id="new-batsman-select"></select>
    <button onclick="confirmNewBatsman()" class="btn" style="width:100%; margin-top:15px; background:var(--pop-cyan); color:white;">Send In</button>
  </div>
</div>

<div id="modal-wicket" class="modal-wrap">
  <div class="modal-box">
    <h3 id="wicket-title">Wicket Type</h3>
    
    <div id="wicket-step-1" class="wicket-grid">
       <button class="wicket-btn" onclick="selWicket('bowled')">Bowled</button>
       <button class="wicket-btn" onclick="selWicket('caught')">Catch</button>
       <button class="wicket-btn" onclick="selWicket('stumped')">Stumped</button>
       <button class="wicket-btn" onclick="selWicket('lbw')">LBW</button>
       <button class="wicket-btn" onclick="selWicket('hit wicket')">Hit Wicket</button>
       <button class="wicket-btn" onclick="selWicket('run out')" style="background:#fff0f0; border-color:#ff5252; color:#ff5252;">Run Out</button>
    </div>

    <div id="wicket-step-2" style="display:none;">
       <p class="muted" style="margin-bottom:15px;">Who is out?</p>
       <div class="wicket-grid">
         <button id="btn-out-striker" class="wicket-btn" onclick="selWho('striker')">Striker</button>
         <button id="btn-out-nonstriker" class="wicket-btn" onclick="selWho('non_striker')">Non-Striker</button>
       </div>
    </div>

    <div id="wicket-step-3" style="display:none;">
       <p class="muted">Runs Scored (if any)</p>
       <input type="number" id="wicket-runs" value="0" style="font-size:32px; font-weight:bold; text-align:center; padding:10px; margin-bottom:20px; width:100px; border:2px solid #2c3e50; border-radius:8px;">
       
       <div style="display:flex; gap:15px; justify-content:center; margin-bottom:25px;">
          <label style="font-weight:bold; display:flex; align-items:center; gap:5px;"><input type="checkbox" id="wicket-wd" style="width:20px; height:20px;"> Wide</label>
          <label style="font-weight:bold; display:flex; align-items:center; gap:5px;"><input type="checkbox" id="wicket-nb" style="width:20px; height:20px;"> No Ball</label>
       </div>

       <button class="btn" style="width:100%; background:var(--pop-red); color:white; padding:15px;" onclick="submitWicket()">CONFIRM OUT</button>
    </div>
    
    <button class="btn danger" style="margin-top:20px; width:100%; border:none; background:#eee; color:#555;" onclick="closeWicketModal()">Cancel</button>
  </div>
</div>

<div id="modal-edit-ball" class="modal-wrap">
  <div class="modal-box">
    <h3>Edit Ball</h3>
    <input type="hidden" id="edit-ball-id">
    
    <div style="margin-bottom:15px; text-align:left;">
        <label>Runs (Bat)</label>
        <div class="scorer-grid" style="grid-template-columns:repeat(7, 1fr); gap:5px; margin-top:5px;">
            <button onclick="setEditRun(0)" class="btn-edit-run">0</button>
            <button onclick="setEditRun(1)" class="btn-edit-run">1</button>
            <button onclick="setEditRun(2)" class="btn-edit-run">2</button>
            <button onclick="setEditRun(3)" class="btn-edit-run">3</button>
            <button onclick="setEditRun(4)" class="btn-edit-run">4</button>
            <button onclick="setEditRun(6)" class="btn-edit-run">6</button>
        </div>
        <input type="number" id="edit-runs-input" style="width:50px; padding:5px; margin-top:5px;">
    </div>

    <div style="margin-bottom:15px; text-align:left;">
        <label>Extras</label><br>
        <select id="edit-extras-type" style="padding:8px;">
            <option value="">None</option>
            <option value="wd">Wide</option>
            <option value="nb">No Ball</option>
            <option value="lb">Leg Bye</option>
            <option value="b">Bye</option>
        </select>
        <input type="number" id="edit-extras-runs" placeholder="+Runs" value="0" style="width:50px; padding:8px;">
    </div>

    <div style="margin-bottom:15px; text-align:left;">
        <label>Wicket?</label>
        <select id="edit-wicket-type" style="padding:8px; width:100%;">
            <option value="">Not Out</option>
            <option value="bowled">Bowled</option>
            <option value="caught">Caught</option>
            <option value="lbw">LBW</option>
            <option value="run out">Run Out</option>
        </select>
    </div>

    <button onclick="submitEditBall()" class="btn" style="width:100%; background:var(--pop-cyan); color:white;">Save Changes</button>
    <button onclick="document.getElementById('modal-edit-ball').style.display='none'" class="btn danger" style="width:100%; margin-top:10px; background:#eee; color:#333;">Cancel</button>
  </div>
</div>

<div class="wrap">
  <div class="topbar">
    <div class="brand">
      <a href="../index.php" class="brand-link">
       <img src="../assets/logo.png" alt="Logo">
      </a>
    </div>
     <div class="top-actions">
        <?php if($canEdit): ?><a class="chip" href="#" onclick="return doLogout()">Logout</a><?php else: ?><a class="chip" href="login.php">Login</a><?php endif; ?>
     </div>
  </div>
  <div style="margin-bottom:15px;"><a href="javascript:history.back()" style="font-weight:700;">← Back</a></div>

  <div id="result-banner" class="result-banner" style="display:none;"></div>
  
  <div id="mom-container"></div>

  <div id="meta" class="card" style="text-align:center;">Loading...</div>

  <div id="scorer-area" style="display:none;">
     <div class="score-display">
         <div class="inn-switcher">
             <select id="active-inn-sel" onchange="manualSwitchInnings(this.value)" style="border:1px solid #aaa;"></select>
         </div>
         <div id="view" style="display:none;"></div>
     </div>

     <?php if ($canEdit): ?>
     <div class="player-select">
        <div>
            <label>Striker</label>
            <select id="striker"></select>
        </div>
        
        <div style="padding-top: 18px;">
            <button onclick="swapBatters()" class="btn" style="padding: 0; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow:none; border:2px solid #ccc; font-size: 18px;" title="Swap Batters">⇄</button>
        </div>

        <div>
            <label>Non-Striker</label>
            <select id="nonstriker"></select>
        </div>
        
        <div style="grid-column: span 3;">
            <label>Current Bowler</label>
            <select id="bowler"></select>
        </div>
     </div>

     <div class="scorer-buttons">
        <div class="scorer-grid">
            <button onclick="addBall(0)">0</button>
            <button onclick="addBall(1)">1</button>
            <button onclick="addBall(2)">2</button>
            <button onclick="addBall(3)">3</button>
            <button onclick="addBall(4)" class="btn-4">4</button>
            <button onclick="addBall(6)" class="btn-6">6</button>
        </div>
        <div class="scorer-grid">
            <button onclick="addExtraPrompt('wd')" style="color:#ab47bc;">WD</button>
            <button onclick="addExtraPrompt('nb')" style="color:#ff5722;">NB</button>
            <button onclick="addExtraPrompt('b')" style="color:#00bcd4;">B</button>
            <button onclick="addExtraPrompt('lb')" style="color:#009688;">LB</button>
            <button onclick="openWicketModal()" class="btn-w" style="grid-column:span 2;">OUT</button>
        </div>
        <div class="scorer-actions" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px;">
          <button class="danger" onclick="undoBall()">Undo</button>
          <button onclick="refresh()">Refresh</button>
          <button style="border:2px dashed #000;" onclick="endInnings()">End Inn</button>
        </div>
     </div>
     <?php endif; ?>
  </div>

  <div class="card">
     <div class="tabs">
        <div class="tab active" id="tb-scorecard" onclick="switchTab('scorecard')">Scorecard</div>
        <div class="tab" id="tb-summary" onclick="switchTab('summary')">Summary</div>
        <div class="tab" id="tb-graphs" onclick="switchTab('graphs')">Charts</div>
        <div class="tab" id="tb-comm" onclick="switchTab('comm')">Comm</div>
     </div>
     
     <div id="tab-scorecard" style="display:block;"></div>
     <div id="tab-summary" style="display:none;"></div>
     <div id="tab-graphs" style="display:none; padding:10px;"><canvas id="compChart"></canvas></div>
     <div id="tab-comm" style="display:none;"></div>
  </div>
</div>

<script>
const matchId = <?= $id ?>;
const CAN_EDIT = <?= $canEdit ? 'true' : 'false' ?>;
let matchData = null;
let currentInnings = null;
let manualInningsId = null;
let refreshInterval = null;
let chart = null;
let lastProcessedBallId = null; 

// WICKET MODAL STATE
let wType = '';
let wWho = '';

window.addEventListener('scroll', () => {
  const sc = document.querySelector('.score-display');
  const h = document.getElementById('sticky-header');
  if(sc && h) h.classList.toggle('visible', sc.getBoundingClientRect().bottom < 80);
});

async function refresh() {
    try {
        const r = await fetch(`../api/match_get.php?match_id=${matchId}&include_balls=1&t=${Date.now()}`);
        matchData = await r.json();
        
        if(matchData.error) {
            document.getElementById('meta').innerHTML = `<div style="color:red; text-align:center; padding:20px;">${matchData.error}</div>`;
            return;
        }

        render();
        if(CAN_EDIT) updateAutoDetect();
        
        if(!CAN_EDIT && (matchData.match.status === 'completed' || matchData.match.status === 'match_tied')) {
             if(refreshInterval) clearInterval(refreshInterval);
        }
    } catch(e) { console.error(e); }
}

function manualSwitchInnings(id) {
    manualInningsId = parseInt(id);
    render();
    updateAutoDetect();
}

function render() {
    const m = matchData.match;
    let finalHtml = m.is_final == 1 ? '<div style="margin-bottom:10px;"><span style="background:#ffeb3b; padding:4px 8px; font-weight:bold; border:2px solid #000; box-shadow:3px 3px 0 #000;">🏆 GRAND FINAL</span></div>' : '';
    
    document.getElementById('meta').innerHTML = finalHtml + `
        <h2><span class="team-sticker" style="background:#fff; color:#000; border:1px solid #000;">${m.team_a}</span> <span style="font-size:16px; color:#888;">VS</span> <span class="team-sticker" style="background:#000; color:#fff;">${m.team_b}</span></h2>
        <div class="muted" style="margin-top:10px; font-weight:600;">${m.status.toUpperCase().replace('_',' ')} &nbsp;&bull;&nbsp; ${m.overs_limit} Overs</div>
    `;

    const banner = document.getElementById('result-banner');
    const scorer = document.getElementById('scorer-area');
    const momContainer = document.getElementById('mom-container');
    
    banner.style.display = 'none';
    momContainer.innerHTML = ''; 

    if (m.status === 'completed' || m.status === 'match_tied' || m.status === 'awaiting_super_over') {
        banner.style.display = 'block';
        if (m.status === 'match_tied' || m.status === 'awaiting_super_over' || m.result_type === 'tie') { 
            banner.className='result-banner tie'; 
            if (m.status === 'awaiting_super_over') {
                let btns = '';
                if(CAN_EDIT) {
                    btns = `<div style="margin-top:15px; display:flex; gap:10px; justify-content:center;">
                        <button class="btn" style="background:var(--pop-cyan); color:white;" onclick="startSuperOver()">Start Super Over</button>
                        <button class="btn" style="background:#fff; color:#000; border:2px solid #ccc;" onclick="endAsTie()">Keep as Tie</button>
                    </div>`;
                }
                banner.innerHTML = `<div>MATCH TIED (Super Over?)</div>${btns}`;
            } else banner.textContent = "MATCH TIED"; 
        }
        else if (m.result_type === 'nr') { banner.textContent="NO RESULT"; }
        else if (m.result_text) { banner.textContent = m.result_text; } 
        else { banner.textContent = "MATCH COMPLETED"; }
        
        // MAN OF THE MATCH LOGIC [FIXED]
        if (m.status === 'completed') {
            if (m.mom_name) {
                momContainer.innerHTML = `
                    <div class="card" style="text-align:center; border:2px solid #00bcd4; background:#f0faff; position:relative;">
                        <span style="font-size:11px; font-weight:900; color:#00bcd4; text-transform:uppercase;">🌟 Man of the Match</span>
                        <div style="font-size:20px; font-weight:bold; color:#2c3e50; margin:5px 0;">${m.mom_name}</div>
                        ${CAN_EDIT ? `<button class="chip" onclick="showMomEditor()" style="border:none; cursor:pointer; background:#eee;">Change</button>` : ''}
                    </div>`;
            } else if (CAN_EDIT) {
                momContainer.innerHTML = `
                    <div class="card" style="text-align:center; border:2px dashed #ccc;">
                        <h4 style="margin-bottom:10px;">Select Man of the Match</h4>
                        <select id="mom-select" style="padding:8px; margin-bottom:10px; width:200px; border-radius:4px; border:1px solid #ccc;">
                            <option value="">-- Select Player --</option>
                            ${matchData.players.map(p => `<option value="${p.id}">${p.name}</option>`).join('')}
                        </select><br>
                        <button class="btn" onclick="saveMOM()" style="padding:8px 15px; background:#2c3e50; color:white; border-radius:4px;">Save Selection</button>
                    </div>`;
            }
        }
        scorer.style.display = 'block';
    } else {
        scorer.style.display = 'block';
    }

    currentInnings = null; 
    if (manualInningsId) currentInnings = matchData.innings.find(i => i.id === manualInningsId);
    if (!currentInnings) currentInnings = matchData.innings.find(i => !i.completed) || matchData.innings[matchData.innings.length-1];

    const sel = document.getElementById('active-inn-sel');
    if(sel) sel.innerHTML = matchData.innings.map(i => `<option value="${i.id}" ${currentInnings && currentInnings.id === i.id ? 'selected' : ''}>Innings ${i.innings_no}: ${i.batting_team}</option>`).join('');
    
    if(currentInnings) {
        const s = currentInnings.summary;
        let chaseHtml = '';
        let stickyChase = `<span>CRR ${s.rr}</span>`;

        if (matchData.chase && matchData.chase.innings_no === currentInnings.innings_no) {
             const c = matchData.chase;
             chaseHtml = `<div class="chase-indicator">🎯 Target ${c.target} &bull; Need <b>${c.required_runs}</b> off <b>${c.remaining_balls}</b> <small>(RR ${c.required_rr})</small></div>`;
             stickyChase = `<span class="sticky-req">Need ${c.required_runs} off ${c.remaining_balls}</span>`;
        }

        let partHtml = '';
        if(!currentInnings.completed && currentInnings.current_partnership) {
            const p = currentInnings.current_partnership;
            partHtml = `<div style="margin-top:10px; font-size:13px; color:#555; border-top:1px dashed #ccc; padding-top:5px;">Partnership: <b>${p.runs}</b> (${p.balls})</div>`;
        }

        const tName = currentInnings.batting_team_id == m.team_a_id ? (m.team_a_short||m.team_a) : (m.team_b_short||m.team_b);
        document.getElementById('sticky-left').innerHTML = `<div class="sticky-team">${tName}</div><div class="sticky-score">${s.runs}/${s.wkts} <span class="sticky-overs">${s.overs_text} Ov</span></div>`;
        document.getElementById('sticky-right').innerHTML = stickyChase;

        document.getElementById('view').style.display = 'block';
        document.getElementById('view').innerHTML = `
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="team-sticker">${currentInnings.batting_team}</div>
                <div style="text-align:right;">
                   <div class="score-big">${s.runs}/${s.wkts}</div>
                   <div class="muted" style="font-size:14px; font-weight:bold;">${s.overs_text} Ov &bull; CRR ${s.rr}</div>
                </div>
            </div>
            ${partHtml} ${chaseHtml}
            ${CAN_EDIT ? `<div style="margin-top:12px; display:flex; gap:4px; flex-wrap:wrap;">${s.recent_balls.map(formatPill).join('')}</div>` : ''}
        `;
    }
    renderTabs();
    renderCompChart();
}

function showMomEditor() {
    matchData.match.mom_name = null; 
    render();
}

async function saveMOM() {
    const pid = document.getElementById('mom-select').value;
    if(!pid) return alert("Please select a player");
    
    const fd = new FormData();
    fd.append('match_id', matchId);
    fd.append('player_id', pid); // Must match $_POST['player_id'] in your API

    try {
        const response = await fetch('../api/match_set_mom.php', {
            method: 'POST',
            body: fd
        });
        const res = await response.json();
        if(res.ok) {
            refresh(); // This re-fetches match_get.php which now has mom_name
        } else {
            alert("Error: " + res.error);
        }
    } catch(e) {
        console.error("MOM Save Failed:", e);
    }
}

function renderTabs() {
    let scHtml = '', sumHtml = '';
    matchData.innings.forEach(inn => {
        const ex = inn.scorecard.extras || {};
        const extrasStr = `Extras: <b>${ex.total||0}</b> (WD ${ex.wides||0}, NB ${ex.no_balls||0}, B ${ex.byes||0}, LB ${ex.leg_byes||0})`;

        scHtml += `<div style="border:2px solid #2c3e50; border-radius:6px; padding:15px; margin-bottom:20px; background:#fff; box-shadow:4px 4px 0 #eee;">
            <h3 style="margin:0 0 10px 0; border-bottom:2px solid #eee; padding-bottom:5px;">
                <span class="team-sticker" style="font-size:16px;">${inn.batting_team}</span> 
                <span style="float:right;">${inn.summary.runs}/${inn.summary.wkts}</span>
            </h3>
            <div class="table-responsive"><table class="score-table"><thead><tr><th>Batter</th><th>R</th><th>B</th><th>4s</th><th>6s</th><th>SR</th></tr></thead><tbody>
            ${inn.scorecard.batsmen.map(b => {
                const sr = b.balls > 0 ? ((b.runs/b.balls)*100).toFixed(1) : '0.0';
                const outText = b.dismissal ? `<span style="display:block; font-size:10px; color:#e53935; font-weight:bold;">${b.dismissal}</span>` : `<span style="display:block; font-size:10px; color:#43a047;">not out</span>`;
                return `<tr><td><a href="player.php?id=${b.id}">${b.name}</a>${b.is_captain==1?' (c)':''} ${outText}</td><td><b>${b.runs}</b></td><td>${b.balls}</td><td>${b.fours}</td><td>${b.sixes}</td><td>${sr}</td></tr>`;
            }).join('')}
            </tbody></table></div>
            <div style="padding:10px; background:#fafafa; border-bottom:1px solid #eee; font-size:13px; color:#444;">${extrasStr}</div>
            <div style="height:15px;"></div>
            <div class="table-responsive"><table class="score-table"><thead><tr><th>Bowler</th><th>O</th><th>R</th><th>W</th><th style="font-size:10px; color:#555;">WD</th><th style="font-size:10px; color:#555;">NB</th><th>Econ</th></tr></thead><tbody>
            ${inn.scorecard.bowlers.map(b => {
                 const overs = Math.floor(b.legal_balls/6) + '.' + (b.legal_balls%6);
                 const econ = b.legal_balls > 0 ? ((b.runs_conceded / b.legal_balls)*6).toFixed(1) : '-';
                 return `<tr><td><a href="player.php?id=${b.id}">${b.name}</a></td><td>${overs}</td><td>${b.runs_conceded}</td><td><b>${b.wickets}</b></td><td style="color:#666; font-size:12px;">${b.wides}</td><td style="color:#666; font-size:12px;">${b.no_balls}</td><td>${econ}</td></tr>`;
            }).join('')}
            </tbody></table></div>
            ${inn.fow.length > 0 ? `<div style="margin-top:15px; font-weight:bold; font-size:12px;">Fall of Wickets:</div><div style="font-size:12px; line-height:1.6;">${inn.fow.map(f => `${f.score}-${f.wicket} (${f.player}, ${f.over} ov)`).join(', ')}</div>` : ''}
        </div>`;

        sumHtml += `<h4 style="margin:20px 0 10px 0; display:inline-block;"><span class="team-sticker">${inn.batting_team}</span></h4>`;
        if(!inn.overs_history?.length) sumHtml += '<div class="muted">No balls yet.</div>';
        else {
            sumHtml += inn.overs_history.map(o => `
                <div style="border-bottom:1px solid #ddd; padding:8px 0; display:flex; justify-content:space-between;">
                   <div style="font-weight:bold; font-size:13px;">Over ${o.over} <span class="muted" style="font-weight:400;">${o.bowler}</span></div>
                   <div style="display:flex; gap:2px;">
                     ${o.balls.map(x => `<span onclick="openEditBall(${x.id}, ${x.runs_bat}, '${x.extras_type||''}', ${x.extras_runs}, '${x.wicket_type||''}')" style="cursor:pointer">${formatPill(x.label)}</span>`).join('')}
                   </div>
                </div>`).join('');
        }
    });
    
    document.getElementById('tab-scorecard').innerHTML = scHtml;
    document.getElementById('tab-summary').innerHTML = sumHtml;
    if(currentInnings) {
        document.getElementById('tab-comm').innerHTML = currentInnings.commentary.map(c => {
            if (c.type === 'over_end') {
                return `<div class="comm-summary">
                    <div class="comm-summary-title"><span>${c.over}</span> <span style="font-weight:900; color:#00bcd4;">Runs this over: ${c.this_over_runs}</span></div>
                    <div class="comm-summary-stats">
                        <div class="comm-summary-score">${c.score}</div>
                        <div>${c.batsmen}</div>
                    </div>
                    <div style="text-align:right; font-size:11px; color:#888;">${c.partnership}</div>
                </div>`;
            }
            
            let ballHtml = '';
            if (c.is_wicket) ballHtml = '<div class="comm-ball wicket">W</div>';
            else if (c.runs_bat == 6) ballHtml = '<div class="comm-ball six">6</div>';
            else if (c.runs_bat == 4) ballHtml = '<div class="comm-ball four">4</div>';
            else if (c.extras_type) ballHtml = `<div class="comm-ball extra">${c.extras_runs}${c.extras_type.toUpperCase()}</div>`;
            else ballHtml = `<div class="comm-ball normal">${c.runs}</div>`;

            return `<div class="comm-row">
                <div class="comm-over">${c.over}</div>
                <div class="comm-score">${ballHtml}</div>
                <div class="comm-text">${c.text}</div>
            </div>`;
        }).join('');
    }
}

function renderCompChart() {
    const ctx = document.getElementById('compChart').getContext('2d');
    const datasets = [];
    matchData.innings.forEach((inn, idx) => {
        const color = idx===0 ? '#00bcd4' : '#ffeb3b';
        const borderColor = idx===0 ? '#008ba3' : '#fbc02d';
        datasets.push({ 
            type: 'line', 
            label: inn.batting_team, 
            data: inn.graph_data, 
            borderColor: borderColor, 
            backgroundColor: color, 
            borderWidth: 2, 
            tension: 0.1, 
            pointRadius: 0, 
            fill: false 
        });
        if (inn.wickets_data?.length > 0) datasets.push({ 
            type: 'scatter', 
            label: 'W', 
            data: inn.wickets_data, 
            backgroundColor: '#ff5252', 
            borderColor: '#b71c1c', 
            borderWidth: 1, 
            pointRadius: 6 
        });
    });
    if(chart) chart.destroy();
    chart = new Chart(ctx, { 
        data: { datasets: datasets }, 
        options: { 
            responsive: true, 
            scales: { 
                x: { 
                    type: 'linear', 
                    title: {display:true, text:'Overs'}, 
                    grid: {color:'#eee'},
                    ticks: { stepSize: 1 }
                }, 
                y: { 
                    beginAtZero: true, 
                    title: {display:true, text:'Runs'}, 
                    grid: {color:'#eee'} 
                } 
            } 
        } 
    });
}

function updateAutoDetect() {
    if(!currentInnings || currentInnings.completed == 1 || matchData.match.status === 'completed') return;

    const last = currentInnings.last_ball;
    const thisBallId = last ? last.id : 0;
    if (thisBallId === lastProcessedBallId && manualInningsId === null) return; 
    
    const batPlayers = matchData.players.filter(p => p.team_id == currentInnings.batting_team_id);
    const bowlPlayers = matchData.players.filter(p => p.team_id != currentInnings.batting_team_id);
    const populate = (id, list, selected) => { const el = document.getElementById(id); el.innerHTML = '<option value="">Select...</option>'; list.forEach(p => el.innerHTML += `<option value="${p.id}" ${p.id==selected?'selected':''}>${p.name}</option>`); };
    
    if(!last) { populate('striker', batPlayers, ''); populate('nonstriker', batPlayers, ''); populate('bowler', bowlPlayers, ''); lastProcessedBallId=thisBallId; return; }
    
    let s = last.striker_id, ns = last.non_striker_id, b = last.bowler_id;
    let runsRan = parseInt(last.runs_bat);
    if (runsRan % 2 !== 0 && runsRan !== 4 && runsRan !== 6) { let temp = s; s = ns; ns = temp; }
    if ((last.extras_type === 'b' || last.extras_type === 'lb') && parseInt(last.extras_runs) % 2 !== 0) { let temp = s; s = ns; ns = temp; }
    
    if (last.is_wicket == 1) { 
        const outId = last.wicket_player_out_id;
        if(outId == s) { s = ''; showBatsmanModal(batPlayers, ns); }
        else if(outId == ns) { ns = ''; showBatsmanModal(batPlayers, s); }
        else { s = ''; showBatsmanModal(batPlayers, ns); } 
    }
    
    let legal = currentInnings.summary.legal_balls;
    if(legal > 0 && legal % 6 === 0 && last.is_legal == 1) { 
        let temp = s; s = ns; ns = temp; b = ''; 
        if(!currentInnings.completed) showBowlerModal(bowlPlayers, last.bowler_id); 
    }
    
    populate('striker', batPlayers, s); populate('nonstriker', batPlayers, ns); populate('bowler', bowlPlayers, b);
    lastProcessedBallId = thisBallId;
}

function swapBatters() {
    var s = document.getElementById('striker');
    var ns = document.getElementById('nonstriker');
    var tmp = s.value;
    s.value = ns.value;
    ns.value = tmp;
}

function openWicketModal() {
  if(!validateSel()) return;
  wType=''; wWho='';
  document.getElementById('wicket-runs').value = 0;
  document.getElementById('wicket-wd').checked = false;
  document.getElementById('wicket-nb').checked = false;
  
  document.getElementById('wicket-title').innerText = "Wicket Type";
  document.getElementById('wicket-step-1').style.display = 'grid';
  document.getElementById('wicket-step-2').style.display = 'none';
  document.getElementById('wicket-step-3').style.display = 'none';
  
  document.getElementById('modal-wicket').style.display = 'flex';
}

function selWicket(type) {
  wType = type;
  if(type === 'run out') {
     document.getElementById('wicket-title').innerText = "Run Out: Who?";
     document.getElementById('wicket-step-1').style.display = 'none';
     const sName = document.querySelector('#striker option:checked').text;
     const nsName = document.querySelector('#nonstriker option:checked').text;
     document.getElementById('btn-out-striker').innerHTML = `STRIKER<span style="display:block; font-size:11px; font-weight:bold; text-transform:none; margin-top:5px; color:#e91e63;">${sName}</span>`;
     document.getElementById('btn-out-nonstriker').innerHTML = `NON-STRIKER<span style="display:block; font-size:11px; font-weight:bold; text-transform:none; margin-top:5px; color:#e91e63;">${nsName}</span>`;
     document.getElementById('wicket-step-2').style.display = 'grid';
  } else {
     wWho = 'striker'; 
     showRunsStep();
  }
}

function selWho(who) {
  wWho = who;
  showRunsStep();
}

function showRunsStep() {
  document.getElementById('wicket-title').innerText = "Details";
  document.getElementById('wicket-step-1').style.display = 'none';
  document.getElementById('wicket-step-2').style.display = 'none';
  document.getElementById('wicket-step-3').style.display = 'block';
  document.getElementById('wicket-runs').focus();
}

function closeWicketModal() {
  document.getElementById('modal-wicket').style.display = 'none';
}

async function submitWicket() {
  const runs = parseInt(document.getElementById('wicket-runs').value || 0);
  const isWd = document.getElementById('wicket-wd').checked;
  const isNb = document.getElementById('wicket-nb').checked;
  let exType = ''; let exRuns = 0;
  if(isWd) { exType='wd'; exRuns=1; }
  else if(isNb) { exType='nb'; exRuns=1; }
  const fd = new FormData();
  fd.append('innings_id', currentInnings.id);
  fd.append('runs_bat', runs);
  fd.append('extras_runs', exRuns);
  if(exType) fd.append('extras_type', exType);
  fd.append('is_wicket', 1);
  fd.append('wicket_type', wType);
  const sId = document.getElementById('striker').value;
  const nsId = document.getElementById('nonstriker').value;
  fd.append('striker_id', sId);
  fd.append('non_striker_id', nsId);
  fd.append('bowler_id', document.getElementById('bowler').value);
  let outId = sId;
  if(wWho === 'non_striker') outId = nsId;
  fd.append('wicket_player_out_id', outId);
  await postBall(fd);
  closeWicketModal();
}

async function startSuperOver() { if(confirm("Start Super Over?")) { await fetch('../api/super_over_start.php', {method:'POST', body:new URLSearchParams({match_id:matchId})}); location.reload(); } }
async function endAsTie() {
    if(!confirm("End Match as Tie?")) return;
    const fd = new FormData(); fd.append('match_id', matchId); fd.append('result', 'tie');
    await fetch('../api/match_result.php', {method:'POST', body:fd});
    location.reload();
}
function formatPill(label) { let cls='pill'; if(label.includes('W')) cls='pill pill-w'; else if(label==='6') cls='pill pill-6'; else if(label==='4') cls='pill pill-4'; else if(['WD','NB','B','LB'].some(x=>label.includes(x))) cls='pill'; return `<span class="${cls}" style="margin-right:2px; font-weight:bold; font-family:'Courier New'; padding:2px 6px; border:1px solid #2c3e50; border-radius:4px; ${label.includes('W')?'background:#ff5252;color:white;':(label==='6'?'background:#ffeb3b;color:black;':(label==='4'?'background:#00bcd4;color:white;':'background:white;color:black;'))}">${label}</span>`; }
function switchTab(t){ ['summary','scorecard','graphs','comm'].forEach(x=>document.getElementById('tab-'+x).style.display='none'); ['tb-summary','tb-scorecard','tb-graphs','tb-comm'].forEach(x=>document.getElementById(x).classList.remove('active')); document.getElementById('tab-'+t).style.display='block'; document.getElementById('tb-'+t).classList.add('active'); }
function showBowlerModal(players, lastBowlerId) { const el = document.getElementById('modal-bowler'); const sel = document.getElementById('new-bowler-select'); sel.innerHTML = '<option value="">Select Next Bowler...</option>'; players.forEach(p => { if(p.id != lastBowlerId) sel.innerHTML += `<option value="${p.id}">${p.name}</option>`; }); el.style.display = 'flex'; }
function confirmNewBowler() { const val = document.getElementById('new-bowler-select').value; if(val) { document.getElementById('bowler').value = val; document.getElementById('modal-bowler').style.display = 'none'; } }
function showBatsmanModal(players, currentOtherId) { const el = document.getElementById('modal-batsman'); const sel = document.getElementById('new-batsman-select'); sel.innerHTML = '<option value="">Select New Batsman...</option>'; players.forEach(p => { if(p.id != currentOtherId) sel.innerHTML += `<option value="${p.id}">${p.name}</option>`; }); el.style.display = 'flex'; }
function confirmNewBatsman() { const val = document.getElementById('new-batsman-select').value; if(val) { document.getElementById('striker').value = val; document.getElementById('modal-batsman').style.display = 'none'; } }
async function addBall(runs) { if(validateSel()) await postBall(makeFD(runs, 0, '', '')); }
async function addExtraPrompt(type) { if(!validateSel()) return; let r = (type==='wd'||type==='nb') ? parseInt(prompt("Runs ran?", "0")||0) : parseInt(prompt("Runs?", "1")||0); await postBall(makeFD((type==='wd'||type==='nb')?r:0, (type==='wd'||type==='nb')?1:r, type, '')); }
function validateSel() { if(!document.getElementById('striker').value || !document.getElementById('nonstriker').value || !document.getElementById('bowler').value) { alert("Select Striker, Non-Striker and Bowler first."); return false; } return true; }
function makeFD(runs, exRuns, exType, wType) { const fd = new FormData(); fd.append('innings_id', currentInnings.id); fd.append('runs_bat', runs); fd.append('extras_runs', exRuns); if(exType) fd.append('extras_type', exType); if(wType) fd.append('wicket_type', wType); fd.append('striker_id', document.getElementById('striker').value); fd.append('non_striker_id', document.getElementById('nonstriker').value); fd.append('bowler_id', document.getElementById('bowler').value); return fd; }
async function postBall(fd) { await fetch('../api/ball_add.php', {method:'POST', body:fd}); refresh(); }
async function undoBall() { await fetch('../api/ball_undo.php', {method:'POST', body:new URLSearchParams({innings_id:currentInnings.id})}); refresh(); }
async function endInnings() { if(confirm('End Innings?')) { await fetch('../api/innings_complete.php', {method:'POST', body:new URLSearchParams({innings_id:currentInnings.id})}); refresh(); } }
async function doLogout(){ await fetch('../api/logout.php',{method:'POST'}); location.href='../index.php'; }
function openEditBall(id, runs, exType, exRuns, wType) {
    if(!CAN_EDIT) return;
    document.getElementById('edit-ball-id').value = id;
    document.getElementById('edit-runs-input').value = runs;
    document.getElementById('edit-extras-type').value = exType;
    document.getElementById('edit-extras-runs').value = exRuns;
    document.getElementById('edit-wicket-type').value = wType || '';
    document.getElementById('modal-edit-ball').style.display = 'flex';
}
function setEditRun(r) { document.getElementById('edit-runs-input').value = r; }
async function submitEditBall() {
    const fd = new FormData();
    fd.append('ball_id', document.getElementById('edit-ball-id').value);
    fd.append('runs_bat', document.getElementById('edit-runs-input').value);
    fd.append('extras_type', document.getElementById('edit-extras-type').value);
    fd.append('extras_runs', document.getElementById('edit-extras-runs').value);
    const wType = document.getElementById('edit-wicket-type').value;
    if(wType) {
        fd.append('is_wicket', 1);
        fd.append('wicket_type', wType);
    } else {
        fd.append('is_wicket', 0);
    }
    await fetch('../api/ball_edit.php', { method:'POST', body:fd });
    document.getElementById('modal-edit-ball').style.display = 'none';
    refresh();
}
refresh();
const refreshRate = 5000;
refreshInterval = setInterval(refresh, refreshRate);
</script>
</body>
</html>