<?php
require_once __DIR__ . '/../../utils/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }


// Use the global $conn from db.php (no getConnection())
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

// -------- Robust Authorization header --------
$authHeader = '';
if (function_exists('getallheaders')) {
    $h = getallheaders();
    foreach ($h as $k => $v) { if (strtolower($k) === 'authorization') { $authHeader = $v; break; } }
}
if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
if (!$authHeader && isset($_SERVER['Authorization']))        $authHeader = $_SERVER['Authorization'];

if (!preg_match('/Bearer\s(\S+)/i', $authHeader, $m)) {
    http_response_code(401); echo json_encode(['success'=>false,'error'=>'no token']); exit;
}
$jwt_secret = 'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY'; // MUST match your login issuer
$payload = verify_jwt($m[1], $jwt_secret);
if (!$payload || empty($payload['id'])) {
    http_response_code(401); echo json_encode(['success'=>false,'error'=>'bad token']); exit;
}
$payerId = (int)$payload['id'];

// -------- Inputs --------
$recipientId = isset($_POST['r']) ? (int)$_POST['r'] : 0;
$amountCents = isset($_POST['a']) ? (int)$_POST['a'] : 0;
$expiry      = isset($_POST['e']) ? (int)$_POST['e'] : 0;
$sig         = $_POST['s'] ?? '';

if ($recipientId<=0 || $amountCents<=0 || $expiry<=0 || !$sig) {
    http_response_code(400); echo json_encode(['success'=>false,'error'=>'invalid params']); exit;
}

// -------- Signature / expiry check --------
$QR_SECRET = 'CHANGE_ME_TO_A_LONG_RANDOM_SECRET_IN_.ENV'; // MUST match utils/generate_qr.php
$payloadStr = "{$recipientId}|{$amountCents}|{$expiry}";
$expected   = hash_hmac('sha256', $payloadStr, $QR_SECRET);

if (!hash_equals($expected, $sig)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'bad signature']); exit; }
if (time() > $expiry)             { http_response_code(400); echo json_encode(['success'=>false,'error'=>'QR expired']); exit; }
if ($payerId === $recipientId)    { http_response_code(400); echo json_encode(['success'=>false,'error'=>'cannot pay yourself']); exit; }

try {
    // Use $conn from db.php
    /** @var PDO $conn */
    $conn->beginTransaction();

    // --- Fetch wallets (create recipient wallet lazily if missing) ---
    $stmt = $conn->prepare("SELECT id, balance FROM wallets WHERE user_id = :uid LIMIT 1");

    // Payer wallet
    $stmt->execute([':uid' => $payerId]);
    $payerW = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payerW) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'payer wallet not found']);
        exit;
    }

    // Recipient wallet
    $stmt->execute([':uid' => $recipientId]);
    $rcptW = $stmt->fetch(PDO::FETCH_ASSOC);

    // Normalize to cents
    $payerBalC = (int) round((float)$payerW['balance'] * 100);
    $rcptBalC  = $rcptW ? (int) round((float)$rcptW['balance'] * 100) : 0;

    if ($payerBalC < $amountCents) {
        $conn->rollBack();
        http_response_code(400);
        echo json_encode(['success'=>false,'error'=>'insufficient funds']);
        exit;
    }

    $newPayer = ($payerBalC - $amountCents) / 100.0;
    $newRcpt  = ($rcptBalC + $amountCents) / 100.0;

    // --- Update payer balance ---
    $up = $conn->prepare("UPDATE wallets SET balance = :bal WHERE id = :id");
    $up->execute([':bal' => $newPayer, ':id' => (int)$payerW['id']]);

    // --- Update or create recipient wallet ---
    if ($rcptW) {
        $up->execute([':bal' => $newRcpt, ':id' => (int)$rcptW['id']]);
        $rcptWalletId = (int)$rcptW['id'];
    } else {
        // If your schema has extra columns, ADD them here.
        $ins = $conn->prepare("INSERT INTO wallets (user_id, balance) VALUES (:uid, :bal)");
        $ins->execute([':uid' => $recipientId, ':bal' => $newRcpt]);
        $rcptWalletId = (int)$conn->lastInsertId();
    }

    // --- Log transaction ---
    // If your schema requires different columns, adjust the INSERT accordingly.
    $insTx = $conn->prepare("
        INSERT INTO transactions (sender_wallet_id, recipient_wallet_id, type, amount, created_at)
        VALUES (:sw, :rw, :type, :amt, NOW())
    ");
    $insTx->execute([
        ':sw'   => (int)$payerW['id'],
        ':rw'   => $rcptWalletId,
        ':type' => 'QR_TRANSFER',
        ':amt'  => $amountCents / 100.0
    ]);

    $conn->commit();

    echo json_encode([
        'success'      => true,
        'message'      => 'Transfer completed',
        'payer_id'     => $payerId,
        'recipient_id' => $recipientId,
        'amount'       => $amountCents / 100.0
    ]);
} catch (Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'server error','detail'=>$e->getMessage()]);
}
