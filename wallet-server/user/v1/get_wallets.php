<?php
// wallet-server/user/v1/get_wallets.php
header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

// --- JWT ---
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'No authorization header']);
  exit;
}
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Invalid token format']);
  exit;
}

$jwt = $m[1];
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // set the real secret
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Invalid or expired token']);
  exit;
}

$user_id = $decoded['id'];

try {
  $model = new WalletsModel();
  $rows  = $model->getWalletsByUser($user_id); // returns all columns

  $wallets = [];
  foreach ($rows as $r) {
    $wallets[] = [
      'coin_symbol'    => strtoupper($r['coin_symbol']),
      'balance'        => isset($r['balance']) ? (float)$r['balance'] : 0.0,
      'locked_balance' => isset($r['locked_balance']) ? (float)$r['locked_balance'] : 0.0,
      'updated_at'     => $r['updated_at'] ?? null,
    ];
  }

  echo json_encode([
    'success' => true,
    'wallets' => $wallets
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  error_log("get_wallets.php error: " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Server error']);
}
