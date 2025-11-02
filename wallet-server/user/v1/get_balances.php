<?php
// wallet-server/user/v1/get_balances.php
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

// --- JWT auth ---
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(['error' => 'No authorization header']);
    exit;
}
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {
    echo json_encode(['error' => 'Invalid token format']);
    exit;
}
$jwt = $m[1];
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // same key you use elsewhere
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}
$userId = $decoded['id'];

// --- Fetch all wallets for this user ---
try {
    $walletsModel = new WalletsModel();
    $wallets = $walletsModel->getWalletsByUser($userId);

    $balances = [];
    foreach ($wallets as $w) {
        $sym = strtoupper($w['coin_symbol']);
        $balances[$sym] = isset($w['balance']) ? floatval($w['balance']) : 0.0;
    }

    // Return empty map if user has no wallets yet
    echo json_encode(['success' => true, 'balances' => $balances, 'count' => count($balances)]);
} catch (PDOException $e) {
    error_log("get_balances.php DB error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
