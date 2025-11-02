<?php
header("Content-Type: application/json");

require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/TransactionsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

$allowed = [
  'https://web03-phi.vercel.app',                           // Your Vercel frontend
  'https://faces-wood-energy-catalog.trycloudflare.com',    // Your new tunnel URL
  'http://localhost',
  'http://127.0.0.1'
];

// --- JWT ---
$headers = getallheaders();
if (!isset($headers['Authorization'])) { echo json_encode(["error" => "No authorization header."]); exit; }
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) { echo json_encode(["error" => "Invalid token format."]); exit; }
$jwt = $m[1];
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) { echo json_encode(["error" => "Invalid or expired token."]); exit; }
$userId = $decoded['id'];

// --- filters ---
$date = isset($_GET['date']) ? $_GET['date'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;

try {
  $txModel = new TransactionsModel();
  $userModel = new UsersModel();

  // Get all tx; keep meta_json & transaction_type
  $rows = $txModel->getAllTransactions();
  $out = [];

  foreach ($rows as $tx) {
    // only tx where user participates
    if (($tx['sender_id'] ?? null) != $userId && ($tx['recipient_id'] ?? null) != $userId) continue;

    // filter type
    if ($type && strtolower($tx['transaction_type'] ?? '') !== strtolower($type)) continue;

    // filter date
    if ($date) {
      $created = isset($tx['created_at']) ? date('Y-m-d', strtotime($tx['created_at'])) : null;
      if ($created !== $date) continue;
    }

    // enrich with emails
    $tx['sender_email'] = $tx['sender_id'] ? ($userModel->getUserById($tx['sender_id'])['email'] ?? null) : null;
    $tx['recipient_email'] = $tx['recipient_id'] ? ($userModel->getUserById($tx['recipient_id'])['email'] ?? null) : null;

    // make sure keys exist for the frontend
    if (!array_key_exists('transaction_type', $tx)) $tx['transaction_type'] = null;
    if (!array_key_exists('meta_json', $tx)) $tx['meta_json'] = null;

    $out[] = $tx;
  }

  echo json_encode([
    "success" => true,
    "userId" => $userId,
    "transactions" => $out
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(["error" => $e->getMessage()]);
}
