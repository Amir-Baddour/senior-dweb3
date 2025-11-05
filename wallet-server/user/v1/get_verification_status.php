<?php
// Set JSON response header


// Include required dependencies
require_once __DIR__ . '/../../utils/cors.php';
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php'; // Adjust path if necessary


// --- JWT Authentication ---
// Retrieve the Authorization header and verify JWT
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(['error' => 'No authorization header provided']);
    exit;
}
list($bearer, $jwt) = explode(' ', $headers['Authorization']);
if ($bearer !== 'Bearer' || !$jwt) {
    echo json_encode(['error' => 'Invalid token format']);
    exit;
}

$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Replace with a secure secret key
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(['error' => 'Invalid or expired token']);
    exit;
}

// Extract user ID from the JWT payload
$userId = $decoded['id'];

// --- Fetch Verification Status ---
// Initialize the VerificationsModel and fetch verification record
try {
    $verificationsModel = new VerificationsModel();
    $verification = $verificationsModel->getVerificationByUserId($userId);

    // If no verification record is found, return a default status of 0
    if (!$verification) {
        echo json_encode(['is_validated' => 0]);
        exit;
    }
    
    // Return the verification status (cast to integer)
    echo json_encode(['is_validated' => (int)$verification['is_validated']]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>