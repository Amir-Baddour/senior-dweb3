<?php
// wallet-server/user/v1/deposit.php  (DEV FRIENDLY)
ob_start();
require_once __DIR__ . '/../../utils/cors.php';

$DEBUG = true; // ← set to false for production

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}


error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', 0);

function out($arr)
{
  echo json_encode($arr, JSON_UNESCAPED_SLASHES);
  exit;
}
function derr($msg, $code = 400, $extra = [])
{
  if ($code) http_response_code($code);
  out(array_merge(['error' => $msg], $extra));
}

// ---------- Includes ----------
$phase = 'includes';
try {
  require_once __DIR__ . '/../../utils/cors.php';
  require_once __DIR__ . '/../../connection/db.php';
  require_once __DIR__ . '/../../models/WalletsModel.php';
  require_once __DIR__ . '/../../models/VerificationsModel.php';
  require_once __DIR__ . '/../../models/TransactionsModel.php';
  require_once __DIR__ . '/../../models/UsersModel.php';
  require_once __DIR__ . '/../../utils/verify_jwt.php';
} catch (Throwable $e) {
  derr('Server error. Please try again later.', 500, $DEBUG ? ['dev_phase' => $phase, 'dev_error' => $e->getMessage()] : []);
}

// ---------- Auth ----------
$phase = 'auth';
try {
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  $auth = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
  [$bearer, $jwt] = array_pad(explode(' ', $auth, 2), 2, null);
  if ($bearer !== 'Bearer' || empty($jwt)) derr('Invalid or missing Authorization header', 401);

  // MUST match the secret used when you minted the JWT at login/google-oauth
  $jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
  $decoded = verify_jwt($jwt, $jwt_secret);
  if (!$decoded) derr('Invalid or expired token', 401);
  $userId = (int)$decoded['id'];
} catch (Throwable $e) {
  derr('Server error. Please try again later.', 500, $DEBUG ? ['dev_phase' => $phase, 'dev_error' => $e->getMessage()] : []);
}

// ---------- Input ----------
$phase = 'input';
try {
  $payload = json_decode(file_get_contents('php://input'), true) ?: [];
  $amount  = isset($payload['amount']) ? (float)$payload['amount'] : 0.0;
  if ($amount <= 0) derr('Invalid deposit amount');
} catch (Throwable $e) {
  derr('Server error. Please try again later.', 500, $DEBUG ? ['dev_phase' => $phase, 'dev_error' => $e->getMessage()] : []);
}

// ---------- Business logic ----------
$phase = 'deposit';
try {
  $walletsModel       = new WalletsModel();
  $verificationsModel = new VerificationsModel();
  $transactionsModel  = new TransactionsModel();
  $usersModel         = new UsersModel();

  $verification = $verificationsModel->getVerificationByUserId($userId);
  if (!$verification || (int)$verification['is_validated'] !== 1) {
    derr('Your account is not verified. You cannot deposit.');
  }

  $wallet = $walletsModel->getWalletByUserAndCoin($userId, 'USDT');
  if (!$wallet) {
    $walletsModel->create($userId, 'USDT', $amount);
    $newBalance = $amount;
  } else {
    $newBalance = (float)$wallet['balance'] + $amount;
    $walletsModel->updateBalance($userId, 'USDT', $newBalance);
  }

  $transactionsModel->create(null, $userId, 'deposit', $amount);
} catch (Throwable $e) {
  derr('Server error. Please try again later.', 500, $DEBUG ? ['dev_phase' => $phase, 'dev_error' => $e->getMessage()] : []);
}

// ---------- Email (non-fatal if it fails) ----------
$phase = 'email';
$emailSent  = false;
$emailError = null;
try {
  $user      = $usersModel->getUserById($userId);
  $userEmail = $user['email'] ?? null;

  if ($userEmail) {
    // ✅ RESEND API CONFIGURATION
    $resendApiKey = 're_QzowrxAp_DzoVNDUCdMhL5jovg7fdyCPw';
    
    $fmtAmount = number_format($amount, 2, '.', '');
    $fmtBal    = number_format($newBalance, 2, '.', '');
    $subject   = "Deposit Confirmation";
    $htmlBody  = "
      <h2>Deposit Successful</h2>
      <p>You have deposited <strong>{$fmtAmount} USDT</strong> into your wallet.</p>
      <p>Your new balance is <strong>{$fmtBal} USDT</strong>.</p>
      <hr>
      <p>If you did not make this transaction, please contact support immediately.</p>
    ";

    // Send email via Resend API
    $data = [
      'from' => 'Digital Wallet <onboarding@resend.dev>', // Change to your domain later
      'to' => [$userEmail],
      'subject' => $subject,
      'html' => $htmlBody
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => 'https://api.resend.com/emails',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $resendApiKey,
        'Content-Type: application/json'
      ],
      CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode === 200) {
      $emailSent = true;
    } else {
      $emailError = 'Failed to send email. HTTP ' . $httpCode . ': ' . $response;
    }
  } else {
    $emailError = 'Missing recipient email';
  }
} catch (Throwable $e) {
  $emailError = $e->getMessage();
  error_log('deposit email error: ' . $emailError);
}

// ---------- Success ----------
out([
  'newBalance' => (float)$newBalance,
  'message'    => 'Deposit successful',
  'emailSent'  => (bool)$emailSent,
  'emailError' => $emailError,
  // Dev debug (remove when $DEBUG=false)
  'dev_phase'  => $DEBUG ? $phase : null
]);