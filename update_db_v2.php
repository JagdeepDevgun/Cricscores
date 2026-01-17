<?php
require_once __DIR__ . '/db.php';

echo "<pre>Adding Match Winning & Hype Lines...\n";

$new_lines = [
    // --- WINNING MOMENTS (CHASE) ---
    ['win_chase', 'default', "VICTORY! They've chased it down with balls to spare!"],
    ['win_chase', 'default', "That's the winning run! A fantastic performance by the batting side."],
    ['win_chase', 'close_finish', "WHAT A FINISH! He holds his nerve and gets them over the line!"],
    ['win_chase', 'boundary', "FINISHES IN STYLE! A magnificent boundary to seal the victory!"],
    ['win_chase', 'six', "MAXIMUM TO WIN! You cannot write a better script than this!"],

    // --- WINNING MOMENTS (DEFENSE) ---
    ['win_defend', 'default', "ALL OUT! The bowling team defends the total successfully!"],
    ['win_defend', 'close_finish', "HE'S DONE IT! The bowler holds his nerve! What a match!"],
    ['win_defend', 'thrashing', "A dominant display! The opposition had no answer today."],

    // --- TIE ---
    ['tie', 'default', "IT'S A TIE! Unbelievable scenes! We might need a Super Over!"],

    // --- CLOSE FINISH HYPE (Context: close_finish) ---
    // These override standard 0,1,2,4,6 descriptions in the last over
    ['0', 'close_finish', "Dot ball! Pure gold dust at this stage! The tension is palpable."],
    ['1', 'close_finish', "Just a single! The fielding side will take that any day."],
    ['4', 'close_finish', "FOUR! Is that the game-changer? The crowd goes wild!"],
    ['6', 'close_finish', "INTO THE CROWD! He's turned this game on its head!"],
    ['out_caught', 'close_finish', "GONE! Is that the match? A massive wicket at a crucial time!"],
    ['out_run out', 'close_finish', "RUN OUT! Panic in the middle! This game has everything!"],
];

$stmt = $pdo->prepare("INSERT INTO commentary (trigger_event, context_tag, text_template, is_user_added) VALUES (?, ?, ?, 0)");

foreach ($new_lines as $l) {
    // Check duplication to avoid spamming if ran twice
    $chk = $pdo->prepare("SELECT id FROM commentary WHERE trigger_event=? AND context_tag=? AND text_template=?");
    $chk->execute($l);
    if (!$chk->fetch()) {
        $stmt->execute($l);
        echo "Added: [{$l[0]}] {$l[2]}\n";
    }
}

echo "\nDone. Delete this file after running.";
?>
