<?php
// 🔧 FIX: go up TWO levels to project-root/vendor
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
  error_log("Autoload NOT found: $autoload");
  // don't fatal here; let caller handle
} else {
  require_once $autoload;
}

use PHPMailer\PHPMailer\PHPMailer;

function sendMail(string $toEmail, string $subject, string $html, string $text = ''): array {
  $cfgPath = __DIR__ . '/../config/mail.php';
  if (!file_exists($cfgPath)) {
    error_log("Missing mail config: $cfgPath");
    return ['ok' => false, 'error' => 'mail.php missing'];
  }
  $cfg = require $cfgPath;

  try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['host'];
    $mail->Port       = $cfg['port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['username'];
    $mail->Password   = $cfg['password'];
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPSecure = (isset($cfg['encryption']) && $cfg['encryption'] === 'ssl')
      ? PHPMailer::ENCRYPTION_SMTPS
      : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom($cfg['from_email'], $cfg['from_name']); // one sender for all
    $mail->addAddress($toEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text ?: strip_tags($html);

    // Uncomment while debugging:
    // $mail->SMTPDebug   = 2;
    // $mail->Debugoutput = function($str){ error_log('PHPMailer: '.trim($str)); };

    $mail->send();
    return ['ok' => true];
  } catch (Throwable $e) {
    error_log('Mail error: '.$e->getMessage());
    return ['ok' => false, 'error' => $e->getMessage()];
  }
}
