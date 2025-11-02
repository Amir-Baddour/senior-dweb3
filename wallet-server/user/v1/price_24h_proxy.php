<?php
// wallet-server/user/v1/price_24h_proxy.php
// Returns current and ~24h-ago USDT prices for a coin (via CoinGecko)

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
if ($coin === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing coin param']);
  exit;
}

$base = "https://api.coingecko.com/api/v3/coins/{$coin}/market_chart";
$query = http_build_query([
  'vs_currency' => 'usd',     // usd ~ usdt proxy
  'days'        => 2,
  'interval'    => 'hourly'
]);

$url = $base . '?' . $query;

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_USERAGENT => 'Wallet-Assets/1.0',
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
if (!is_array($data) || !isset($data['prices']) || !is_array($data['prices']) || !count($data['prices'])) {
  http_response_code(502);
  echo json_encode(['success' => false, 'error' => 'Invalid upstream data']);
  exit;
}

// $data['prices'] is array of [timestamp_ms, price_usd]
$prices = $data['prices'];
$nowPoint = end($prices);
$now = (float)$nowPoint[1];

$targetTs = (int)(($nowPoint[0] ?? (time()*1000)) - 24*60*60*1000);

// Find the price point closest to targetTs
$closest = $prices[0];
$bestDiff = PHP_INT_MAX;
foreach ($prices as $p) {
  $d = abs(($p[0] ?? 0) - $targetTs);
  if ($d < $bestDiff) {
    $bestDiff = $d;
    $closest = $p;
  }
}
$prev = (float)$closest[1];

echo json_encode([
  'success' => true,
  'id'      => $coin,
  'now'     => $now,
  't24h'    => $prev,
  'change'  => ($prev > 0 ? ($now - $prev) / $prev : null)
]);
