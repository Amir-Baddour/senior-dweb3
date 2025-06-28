<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// --- Handle Preflight Request ---
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Include Dependencies ---
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php'; // Adjust path if needed

// --- JWT Authentication ---
// Retrieve and verify the JWT from the Authorization header.
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(["status" => "error", "message" => "No authorization header provided."]);
    exit;
}
list($bearer, $jwt) = explode(' ', $headers['Authorization']);
if ($bearer !== 'Bearer' || !$jwt) {
    echo json_encode(["status" => "error", "message" => "Invalid token format."]);
    exit;
}
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Replace with your secure secret
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(["status" => "error", "message" => "Invalid or expired token."]);
    exit;
}

// --- Authorization Check ---
// Ensure the token belongs to an admin (role = 1).
if (!isset($decoded['role']) || $decoded['role'] != 1) {
    echo json_encode(["status" => "error", "message" => "Access denied. Admins only."]);
    exit;
}

$response = ["status" => "error", "message" => "Something went wrong"];

try {
    // --- Initialize Models ---
    $verificationsModel = new VerificationsModel();
    $usersModel = new UsersModel();

    // --- Fetch Pending Verification Requests ---
    // Retrieve all verification requests with is_validated = 0.
    $verificationRequests = $verificationsModel->getPendingVerifications();

    // Append each user's email to the corresponding verification record.
    $requests = [];
    foreach ($verificationRequests as $request) {
        $user = $usersModel->getUserById($request['user_id']);
        if ($user) {
            $request['email'] = $user['email'];
            $requests[] = $request;
        }
    }

    $response = ["status" => "success", "data" => $requests];
} catch (PDOException $e) {
    $response["message"] = "Database error: " . $e->getMessage();
}

echo json_encode($response);
?>