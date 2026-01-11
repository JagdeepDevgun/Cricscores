<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$match_id = (int)($_POST['match_id'] ?? 0);
if ($match_id <= 0) { 
    http_response_code(400); 
    echo json_encode(['error' => 'match_id required']); 
    exit; 
}

// 1. Validate Match Status
$matchStmt = $pdo->prepare("SELECT * FROM matches WHERE id=?");
$matchStmt->execute([$match_id]);
$m = $matchStmt->fetch(PDO::FETCH_ASSOC);

if (!$m) { 
    http_response_code(404); 
    echo json_encode(['error' => 'Match not found']); 
    exit; 
}

if ($m['status'] !== 'awaiting_super_over') { 
    http_response_code(400); 
    echo json_encode(['error' => 'Match is not awaiting super over']); 
    exit; 
}

// 2. Fetch Previous Innings to determine teams
$inn1s = $pdo->prepare("SELECT * FROM innings WHERE match_id=? AND innings_no=1");
$inn2s = $pdo->prepare("SELECT * FROM innings WHERE match_id=? AND innings_no=2");
$inn1s->execute([$match_id]); 
$inn2s->execute([$match_id]);
$i1 = $inn1s->fetch(PDO::FETCH_ASSOC);
$i2 = $inn2s->fetch(PDO::FETCH_ASSOC);

if (!$i1 || !$i2) { 
    http_response_code(400); 
    echo json_encode(['error' => 'Need innings 1 & 2 to start Super Over']); 
    exit; 
}

// 3. Determine Next Innings Number (Support multiple Super Overs)
$maxNoStmt = $pdo->prepare("SELECT COALESCE(MAX(innings_no),0) FROM innings WHERE match_id=?");
$maxNoStmt->execute([$match_id]);
$maxNo = (int)$maxNoStmt->fetchColumn();

// If match just finished (2 innings), next is 3. 
// If a super over just tied (4 innings), next is 5.
$nextNo = 3;
if ($maxNo >= 3) {
    $nextNo = ($maxNo % 2 === 0) ? ($maxNo + 1) : $maxNo;
}

// 4. Create or Reset Innings
$existStmt = $pdo->prepare("SELECT id, completed FROM innings WHERE match_id=? AND innings_no=?");
$existStmt->execute([$match_id, $nextNo]);
$exist = $existStmt->fetch(PDO::FETCH_ASSOC);

if (!$exist) {
    // Standard Rule: Team batting 2nd in main match bats 1st in Super Over.
    // However, sticking to your logic: using Innings 1 batting team.
    $bat = (int)$i1['batting_team_id'];
    
    // Determine bowling team (the other team)
    $bowl = ($bat == $m['team_a_id']) ? $m['team_b_id'] : $m['team_a_id'];

    // Insert new Super Over Innings (1 Over limit)
    $ins = $pdo->prepare("
        INSERT INTO innings(
            match_id, innings_no, batting_team_id, bowling_team_id, 
            target, completed, is_super_over, overs_limit_override
        ) VALUES(?, ?, ?, ?, NULL, 0, 1, 1)
    ");
    $ins->execute([$match_id, $nextNo, $bat, $bowl]);
} else {
    // If exists (e.g. accidental re-click), reset it
    $pdo->prepare("
        UPDATE innings 
        SET completed=0, target=NULL, is_super_over=1, overs_limit_override=1 
        WHERE id=?
    ")->execute([(int)$exist['id']]);
}

// 5. Update Match Status
$pdo->prepare("UPDATE matches SET status='live', super_over=1 WHERE id=?")->execute([$match_id]);

echo json_encode(['ok' => true]);
?>