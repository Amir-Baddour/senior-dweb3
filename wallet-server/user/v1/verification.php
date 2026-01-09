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

// Load PHPMailer if available - FIXED PATH (3 levels up)
$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    error_log('[verification.php] Autoload not found at: ' . $autoload);
}

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

        // ✅ Send email notification to user
        if ($response["status"] === "success") {
            error_log('[verification.php] Starting email process for user_id: ' . $user_id);
            
            $emailSent = false;
            $emailError = null;
            
            try {
                $user = $usersModel->getUserById($user_id);
                $userEmail = $user['email'] ?? null;
                
                error_log('[verification.php] User email: ' . ($userEmail ?: 'NULL'));
                error_log('[verification.php] PHPMailer class exists: ' . (class_exists(\PHPMailer\PHPMailer\PHPMailer::class) ? 'YES' : 'NO'));

                if ($userEmail && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                    error_log('[verification.php] Attempting to send email...');
                    $subject = "Verification Document Received";
                    
                    // ✅ SIMPLIFIED plain text version (avoid Gmail spam filters)
                    $plainBody = "Dear User,\n\nWe have received your verification document and it is now pending review by our team.\n\nYou will receive a notification once your verification has been processed.\n\nDocument: {$file_name}\nSubmitted: " . date('Y-m-d H:i:s') . "\n\nIf you did not submit this document, please contact support immediately.\n\nThank you!";

                    $gmailUser = 'amirbaddour675@gmail.com';
                    $appPass = 'lqtkykunvmmuhsvj';

                    // Try 587 STARTTLS
                    try {
                        error_log('[verification.php] Trying SMTP port 587...');
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = $gmailUser;
                        $mail->Password = $appPass;
                        $mail->Port = 587;
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->CharSet = 'UTF-8';
                        $mail->SMTPDebug = 2; // ✅ Enable debug
                        $mail->Debugoutput = function($str, $level) {
                            error_log("[PHPMailer DEBUG] $str");
                        };
                        $mail->Timeout = 30;
                        $mail->setFrom($gmailUser, 'Digital Wallet');
                        $mail->addAddress($userEmail);
                        $mail->isHTML(false); // ✅ Plain text
                        $mail->Subject = $subject;
                        $mail->Body = $plainBody;
                        $mail->send();
                        $emailSent = true;
                        error_log('[verification.php] Email sent successfully via port 587!');
                    } catch (Throwable $e1) {
                        error_log('[verification.php] Port 587 failed: ' . $e1->getMessage());
                        $emailError = $e1->getMessage();
                    }
                } else {
                    if (!$userEmail) {
                        $emailError = 'Missing recipient email';
                        error_log('[verification.php] Email error: Missing recipient email');
                    }
                    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
                        $emailError = 'PHPMailer not installed';
                        error_log('[verification.php] Email error: PHPMailer not installed');
                    }
                }
            } catch (Throwable $e) {
                $emailError = $e->getMessage();
                error_log('[verification.php] Email exception: ' . $emailError);
            }

            // Add email info to response
            $response["emailSent"] = $emailSent;
            if ($emailError) {
                $response["emailError"] = $emailError;
            }
            
            error_log('[verification.php] Email process complete. Sent: ' . ($emailSent ? 'YES' : 'NO') . ', Error: ' . ($emailError ?: 'NONE'));
        }
    } else {
        $response["message"] = "File upload failed.";
    }
}

echo json_encode($response);
?>