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
require_once __DIR__ . '/../../utils/jwt.php'; // ✅ Use jwt.php (not verify_jwt.php)

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

// ✅ Use jwt_verify() from jwt.php (uses correct secret: mydevsecret123456789)
try {
    $decoded = jwt_verify($jwt);
} catch (RuntimeException $e) {
    echo json_encode(["success" => false, "message" => "Invalid or expired token: " . $e->getMessage()]);
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
            "validation_note" => $verification['validation_note'] ?? '',
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