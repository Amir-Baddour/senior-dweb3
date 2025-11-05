<?php
require_once __DIR__ . '/../../utils/cors.php';

function out($a){ echo json_encode($a, JSON_UNESCAPED_SLASHES); exit; }
function fail($m,$c=400,$extra=[]){ if ($c) http_response_code($c); out(array_merge(['error'=>$m],$extra)); }

require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../models/TransactionsModel.php';
require_once __DIR__ . '/../../models/TransactionLimitsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

// PHPMailer (project root/vendor)
$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoload)) require_once $autoload;

// ---- Auth (JWT) ----
$headers = function_exists('getallheaders') ? getallheaders() : [];
$auth = $headers['Authorization'] ?? ($headers['authorization'] ?? '');
[$bearer, $jwt] = array_pad(explode(' ', $auth, 2), 2, null);
if ($bearer !== 'Bearer' || empty($jwt)) fail('Invalid or missing Authorization header', 401);

$JWT_SECRET = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // must match your login
$decoded = verify_jwt($jwt, $JWT_SECRET);
if (!$decoded) fail('Invalid or expired token', 401);
$sender_id = (int)$decoded['id'];

// ---- Input ----
$body = json_decode(file_get_contents("php://input"), true) ?: [];
$recipient_email = isset($body['recipient_email']) ? trim($body['recipient_email']) : '';
$amount = isset($body['amount']) ? (float)$body['amount'] : 0.0;

if ($recipient_email === '' || $amount <= 0) fail('Invalid input.');

