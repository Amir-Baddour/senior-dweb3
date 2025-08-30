<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Writer\PngWriter;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    /**
     * Send an email using PHPMailer via SMTP.
     *
     * @param string $to      Recipient email
     * @param string $subject Email subject
     * @param string $body    Email body (HTML)
     * @return bool           True on success, false on failure
     */
    public static function sendMail($to, $subject, $body)
    {
        $mail = new PHPMailer(true);

        try {
            // SMTP Configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.mailtrap.io';       // 🔁 Replace with your SMTP host
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your_mailtrap_username'; // 🔁 Replace with your Mailtrap/Gmail username
            $mail->Password   = 'your_mailtrap_password'; // 🔁 Replace with your Mailtrap/Gmail password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // Email Sender and Recipient
            $mail->setFrom('no-reply@yourwallet.com', 'Digital Wallet');
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("❌ Email error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Generate a payment QR code as a Base64 image.
     *
     * @param int $recipientId
     * @param float $amount
     * @return string Base64-encoded PNG
     */
    public static function generatePaymentQrBase64(int $recipientId, float $amount = 10.0): string
    {
        $url = "http://localhost/digital-wallet-platform/wallet-server/user/v1/receive_payment.php?recipient_id={$recipientId}&amount={$amount}";

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->labelText('Scan to Pay')
            ->labelFont(new NotoSans(14))
            ->labelAlignment(new LabelAlignmentCenter())
            ->build();

        return base64_encode($result->getString());
    }

    /**
     * Directly output a payment QR code as an image response.
     *
     * @param int $recipientId
     * @param float $amount
     */
    public static function outputQrImage(int $recipientId, float $amount = 10.0): void
    {
        $url = "http://localhost/digital-wallet-platform/wallet-server/user/v1/receive_payment.php?recipient_id={$recipientId}&amount={$amount}";

        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->build();

        header('Content-Type: ' . $result->getMimeType());
        echo $result->getString();
        exit;
    }
}
