<?php
if (!headers_sent()) {
  $allowed_origins = [
    'https://yourwallet0.vercel.app',
    'http://localhost',
    'http://127.0.0.1'
  ];

  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  
  // ✅ Auto-allow any trycloudflare.com subdomain
  $is_cloudflare_tunnel = str_ends_with($origin, '.trycloudflare.com');
  
  if (in_array($origin, $allowed_origins, true) || $is_cloudflare_tunnel) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
  }

  header("Access-Control-Allow-Credentials: true");
  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
  header("Content-Type: application/json; charset=UTF-8");

  // ⚡ Must stop OPTIONS requests before reaching logic
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
  }
}
?>