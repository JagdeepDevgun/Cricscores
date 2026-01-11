<?php
require_once __DIR__ . '/../db.php';

echo "<h2>Optimizing Database...</h2>";

try {
    // 1. Force WAL Mode
    $pdo->exec('PRAGMA journal_mode = WAL;');
    echo "✅ WAL Mode Enabled (Fixes locking issues)<br>";

    // 2. Add Critical Indexes
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_balls_innings ON ball_events(innings_id)",
        "CREATE INDEX IF NOT EXISTS idx_balls_batsman ON ball_events(striker_id)",
        "CREATE INDEX IF NOT EXISTS idx_balls_bowler ON ball_events(bowler_id)",
        "CREATE INDEX IF NOT EXISTS idx_innings_match ON innings(match_id)",
        "CREATE INDEX IF NOT EXISTS idx_players_team ON players(team_id)",
        "CREATE INDEX IF NOT EXISTS idx_matches_tourn ON matches(tournament_id)"
    ];

    foreach ($indexes as $sql) {
        $pdo->exec($sql);
    }
    echo "✅ Indexes Created (Fixes slow queries)<br>";
    
    echo "<h3>Done! You can delete this file now.</h3>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
