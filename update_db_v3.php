<?php
require_once __DIR__ . '/db.php';

echo "<pre>Adding Expanded Commentary Lines...\n";

$new_lines = [
    // --- RUNS (2s & 3s) ---
    ['2', 'default', "Pushed into the gap, easy two runs."],
    ['2', 'default', "Good running between the wickets, they come back for the second."],
    ['2', 'pressure', "Nerves? A bit of a mix-up but they get two safely."],
    ['3', 'default', "Great fielding on the boundary saves a four! They pick up three."],
    ['3', 'default', "Timed well but not quite enough for the boundary. Three runs added."],

    // --- WICKET: LBW ---
    ['out_lbw', 'default', "Loud appeal and given! Plumb in front!"],
    ['out_lbw', 'default', "Trapped on the crease! The umpire didn't hesitate."],
    ['out_lbw', 'default', "He walked across his stumps and missed it. Stone dead LBW."],

    // --- WICKET: STUMPED ---
    ['out_stumped', 'default', "Beaten in flight! The keeper whips the bails off in a flash!"],
    ['out_stumped', 'default', "He stepped out and missed it completely. Easy stumping."],

    // --- WICKET: RUN OUT ---
    ['out_run out', 'default', "Direct hit! He's miles out of his crease!"],
    ['out_run out', 'default', "A terrible mix-up in the middle! Both batters at the same end!"],
    ['out_run out', 'close_finish', "Suicidal run! They had to go for it, but he falls short!"],

    // --- WICKET: HIT WICKET ---
    ['out_hit_wicket', 'default', "Unbelievable scenes! He's trodden on his own stumps!"],
    ['out_hit_wicket', 'default', "Disaster! The bat clipped the bails on the follow-through."],

    // --- MILESTONE: HAT-TRICK ---
    ['milestone_hattrick', 'default', "HAT-TRICK! Unbelievable bowling! The crowd is going absolutely berserk!"],
    ['milestone_hattrick', 'default', "Three in three! He writes his name into the history books!"],

    // --- MILESTONE: PARTNERSHIPS ---
    ['partnership_50', 'default', "That's the 50-run stand! These two are rebuilding the innings nicely."],
    ['partnership_100', 'default', "A magnificent 100-run partnership! They are dominating the game now."],
    ['partnership_50', 'rapid', "50 partnership up in no time! They are scoring at a blistering pace."],
];

// FIX: Changed VALUES (?, ?, ?, 0) to VALUES (?, ?, ?, ?) to match the 4 parameters in execute()
$stmt = $pdo->prepare("INSERT INTO commentary (trigger_event, context_tag, text_template, is_user_added) VALUES (?, ?, ?, ?)");

foreach ($new_lines as $l) {
    // Check duplication to avoid duplicates if run multiple times
    $chk = $pdo->prepare("SELECT id FROM commentary WHERE trigger_event=? AND context_tag=? AND text_template=?");
    $chk->execute([$l[0], $l[1], $l[2]]);
    
    if (!$chk->fetch()) {
        // Now passing 4 values matches the 4 placeholders above
        $stmt->execute([$l[0], $l[1], $l[2], 0]); 
        echo "Added: [{$l[0]}] {$l[2]}\n";
    }
}

echo "\nDone. You can delete this file.";
?>