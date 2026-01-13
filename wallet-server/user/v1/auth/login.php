<?php

// Clean output buffer and start fresh
ob_start();
session_start();

require_once __DIR__ . '/../../../utils/cors.php';
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/VerificationsModel.php';

// Suppress display errors for cleaner JSON output
ini_set('display_errors', 0);
error_reporting(E_ALL);

/**
 * Generate a simple JWT
 */
function generate_jwt(array $payload, string $secret, int $expiry_in_seconds = 3600): string
{
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $expire = $issuedAt + $expiry_in_seconds;
    $payload = array_merge($payload, [
        'iat' => $issuedAt,
        'exp' => $expire
    ]);

    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

/**
 * Store pending login in a file (for cross-domain compatibility)
 */
function store_pending_login($token, $data) {
    $dir = sys_get_temp_dir() . '/pending_logins';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $file = $dir . '/' . $token . '.json';
    file_put_contents($file, json_encode($data));
    
    // Clean up old files (older than 20 minutes)
    foreach (glob($dir . '/*.json') as $oldFile) {
        if (filemtime($oldFile) < time() - 1200) {
            unlink($oldFile);
        }
    }
}

/**
 * Send login verification email
 */
function send_login_verification_email($email, $verification_token, $base_url) {
    $verification_link = $base_url . "/auth/verify_login.php?token=" . urlencode($verification_token);
    
    $subject = "Login Verification - Is this you?";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
            .content { background-color: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
            .button { display: inline-block; padding: 12px 30px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Login Verification Required</h2>
            </div>
            <div class='content'>
                <h3>Is this you?</h3>
                <p>We detected a login attempt to your account. If this was you, please verify by clicking the button below:</p>
                <center>
                    <a href='$verification_link' class='button'>Yes, This Was Me</a>
                </center>
                <p><strong>Login Details:</strong></p>
                <ul>
                    <li>Time: " . date('Y-m-d H:i:s') . "</li>
                    <li>Email: $email</li>
                </ul>
                <p><strong>If this wasn't you:</strong></p>
                <p>Please ignore this email and consider changing your password immediately.</p>
                <p>This verification link will expire in 15 minutes.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: noreply@digitalwallet.com" . "\r\n";

    return @mail($email, $subject, $message, $headers);
}

$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$response = ["status" => "error", "message" => "Something went wrong"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    
    error_log("Login attempt for email: " . $email);

    try {
        $usersModel = new UsersModel();
        $verificationsModel = new VerificationsModel();

        $allUsers = $usersModel->getAllUsers();
        $user = null;
        foreach ($allUsers as $u) {
            if ($u['email'] === $email) {
                $user = $u;
                break;
            }
        }

        if ($user) {
            if (password_verify($password, $user['password'])) {
                error_log("Password verified for user: " . $user['id']);
                
                $verification = $verificationsModel->getVerificationByUserId($user['id']);
                $is_validated = $verification ? $verification['is_validated'] : 0;

                if ($user['role'] == 1 && $is_validated == 0) {
                    $response["message"] = "Admin account is not validated. Please contact support.";
                } else {
                    // Generate verification token
                    $login_verification_token = bin2hex(random_bytes(32));
                    $token_expiry = time() + (15 * 60);
                    
                    // Store pending login data in file system
                    $pending_data = [
                        'user_id' => $user['id'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'is_validated' => $is_validated,
                        'expiry' => $token_expiry
                    ];
                    
                    store_pending_login($login_verification_token, $pending_data);
                    
                    // Determine base URL
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $base_url = $protocol . '://' . $host . '/digital-wallet-plateform/wallet-server/user/v1';
                    
                    // Send verification email
                    $email_sent = @send_login_verification_email($email, $login_verification_token, $base_url);
                    
                    error_log("Email sent status: " . ($email_sent ? 'success' : 'failed'));

                    $response = [
                        "status" => "pending_verification",
                        "message" => "Verification required. (Note: Email sending may not be configured on localhost)",
                        "email" => $email,
                        "debug_token" => $login_verification_token,
                        "debug_link" => $base_url . "/auth/verify_login.php?token=" . $login_verification_token
                    ];
                }
            } else {
                error_log("Password verification failed");
                $response["message"] = "Invalid email or password";
            }
        } else {
            error_log("User not found");
            $response["message"] = "Invalid email or password";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $response["message"] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("General error: " . $e->getMessage());
        $response["message"] = "Error: " . $e->getMessage();
    }
}

// Clean any output that might have been generated
ob_clean();

header('Content-Type: application/json');
echo json_encode($response);

ob_end_flush();
?>