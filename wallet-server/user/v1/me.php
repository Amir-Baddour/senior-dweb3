<?php
declare(strict_types=1);

// CORS (allow your web client to call this endpoint)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../utils/verify_jwt.php';

// Robust Authorization header fetch (Windows/Apache variations)
$authHeader = '';
if (function_exists('getallheaders')) {
    $h = getallheaders();
    foreach ($h as $k => $v) {
        if (strtolower($k) === 'authorization') { $authHeader = $v; break; }
    }
}
if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
}
if (!$authHeader && isset($_SERVER['Authorization'])) {
    $authHeader = $_SERVER['Authorization'];
}

if (!preg_match('/Bearer\s(\S+)/i', $authHeader, $m)) {
    http_response_code(401);
    echo json_encode(['error'=>'no token']); exit;
}

$jwt_secret = 'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY'; // must match your login issuer
$payload = verify_jwt($m[1], $jwt_secret);
if (!$payload || empty($payload['id'])) {
    http_response_code(401);
    echo json_encode(['error'=>'bad token']); exit;
}

echo json_encode(['id' => (int)$payload['id']]);
