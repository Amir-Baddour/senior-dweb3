<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");

// Include required dependencies and models
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/TransactionsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/MailService.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

// --- JWT Authentication ---
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

    $data = json_decode(file_get_contents("php://input"), true);
    $amount = isset($data['amount']) ? floatval($data['amount']) : 0;
    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid deposit amount']);
        exit;
    }

    $wallet = $walletsModel->getWalletByUserAndCoin($userId, 'USDT');
    if (!$wallet) {
        $walletsModel->create($userId, 'USDT', $amount);
        $newBalance = $amount;
    } else {
        $newBalance = floatval($wallet['balance']) + $amount;
        $walletsModel->updateBalance($userId, 'USDT', $newBalance);
    }

    $transactionsModel->create(null, $userId, 'deposit', $amount, 'External Deposit');

    // --- Email Confirmation ---
    $user = $usersModel->getUserById($userId);
    $userEmail = $user ? $user['email'] : null;
    $emailSent = false;

    if ($userEmail) {
        $mailer = new MailService();
        $subject = "Deposit Confirmation";
        $body = "
            <h1>Deposit Successful</h1>
            <p>You have deposited <strong>{$amount} USDT</strong> into your wallet.</p>
            <p>Your new balance is: <strong>{$newBalance} USDT</strong></p>
        ";

        error_log("🟢 Trying to send email to: $userEmail");
        $emailSent = $mailer->sendMail($userEmail, $subject, $body);

        if ($emailSent) {
            error_log("✅ Email sent to $userEmail");
        } else {
            error_log("❌ Email failed to send to $userEmail");
        }
    }

    echo json_encode([
        'newBalance' => $newBalance,
        'message' => 'Deposit successful',
        'emailSent' => $emailSent
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
