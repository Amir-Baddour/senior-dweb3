<?php
header("Content-Type: application/json");

// Include required files and models
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php'; // Adjust path if needed

// --- JWT Authentication ---
// Retrieve the Authorization header, validate its format, and verify the JWT.
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(['error' => 'No authorization header']);
    exit;
}
list($bearer, $jwt) = explode(' ', $headers['Authorization']);
if ($bearer !== 'Bearer' || !$jwt) {
    echo json_encode(['error' => 'Invalid token format']);
    exit;
}
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Replace with your secure secret key
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}
$userId = $decoded['id'];

// --- Fetch Wallet Balance ---
// Initialize the WalletsModel, retrieve the user's wallet, and output the balance.
try {
    $walletsModel = new WalletsModel();
    $wallet = $walletsModel->getWalletByUserId($userId);
    if (!$wallet) {
        // Return 0 if no wallet record exists for the user.
        echo json_encode(['balance' => 0]);
        exit;
    }
    echo json_encode(['balance' => floatval($wallet['balance'])]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>