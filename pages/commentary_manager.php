<?php
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../db.php';
$user = auth_user($pdo);
if (!$user) { header("Location: login.php"); exit; }
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>
    <title>Commentary Manager</title>
    <link rel="stylesheet" href="../style.css"/>
    <style>
        .container { max-width: 900px; margin: 0 auto; padding: 15px; }
        .editor-card { background: #fff; padding: 20px; border-radius: 8px; border: 2px solid #2c3e50; margin-bottom: 20px; box-shadow: 4px 4px 0 rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px; text-transform: uppercase; color: #7f8c8d; }
        select, input, textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; font-size: 16px; box-sizing: border-box; }
        .table-responsive { overflow-x: auto; border: 1px solid #eee; border-radius: 6px; }
        .comm-table { width: 100%; border-collapse: collapse; background: #fff; font-size: 14px; min-width: 600px; }
        .comm-table th { background: #2c3e50; color: #fff; padding: 12px; text-align: left; }
        .comm-table td { border-bottom: 1px solid #eee; padding: 12px; vertical-align: top; }
        .tag { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; white-space: nowrap; }
        .tag-event { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .tag-ctx { background: #f3e5f5; color: #7b1fa2; border: 1px solid #e1bee7; }
        .btn-sm { padding: 6px 12px; font-size: 12px; margin-right: 5px; cursor: pointer; border-radius: 4px; border: 1px solid transparent; font-weight: bold; }
        .btn-edit { background: #fff3e0; color: #e65100; border-color: #ffe0b2; }
        .btn-del { background: #ffebee; color: #c62828; border-color: #ffcdd2; }
        .filter-bar { display: flex; gap: 10px; margin-bottom: 15px; }
        .filter-bar input { flex: 1; }
        .back-btn { display: flex; align-items: center; gap: 5px; text-decoration: none; color: #2c3e50; font-weight: bold; margin-bottom: 15px; }
        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; gap: 10px; } .container { padding: 10px; } .editor-card { padding: 15px; } }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand"><a href="../index.php" class="brand-link"><img src="../assets/logo.png" alt="Logo" style="height:24px; vertical-align:middle;"> CricScore Admin</a></div>
        <div class="top-actions"><a class="chip" href="../index.php">Exit</a></div>
    </div>

    <div class="container">
        <a href="../index.php" class="back-btn"><span>←</span> Back to Dashboard</a>

        <div class="editor-card">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:15px;">Add / Edit Commentary</h3>
            <input type="hidden" id="edit-id" value="">
            
            <div class="form-grid">
                <div>
                    <label>Trigger Event</label>
                    <select id="in-trigger">
                        <optgroup label="Runs">
                            <option value="0">Dot Ball (0)</option>
                            <option value="1">1 Run</option>
                            <option value="2">2 Runs</option>
                            <option value="3">3 Runs</option>
                            <option value="4">Four (4)</option>
                            <option value="6">Six (6)</option>
                        </optgroup>
                        <optgroup label="Wickets">
                            <option value="out_caught">Caught</option>
                            <option value="out_bowled">Bowled</option>
                            <option value="out_lbw">LBW</option>
                            <option value="out_stumped">Stumped</option>
                            <option value="out_run out">Run Out</option>
                            <option value="out_hit_wicket">Hit Wicket</option>
                        </optgroup>
                        <optgroup label="Milestones">
                            <option value="milestone_hattrick">Hat-trick</option>
                            <option value="partnership_50">Partnership 50</option>
                            <option value="partnership_100">Partnership 100</option>
                            <option value="milestone_50">Batter 50</option>
                            <option value="milestone_100">Batter 100</option>
                        </optgroup>
                        <optgroup label="Game State">
                            <option value="win_chase">Win (Chase)</option>
                            <option value="win_defend">Win (Defend)</option>
                            <option value="tie">Tie</option>
                            <option value="stat_attack">Random Stat</option>
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label>Context</label>
                    <select id="in-context">
                        <option value="default">Default</option>
                        <option value="close_finish">Close Finish / Hype</option>
                        <option value="pressure">Pressure / Nervous</option>
                        <option value="rapid">Rapid / Aggressive</option>
                        <option value="collapse">Collapse (Wickets)</option>
                        <option value="back_to_back">Back-to-Back</option>
                        <option value="high_rrr">High RRR</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom:15px;">
                <label>Commentary Text</label>
                <textarea id="in-text" rows="3" placeholder="e.g. Unbelievable scenes!"></textarea>
            </div>
            
            <div style="display:flex; gap:10px;">
                <button onclick="saveLine()" class="btn" style="background:#00bcd4; color:white; flex:2;">Save Line</button>
                <button onclick="resetForm()" class="btn danger" style="background:#eee; color:#333; flex:1;">Clear</button>
            </div>
        </div>

        <h4 style="margin-bottom:10px; color:#555;">Existing Lines</h4>
        <div class="filter-bar"><input type="text" id="search" placeholder="Search text..." onkeyup="filterList()"></div>
        <div class="table-responsive">
            <table class="comm-table">
                <thead><tr><th width="140">Trigger / Context</th><th>Text</th><th width="120">Actions</th></tr></thead>
                <tbody id="list-body"><tr><td colspan="3" style="text-align:center; padding:20px;">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>

<script>
let allLines = [];
async function loadLines() {
    try {
        const r = await fetch('../api/commentary_ops.php?action=list');
        allLines = await r.json();
        render(allLines);
    } catch(e) { document.getElementById('list-body').innerHTML = '<tr><td colspan="3" style="color:red; text-align:center;">Error loading data.</td></tr>'; }
}

function render(list) {
    const tbody = document.getElementById('list-body');
    if(list.length === 0) { tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:#888;">No commentary found.</td></tr>'; return; }
    tbody.innerHTML = list.map(item => `
        <tr>
            <td><span class="tag tag-event">${item.trigger_event}</span><br><div style="height:4px;"></div><span class="tag tag-ctx">${item.context_tag}</span></td>
            <td style="line-height:1.4;">${item.text_template}</td>
            <td><div style="display:flex;"><button class="btn-sm btn-edit" onclick='editLine(${JSON.stringify(item)})'>Edit</button><button class="btn-sm btn-del" onclick="deleteLine(${item.id})">Del</button></div></td>
        </tr>`).join('');
}

function filterList() {
    const q = document.getElementById('search').value.toLowerCase();
    render(allLines.filter(i => i.text_template.toLowerCase().includes(q) || i.trigger_event.toLowerCase().includes(q)));
}

function editLine(item) {
    document.getElementById('edit-id').value = item.id;
    document.getElementById('in-trigger').value = item.trigger_event;
    document.getElementById('in-context').value = item.context_tag;
    document.getElementById('in-text').value = item.text_template;
    window.scrollTo({top:0, behavior:'smooth'});
}

function resetForm() {
    document.getElementById('edit-id').value = '';
    document.getElementById('in-text').value = '';
    document.getElementById('in-context').value = 'default';
}

async function saveLine() {
    const id = document.getElementById('edit-id').value;
    const trigger = document.getElementById('in-trigger').value;
    const context = document.getElementById('in-context').value;
    const text = document.getElementById('in-text').value;
    if(!text) { alert("Please enter commentary text"); return; }
    
    const fd = new FormData();
    fd.append('action', id ? 'edit' : 'add');
    if(id) fd.append('id', id);
    fd.append('trigger', trigger);
    fd.append('context', context);
    fd.append('text', text);
    
    try { await fetch('../api/commentary_ops.php', { method:'POST', body:fd }); resetForm(); loadLines(); } 
    catch(e) { alert("Failed to save."); }
}

async function deleteLine(id) {
    if(!confirm("Delete this line?")) return;
    const fd = new FormData(); fd.append('action', 'delete'); fd.append('id', id);
    await fetch('../api/commentary_ops.php', { method:'POST', body:fd }); loadLines();
}
loadLines();
</script>
</body>
</html>