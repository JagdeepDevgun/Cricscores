<?php
require_once 'db.php'; // Ensure your PDO connection is available

try {
    // 1. Check if the column already exists using PRAGMA table_info
    $stmt = $pdo->query("PRAGMA table_info(matches)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $columnExists = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'man_of_match_id') {
            $columnExists = true;
            break;
        }
    }

    // 2. Run the ALTER TABLE only if the column is missing
    if (!$columnExists) {
        $pdo->exec("ALTER TABLE matches ADD COLUMN man_of_match_id INTEGER DEFAULT NULL");
        echo "Column 'man_of_match_id' added successfully.";
    } else {
        echo "Column 'man_of_match_id' already exists. No changes made.";
    }
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
