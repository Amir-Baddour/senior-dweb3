<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Writer\PngWriter;

class MailService
{
    /**
     * Generate a payment QR code as Base64 PNG (safe for web/email usage).
     *
     * @param int $recipientId
     * @param float $amount
     * @return string base64 PNG image
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
     * Directly serve a QR code image (e.g. from a standalone PHP route like qr.php)
     * Note: DO NOT use this inside API responses that return JSON!
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
