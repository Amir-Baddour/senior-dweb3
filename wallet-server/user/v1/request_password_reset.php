<?php
require_once __DIR__ . '/../../utils/cors.php';

// ---------- Resolve and verify include paths (from user/v1/) ----------
$dbPath     = __DIR__ . '/../../connection/db.php';
$usersPath  = __DIR__ . '/../../models/UsersModel.php';
$resetsPath = __DIR__ . '/../../models/PasswordResetsModel.php';
$autoload   = __DIR__ . '/../../../vendor/autoload.php'; // project-root/vendor

$checks = [
  'db.php'                  => file_exists($dbPath),
  'UsersModel.php'          => file_exists($usersPath),
  'PasswordResetsModel.php' => file_exists($resetsPath),
  'vendor/autoload.php'     => file_exists($autoload),
];

// Self-test (open in browser: .../request_password_reset.php?selftest=1)
if (isset($_GET['selftest'])) {
  echo json_encode([
    'status' => 'ok',
    'paths'  => [
      'db.php'                  => $dbPath,
      'UsersModel.php'          => $usersPath,
      'PasswordResetsModel.php' => $resetsPath,
      'vendor/autoload.php'     => $autoload,
    ],
    'exists'  => $checks,
    'php'     => PHP_VERSION,
    'openssl' => extension_loaded('openssl'),
  ], JSON_UNESCAPED_SLASHES);
  exit;
}

// Fail gracefully if a required include is missing
if (!$checks['db.php'] || !$checks['UsersModel.php'] || !$checks['PasswordResetsModel.php']) {
  echo json_encode([
    'error'  => 'Server misconfiguration: include path not found.',
    'checks' => $checks
  ]);
  exit;
}

require_once $dbPath;
require_once $usersPath;
require_once $resetsPath;

function json_ok(array $data){ echo json_encode($data, JSON_UNESCAPED_SLASHES); exit; }
function json_err(string $msg, int $code=400){ http_response_code($code); json_ok(['error'=>$msg]); }

// ---------- Method & input ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_err('Invalid request method.', 405);
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  // keep it generic; also avoid SMTP "Invalid address"
  json_ok([
    'message'        => 'If this email exists, a reset link has been sent.',
    'dev_reset_link' => null,
    'email_sent'     => false,
    'email_error'    => 'Skipped send: invalid email format'
  ]);
}

try {
  $users  = new UsersModel();
  $resets = new PasswordResetsModel();

  // Find user (do NOT leak existence in response)
  $user = $users->getUserByEmail($email);

  // Generate token; persist only if user exists
  $token     = bin2hex(random_bytes(32));
  $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

  if ($user) {
    if (method_exists($resets, 'deleteByUserId')) {
      $resets->deleteByUserId((int)$user['id']);
    }
    $resets->create((int)$user['id'], $token, $expiresAt);
  }

  // Build reset link to the *hyphen* file on localhost
  $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host       = $_SERVER['HTTP_HOST'] ?: 'localhost';
  $clientBase = '/digital-wallet-plateform/wallet-client';
  $resetLink  = $scheme.'://'.$host.$clientBase.'/reset-password.html?token='.urlencode($token);

  // ---------- Send email via PHPMailer (only if vendor/autoload exists & user is real) ----------
  $emailSent  = false;
  $emailError = null;

  if ($checks['vendor/autoload.php'] && $user) {
    require_once $autoload;

    try {
      $mail = new PHPMailer\PHPMailer\PHPMailer(true);
      $mail->isSMTP();
      $mail->Host       = 'smtp.gmail.com';
      $mail->SMTPAuth   = true;

      // Your final sender + App Password (strip spaces)
      $gmailUser = 'amirbaddour675@gmail.com';
      $appPass   = 'lqtkykunvmmuhsvj'; // original provided: "lqtk ykun vmmu hsvj"
      $mail->Username   = $gmailUser;
      $mail->Password   = $appPass;

      // Try STARTTLS/587 first
      $mail->Port       = 587;
      $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

      $mail->CharSet    = 'UTF-8';
      // Uncomment while debugging to see details in php_error_log:
      // $mail->SMTPDebug   = 2;
      // $mail->Debugoutput = function($s){ error_log('PHPMailer: '.trim($s)); };

      // Gmail requires From to match the authenticated account
      $mail->setFrom($gmailUser, 'Digital wallet');
      $mail->addAddress($email);

      $mail->isHTML(true);
      $mail->Subject = 'Reset your password';
      $mail->Body    = 'Click the link to reset your password:<br><a href="'.$resetLink.'">'.$resetLink.'</a>';
      $mail->AltBody = 'Reset link: '.$resetLink;

      $mail->send();
      $emailSent = true;

    } catch (Throwable $e) {
      // Optional: fallback to SMTPS/465 if STARTTLS/587 failed due to env/firewall
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
        $mail->setFrom($gmailUser, 'Digital wallet');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = 'Reset your password';
        $mail->Body    = 'Click the link to reset your password:<br><a href="'.$resetLink.'">'.$resetLink.'</a>';
        $mail->AltBody = 'Reset link: '.$resetLink;
        $mail->send();
        $emailSent = true;
      } catch (Throwable $e2) {
        $emailError = $e2->getMessage();
        error_log('Mailer Error: '.$emailError);
      }
    }

  } elseif (!$checks['vendor/autoload.php']) {
    $emailError = 'PHPMailer not installed';
  } elseif (!$user) {
    $emailError = 'User not found (send suppressed)';
  }

  // Generic message + dev fields for your console
  json_ok([
    'message'        => 'If this email exists, a reset link has been sent.',
    'dev_reset_link' => $resetLink,
    'email_sent'     => $emailSent,
    'email_error'    => $emailError,
    'checks'         => $checks
  ]);

} catch (Throwable $e) {
  error_log('request_password_reset fatal: '.$e->getMessage());
  json_err('Server error. Please try again later.', 500);
}
