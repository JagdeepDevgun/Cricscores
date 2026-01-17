<?php
// Cricscores/update_db.php
require_once __DIR__ . '/db.php';

echo "<pre>";
echo "Running Database Updates...\n";

// 1. Create Commentary Table
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS commentary (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        trigger_event TEXT NOT NULL, 
        context_tag TEXT NOT NULL DEFAULT 'default',
        text_template TEXT NOT NULL,
        is_user_added INTEGER DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS idx_comm_trigger ON commentary(trigger_event);
    ");
    echo "? Commentary table created/verified.\n";
} catch (Exception $e) {
    die("Error creating table: " . $e->getMessage());
}

// 2. Seed Existing Commentary (Idempotent check)
$count = $pdo->query("SELECT COUNT(*) FROM commentary")->fetchColumn();
if ($count == 0) {
    echo "Seeding default commentary lines...\n";
    
    $seeds = [
        // Dots (0)
        ['0', 'default', "Tight line and length — the batsman can only defend. No run."],
        ['0', 'default', "Good pressure from the bowler, absolutely nothing on offer."],
        ['0', 'pressure_dots', "Dot ball! The bowler wins this round — crowd goes silent!"], // mapped to context
        ['0', 'default', "No room, no run, pure domination from the bowler!"],
        
        // Singles (1)
        ['1', 'default', "Just nudged into the gap and they’ll settle for a quick single."],
        ['1', 'default', "Soft hands, sharp running — one more added to the total."],
        
        // Fours (4)
        ['4', 'default', "Cracked! That races away to the boundary — four runs!"],
        ['4', 'default', "Timed to perfection, no chance for the fielder — that’s four."],
        ['4', 'high_rrr', "BANG! Bullet to the fence — exactly what the doctor ordered!"], 
        
        // Sixes (6)
        ['6', 'default', "That’s massive! Picked it up and launched it into the stands!"],
        ['6', 'default', "Clean as a whistle — straight over the ropes for six!"],
        ['6', 'high_rrr', "HUUUGE! That’s gone into orbit — keeping the hopes alive!"],
        
        // Wickets - Bowled
        ['out_bowled', 'default', "Cleaned him up! The stumps are shattered — what a delivery!"],
        ['out_bowled', 'default', "Through the gate and gone! Absolute peach of a ball!"],
        
        // Wickets - Caught
        ['out_caught', 'default', "Edge and taken! Safe hands in the slips — he’s gone!"],
        ['out_caught', 'default', "Mistimed shot, straight to the fielder — catch taken!"],
        
        // Wickets - LBW
        ['out_lbw', 'default', "Big appeal… finger goes up! He’s trapped right in front!"],
        
        // Wickets - Run Out
        ['out_run out', 'default', "Direct hit! That’s brilliant fielding — he’s short of the crease!"],
        
        // Milestones
        ['milestone_50', 'default', "And there it is! A well-deserved fifty!"],
        ['milestone_100', 'default', "What a knock! Brings up a magnificent hundred!"],
        ['milestone_hattrick', 'default', "Hat-trick ball… AND HE’S GOT HIM! HISTORY MADE!"],
        
        // Extras
        ['extra_wd', 'default', "That’s too wide — umpire stretches the arms."],
        ['extra_nb', 'default', "Overstepped! That’s a no-ball — free hit coming up!"],
        
        // Generic / Filler Stats
        ['stat_attack', 'default', "Stat Attack: 80% of runs coming on the off-side today."],
        ['stat_attack', 'default', "Analysis: The projected score is looking massive if they keep this up."],
        
        // Context Specifics (New Logic Seeds)
        ['0', 'pressure_dots', "Another dot! The pressure is really mounting now."],
        ['6', 'back_to_back', "BACK TO BACK! He is absolutely destroying the bowling attack!"],
        ['4', 'back_to_back', "Another boundary! The fielder had no chance."],
        ['run_rate', 'high', "The required rate is climbing! They need boundaries now."],
        ['run_rate', 'low', "Cruising along nicely. Just singles needed to get home."],
        ['spell_return', 'default', "The captain turns back to his strike bowler to break this partnership."]
    ];

    $stmt = $pdo->prepare("INSERT INTO commentary (trigger_event, context_tag, text_template) VALUES (?, ?, ?)");
    foreach ($seeds as $s) {
        $stmt->execute($s);
    }
    echo "? Seeded " . count($seeds) . " lines.\n";
} else {
    echo "? Commentary already seeded. Skipping.\n";
}

echo "\nDone. You can now use the Commentary Manager.";
?>
