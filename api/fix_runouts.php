<?php
// Run this file once to fix existing data in the database
require_once __DIR__ . '/../db.php';

header('Content-Type: text/plain');

try {
    // 1. Standardize variations like "Run Out", "runout", "Run-Out" to "run out"
    // SQLite LIKE is case-insensitive by default for ASCII
    $sql = "UPDATE ball_events 
            SET wicket_type = 'run out' 
            WHERE is_wicket = 1 
            AND (wicket_type LIKE 'run%out' OR wicket_type LIKE 'run%out%')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $count = $stmt->rowCount();
    echo "Success: Updated $count existing records to standard 'run out' format.";

} catch (PDOException $e) {
    echo "Error updating records: " . $e->getMessage();
}
?>
