<?php
// test-brevo.php - Test Brevo SMTP
require_once __DIR__ . '/../../../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
$mail->SMTPDebug = 2; // Show detailed debug info

try {
    $mail->isSMTP();
    $mail->Host = 'smtp-relay.brevo.com';
    $mail->SMTPAuth = true;
    $mail->Username = '9f9f14001@smtp-brevo.com';
    $mail->Password = 'RkWndDBs7phYKfG2';
    $mail->Port = 587;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    
    $mail->setFrom('9f9f14001@smtp-brevo.com', 'Digital Wallet Test');
    $mail->addAddress('amirbaddour675@gmail.com'); // Your email
    $mail->Subject = 'Brevo Test - ' . date('H:i:s');
    $mail->Body = '<h2>Test Email from Brevo</h2><p>If you receive this, Brevo SMTP is working!</p>';
    $mail->isHTML(true);
    
    $mail->send();
    echo "\n✅ SUCCESS! Email sent via Brevo.\n";
    
} catch (Exception $e) {
    echo "\n❌ FAILED: " . $mail->ErrorInfo . "\n";
    echo "Exception: " . $e->getMessage() . "\n";
}