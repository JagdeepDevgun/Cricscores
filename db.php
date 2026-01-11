<?php
// db.php — SQLite connection (PDO)
$path = __DIR__ . '/cric.db';
$dir = dirname($path);
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$pdo = new PDO('sqlite:' . $path);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// --- PERFORMANCE OPTIMIZATIONS ---
// 1. Enforce Foreign Keys
$pdo->exec('PRAGMA foreign_keys = ON;');
// 2. Enable WAL Mode (Allows reading while writing - CRITICAL for live scores)
$pdo->exec('PRAGMA journal_mode = WAL;');
// 3. Optimize synchronization (Safe speed boost)
$pdo->exec('PRAGMA synchronous = NORMAL;');