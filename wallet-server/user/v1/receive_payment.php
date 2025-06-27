<?php
// receive_payment.php - Process incoming QR payment and update user's wallet.
header('Content-Type: application/json');

// Include required files and models.
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/TransactionsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/MailService.php';
require_once __DIR__ . '/../../utils/verify_jwt.php'; // Adjust path as needed

// --- JWT Authentication ---
// Retrieve the Authorization header and verify JWT.
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
$loggedInUserId = $decoded['id'];

// --- Input Validation ---
// Check for recipient_id in URL query if required.
if (!isset($_GET['recipient_id'])) {
    echo json_encode(['error' => 'No recipient specified']);
    exit;
}
$recipientId = $_GET['recipient_id'];

// --- Process Payment ---
// Initialize models.
$walletsModel = new WalletsModel();
$verificationsModel = new VerificationsModel();
$transactionsModel = new TransactionsModel();
$usersModel = new UsersModel();

// Ensure the user is verified.
$verification = $verificationsModel->getVerificationByUserId($loggedInUserId);
if (!$verification || (int)$verification['is_validated'] !== 1) {
    echo json_encode(['error' => 'Your account is not verified. You cannot receive payment.']);
    exit;
}

// Define the payment amount (e.g., 10 USDT).
$amount = 10.0;

// Update or create the wallet record.
$wallet = $walletsModel->getWalletByUserId($loggedInUserId);
if (!$wallet) {
    // Create wallet with initial balance.
    $walletsModel->create($loggedInUserId, $amount);
    $newBalance = $amount;
} else {
    // Update existing wallet balance.
    $newBalance = floatval($wallet['balance']) + $amount;
    $walletsModel->update($wallet['id'], $loggedInUserId, $newBalance);
}

// Log the payment transaction (sender_id is NULL for QR payments).
$transactionsModel->create(null, $loggedInUserId, 'qr_payment', $amount);

// Send an email confirmation if the user's email is available.
$user = $usersModel->getUserById($loggedInUserId);
$userEmail = $user ? $user['email'] : null;
if ($userEmail) {
    $mailer = new MailService();
    $subject = "Payment Received";
    $body = "
        <h1>Payment Received</h1>
        <p>You have received <strong>{$amount} USDT</strong> into your wallet via QR code.</p>
        <p>Your new balance is: <strong>{$newBalance} USDT</strong></p>
    ";
    $mailer->sendMail($userEmail, $subject, $body);
}

// Return success response.
echo json_encode([
    'status'      => 'success',
    'message'     => "Payment of {$amount} credits has been added to your wallet.",
    'user_id'     => $loggedInUserId,
    'new_balance' => $newBalance
]);
?>