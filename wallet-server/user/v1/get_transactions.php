<?php
header("Content-Type: application/json");

// Include dependencies
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/TransactionsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

// --- JWT Authentication ---
// Retrieve and verify the JWT from the Authorization header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(["error" => "No authorization header provided."]);
    exit;
}
list($bearer, $jwt) = explode(' ', $headers['Authorization']);
if ($bearer !== 'Bearer' || !$jwt) {
    echo json_encode(["error" => "Invalid token format."]);
    exit;
}
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(["error" => "Invalid or expired token."]);
    exit;
}
$userId = $decoded['id'];

// --- Retrieve Filter Parameters ---
// Optionally filter transactions by date and type
$date = isset($_GET['date']) ? $_GET['date'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;

try {
    // Initialize models
    $transactionsModel = new TransactionsModel();
    $usersModel = new UsersModel();

    // Fetch all transactions and filter for those involving the logged-in user
    $transactions = $transactionsModel->getAllTransactions();
    $filteredTransactions = [];

    foreach ($transactions as $transaction) {
        // Only include transactions where the user is sender or recipient
        if ($transaction['sender_id'] != $userId && $transaction['recipient_id'] != $userId) {
            continue;
        }
        // Filter by transaction type if provided
        if ($type && $transaction['transaction_type'] !== $type) {
            continue;
        }
        // Filter by date if provided
        if ($date && date('Y-m-d', strtotime($transaction['created_at'])) !== $date) {
            continue;
        }
        // Append sender and recipient emails to the transaction record
        $transaction['sender_email'] = $transaction['sender_id'] ? $usersModel->getUserById($transaction['sender_id'])['email'] : null;
        $transaction['recipient_email'] = $transaction['recipient_id'] ? $usersModel->getUserById($transaction['recipient_id'])['email'] : null;
        $filteredTransactions[] = $transaction;
    }

    echo json_encode(["transactions" => $filteredTransactions, "userId" => $userId]);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>