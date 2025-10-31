<?php
// utils/cors.php

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [
  'http://localhost',
  'http://127.0.0.1',
  'https://senior-dweb3-844g.vercel.app',  // your frontend on Vercel
  'https://cagelike-georgina-unrustically.ngrok-free.dev', // your ngrok backend URL
];


// Send EXACTLY ONE Access-Control-Allow-Origin (never combine with "*")
if ($origin && in_array($origin, $allowed_origins, true)) {
  header("Access-Control-Allow-Origin: $origin");
  header("Vary: Origin");
  // Only if you actually need cookies/sessions cross-site:
  // header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

// Preflight short-circuit
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}
