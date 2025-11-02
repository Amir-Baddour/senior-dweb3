<?php
// wallet-server/user/v1/exchange_processor.php
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';


$allowed = [
  'https://web03-phi.vercel.app',                           // Your Vercel frontend
  'https://faces-wood-energy-catalog.trycloudflare.com',    // Your new tunnel URL
  'http://localhost',
  'http://127.0.0.1'
];

// --- JWT ---
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'No authorization header']); exit;
}
if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $m)) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Invalid token format']); exit;
}
$jwt = $m[1];
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Invalid or expired token']); exit;
}
$user_id = $decoded['id'];

// ---------- helpers ----------
function base_host_url() {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  return $scheme . '://' . $_SERVER['HTTP_HOST'];
}
function project_root_path() {
  $parts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
  return '/' . ($parts[0] ?? '');
}
function price_in_usdt_via_proxy($coinId) {
  $url = base_host_url() . project_root_path() . '/wallet-server/user/v1/price_proxy.php?coin=' . urlencode($coinId);
  $ctx = stream_context_create([
    'http' => [
      'timeout' => 12,
      'method'  => 'GET',
      'header'  => "User-Agent: Wallet-Exchange/1.0\r\nAccept: application/json\r\n"
    ]
  ]);
  $res = @file_get_contents($url, false, $ctx);
  if ($res === false) return null;
  $data = json_decode($res, true);
  if (json_last_error() !== JSON_ERROR_NONE) return null;
  return isset($data['price_in_usdt']) ? floatval($data['price_in_usdt']) : null;
}

// map common IDs → symbols for wallet rows
function id_to_symbol($idOrSym) {
  static $map = [
    'bitcoin'=>'BTC','ethereum'=>'ETH','binancecoin'=>'BNB','solana'=>'SOL','cardano'=>'ADA',
    'tether'=>'USDT','usd-coin'=>'USDC','ripple'=>'XRP','dogecoin'=>'DOGE','polygon'=>'MATIC',
    'avalanche-2'=>'AVAX','chainlink'=>'LINK','litecoin'=>'LTC','bitcoin-cash'=>'BCH','stellar'=>'XLM'
  ];
  $k = strtolower($idOrSym);
  return strtoupper($map[$k] ?? $idOrSym);
}

try {
  // --- Input (support old & new payloads) ---
  $from_id  = trim($_POST['from_id']  ?? ($_POST['from'] ?? ''));
  $to_id    = trim($_POST['to_id']    ?? ($_POST['to']   ?? ''));
  $from_sym = strtoupper(trim($_POST['from_sym'] ?? id_to_symbol($from_id)));
  $to_sym   = strtoupper(trim($_POST['to_sym']   ?? id_to_symbol($to_id)));
  $amount   = (float)($_POST['amount'] ?? 0);

  if (!$from_id || !$to_id || !$from_sym || !$to_sym || $amount <= 0) {
    throw new Exception('Invalid input: provide coin ids, symbols and a positive amount.');
  }
  if (strcasecmp($from_id, $to_id) === 0) {
    throw new Exception('Cannot exchange the same cryptocurrency.');
  }

  // --- Prices via PHP proxy ---
  $fromPrice = price_in_usdt_via_proxy($from_id);
  $toPrice   = price_in_usdt_via_proxy($to_id);
  if (!$fromPrice || !$toPrice || $fromPrice <= 0 || $toPrice <= 0) {
    throw new Exception('Unable to fetch current market prices. Try again.');
  }

  $walletModel = new WalletsModel();

  // --- Balances by SYMBOL ---
  $fromWallet  = $walletModel->getWalletByUserAndCoin($user_id, $from_sym);
  $toWallet    = $walletModel->getWalletByUserAndCoin($user_id, $to_sym);
  $fromBalance = (float)($fromWallet['balance'] ?? 0);

  if ($fromBalance < $amount) {
    throw new Exception("Insufficient balance. Available: " . number_format($fromBalance, 6) . " $from_sym");
  }

  // --- Conversion ---
  $usdtAmount      = $amount * $fromPrice;
  $convertedAmount = $usdtAmount / $toPrice;
  $exchangeRate    = $fromPrice / $toPrice;
  if ($convertedAmount < 1e-6) {
    throw new Exception('Conversion amount too small.');
  }

  // --- TX ---
  $GLOBALS['conn']->beginTransaction();
  try {
    $newFrom = $fromBalance - $amount;
    if (!$walletModel->updateBalance($user_id, $from_sym, $newFrom)) {
      throw new Exception('Failed updating source wallet.');
    }

    if ($toWallet) {
      $newTo = (float)$toWallet['balance'] + $convertedAmount;
      if (!$walletModel->updateBalance($user_id, $to_sym, $newTo)) {
        throw new Exception('Failed updating target wallet.');
      }
    } else {
      if (!$walletModel->create($user_id, $to_sym, $convertedAmount)) {
        throw new Exception('Failed creating target wallet.');
      }
    }

    // --- record exchange in history (uses meta_json when available) ---
    $hasMeta = false;
    try {
      $chk = $GLOBALS['conn']->query("SHOW COLUMNS FROM transactions LIKE 'meta_json'");
      if ($chk && $chk->rowCount() > 0) { $hasMeta = true; }
    } catch (Throwable $e) { /* ignore */ }

    if ($hasMeta) {
      $sql = "INSERT INTO transactions
                (sender_id, recipient_id, transaction_type, amount, created_at, meta_json)
              VALUES
                (:uid, :uid, 'exchange', :amount, NOW(), :meta)";
      $meta = json_encode([
        'from_sym'    => $from_sym,
        'to_sym'      => $to_sym,
        'from_amount' => (float)$amount,
        'to_amount'   => (float)$convertedAmount,
        'rate'        => (float)$exchangeRate,
        'usdt_value'  => (float)$usdtAmount
      ]);
      $stmt = $GLOBALS['conn']->prepare($sql);
      $stmt->execute([
        ':uid'    => $user_id,
        ':amount' => $amount,   // amount SOLD
        ':meta'   => $meta
      ]);
    } else {
      $sql = "INSERT INTO transactions
                (sender_id, recipient_id, transaction_type, amount, created_at)
              VALUES
                (:uid, :uid, 'exchange', :amount, NOW())";
      $stmt = $GLOBALS['conn']->prepare($sql);
      $stmt->execute([
        ':uid'    => $user_id,
        ':amount' => $amount
      ]);
    }

    $GLOBALS['conn']->commit();
    echo json_encode([
      'success' => true,
      'message' => 'Exchange completed successfully',
      'from' => $from_sym,
      'to' => $to_sym,
      'amount_spent' => round($amount, 8),
      'converted' => round($convertedAmount, 8),
      'rate' => round($exchangeRate, 8),
      'usdt_value' => round($usdtAmount, 2),
      'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
  } catch (Exception $e) {
    $GLOBALS['conn']->rollBack();
    throw $e;
  }

} catch (Exception $e) {
  http_response_code(400);
  error_log("Exchange error (user $user_id): " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (PDOException $e) {
  http_response_code(500);
  error_log("DB error (user $user_id): " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Database error']);
}
