<?php
if (!headers_sent()) {
  $allowed_origins = [
    'https://yourwallet0.vercel.app',
    'https://faces-wood-energy-catalog.trycloudflare.com',
    'http://localhost',
    'http://127.0.0.1'
  ];

  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
  }

  header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
  header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
  header("Content-Type: application/json; charset=UTF-8");

  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
  }
}
