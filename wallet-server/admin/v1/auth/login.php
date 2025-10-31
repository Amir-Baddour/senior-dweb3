<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../utils/jwt.php'; // <-- IMPORTANT: bring in jwt_sign/jwt_verify

// Dev: log errors to help debug (turn off in prod)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  echo json_encode(["status"=>"error","message"=>"Invalid method"]); exit;
}

// Read POSTed credentials (form-encoded or JSON)
$email    = isset($_POST['email']) ? trim($_POST['email']) : null;
$password = $_POST['password'] ?? null;

if (!$email || !$password) {
  echo json_encode(["status"=>"error","message"=>"Email and password are required"]); exit;
}

try {
  $usersModel = new UsersModel();

  // Find user by email (adjust if you have a dedicated method)
  $user = null;
  foreach ($usersModel->getAllUsers() as $u) {
    if ($u['email'] === $email) { $user = $u; break; }
  }

  // Only allow admins (role == 1) to login here
  if (!$user || (string)$user['role'] !== '1') {
    echo json_encode(["status"=>"error","message"=>"Access denied. Only admins can log in."]); exit;
  }

  if (!password_verify($password, $user['password'])) {
    echo json_encode(["status"=>"error","message"=>"Invalid email or password"]); exit;
  }

  // Build JWT payload (role "1" is fine; verifier accepts it)
  $payload = [
    "id"    => $user["id"],
    "email" => $user["email"],
    "role"  => $user["role"], // "1"
  ];

  // SIGN WITH THE SAME SECRET/ALG AS utils/jwt.php
  $token = jwt_sign($payload, 3600); // 1h

  echo json_encode([
    "status" => "success",
    "message"=> "Login successful",
    "token"  => $token
  ]);
  exit;

} catch (Throwable $e) {
  echo json_encode(["status"=>"error","message"=>"Server error: ".$e->getMessage()]); exit;
}
