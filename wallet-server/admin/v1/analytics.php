<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../connection/db.php';      // provides $conn (PDO)
require_once __DIR__ . '/../../utils/jwt_admin.php';    // verify_admin_jwt_from_request()

try {
    // ---- AUTH ----
    verify_admin_jwt_from_request(); // will throw if header/token missing or not admin

    // ---- DATES ----
    $from = $_GET['from'] ?? null;
    $to   = $_GET['to']   ?? null;
    if (!$from || !$to) {
        $to = (new DateTime('today'))->format('Y-m-d');
        $from = (new DateTime('today -30 days'))->format('Y-m-d');
    }

    // ---- KPIs ----
    // Total users
    $q = $conn->query("SELECT COUNT(*) FROM users");
    $total_users = (int)$q->fetchColumn();

    // New users in last 7 days
    $q = $conn->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) >= DATE(NOW() - INTERVAL 7 DAY)");
    $new_users_7d = (int)$q->fetchColumn();

    // Tx volume & count in range (use amount; no status filter in your schema)
    $stmt = $conn->prepare("
      SELECT COALESCE(SUM(amount),0) AS volume, COUNT(*) AS cnt
      FROM transactions
      WHERE DATE(created_at) BETWEEN :f AND :t
    ");
    $stmt->execute([':f'=>$from, ':t'=>$to]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['volume'=>0,'cnt'=>0];
    $tx_volume_range = (float)$row['volume'];
    $tx_count_range  = (int)$row['cnt'];

    // ---- CHARTS ----

    // User growth (new users per day)
    $stmt = $conn->prepare("
      SELECT DATE(created_at) AS d, COUNT(*) AS c
      FROM users
      WHERE DATE(created_at) BETWEEN :f AND :t
      GROUP BY DATE(created_at)
      ORDER BY d ASC
    ");
    $stmt->execute([':f'=>$from, ':t'=>$to]);
    $user_growth = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $user_growth[] = ['date'=>$r['d'], 'count'=>(int)$r['c']];
    }

    // Daily transaction volume (sum of amount)
    $stmt = $conn->prepare("
      SELECT DATE(created_at) AS d, COALESCE(SUM(amount),0) AS vol
      FROM transactions
      WHERE DATE(created_at) BETWEEN :f AND :t
      GROUP BY DATE(created_at)
      ORDER BY d ASC
    ");
    $stmt->execute([':f'=>$from, ':t'=>$to]);
    $tx_volume_daily = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tx_volume_daily[] = ['date'=>$r['d'], 'volume'=>(float)$r['vol']];
    }

    // Transactions by type (count)
    $stmt = $conn->prepare("
      SELECT transaction_type AS t, COUNT(*) AS c
      FROM transactions
      WHERE DATE(created_at) BETWEEN :f AND :t
      GROUP BY transaction_type
      ORDER BY c DESC
    ");
    $stmt->execute([':f'=>$from, ':t'=>$to]);
    $tx_by_type = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tx_by_type[] = ['type'=>$r['t'] ?? 'unknown', 'count'=>(int)$r['c']];
    }

    // “Top coins” compatibility: we don’t have coin symbols.
    // Reuse transaction_type as the category and compute volume by type so the existing chart still works.
    $stmt = $conn->prepare("
      SELECT transaction_type AS cat, COALESCE(SUM(amount),0) AS vol
      FROM transactions
      WHERE DATE(created_at) BETWEEN :f AND :t
      GROUP BY transaction_type
      ORDER BY vol DESC
      LIMIT 8
    ");
    $stmt->execute([':f'=>$from, ':t'=>$to]);
    $top_coins = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $top_coins[] = [
          // Keep key name 'coin_symbol' so frontend code doesn't change
          'coin_symbol' => $r['cat'] ?? 'N/A',
          'volume'      => (float)$r['vol']
        ];
    }

    echo json_encode([
        'total_users'      => $total_users,
        'new_users_7d'     => $new_users_7d,
        'tx_volume_range'  => $tx_volume_range,
        'tx_count_range'   => $tx_count_range,
        'user_growth'      => $user_growth,
        'tx_volume_daily'  => $tx_volume_daily,
        'tx_by_type'       => $tx_by_type,
        'top_coins'        => $top_coins, // actually “top types by volume”
        'range'            => ['from'=>$from,'to'=>$to],
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()]);
}
