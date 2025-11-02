<?php
// wallet-server/user/v1/withdraw.php  (DEV-FRIENDLY)


$DEBUG = true; // set to false for production

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
$allowed = [
  'https://web03-phi.vercel.app',                           // Your Vercel frontend
  'https://faces-wood-energy-catalog.trycloudflare.com',    // Your new tunnel URL
  'http://localhost',
  'http://127.0.0.1'
];
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('display_errors', 0);

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_SLASHES); exit; }
function derr($msg,$code=400,$extra=[]){ if ($code) http_response_code($code); out(array_merge(['error'=>$msg],$extra)); }

// ---------- Includes ----------
$phase = 'includes';
try {
  require_once __DIR__ . '/../../utils/cors.php';
  require_once __DIR__ . '/../../connection/db.php';
  require_once __DIR__ . '/../../models/WalletsModel.php';
  require_once __DIR__ . '/../../models/VerificationsModel.php';
  require_once __DIR__ . '/../../models/TransactionsModel.php';
  require_once __DIR__ . '/../../models/UsersModel.php';
  require_once __DIR__ . '/../../models/TransactionLimitsModel.php';
  require_once __DIR__ . '/../../utils/verify_jwt.php';

  // PHPMailer autoload (project root/vendor) — from user/v1 go up 3 levels
  $autoload = __DIR__ . '/../../../vendor/autoload.php';
  if (file_exists($autoload)) {
    require_once $autoload;
  } else {
    error_log("PHPMailer autoload not found at: $autoload");
  }
} catch (Throwable $e) {
  derr('Server error. Please try again later.', 500, $DEBUG ? ['dev_phase'=>$phase,'dev_error'=>$e->getMessage()] : []);
}

// ---------- Auth ----------
$phase = 'auth';
try {
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  $auth = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
  [$bearer, $jwt] = array_pad(explode(' ', $auth, 2), 2, null);
  if ($bearer !== 'Bearer' || empty($jwt)) derr('Invalid or missing Authorization header', 401);

  // MUST match the secret used when generating your JWT at login/google-oauth
  $jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
  $decoded = verify_jwt($jwt, $jwt_secret);
  if (!$decoded) derr('Invalid or expired token', 401);
  $userId = (int)$decoded['id'];

} catch (Throwable $e) {
  derr('Server error. Please try again later.', 500, $DEBUG ? ['dev_phase'=>$phase,'dev_error'=>$e->getMessage()] : []);
}

// ---------- Input ----------
$phase = 'input';
try {
  $data   = json_decode(file_get_contents("php://input"), true) ?: [];
  $amount = isset($data['amount']) ? (float)$data['amount'] : 0.0;
  if ($amount <= 0) derr('Invalid withdrawal amount');
} catch (Throwable $e) {
  derr('Server error. Please try again later.', 500, $DEBUG ? ['dev_phase'=>$phase,'dev_error'=>$e->getMessage()] : []);
}

