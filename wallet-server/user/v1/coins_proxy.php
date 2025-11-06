<?php
// ✅ CORS headers FIRST - before ANY output
$allowed_origins = [
    'https://yourwallet0.vercel.app',
    'http://localhost',
    'http://127.0.0.1'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Auto-allow any trycloudflare.com subdomain
$is_cloudflare_tunnel = preg_match('/^https:\/\/[a-z0-9\-]+\.trycloudflare\.com$/', $origin);

if (in_array($origin, $allowed_origins, true) || $is_cloudflare_tunnel) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
} else {
    header("Access-Control-Allow-Origin: *"); // Fallback
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Page param (1..5)
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
if ($page > 5) $page = 5;

// Cache (per page)
$cacheDir = sys_get_temp_dir();
$cacheKey = "cg_coins_p{$page}.json";
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $cacheKey;
$cacheTtl = 45; // seconds

// Serve cache if fresh
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
    $raw = file_get_contents($cacheFile);
    if ($raw !== false) {
        echo $raw;
        exit;
    }
}

$url = "https://api.coingecko.com/api/v3/coins/markets"
     . "?vs_currency=usd&order=market_cap_desc&per_page=250&page={$page}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 12,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: Wallet-App/1.0'
    ]
]);
$res  = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($res === false || $http !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream error', 'status' => $http, 'message' => $err]);
    exit;
}

$data = json_decode($res, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['error' => 'Bad upstream JSON']);
    exit;
}

// Normalize — include price_usd
$out = [];
foreach ($data as $c) {
    if (!isset($c['id'])) continue;
    $out[] = [
        'id'        => $c['id'],
        'symbol'    => strtoupper($c['symbol'] ?? ''),
        'name'      => $c['name'] ?? ($c['symbol'] ?? $c['id']),
        'image'     => $c['image'] ?? '',
        'price_usd' => isset($c['current_price']) ? floatval($c['current_price']) : null
    ];
}

$json = json_encode($out);
@file_put_contents($cacheFile, $json);
echo $json;