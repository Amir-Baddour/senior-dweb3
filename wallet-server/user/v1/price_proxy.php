<?php
ob_start();
require_once __DIR__ . '/../../utils/cors.php';
$coin = isset($_GET['coin']) ? strtolower(trim($_GET['coin'])) : '';
if ($coin === '' || !preg_match('/^[a-z0-9\-]+$/', $coin)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coin parameter']);
    exit;
}

// Normalize common aliases → CoinGecko IDs
$aliases = [
  'btc'=>'bitcoin','eth'=>'ethereum','usdt'=>'tether','usdc'=>'usd-coin',
  'bnb'=>'binancecoin','xrp'=>'ripple','ada'=>'cardano','doge'=>'dogecoin',
  'matic'=>'polygon','sol'=>'solana','avax'=>'avalanche-2','link'=>'chainlink',
  'ltc'=>'litecoin','bch'=>'bitcoin-cash','xlm'=>'stellar'
];
$coin = $aliases[$coin] ?? $coin;

$cacheDir  = sys_get_temp_dir();
$priceKey  = "cg_price_{$coin}.json";
$priceFile = $cacheDir . DIRECTORY_SEPARATOR . $priceKey;
$freshTtl  = 60;   // fresh price cache
$staleTtl  = 600;  // stale allowance for emergencies

function read_cache($file, $maxAge) {
    if (file_exists($file) && (time() - filemtime($file) < $maxAge)) {
        $raw = file_get_contents($file);
        if ($raw !== false) return $raw;
    }
    return null;
}
function write_cache($file, $json) { @file_put_contents($file, $json); }

// 0) Serve fresh dedicated price cache if present
if ($fresh = read_cache($priceFile, $freshTtl)) { echo $fresh; exit; }

// 1) Try to read from coins page caches (p1..p5) produced by coins_proxy.php
for ($p = 1; $p <= 5; $p++) {
    $pageFile = $cacheDir . DIRECTORY_SEPARATOR . "cg_coins_p{$p}.json";
    if (!file_exists($pageFile)) continue;
    // Accept slightly older page cache (since it’s still very recent market data)
    if (time() - filemtime($pageFile) > 120) continue;

    $raw = file_get_contents($pageFile);
    if ($raw === false) continue;
    $arr = json_decode($raw, true);
    if (!is_array($arr)) continue;

    foreach ($arr as $row) {
        if (!isset($row['id'])) continue;
        if (strtolower($row['id']) === $coin) {
            $p = isset($row['price_usd']) ? floatval($row['price_usd']) : null;
            if (is_numeric($p) && $p > 0) {
                $out = json_encode(['price_in_usdt' => $p]);
                write_cache($priceFile, $out);
                echo $out;
                exit;
            }
        }
    }
}

// 2) If not found in caches, hit CoinGecko with fallbacks
function cg_get($url, $timeout = 12) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Wallet-App/1.0'
        ]
    ]);
    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$http, $body, $err];
}

$price = null;

// Try 1: simple/price
$url1 = "https://api.coingecko.com/api/v3/simple/price?ids=" . rawurlencode($coin) . "&vs_currencies=usd";
list($http1, $body1, $err1) = cg_get($url1);
if ($http1 === 200) {
    $j = json_decode($body1, true);
    if (isset($j[$coin]['usd']) && is_numeric($j[$coin]['usd'])) $price = floatval($j[$coin]['usd']);
}

// Try 2: coins/markets
if ($price === null) {
    $url2 = "https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=" . rawurlencode($coin) . "&per_page=1&page=1";
    list($http2, $body2, $err2) = cg_get($url2);
    if ($http2 === 200) {
        $j2 = json_decode($body2, true);
        if (is_array($j2) && isset($j2[0]['current_price']) && is_numeric($j2[0]['current_price'])) {
            $price = floatval($j2[0]['current_price']);
        }
    }
}

// Try 3: coins/{id}
if ($price === null) {
    $url3 = "https://api.coingecko.com/api/v3/coins/" . rawurlencode($coin);
    list($http3, $body3, $err3) = cg_get($url3);
    if ($http3 === 200) {
        $j3 = json_decode($body3, true);
        if (isset($j3['market_data']['current_price']['usd']) && is_numeric($j3['market_data']['current_price']['usd'])) {
            $price = floatval($j3['market_data']['current_price']['usd']);
        }
    }
}

// Respond
if ($price !== null) {
    $out = json_encode(['price_in_usdt' => $price]);
    write_cache($priceFile, $out);
    echo $out;
    exit;
}

// Last resort: stale dedicated price cache (<= 10 min)
if ($stale = read_cache($priceFile, $staleTtl)) {
    echo $stale;
    exit;
}

http_response_code(502);
echo json_encode([
    'error' => 'Upstream unavailable',
    'note'  => 'No cached price; CoinGecko may be rate-limiting (429) or unreachable.'
]);
