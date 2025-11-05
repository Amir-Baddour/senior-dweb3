<?php
if (!headers_sent()) {
  $allowed_origins = [
    'https://yourwallet0.vercel.app',
    'https://hugh-girls-pumps-neither.trycloudflare.com',
    'http://localhost',
    'http://127.0.0.1'
  ];

  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  if (in_array($origin, $allowed_origins, true)) {
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
