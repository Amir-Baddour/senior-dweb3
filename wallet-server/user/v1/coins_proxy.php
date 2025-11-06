<?php
require_once __DIR__ . '/../../../utils/cors.php';

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
        header('Content-Type: application/json');
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