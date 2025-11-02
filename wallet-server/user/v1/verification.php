<?php
// Set CORS and content headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
// Include dependencies and models
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

$response = ["status" => "error", "message" => "Something went wrong."];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Authenticate user via JWT from the Authorization header
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        $response["message"] = "No authorization header provided.";
        echo json_encode($response);
        exit;
    }
    
    list($bearer, $jwt) = explode(' ', $headers['Authorization']);
    if ($bearer !== 'Bearer' || !$jwt) {
        $response["message"] = "Invalid token format.";
        echo json_encode($response);
        exit;
    }
    
    $jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Replace with a secure secret key
    $decoded = verify_jwt($jwt, $jwt_secret);
    if (!$decoded) {
        $response["message"] = "Invalid or expired token.";
        echo json_encode($response);
        exit;
    }
    
    $user_id = $decoded['id'];
    
    // Validate file upload
    if (!isset($_FILES["id_document"])) {
        $response["message"] = "No file uploaded.";
        echo json_encode($response);
        exit;
    }
    
    $file = $_FILES["id_document"];
    $allowed_types = ["image/jpeg", "image/png", "application/pdf"];
    
    if (!in_array($file["type"], $allowed_types)) {
        $response["message"] = "Invalid file type. Only JPG, PNG, and PDF are allowed.";
        echo json_encode($response);
        exit;
    }
    
    if ($file["size"] > 2 * 1024 * 1024) { // 2MB limit
        $response["message"] = "File too large. Max size: 2MB.";
        echo json_encode($response);
        exit;
    }
    
    // Prepare the upload directory and file name
    $upload_dir = __DIR__ . "/../../uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = "id_" . $user_id . "_" . time() . "." . pathinfo($file["name"], PATHINFO_EXTENSION);
    $file_path = $upload_dir . $file_name;
    
    // Move the uploaded file to the designated directory
    if (move_uploaded_file($file["tmp_name"], $file_path)) {
        // Initialize the VerificationsModel and update or create a verification record
        $verificationsModel = new VerificationsModel();
        $existingVerification = $verificationsModel->getVerificationByUserId($user_id);
    
        if ($existingVerification) {
            $updated = $verificationsModel->update(
                $existingVerification['id'],
                $user_id,
                $file_name,
                0,
                'Verification resubmitted'
            );
            if ($updated) {
                $response = ["status" => "success", "message" => "Document updated successfully. Pending admin approval."];
            } else {
                $response["message"] = "Database update failed.";
            }
        } else {
            $created = $verificationsModel->create($user_id, $file_name, 0, 'Verification submitted');
            if ($created) {
                $response = ["status" => "success", "message" => "Document uploaded successfully. Pending admin approval."];
            } else {
                $response["message"] = "Database update failed.";
            }
        }
    } else {
        $response["message"] = "File upload failed.";
    }
}

echo json_encode($response);
?>