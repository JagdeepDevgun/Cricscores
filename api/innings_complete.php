<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/auth.php';
require_login($pdo);
header('Content-Type: application/json');

$innings_id = (int)($_POST['innings_id'] ?? 0);
if ($innings_id<=0) { http_response_code(400); echo json_encode(['error'=>'innings_id required']); exit; }

$res = complete_innings($pdo, $innings_id);
if (isset($res['error'])) { http_response_code($res['code'] ?? 400); echo json_encode(['error'=>$res['error']]); exit; }
echo json_encode($res);
