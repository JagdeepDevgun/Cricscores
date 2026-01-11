<?php
// api/_helpers.php

function innings_totals(PDO $pdo, int $innings_id): array {
  $stmt = $pdo->prepare("SELECT runs_bat, extras_runs, is_legal, is_wicket FROM ball_events WHERE innings_id=? ORDER BY seq ASC");
  $stmt->execute([$innings_id]);

  $runs = 0;
  $legal = 0;
  $wkts = 0;

  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $runs += (int)$r['runs_bat'] + (int)$r['extras_runs'];
    if ((int)$r['is_legal'] === 1) $legal++;
    if ((int)$r['is_wicket'] === 1) $wkts++;
  }

  $overs = intdiv($legal, 6);
  $balls = $legal % 6;
  $overs_float = $overs + ($balls / 6.0);
  
  // FIX: Calculate Run Rate
  $rr = ($overs_float > 0) ? round($runs / $overs_float, 2) : 0;

  return [
    'runs' => $runs,
    'legal_balls' => $legal,
    'wkts' => $wkts,
    'overs_float' => $overs_float,
    'overs_text' => $overs . '.' . $balls,
    'rr' => $rr // Added Run Rate
  ];
}

/**
 * Marks innings completed, advances match state:
 * (Logic remains same as before)
 */
