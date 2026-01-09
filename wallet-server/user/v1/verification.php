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
require_once __DIR__ . '/../../utils/verify_jwt.php';

// Load PHPMailer if available
$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    error_log('[verification.php] Autoload not found at: ' . $autoload);
}

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$response = ["status" => "error", "message" => "Something went wrong."];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
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
    $jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Must match login.php
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
            
            $emailSent = false;
            $emailError = null;
            
            try {
                $user = $usersModel->getUserById($user_id);
                $userEmail = $user['email'] ?? null;
                
                error_log('[verification.php] User email: ' . ($userEmail ?: 'NULL'));

                if ($userEmail && class_exists(PHPMailer::class)) {
                    error_log('[verification.php] Attempting to send email...');
                    
                    $mail = new PHPMailer(true);
                    
                    // Enable verbose debug output
                    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                    $mail->Debugoutput = function($str, $level) {
                        error_log("[PHPMailer Debug] $str");
                    };
                    
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'amirbaddour675@gmail.com';
                    $mail->Password = 'lqtkykunvmmuhsvj';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';
                    
                    // Set timeout values
                    $mail->Timeout = 30;
                    $mail->SMTPKeepAlive = true;
                    
                    // Recipients
                    $mail->setFrom('amirbaddour675@gmail.com', 'Digital Wallet');
                    $mail->addAddress($userEmail);
                    $mail->addReplyTo('amirbaddour675@gmail.com', 'Digital Wallet Support');
                    
                    // Content
                    $mail->isHTML(false);
                    $mail->Subject = 'Verification Document Received';
                    $mail->Body = "Dear User,\n\n" .
                                  "Your verification document has been received and is pending review.\n\n" .
                                  "Document: {$file_name}\n" .
                                  "Submitted: " . date('Y-m-d H:i:s') . "\n\n" .
                                  "We will review your document and notify you once the verification is complete.\n\n" .
                                  "Best regards,\n" .
                                  "Digital Wallet Team";
                    
                    $mail->send();
                    $emailSent = true;
                    error_log('[verification.php] Email sent successfully to: ' . $userEmail);
                } else {
                    $emailError = !$userEmail ? 'Missing recipient email' : 'PHPMailer not installed';
                    error_log('[verification.php] Email error: ' . $emailError);
                }
            } catch (Exception $e) {
                $emailError = $e->getMessage();
                error_log('[verification.php] Email exception: ' . $emailError);
                error_log('[verification.php] Full error info: ' . $mail->ErrorInfo);
            } catch (Throwable $e) {
                $emailError = $e->getMessage();
                error_log('[verification.php] Unexpected error: ' . $emailError);
            }

            $response["emailSent"] = $emailSent;
            if ($emailError) {
                $response["emailError"] = $emailError;
            }
        }

    } else {
        $response["message"] = "File upload failed.";
    }
}

echo json_encode($response);
?>