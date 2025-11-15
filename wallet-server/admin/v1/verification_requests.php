<?php
// ✅ Use cors.php instead of hardcoded headers
error_log("[verification_requests.php] File loaded at " . date('Y-m-d H:i:s'));

require_once __DIR__ . '/../../utils/cors.php';
error_log("[verification_requests.php] CORS file included");
// --- Include Dependencies ---
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

// --- JWT Authentication ---
// Retrieve and verify the JWT from the Authorization header.
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(["status" => "error", "message" => "No authorization header provided."]);
    exit;
}

$auth_header = $headers['Authorization'];
$parts = explode(' ', $auth_header);

if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
    echo json_encode(["status" => "error", "message" => "Invalid token format."]);
    exit;
}

$jwt = $parts[1];
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Must match login.php
$decoded = verify_jwt($jwt, $jwt_secret);

if (!$decoded) {
    echo json_encode(["status" => "error", "message" => "Invalid or expired token."]);
    exit;
}

// --- Authorization Check ---
// ✅ Use string comparison since JWT stores role as string
if (!isset($decoded['role']) || (string)$decoded['role'] !== '1') {
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
} catch (Exception $e) {
    $response["message"] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>