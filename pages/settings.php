<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../api/auth.php';

$user = auth_user($pdo);
if (!$user) {
    header("Location: login.php");
    exit;
}

/**
 * FIXED: The backup directory must match the one used in backup_ops.php
 * Path: Cricscores/data/backups/
 */
$backupDir = __DIR__ . '/../data/backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0775, true);

$files = glob($backupDir . '*.db');
$backups = [];
foreach ($files as $f) {
    $backups[] = [
        'file' => basename($f),
        'date' => filemtime($f),
        'size' => filesize($f)
    ];
}
// Sort by date descending (newest first)
usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Settings - CricScores</title>
  <link rel="stylesheet" href="../style.css"/>
  <style>
    .card { max-width: 800px; margin: 20px auto; }
    .table-responsive { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
    th { background: #f8f9fa; font-weight: 600; color: #555; }
    .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 4px; border: 1px solid #ccc; cursor: pointer; }
    .actions { display: flex; gap: 5px; }
    .divider { border-top: 1px solid #eee; margin: 30px 0; }
  </style>
</head>
<body>

<div class="wrap">
  <div class="topbar">
    <div class="brand">
      <a href="../index.php" class="brand-link">
        <img src="../assets/logo.png" alt="Logo">
      </a>
    </div>
    <div class="right-actions">
        <a class="btn" href="../index.php">Back to Dashboard</a>
    </div>
  </div>

  <div class="card">
    <h1 style="font-size: 20px; margin-bottom: 5px;">Settings & Maintenance</h1>
    <div class="muted">Manage database backups and security.</div>

    <div style="margin-top: 30px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Database Backups</h3>
            <button class="btn" onclick="createBackup(this)">+ Create Backup</button>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Created</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="backupList">
                    <?php if(empty($backups)): ?>
                        <tr><td colspan="4" class="muted" style="text-align:center;">No backups found in data/backups/.</td></tr>
                    <?php else: ?>
                        <?php foreach($backups as $b): ?>
                        <tr>
                            <td><?= htmlspecialchars($b['file']) ?></td>
                            <td><?= date('d M Y, h:i A', $b['date']) ?></td>
                            <td><?= round($b['size'] / 1024, 1) ?> KB</td>
                            <td class="actions">
                                <button class="btn-sm" onclick="restoreBackup('<?= $b['file'] ?>')">Restore</button>
                                <a class="btn-sm" href="../api/backup_ops.php?action=download&file=<?= urlencode($b['file']) ?>" style="text-decoration:none; color:inherit; background:#eee;">Download</a>
                                <button class="btn-sm danger" onclick="deleteBackup('<?= $b['file'] ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="divider"></div>

    <div>
        <h3>Change Password</h3>
        <form onsubmit="changePassword(event)" style="max-width: 400px;">
            <label class="muted" style="font-size:12px;">Current Password</label>
            <input type="password" name="old_password" required style="margin-bottom:10px; width:100%;">
            
            <label class="muted" style="font-size:12px;">New Password</label>
            <input type="password" name="new_password" required style="margin-bottom:20px; width:100%;">
            
            <button type="submit" class="btn">Update Password</button>
        </form>
    </div>

  </div>
</div>

<script>
async function createBackup(btn) {
    const orig = btn.innerText;
    btn.innerText = 'Backing up...';
    btn.disabled = true;
    try {
        const r = await fetch('../api/backup_ops.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'create' })
        });
        const j = await r.json();
        if(j.success) {
            alert('Backup created successfully!');
            location.reload();
        } else {
            alert(j.error || 'Backup failed');
        }
    } catch(e) { alert('Network Error'); }
    btn.innerText = orig;
    btn.disabled = false;
}

async function deleteBackup(file) {
    if(!confirm("Are you sure you want to delete " + file + "?")) return;
    const r = await fetch('../api/backup_ops.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'delete', file: file })
    });
    const j = await r.json();
    if(j.success) location.reload();
    else alert('Failed to delete: ' + (j.error || 'Unknown error'));
}

async function restoreBackup(file) {
    if(!confirm("WARNING: This will overwrite current data with " + file + ".\n\nYou will be logged out. Continue?")) return;
    
    const r = await fetch('../api/backup_ops.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'restore', file: file })
    });
    const j = await r.json();
    if(j.success) {
        alert('Database restored! Redirecting to login...');
        location.href = 'login.php';
    } else {
        alert(j.error || 'Restore failed');
    }
}

async function changePassword(e) {
    e.preventDefault();
    const form = e.target;
    const fd = new FormData(form);
    const r = await fetch('../api/change_password.php', { method: 'POST', body: fd });
    const j = await r.json();
    if(r.ok) {
        alert('Password updated!');
        form.reset();
    } else {
        alert(j.error || 'Error updating password');
    }
}
</script>

</body>
</html>