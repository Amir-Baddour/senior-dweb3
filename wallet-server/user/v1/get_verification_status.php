<?php
// ✅ Use cors.php for consistent CORS handling
require_once __DIR__ . '/../../utils/cors.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php'; // ✅ Use verify_jwt.php like other user endpoints

// --- JWT Authentication ---
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(["success" => false, "message" => "No authorization header."]);
    exit;
}

$auth_parts = explode(' ', $headers['Authorization']);
if (count($auth_parts) !== 2 || $auth_parts[0] !== 'Bearer') {
    echo json_encode(["success" => false, "message" => "Invalid token format."]);
    exit;
}

$jwt = $auth_parts[1];

// ✅ Use verify_jwt with the SAME secret as login.php
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Must match login.php
$decoded = verify_jwt($jwt, $jwt_secret);

if (!$decoded) {
    echo json_encode(["success" => false, "message" => "Invalid or expired token."]);
    exit;
}

$user_id = $decoded['id'];

// --- Fetch Verification Status ---
try {
    $verificationsModel = new VerificationsModel();
    $verification = $verificationsModel->getVerificationByUserId($user_id);

    if ($verification) {
        // ✅ Return actual status from database
        echo json_encode([
            "success" => true,
            "is_validated" => (int)$verification['is_validated'], // Ensure integer: -1, 0, or 1
            "validation_note" => $verification['verification_note'] ?? '', // ✅ Fixed column name
            "created_at" => $verification['created_at'] ?? null
        ]);
    } else {
        // No verification record yet
        echo json_encode([
            "success" => true,
            "is_validated" => 0, // Not submitted
            "validation_note" => "No verification submitted yet",
            "created_at" => null
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false, 
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>