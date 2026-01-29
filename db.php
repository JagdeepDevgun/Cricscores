<?php
// db.php
$path = __DIR__ . '/cric.db';
$dir = dirname($path);
if (!is_dir($dir)) @mkdir($dir, 0775, true);

$pdo = new PDO('sqlite:' . $path);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// 1. Enforce Foreign Keys
$pdo->exec('PRAGMA foreign_keys = ON;');

// 2. Disable WAL Mode - Set to DELETE (Standard rollback journal)
$pdo->exec('PRAGMA journal_mode = DELETE;');

// 3. Set Synchronous to FULL for data safety without WAL
$pdo->exec('PRAGMA synchronous = FULL;');