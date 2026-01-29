<?php
// init_db.php
require_once __DIR__ . '/db.php';

// Force the database to use standard rollback journal mode instead of WAL
$pdo->exec("PRAGMA journal_mode = DELETE;");
$pdo->exec("PRAGMA synchronous = FULL;");

$pdo->exec("
CREATE TABLE IF NOT EXISTS tournaments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  type TEXT NOT NULL DEFAULT 'round_robin',
  win_points INTEGER NOT NULL DEFAULT 2,
  tie_points INTEGER NOT NULL DEFAULT 1,
  nr_points  INTEGER NOT NULL DEFAULT 1,
  loss_points INTEGER NOT NULL DEFAULT 0,
  default_overs INTEGER NOT NULL DEFAULT 20,
  default_wickets INTEGER NOT NULL DEFAULT 10,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS teams (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tournament_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  UNIQUE(tournament_id, name)
);

CREATE TABLE IF NOT EXISTS players (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  team_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_players_team ON players(team_id);

CREATE TABLE IF NOT EXISTS matches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tournament_id INTEGER,
  team_a_id INTEGER NOT NULL,
  team_b_id INTEGER NOT NULL,
  overs_limit INTEGER NOT NULL DEFAULT 20,
  wickets_limit INTEGER NOT NULL DEFAULT 10,
  status TEXT NOT NULL DEFAULT 'scheduled',
  toss_winner_team_id INTEGER,
  toss_decision TEXT, 
  winner_team_id INTEGER,
  result_type TEXT,
  super_over INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  is_final INTEGER DEFAULT 0, 
  man_of_match_id INTEGER DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_matches_tourn ON matches(tournament_id);

CREATE TABLE IF NOT EXISTS innings (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  match_id INTEGER NOT NULL,
  innings_no INTEGER NOT NULL,
  batting_team_id INTEGER NOT NULL,
  bowling_team_id INTEGER NOT NULL,
  target INTEGER,
  completed INTEGER NOT NULL DEFAULT 0,
  is_super_over INTEGER NOT NULL DEFAULT 0,
  overs_limit_override INTEGER,
  total_runs INTEGER DEFAULT 0, 
  total_wickets INTEGER DEFAULT 0, 
  total_legal_balls INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_innings_match ON innings(match_id);

CREATE TABLE IF NOT EXISTS ball_events (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  innings_id INTEGER NOT NULL,
  seq INTEGER NOT NULL,
  striker_id INTEGER,
  non_striker_id INTEGER,
  bowler_id INTEGER,
  runs_bat INTEGER NOT NULL DEFAULT 0,
  extras_type TEXT,
  extras_runs INTEGER NOT NULL DEFAULT 0,
  is_wicket INTEGER NOT NULL DEFAULT 0,
  wicket_type TEXT,
  wicket_player_out_id INTEGER,
  is_legal INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_balls_innings ON ball_events(innings_id);
CREATE INDEX IF NOT EXISTS idx_balls_batsman ON ball_events(striker_id);
CREATE INDEX IF NOT EXISTS idx_balls_bowler ON ball_events(bowler_id);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS saved_players (
  id INTEGER PRIMARY KEY AUTOINCREMENT, 
  name TEXT UNIQUE
);
");

// Admin user setup
$adminUser = getenv('ADMIN_USER') ?: 'admin';
$adminPass = getenv('ADMIN_PASS') ?: 'admin123';
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount === 0) {
  $hash = password_hash($adminPass, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare('INSERT INTO users(username, password_hash) VALUES(?,?)');
  $stmt->execute([$adminUser, $hash]);
}

echo "✅ DB initialized (Rollback Journal Mode Active).";