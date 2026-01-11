<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/_helpers.php';
header('Content-Type: application/json');

$tournament_id = (int)($_GET['tournament_id'] ?? 0);
if ($tournament_id<=0) { http_response_code(400); echo json_encode(['error'=>'tournament_id required']); exit; }

$tour = $pdo->prepare("SELECT * FROM tournaments WHERE id=?");
$tour->execute([$tournament_id]);
$t = $tour->fetch(PDO::FETCH_ASSOC);
if (!$t) { http_response_code(404); echo json_encode(['error'=>'Tournament not found']); exit; }

$teamsStmt = $pdo->prepare("SELECT id,name FROM teams WHERE tournament_id=? ORDER BY name");
$teamsStmt->execute([$tournament_id]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

$rows = [];
foreach ($teams as $tm) {
  $rows[(int)$tm['id']] = [
    'team_id'=>(int)$tm['id'],
    'team'=> $tm['name'],
    'P'=>0,'W'=>0,'L'=>0,'T'=>0,'NR'=>0,'Pts'=>0,
    'runs_for'=>0,'overs_for'=>0.0,'runs_against'=>0,'overs_against'=>0.0,
    'NRR'=>0.0
  ];
}

$matchesStmt = $pdo->prepare("SELECT * FROM matches WHERE tournament_id=? AND status='completed'");
$matchesStmt->execute([$tournament_id]);
$matches = $matchesStmt->fetchAll(PDO::FETCH_ASSOC);

$winPts = (int)$t['win_points'];
$tiePts = (int)$t['tie_points'];
$nrPts  = (int)$t['nr_points'];
$lossPts= (int)$t['loss_points'];

foreach ($matches as $m) {
  $a = (int)$m['team_a_id'];
  $b = (int)$m['team_b_id'];
  if (!isset($rows[$a]) || !isset($rows[$b])) continue;

  $rows[$a]['P']++; $rows[$b]['P']++;

  $rt = $m['result_type'];
  if ($rt === 'A') {
    $rows[$a]['W']++; $rows[$b]['L']++;
    $rows[$a]['Pts'] += $winPts;
    $rows[$b]['Pts'] += $lossPts;
  } else if ($rt === 'B') {
    $rows[$b]['W']++; $rows[$a]['L']++;
    $rows[$b]['Pts'] += $winPts;
    $rows[$a]['Pts'] += $lossPts;
  } else if ($rt === 'tie') {
    $rows[$a]['T']++; $rows[$b]['T']++;
    $rows[$a]['Pts'] += $tiePts;
    $rows[$b]['Pts'] += $tiePts;
  } else if ($rt === 'nr') {
    $rows[$a]['NR']++; $rows[$b]['NR']++;
    $rows[$a]['Pts'] += $nrPts;
    $rows[$b]['Pts'] += $nrPts;
  }

  if ($rt !== 'nr') {
    $inn = $pdo->prepare("SELECT id, batting_team_id, is_super_over FROM innings WHERE match_id=? ORDER BY innings_no ASC");
    $inn->execute([(int)$m['id']]);
    $inns = $inn->fetchAll(PDO::FETCH_ASSOC);

    if (count($inns) >= 2) {
      foreach ($inns as $ix) {
        if ((int)$ix['is_super_over'] === 1) continue;
        $tot = innings_totals($pdo, (int)$ix['id']);
        $bt = (int)$ix['batting_team_id'];
        $opp = ($bt === $a) ? $b : $a;

        if (isset($rows[$bt])) {
          $rows[$bt]['runs_for'] += (int)$tot['runs'];
          $rows[$bt]['overs_for'] += (float)$tot['overs_float'];
        }
        if (isset($rows[$opp])) {
          $rows[$opp]['runs_against'] += (int)$tot['runs'];
          $rows[$opp]['overs_against'] += (float)$tot['overs_float'];
        }
      }
    }
  }
}

$vals = array_values($rows);
foreach ($vals as &$r) {
  $for = ($r['overs_for'] > 0) ? ($r['runs_for'] / $r['overs_for']) : 0.0;
  $ag  = ($r['overs_against'] > 0) ? ($r['runs_against'] / $r['overs_against']) : 0.0;
  $r['NRR'] = round($for - $ag, 3);
}

usort($vals, function($x,$y){
  if ($x['Pts'] !== $y['Pts']) return $y['Pts'] <=> $x['Pts'];
  if ($x['NRR'] !== $y['NRR']) return ($y['NRR'] <=> $x['NRR']);
  if ($x['W'] !== $y['W']) return $y['W'] <=> $x['W'];
  return strcmp($x['team'], $y['team']);
});

echo json_encode([
  'ok'=>true,
  'tournament'=>[
    'id'=>(int)$t['id'],
    'name'=>$t['name'],
    'type'=>$t['type'],
    'win_points'=>(int)$t['win_points'],
    'tie_points'=>(int)$t['tie_points'],
    'nr_points'=>(int)$t['nr_points'],
    'loss_points'=>(int)$t['loss_points']
  ],
  'table'=>$vals
]);
