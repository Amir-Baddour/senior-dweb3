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
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
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
            $emailSent = false;
            $emailError = null;
            
            try {
                $user = $usersModel->getUserById($user_id);
                $userEmail = $user['email'] ?? null;

                if ($userEmail && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
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
                    $altBody = "Your verification document has been submitted and is pending review.";

                    $gmailUser = 'amirbaddour675@gmail.com';
                    $appPass = 'lqtkykunvmmuhsvj';

                    // Try 587 STARTTLS first
                    try {
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = $gmailUser;
                        $mail->Password = $appPass;
                        $mail->Port = 587;
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->CharSet = 'UTF-8';
                        $mail->setFrom($gmailUser, 'Digital Wallet');
                        $mail->addAddress($userEmail);
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = $htmlBody;
                        $mail->AltBody = $altBody;
                        $mail->send();
                        $emailSent = true;
                    } catch (Throwable $e1) {
                        // Fallback to 465 SMTPS
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = $gmailUser;
                        $mail->Password = $appPass;
                        $mail->Port = 465;
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        $mail->CharSet = 'UTF-8';
                        $mail->setFrom($gmailUser, 'Digital Wallet');
                        $mail->addAddress($userEmail);
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = $htmlBody;
                        $mail->AltBody = $altBody;
                        $mail->send();
                        $emailSent = true;
                    }
                } else {
                    if (!$userEmail) $emailError = 'Missing recipient email';
                    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) $emailError = 'PHPMailer not installed';
                }
            } catch (Throwable $e) {
                $emailError = $e->getMessage();
                error_log('verification email error: ' . $emailError);
            }

            // Add email info to response (optional)
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