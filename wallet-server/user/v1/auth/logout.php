<?php
header("Content-Type: application/json");
$allowed = [
  'https://web03-phi.vercel.app',                           // Your Vercel frontend
  'https://faces-wood-energy-catalog.trycloudflare.com',    // Your new tunnel URL
  'http://localhost',
  'http://127.0.0.1'
];
$response = [
    "status" => "success",
    "message" => "Logout successful. Please remove your token client-side."
];

echo json_encode($response);