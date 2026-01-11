<?php
require_once __DIR__ . '/../db.php';

try {
    // 1. Add columns to 'teams' table
    $cols = $pdo->query("PRAGMA table_info(teams)")->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('short_name', $cols)) {
        $pdo->exec("ALTER TABLE teams ADD COLUMN short_name TEXT DEFAULT NULL");
        echo "Added 'short_name' to teams.<br>";
    }
    
    if (!in_array('icon', $cols)) {
        $pdo->exec("ALTER TABLE teams ADD COLUMN icon TEXT DEFAULT 'shield'");
        echo "Added 'icon' to teams.<br>";
    }

    // 2. Add column to 'players' table
    $pCols = $pdo->query("PRAGMA table_info(players)")->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('is_captain', $pCols)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN is_captain INTEGER DEFAULT 0");
        echo "Added 'is_captain' to players.<br>";
    }

    echo "<h3>Database Updated Successfully! (v3)</h3>";
    echo "<a href='../index.php'>Go Home</a>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>