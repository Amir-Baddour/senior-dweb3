<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;

// --- Retrieve Parameters ---
$recipientId = isset($_GET['recipient_id']) ? (int) $_GET['recipient_id'] : 0;
$amount      = isset($_GET['amount']) ? (float) $_GET['amount'] : 10.0;

// --- Build Payment URL ---
// NOTE: your project folder elsewhere is spelled "digital-wallet-plateform".
// If that is the real folder name, keep it consistent here to avoid 404s.
$data = "http://localhost/digital-wallet-plateform/wallet-server/user/v1/receive_payment.php"
      . "?recipient_id={$recipientId}&amount={$amount}";

// --- Create QR Code ---
$result = Builder::create()
    ->data($data)
    ->encoding(new Encoding('UTF-8'))
    ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
    ->size(300)
    ->margin(10)
    ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
    ->build();

// --- Output QR Code ---
header('Content-Type: ' . $result->getMimeType());
echo $result->getString();
exit;
