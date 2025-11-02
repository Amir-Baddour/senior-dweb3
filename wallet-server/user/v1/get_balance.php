<?php
// wallet-server/user/v1/get_balance.php
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$allowed = [
  'https://web03-phi.vercel.app',                           // Your Vercel frontend
  'https://faces-wood-energy-catalog.trycloudflare.com',    // Your new tunnel URL
  'http://localhost',
  'http://127.0.0.1'
];

require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

$headers = getallheaders();
if (!isset($headers['Authorization'])) {
  http_response_code(401);
  echo json_encode(['error' => 'No authorization header']); exit;
}
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {
  http_response_code(401);
  echo json_encode(['error' => 'Invalid token format']); exit;
}

$jwt = $m[1];
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
  http_response_code(401);
  echo json_encode(['error' => 'Invalid or expired token']); exit;
}
$userId = $decoded['id'];

try {
  $walletsModel = new WalletsModel();
  $rows = $walletsModel->getWalletsByUser($userId);

  $balances = [];
  foreach ($rows as $r) {
    $symbol = strtoupper($r['coin_symbol']);     // normalize
    $balances[$symbol] = (float)$r['balance'];
  }

  echo json_encode(['balances' => $balances, 'user_id' => $userId]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'DB error']);
}
