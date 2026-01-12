<?php
// wallet-server/user/v1/deposit.php (Using Brevo SMTP - 300 emails/day FREE)
ob_start();
require_once __DIR__ . '/../../utils/cors.php';

$DEBUG = true;

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

  // ✅ Need autoload for PHPMailer
  $autoload = __DIR__ . '/../../../vendor/autoload.php';
  if (file_exists($autoload)) require_once $autoload;
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

// ---------- Email via Brevo SMTP (non-fatal if it fails) ----------
$phase = 'email';
$emailSent  = false;
$emailError = null;
try {
  $user      = $usersModel->getUserById($userId);
  $userEmail = $user['email'] ?? null;

  if ($userEmail && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
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
    $altBody   = "Deposit {$fmtAmount} USDT. New balance: {$fmtBal} USDT.";

    // ✅ BREVO SMTP CONFIGURATION (300 emails/day FREE)
    $brevoLogin    = '9f9f14001@smtp-brevo.com';    // From your screenshot
    $brevoPassword = 'RkWndDBs7phYKfG2';             // From your screenshot

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp-relay.brevo.com';      // Brevo SMTP server
    $mail->SMTPAuth   = true;
    $mail->Username   = $brevoLogin;                 // Your Brevo login
    $mail->Password   = $brevoPassword;              // Your Brevo password
    $mail->Port       = 587;
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet    = 'UTF-8';
    
    $mail->setFrom('9f9f14001@smtp-brevo.com', 'Digital Wallet');
    $mail->addAddress($userEmail);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $altBody;
    
    $mail->send();
    $emailSent = true;
  } else {
    if (!$userEmail) $emailError = 'Missing recipient email';
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) $emailError = 'PHPMailer not installed';
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
  'dev_phase'  => $DEBUG ? $phase : null
]);