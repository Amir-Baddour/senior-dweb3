<?php
ob_start();

require_once __DIR__ . '/../../utils/cors.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include dependencies and models
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/MailService.php';

// Try to load JWT - check which one exists in your system
if (file_exists(__DIR__ . '/../../utils/jwt.php')) {
    require_once __DIR__ . '/../../utils/jwt.php';
} elseif (file_exists(__DIR__ . '/../../utils/verify_jwt.php')) {
    require_once __DIR__ . '/../../utils/verify_jwt.php';
}

$response = ["status" => "error", "message" => "Something went wrong."];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    error_log('[verification.php] POST request received');
    
    // Authenticate user via JWT from the Authorization header
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        $response["message"] = "No authorization header provided.";
        echo json_encode($response);
        exit;
    }
    
    $auth_parts = explode(' ', $headers['Authorization']);
    if (count($auth_parts) !== 2 || $auth_parts[0] !== 'Bearer') {
        $response["message"] = "Invalid token format.";
        echo json_encode($response);
        exit;
    }
    
    $jwt = $auth_parts[1];
    
    // Try both JWT verification functions
    $decoded = null;
    if (function_exists('jwt_verify')) {
        $decoded = jwt_verify($jwt);
    } elseif (function_exists('verify_jwt')) {
        $jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
        $decoded = verify_jwt($jwt, $jwt_secret);
    }
    
    if (!$decoded) {
        $response["message"] = "Invalid or expired token.";
        echo json_encode($response);
        exit;
    }
    
    $user_id = $decoded['id'];
    error_log('[verification.php] User ID: ' . $user_id);
    
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
    
    error_log('[verification.php] Attempting to upload file: ' . $file_name);
    
    // Move the uploaded file to the designated directory
    if (move_uploaded_file($file["tmp_name"], $file_path)) {
        error_log('[verification.php] File uploaded successfully');
        
        // Initialize the models
        $verificationsModel = new VerificationsModel();
        $usersModel = new UsersModel();
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

        // Send email notification to user
        if ($response["status"] === "success") {
            error_log('[verification.php] Starting email process for user_id: ' . $user_id);
            
            try {
                // Get user email
                $user = $usersModel->getUserById($user_id);
                $userEmail = $user['email'] ?? null;
                
                error_log('[verification.php] User email: ' . ($userEmail ?: 'NULL'));

                if ($userEmail) {
                    error_log('[verification.php] Attempting to send email');
                    
                    // Prepare email content
                    $subject = "Verification Document Received";
                    $htmlBody = "
                        <h2>Verification Submitted Successfully</h2>
                        <p>Dear User,</p>
                        <p>We have received your verification document and it is now pending review by our team.</p>
                        <p>You will receive a notification once your verification has been processed.</p>
                        <p><strong>Document:</strong> {$file_name}</p>
                        <p><strong>Submitted:</strong> " . date('Y-m-d H:i:s') . "</p>
                        <hr>
                        <p>If you did not submit this document, please contact support immediately.</p>
                        <p>Thank you for your patience!</p>
                    ";

                    // Use MailService
                    $mailer = new MailService();
                    $emailSent = $mailer->sendMail($userEmail, $subject, $htmlBody);
                    
                    if ($emailSent) {
                        error_log('[verification.php] ✅ Email sent successfully!');
                        $response["emailSent"] = true;
                    } else {
                        error_log('[verification.php] ❌ Email failed to send');
                        $response["emailSent"] = false;
                        $response["emailError"] = "Failed to send email";
                    }
                } else {
                    error_log('[verification.php] ❌ No user email found');
                    $response["emailSent"] = false;
                    $response["emailError"] = "User email not found";
                }
            } catch (Exception $e) {
                error_log('[verification.php] ❌ Email exception: ' . $e->getMessage());
                $response["emailSent"] = false;
                $response["emailError"] = $e->getMessage();
            }
        }
    } else {
        error_log('[verification.php] ❌ File upload failed');
        $response["message"] = "File upload failed.";
    }
}

echo json_encode($response);
?>