// ---------- Business logic ----------
$phase = 'withdraw';
try {
  $walletsModel       = new WalletsModel();
  $verificationsModel = new VerificationsModel();
  $transactionsModel  = new TransactionsModel();
  $usersModel         = new UsersModel();
  $transactionLimitsModel = new TransactionLimitsModel();

  // Ensure the user is verified
  $verification = $verificationsModel->getVerificationByUserId($userId);
  if (!$verification || (int)$verification['is_validated'] !== 1) {
    derr('Your account is not verified. You cannot withdraw.');
  }

  // Get user, tier and limits
  $user = $usersModel->getUserById($userId);
  $tier = $user['tier'] ?? 'regular';
  $limits = $transactionLimitsModel->getTransactionLimitByTier($tier);
  if (!$limits) derr('Transaction limits not defined for your tier');

  // Calculate current usage for daily, weekly, and monthly (using PDO $conn)
  global $conn;
  $dailyStmt = $conn->prepare("
      SELECT COALESCE(SUM(amount), 0) AS total 
      FROM transactions 
      WHERE sender_id = :user_id 
        AND DATE(created_at) = CURDATE() 
        AND transaction_type IN ('withdrawal', 'transfer')
  ");
  $dailyStmt->execute(['user_id' => $userId]);
  $dailyTotal = (float)$dailyStmt->fetch(PDO::FETCH_ASSOC)['total'];

  $weeklyStmt = $conn->prepare("
      SELECT COALESCE(SUM(amount), 0) AS total 
      FROM transactions 
      WHERE sender_id = :user_id 
        AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) 
        AND transaction_type IN ('withdrawal', 'transfer')
  ");
  $weeklyStmt->execute(['user_id' => $userId]);
  $weeklyTotal = (float)$weeklyStmt->fetch(PDO::FETCH_ASSOC)['total'];

  $monthlyStmt = $conn->prepare("
      SELECT COALESCE(SUM(amount), 0) AS total 
      FROM transactions 
      WHERE sender_id = :user_id 
        AND MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE()) 
        AND transaction_type IN ('withdrawal', 'transfer')
  ");
  $monthlyStmt->execute(['user_id' => $userId]);
  $monthlyTotal = (float)$monthlyStmt->fetch(PDO::FETCH_ASSOC)['total'];

  // Check limits with new withdrawal
  if (($dailyTotal + $amount)   > (float)$limits['daily_limit'])   derr('Daily withdrawal limit exceeded');
  if (($weeklyTotal + $amount)  > (float)$limits['weekly_limit'])  derr('Weekly withdrawal limit exceeded');
  if (($monthlyTotal + $amount) > (float)$limits['monthly_limit']) derr('Monthly withdrawal limit exceeded');

  // Verify wallet balance and update
  $wallet = $walletsModel->getWalletByUserAndCoin($userId, 'USDT');
  if (!$wallet) derr('Wallet not found');
  if ((float)$wallet['balance'] < $amount) derr('Insufficient funds');

  $newBalance = (float)$wallet['balance'] - $amount;
  $walletsModel->updateBalance($userId, 'USDT', $newBalance);

  // Record the withdrawal transaction (keep your existing signature)
  $transactionsModel->create($userId, null, 'withdrawal', $amount);

} catch (Throwable $e) {
  derr('Server error. Please try again later.', 500, $DEBUG ? ['dev_phase'=>$phase,'dev_error'=>$e->getMessage()] : []);
}

// ---------- Email (non-fatal) ----------
$phase = 'email';
$emailSent  = false;
$emailError = null;
try {
  $userEmail = $user['email'] ?? null;
  if ($userEmail && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    $fmtAmount = number_format($amount, 2, '.', '');
    $fmtBal    = number_format($newBalance, 2, '.', '');
    $subject   = "Withdrawal Confirmation";
    $htmlBody  = "
      <h2>Withdrawal Successful</h2>
      <p>You have withdrawn <strong>{$fmtAmount} USDT</strong> from your wallet.</p>
      <p>Your new balance is <strong>{$fmtBal} USDT</strong>.</p>
      <hr>
      <p>If you did not make this transaction, please contact support immediately.</p>
    ";
    $altBody   = "Withdrawal {$fmtAmount} USDT. New balance: {$fmtBal} USDT.";

    // Your Gmail + App Password (no spaces)
    $gmailUser = 'amirbaddour675@gmail.com';
    $appPass   = 'lqtkykunvmmuhsvj';

    // Try STARTTLS/587, then fallback to SMTPS/465
    try {
      $mail = new PHPMailer\PHPMailer\PHPMailer(true);
      $mail->isSMTP();
      $mail->Host       = 'smtp.gmail.com';
      $mail->SMTPAuth   = true;
      $mail->Username   = $gmailUser;
      $mail->Password   = $appPass;
      $mail->Port       = 587;
      $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
      $mail->CharSet    = 'UTF-8';
      $mail->setFrom($gmailUser, 'Digital Wallet');
      $mail->addAddress($userEmail);
      $mail->isHTML(true);
      $mail->Subject = $subject;
      $mail->Body    = $htmlBody;
      $mail->AltBody = $altBody;
      // Debug if needed:
      // $mail->SMTPDebug   = 2;
      // $mail->Debugoutput = function($s){ error_log('PHPMailer: '.trim($s)); };
      $mail->send();
      $emailSent = true;
    } catch (Throwable $e1) {
      try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $gmailUser;
        $mail->Password   = $appPass;
        $mail->Port       = 465;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($gmailUser, 'Digital Wallet');
        $mail->addAddress($userEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody;
        $mail->send();
        $emailSent = true;
      } catch (Throwable $e2) {
        $emailError = $e2->getMessage();
        error_log('withdraw email error: ' . $emailError);
      }
    }
  } else {
    if (!$userEmail) $emailError = 'Missing recipient email';
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) $emailError = 'PHPMailer not installed';
  }
} catch (Throwable $e) {
  $emailError = $e->getMessage();
  error_log('withdraw email error: ' . $emailError);
}

// ---------- Success ----------
out([
  'newBalance' => (float)$newBalance,
  'message'    => 'Withdrawal successful',
  'emailSent'  => (bool)$emailSent,
  'emailError' => $emailError,     // optional (remove for prod)
  'dev_phase'  => $DEBUG ? $phase : null
]);
