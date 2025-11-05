<?php
require_once __DIR__ . '/../../utils/cors.php';

require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/TransactionsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/MailService.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

// ---- helpers ----
function fail(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'error' => $msg]);
    exit;
}
// ---- /helpers ----

// --- JWT Authentication ---
$headers = function_exists('getallheaders') ? getallheaders() : [];
if (!isset($headers['Authorization'])) fail('No authorization header provided', 401);

[$bearer, $jwt] = array_pad(explode(' ', $headers['Authorization'], 2), 2, '');
if (strcasecmp($bearer, 'Bearer') !== 0 || $jwt === '') fail('Invalid token format', 401);

$jwt_secret = 'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY'; // move to env/config
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded || !isset($decoded['id'])) fail('Invalid or expired token', 401);

$loggedInUserId = (int)$decoded['id'];

// --- Inputs from QR URL ---
if (!isset($_GET['recipient_id'])) fail('No recipient specified');
$recipientId = (int)$_GET['recipient_id'];
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 10.0;
if ($amount <= 0) fail('Amount must be positive');

// security: QR recipient must be the authenticated user
if ($recipientId !== $loggedInUserId) fail('Recipient mismatch', 403);

// --- Models ---
$walletsModel       = new WalletsModel();
$verificationsModel = new VerificationsModel();
$transactionsModel  = new TransactionsModel();
$usersModel         = new UsersModel();

$coin = 'USDT'; // wallet coin you credit

// Ensure account is verified
$verification = $verificationsModel->getVerificationByUserId($loggedInUserId);
if (!$verification || (int)$verification['is_validated'] !== 1) {
    fail('Your account is not verified. You cannot receive payment.', 403);
}

// --- Credit wallet using your WalletsModel API ---
$wallet = $walletsModel->getWalletByUserAndCoin($loggedInUserId, $coin);
if (!$wallet) {
    // create(user_id, coin_symbol, balance) — adds row or upserts (+balance)
    $walletsModel->create($loggedInUserId, $coin, $amount);
    $newBalance = $amount;
} else {
    $current    = isset($wallet['balance']) ? (float)$wallet['balance'] : 0.0;
    $newBalance = $current + $amount;
    // updateBalance(user_id, coin_symbol, new_balance)
    $walletsModel->updateBalance($loggedInUserId, $coin, $newBalance);
}

// Log transaction (keep your existing signature)
if (method_exists($transactionsModel, 'create')) {
    // Adjust if your create() needs coin_symbol or metadata
    $transactionsModel->create(null, $loggedInUserId, 'qr_payment', $amount);
}

// Email confirmation (best-effort)
$user = $usersModel->getUserById($loggedInUserId);
if ($user && !empty($user['email'])) {
    try {
        $mailer  = new MailService();
        $subject = 'Payment Received';
        $body    = '<h1>Payment Received</h1>'
                 . '<p>You have received <strong>' . number_format($amount, 2) . " {$coin}</strong> via QR code.</p>"
                 . '<p>Your new balance is: <strong>' . number_format($newBalance, 2) . " {$coin}</strong></p>";
        $mailer->sendMail($user['email'], $subject, $body);
    } catch (Throwable $e) { /* non-fatal */ }
}

// Success
echo json_encode([
    'status'      => 'success',
    'message'     => "Payment of {$amount} {$coin} has been added to your wallet.",
    'user_id'     => $loggedInUserId,
    'coin'        => $coin,
    'new_balance' => $newBalance
]);
