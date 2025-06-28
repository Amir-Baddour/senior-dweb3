<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

// --- Retrieve Parameters ---
// Get recipient ID and amount from the query string (default amount is 10.0)
$recipientId = isset($_GET['recipient_id']) ? (int) $_GET['recipient_id'] : 0;
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 10.0;

// --- Build Payment URL ---
// Construct the URL that will be encoded into the QR code
$data = "http://localhost/digital-wallet-platform/wallet-server/user/v1/receive_payment.php?recipient_id={$recipientId}&amount={$amount}";

// --- Create QR Code ---
// Generate the QR code with high error correction, a defined size and margin
$qrCode = new QrCode(
    data: $data,
    errorCorrectionLevel: ErrorCorrectionLevel::High,
    size: 300,
    margin: 10
);

// Write the QR code as PNG
$writer = new PngWriter();
$result = $writer->write($qrCode);

// --- Output QR Code ---
// Set appropriate header and output the PNG image
header('Content-Type: ' . $result->getMimeType());
echo $result->getString();
exit;