function complete_innings(PDO $pdo, int $innings_id): array {
  $innStmt = $pdo->prepare("SELECT * FROM innings WHERE id=?");
  $innStmt->execute([$innings_id]);
  $inn = $innStmt->fetch(PDO::FETCH_ASSOC);
  if (!$inn) return ['error'=>'Innings not found','code'=>404];

  $match_id = (int)$inn['match_id'];
  $matchStmt = $pdo->prepare("SELECT * FROM matches WHERE id=?");
  $matchStmt->execute([$match_id]);
  $m = $matchStmt->fetch(PDO::FETCH_ASSOC);
  if (!$m) return ['error'=>'Match not found','code'=>404];
  if ($m['status'] === 'completed') return ['error'=>'Match already completed','code'=>400];

  $innings_no = (int)$inn['innings_no'];

  $pdo->prepare("UPDATE innings SET completed=1 WHERE id=?")->execute([$innings_id]);

  $inn1 = $pdo->prepare("SELECT * FROM innings WHERE match_id=? AND innings_no=1");
  $inn1->execute([$match_id]);
  $i1 = $inn1->fetch(PDO::FETCH_ASSOC);

  $inn2 = $pdo->prepare("SELECT * FROM innings WHERE match_id=? AND innings_no=2");
  $inn2->execute([$match_id]);
  $i2 = $inn2->fetch(PDO::FETCH_ASSOC);

  $createInnings = function(int $no, int $bat_id, int $bowl_id, ?int $target, int $is_super_over, ?int $overs_override) use ($pdo, $match_id) {
    $stmt = $pdo->prepare("INSERT INTO innings(match_id, innings_no, batting_team_id, bowling_team_id, target, completed, is_super_over, overs_limit_override) VALUES(?,?,?,?,?,0,?,?)");
    $stmt->execute([$match_id, $no, $bat_id, $bowl_id, $target, $is_super_over, $overs_override]);
    return (int)$pdo->lastInsertId();
  };

  if ($innings_no === 1) {
    if (!$i1) return ['error'=>'Innings1 missing','code'=>500];
    $t1 = innings_totals($pdo, (int)$i1['id']);
    $target = $t1['runs'] + 1;

    if (!$i2) {
      $createInnings(2, (int)$i1['bowling_team_id'], (int)$i1['batting_team_id'], $target, 0, null);
    } else {
      $pdo->prepare("UPDATE innings SET target=?, completed=0 WHERE id=?")->execute([$target, (int)$i2['id']]);
    }
    $pdo->prepare("UPDATE matches SET status='live' WHERE id=?")->execute([$match_id]);
    return ['ok'=>true,'next'=>'innings2','target'=>$target];
  }

  if ($innings_no === 2) {
    if (!$i1 || !$i2) return ['error'=>'Innings data missing','code'=>500];
    $t1 = innings_totals($pdo, (int)$i1['id']);
    $t2 = innings_totals($pdo, (int)$i2['id']);
    $target = ($i2['target'] !== null) ? (int)$i2['target'] : ($t1['runs'] + 1);

    $winner_team_id = null;
    if ($t2['runs'] >= $target) $winner_team_id = (int)$i2['batting_team_id'];
    else if ($t2['runs'] > $t1['runs']) $winner_team_id = (int)$i2['batting_team_id'];
    else if ($t2['runs'] < $t1['runs']) $winner_team_id = (int)$i1['batting_team_id'];
    else $winner_team_id = null;

    if ($winner_team_id === null) {
      $pdo->prepare("UPDATE matches SET status='awaiting_super_over', winner_team_id=NULL, result_type='tie' WHERE id=?")->execute([$match_id]);
      return ['ok'=>true,'next'=>'tie','can_super_over'=>true];
    }

    $result_type = ((int)$winner_team_id === (int)$m['team_a_id']) ? 'A' : 'B';
    $pdo->prepare("UPDATE matches SET status='completed', winner_team_id=?, result_type=? WHERE id=?")->execute([$winner_team_id, $result_type, $match_id]);
    return ['ok'=>true,'next'=>'completed','winner_team_id'=>$winner_team_id,'result_type'=>$result_type];
  }

  // Super over logic...
  if ((int)$inn['is_super_over'] === 1 && $innings_no >= 3) {
    if ($innings_no % 2 === 1) {
      $t = innings_totals($pdo, (int)$inn['id']);
      $target = $t['runs'] + 1;
      $next_no = $innings_no + 1;
      $nextStmt = $pdo->prepare("SELECT * FROM innings WHERE match_id=? AND innings_no=?");
      $nextStmt->execute([$match_id, $next_no]);
      $next = $nextStmt->fetch(PDO::FETCH_ASSOC);

      if (!$next) {
        $createInnings($next_no, (int)$inn['bowling_team_id'], (int)$inn['batting_team_id'], $target, 1, 1);
      } else {
        $pdo->prepare("UPDATE innings SET target=?, completed=0 WHERE id=?")->execute([$target, (int)$next['id']]);
      }
      $pdo->prepare("UPDATE matches SET status='live' WHERE id=?")->execute([$match_id]);
      return ['ok'=>true,'next'=>'super_over_chase','target'=>$target,'innings_no'=>$next_no];
    }

    $prev_no = $innings_no - 1;
    $prevStmt = $pdo->prepare("SELECT * FROM innings WHERE match_id=? AND innings_no=?");
    $prevStmt->execute([$match_id, $prev_no]);
    $prev = $prevStmt->fetch(PDO::FETCH_ASSOC);
    if (!$prev) return ['error'=>'Super over previous innings missing','code'=>500];

    $t_prev = innings_totals($pdo, (int)$prev['id']);
    $t_cur = innings_totals($pdo, (int)$inn['id']);
    $target = ($inn['target'] !== null) ? (int)$inn['target'] : ($t_prev['runs'] + 1);

    $winner_team_id = null;
    if ($t_cur['runs'] >= $target) $winner_team_id = (int)$inn['batting_team_id'];
    else if ($t_cur['runs'] > $t_prev['runs']) $winner_team_id = (int)$inn['batting_team_id'];
    else if ($t_cur['runs'] < $t_prev['runs']) $winner_team_id = (int)$prev['batting_team_id'];
    
    if ($winner_team_id === null) {
      $pdo->prepare("UPDATE matches SET status='awaiting_super_over', winner_team_id=NULL, result_type='tie', super_over=1 WHERE id=?")->execute([$match_id]);
      return ['ok'=>true,'next'=>'tie','can_super_over'=>true,'super_over_round'=>'another'];
    }

    $result_type = ((int)$winner_team_id === (int)$m['team_a_id']) ? 'A' : 'B';
    $pdo->prepare("UPDATE matches SET status='completed', winner_team_id=?, result_type=? WHERE id=?")->execute([$winner_team_id, $result_type, $match_id]);
    return ['ok'=>true,'next'=>'completed','winner_team_id'=>$winner_team_id,'result_type'=>$result_type];
  }

  return ['ok'=>true,'next'=>'noop'];
}