// ---- Business ----
try {
  global $conn;

  $usersModel        = new UsersModel();
  $walletsModel      = new WalletsModel();
  $transactionsModel = new TransactionsModel();
  $limitsModel       = new TransactionLimitsModel();

  // Resolve recipient
  $recipient = $usersModel->getUserByEmail($recipient_email);
  if (!$recipient) fail('Recipient not found');
  $recipient_id = (int)$recipient['id'];
  if ($recipient_id === $sender_id) fail('You cannot transfer funds to yourself');

  // Sender wallet & balance
  $sender_wallet = $walletsModel->getWalletByUserAndCoin($sender_id, 'USDT');
  if (!$sender_wallet || (float)$sender_wallet['balance'] < $amount) fail('Insufficient funds');

  // Limits by sender tier
  $sender = $usersModel->getUserById($sender_id);
  $tier   = $sender ? ($sender['tier'] ?? 'regular') : 'regular';
  $limits = $limitsModel->getTransactionLimitByTier($tier);
  if (!$limits) fail('Transaction limits not defined for your tier');

  // Current usage (withdrawal + transfer)
  $dailyStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) total FROM transactions
    WHERE sender_id=:uid AND DATE(created_at)=CURDATE()
      AND transaction_type IN ('withdrawal','transfer')");
  $dailyStmt->execute(['uid'=>$sender_id]);
  $dailyTotal = (float)$dailyStmt->fetch(PDO::FETCH_ASSOC)['total'];

  $weeklyStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) total FROM transactions
    WHERE sender_id=:uid AND YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)
      AND transaction_type IN ('withdrawal','transfer')");
  $weeklyStmt->execute(['uid'=>$sender_id]);
  $weeklyTotal = (float)$weeklyStmt->fetch(PDO::FETCH_ASSOC)['total'];

  $monthlyStmt = $conn->prepare("
    SELECT COALESCE(SUM(amount),0) total FROM transactions
    WHERE sender_id=:uid AND MONTH(created_at)=MONTH(CURDATE())
      AND YEAR(created_at)=YEAR(CURDATE())
      AND transaction_type IN ('withdrawal','transfer')");
  $monthlyStmt->execute(['uid'=>$sender_id]);
  $monthlyTotal = (float)$monthlyStmt->fetch(PDO::FETCH_ASSOC)['total'];

  if (($dailyTotal + $amount)   > (float)$limits['daily_limit'])   fail('Daily transfer/withdrawal limit exceeded');
  if (($weeklyTotal + $amount)  > (float)$limits['weekly_limit'])  fail('Weekly transfer/withdrawal limit exceeded');
  if (($monthlyTotal + $amount) > (float)$limits['monthly_limit']) fail('Monthly transfer/withdrawal limit exceeded');

  // ---- Atomic transfer ----
  $conn->beginTransaction();

  // 1) Debit sender
  $newSenderBalance = (float)$sender_wallet['balance'] - $amount;
  $walletsModel->updateBalance($sender_id, 'USDT', $newSenderBalance);

  // 2) Credit recipient (create wallet if missing)
  $recipient_wallet = $walletsModel->getWalletByUserAndCoin($recipient_id, 'USDT');
  if (!$recipient_wallet) {
    $walletsModel->create($recipient_id, 'USDT', $amount);
  } else {
    $walletsModel->updateBalance($recipient_id, 'USDT', (float)$recipient_wallet['balance'] + $amount);
  }

  // 3) Record transaction
  $transactionsModel->create($sender_id, $recipient_id, 'transfer', $amount);

  $conn->commit();

  // ---- Email notifications (non-fatal) ----
  $senderEmail    = $sender['email'] ?? null;
  $recipientEmail = $recipient_email;

  $fmtAmount = number_format($amount, 2, '.', '');
  $fmtBal    = number_format($newSenderBalance, 2, '.', '');

  $gmailUser = 'amirbaddour675@gmail.com';
  $appPass   = 'lqtkykunvmmuhsvj';

  $emailSentSender    = false;
  $emailSentRecipient = false;
  $emailError         = null;

  if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    // Function to send with STARTTLS (587) and fallback to SMTPS (465)
    $sendMail = function ($to, $subject, $html, $alt='') use ($gmailUser, $appPass) {
      try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP(); $m->Host='smtp.gmail.com'; $m->SMTPAuth=true;
        $m->Username=$gmailUser; $m->Password=$appPass;
        $m->Port=587; $m->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->CharSet='UTF-8'; $m->setFrom($gmailUser,'Digital Wallet'); $m->addAddress($to);
        $m->isHTML(true); $m->Subject=$subject; $m->Body=$html; $m->AltBody=$alt ?: strip_tags($html);
        $m->send(); return true;
      } catch (Throwable $e1) {
        try {
          $m = new PHPMailer\PHPMailer\PHPMailer(true);
          $m->isSMTP(); $m->Host='smtp.gmail.com'; $m->SMTPAuth=true;
          $m->Username=$gmailUser; $m->Password=$appPass;
          $m->Port=465; $m->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
          $m->CharSet='UTF-8'; $m->setFrom($gmailUser,'Digital Wallet'); $m->addAddress($to);
          $m->isHTML(true); $m->Subject=$subject; $m->Body=$html; $m->AltBody=$alt ?: strip_tags($html);
          $m->send(); return true;
        } catch (Throwable $e2) { error_log('transfer email error: '.$e2->getMessage()); return false; }
      }
    };

    if ($senderEmail) {
      $emailSentSender = $sendMail(
        $senderEmail,
        'Transfer Confirmation',
        "<h2>Transfer Successful</h2>
         <p>You sent <strong>{$fmtAmount} USDT</strong> to <strong>{$recipientEmail}</strong>.</p>
         <p>Your new balance is <strong>{$fmtBal} USDT</strong>.</p>",
        "You sent {$fmtAmount} USDT to {$recipientEmail}. New balance: {$fmtBal} USDT."
      );
    }

    $emailSentRecipient = $sendMail(
      $recipientEmail,
      'Funds Received',
      "<h2>You've Received Funds</h2>
       <p>You received <strong>{$fmtAmount} USDT</strong> from <strong>{$senderEmail}</strong>.</p>",
      "You received {$fmtAmount} USDT from {$senderEmail}."
    );
  } else {
    $emailError = 'PHPMailer not installed';
  }

  out([
    'message'              => 'Transfer successful',
    'new_balance'          => (float)$newSenderBalance,
    'emailSentSender'      => (bool)$emailSentSender,
    'emailSentRecipient'   => (bool)$emailSentRecipient,
    'emailError'           => $emailError, // remove in prod if you want
  ]);

} catch (Throwable $e) {
  if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
  fail('Transfer failed: '.$e->getMessage(), 500);
}
