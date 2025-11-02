<?php
// wallet-server/user/v1/price_window_proxy.php
// Return current and N-minutes-ago USDT price for a coin via CoinGecko market_chart/range.

header("Content-Type: application/json");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$allowed = [
  'https://web03-phi.vercel.app',                           // Your Vercel frontend
  'https://faces-wood-energy-catalog.trycloudflare.com',    // Your new tunnel URL
  'http://localhost',
  'http://127.0.0.1'
];

$coin = isset($_GET['coin']) ? trim($_GET['coin']) : '';
$minutes = isset($_GET['minutes']) ? intval($_GET['minutes']) : 1440; // default 24h
if ($coin === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing coin param']);
  exit;
}
if ($minutes < 1) $minutes = 1;
if ($minutes > 1440) $minutes = 1440; // cap to 24h to avoid huge payloads

$nowSec = time();
$fromSec = $nowSec - ($minutes * 60);

// CoinGecko: /market_chart/range has per-minute data within ~1 day.
$url = "https://api.coingecko.com/api/v3/coins/{$coin}/market_chart/range"
     . "?vs_currency=usd&from={$fromSec}&to={$nowSec}";

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_USERAGENT => 'Wallet-Assets/2.0',
  CURLOPT_TIMEOUT => 20
]);

$res = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($err || !$res || $code >= 400) {
  http_response_code(502);
  echo json_encode(['success' => false, 'error' => 'Upstream unavailable']);
  exit;
}

$data = json_decode($res, true);
if (!is_array($data) || !isset($data['prices']) || !is_array($data['prices']) || count($data['prices']) < 2) {
  http_response_code(502);
  echo json_encode(['success' => false, 'error' => 'Invalid upstream data']);
  exit;
}

// Each price point is [timestamp_ms, price_usd]
$prices = $data['prices'];
$first = $prices[0];
$last  = end($prices);

$prev = (float)$first[1];
$now  = (float)$last[1];

echo json_encode([
  'success' => true,
  'id'      => $coin,
  'window_minutes' => $minutes,
  'now'     => $now,
  'prev'    => $prev,
  'change'  => ($prev > 0 ? ($now - $prev) / $prev : null)
]);
