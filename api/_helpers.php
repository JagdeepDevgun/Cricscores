<?php
// api/_helpers.php

/**
 * FAST READ: Uses cached columns from 'innings' table if available.
 * Falls back to fresh calculation if cache columns are missing or null.
 */
function innings_totals(PDO $pdo, int $innings_id): array {
  // Try to fetch cached columns
  $stmt = $pdo->prepare("SELECT total_runs, total_wickets, total_legal_balls FROM innings WHERE id=?");
  $stmt->execute([$innings_id]);
  $cache = $stmt->fetch(PDO::FETCH_ASSOC);

  // Fallback to calculation if columns don't exist or haven't been populated
  if (!isset($cache['total_runs']) || $cache['total_runs'] === null) {
      return innings_totals_fresh($pdo, $innings_id);
  }

  $runs = (int)$cache['total_runs'];
  $wkts = (int)$cache['total_wickets'];
  $legal = (int)$cache['total_legal_balls'];

  $overs = intdiv($legal, 6);
  $balls = $legal % 6;
  $overs_float = $overs + ($balls / 6.0);
  $rr = ($overs_float > 0) ? round($runs / $overs_float, 2) : "0.00";

  return [
    'runs' => $runs,
    'legal_balls' => $legal,
    'wkts' => $wkts,
    'overs_float' => $overs_float,
    'overs_text' => $overs . '.' . $balls,
    'rr' => $rr
  ];
}

/**
 * SLOW CALCULATION: Sums raw data from ball_events.
 */
function innings_totals_fresh(PDO $pdo, int $innings_id): array {
  $stmt = $pdo->prepare("SELECT SUM(runs_bat + extras_runs) as runs, COUNT(CASE WHEN is_legal=1 THEN 1 END) as legal, SUM(CASE WHEN is_wicket=1 THEN 1 END) as wkts FROM ball_events WHERE innings_id=?");
  $stmt->execute([$innings_id]);
  $res = $stmt->fetch(PDO::FETCH_ASSOC);

  $runs = (int)($res['runs'] ?? 0);
  $legal = (int)($res['legal'] ?? 0);
  $wkts = (int)($res['wkts'] ?? 0);

  $overs = intdiv($legal, 6);
  $balls = $legal % 6;
  $overs_float = $overs + ($balls / 6.0);
  $rr = ($overs_float > 0) ? round($runs / $overs_float, 2) : "0.00";

  return [
    'runs' => $runs,
    'legal_balls' => $legal,
    'wkts' => $wkts,
    'overs_float' => $overs_float,
    'overs_text' => $overs . '.' . $balls,
    'rr' => $rr
  ];
}

/**
 * TRIGGER: Recalculates totals from scratch and updates the cache.
 */
function recalculate_innings_score(PDO $pdo, int $innings_id) {
    $fresh = innings_totals_fresh($pdo, $innings_id);
    try {
        $upd = $pdo->prepare("UPDATE innings SET total_runs=?, total_wickets=?, total_legal_balls=? WHERE id=?");
        $upd->execute([$fresh['runs'], $fresh['wkts'], $fresh['legal_balls'], $innings_id]);
    } catch (Exception $e) {}
}

/**
 * Marks innings completed, advances match state.
 * Fixed to prevent premature Super Over prompts during live innings.
 */
function complete_innings(PDO $pdo, int $innings_id): array {
  // 1. Force a score refresh to ensure logic uses most recent ball
  recalculate_innings_score($pdo, $innings_id);

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

  $i1 = $pdo->query("SELECT * FROM innings WHERE match_id=$match_id AND innings_no=1")->fetch(PDO::FETCH_ASSOC);
  $i2 = $pdo->query("SELECT * FROM innings WHERE match_id=$match_id AND innings_no=2")->fetch(PDO::FETCH_ASSOC);

  // Helper for creating subsequent innings
  $createInnings = function($no, $bat_id, $bowl_id, $target, $is_super, $limit) use ($pdo, $match_id) {
    $stmt = $pdo->prepare("INSERT INTO innings(match_id, innings_no, batting_team_id, bowling_team_id, target, completed, is_super_over, overs_limit_override) VALUES(?,?,?,?,?,0,?,?)");
    $stmt->execute([$match_id, $no, $bat_id, $bowl_id, $target, $is_super, $limit]);
  };

  if ($innings_no === 1) {
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
    $t1 = innings_totals($pdo, (int)$i1['id']);
    $t2 = innings_totals($pdo, (int)$i2['id']);
    $target = (int)$inn['target'];

    $winner_team_id = null;
    
    // Explicit Win/Loss/Tie Logic
    if ($t2['runs'] >= $target) {
        $winner_team_id = (int)$i2['batting_team_id'];
    } else if ($t2['runs'] < ($target - 1)) {
        // Only award win to team 1 if team 2 failed to reach even the tie score
        $winner_team_id = (int)$i1['batting_team_id'];
    } else {
        // Scores level (Tie)
        $winner_team_id = null;
    }

    if ($winner_team_id === null) {
      $pdo->prepare("UPDATE matches SET status='awaiting_super_over', winner_team_id=NULL, result_type='tie' WHERE id=?")->execute([$match_id]);
      return ['ok'=>true,'next'=>'tie','can_super_over'=>true];
    }

    $result_type = ((int)$winner_team_id === (int)$m['team_a_id']) ? 'A' : 'B';
    $pdo->prepare("UPDATE matches SET status='completed', winner_team_id=?, result_type=? WHERE id=?")->execute([$winner_team_id, $result_type, $match_id]);
    return ['ok'=>true,'next'=>'completed','winner_team_id'=>$winner_team_id,'result_type'=>$result_type];
  }

  // Super Over Logic
  if ((int)$inn['is_super_over'] === 1) {
      if ($innings_no % 2 !== 0) { // Super Over Innings 1 (e.g., Innings 3)
          $t = innings_totals($pdo, $innings_id);
          $createInnings($innings_no + 1, (int)$inn['bowling_team_id'], (int)$inn['batting_team_id'], $t['runs'] + 1, 1, 1);
          return ['ok'=>true, 'next'=>'super_over_chase'];
      } else { // Super Over Innings 2 (e.g., Innings 4)
          $prev_id = (int)$pdo->query("SELECT id FROM innings WHERE match_id=$match_id AND innings_no=".($innings_no-1))->fetchColumn();
          $t_prev = innings_totals($pdo, $prev_id);
          $t_cur = innings_totals($pdo, $innings_id);
          
          if ($t_cur['runs'] > $t_prev['runs']) $win_id = $inn['batting_team_id'];
          else if ($t_cur['runs'] < $t_prev['runs']) $win_id = $inn['bowling_team_id'];
          else {
              // Tie in super over - trigger another one
              $pdo->prepare("UPDATE matches SET status='awaiting_super_over', result_type='tie' WHERE id=?")->execute([$match_id]);
              return ['ok'=>true, 'next'=>'tie'];
          }
          
          $res_type = ($win_id == $m['team_a_id']) ? 'A' : 'B';
          $pdo->prepare("UPDATE matches SET status='completed', winner_team_id=?, result_type=? WHERE id=?")->execute([$win_id, $res_type, $match_id]);
          return ['ok'=>true, 'next'=>'completed'];
      }
  }

  return ['ok'=>true,'next'=>'noop'];
}
?>