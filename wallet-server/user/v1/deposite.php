<?php
header('Content-Type: application/json');

// Include required dependencies and models
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/TransactionsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/MailService.php';
require_once __DIR__ . '/../../utils/verify_jwt.php'; // Adjust path if needed

// --- JWT Authentication ---
// Retrieve the Authorization header and validate the JWT token.
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(['error' => 'No authorization header provided']);
    exit;
}
list($bearer, $jwt) = explode(' ', $headers['Authorization']);
if ($bearer !== 'Bearer' || !$jwt) {
    echo json_encode(['error' => 'Invalid token format']);
    exit;
}
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Replace with your secure key
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}
$userId = $decoded['id'];

// --- Deposit Processing ---
// Initialize models and check if the user's account is verified.
try {
    $walletsModel = new WalletsModel();
    $verificationsModel = new VerificationsModel();
    $transactionsModel = new TransactionsModel();
    $usersModel = new UsersModel();

    $verification = $verificationsModel->getVerificationByUserId($userId);
    if (!$verification || $verification['is_validated'] != 1) {
        echo json_encode(['error' => 'Your account is not verified. You cannot deposit.']);
        exit;
    }

    // Get deposit amount from the JSON input.
    $data = json_decode(file_get_contents("php://input"), true);
    $amount = floatval($data['amount']);
    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid deposit amount']);
        exit;
    }

    // Update or create wallet record for the user.
    $wallet = $walletsModel->getWalletByUserId($userId);
    if (!$wallet) {
        // If no wallet exists, create one with the deposit amount.
        $walletsModel->create($userId, $amount);
        $newBalance = $amount;
    } else {
        // Otherwise, update the existing balance.
        $newBalance = floatval($wallet['balance']) + $amount;
        $walletsModel->update($wallet['id'], $userId, $newBalance);
    }

    // Record the deposit transaction (sender_id is NULL for external deposits).
    $transactionsModel->create(null, $userId, 'deposit', $amount, 'External Deposit');

    // --- Email Confirmation --- 
    // Retrieve the user's email and send a deposit confirmation if available.
    $user = $usersModel->getUserById($userId);
    $userEmail = $user ? $user['email'] : null;
    if ($userEmail) {
        $mailer = new MailService();
        $subject = "Deposit Confirmation";
        $body = "
            <h1>Deposit Successful</h1>
            <p>You have deposited <strong>{$amount} USDT</strong> into your wallet.</p>
            <p>Your new balance is: <strong>{$newBalance} USDT</strong></p>
        ";
        $mailer->sendMail($userEmail, $subject, $body);
    }

    // Return the new balance and success message.
    echo json_encode(['newBalance' => $newBalance, 'message' => 'Deposit successful']);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>