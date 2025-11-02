<?php
// Set CORS and JSON response headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$allowed = [
  'https://web03-phi.vercel.app',                           // Your Vercel frontend
  'https://faces-wood-energy-catalog.trycloudflare.com',    // Your new tunnel URL
  'http://localhost',
  'http://127.0.0.1'
];
// Include required files and models
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/UserProfilesModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php'; // Adjust path if needed

// --- JWT Authentication ---
// Retrieve the Authorization header and verify JWT
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(["success" => false, "message" => "No authorization header."]);
    exit;
}
list($bearer, $jwt) = explode(' ', $headers['Authorization']);
if ($bearer !== 'Bearer' || !$jwt) {
    echo json_encode(["success" => false, "message" => "Invalid token format."]);
    exit;
}

$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Replace with your secure key
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(["success" => false, "message" => "Invalid or expired token."]);
    exit;
}
$user_id = $decoded['id'];

// --- Fetch User Profile ---
// Initialize models and retrieve user profile and tier information
try {
    $userProfilesModel = new UserProfilesModel();
    $usersModel = new UsersModel();

    $userProfile = $userProfilesModel->getProfileByUserId($user_id);
    if (!$userProfile) {
        echo json_encode(["success" => false, "message" => "User profile not found."]);
        exit;
    }
    // Fetch user tier from the users table; default to 'regular'
    $user = $usersModel->getUserById($user_id);
    $userProfile['tier'] = $user ? $user['tier'] : 'regular';

    echo json_encode(["success" => true, "user" => $userProfile]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>