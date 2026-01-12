<?php

ob_start();
session_start(); // Start session at the beginning

require_once __DIR__ . '/../../../utils/cors.php';
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/VerificationsModel.php';

/**
 * Generate a simple JWT (for demonstration purposes).
 * In production, consider using firebase/php-jwt.
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
 * Send login verification email
 */
function send_login_verification_email($email, $verification_token) {
    $verification_link = "http://localhost/digital-wallet-plateform/wallet-server/user/v1/auth/verify_login.php?token=" . urlencode($verification_token);
    
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

    return mail($email, $subject, $message, $headers);
}

// Replace with your secure secret key
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$response = ["status" => "error", "message" => "Something went wrong"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    try {
        $usersModel = new UsersModel();
        $verificationsModel = new VerificationsModel();

        // Lookup user by email
        $allUsers = $usersModel->getAllUsers();
        $user = null;
        foreach ($allUsers as $u) {
            if ($u['email'] === $email) {
                $user = $u;
                break;
            }
        }

        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Fetch user's verification status
                $verification = $verificationsModel->getVerificationByUserId($user['id']);
                $is_validated = $verification ? $verification['is_validated'] : 0;

                // Prevent login for unvalidated admin accounts
                if ($user['role'] == 1 && $is_validated == 0) {
                    $response["message"] = "Admin account is not validated. Please contact support.";
                } else {
                    // Generate a temporary login verification token (valid for 15 minutes)
                    $login_verification_token = bin2hex(random_bytes(32));
                    $token_expiry = time() + (15 * 60); // 15 minutes
                    
                    // Store the pending login in session
                    $_SESSION['pending_login'] = [
                        'user_id' => $user['id'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'is_validated' => $is_validated,
                        'token' => $login_verification_token,
                        'expiry' => $token_expiry
                    ];

                    // Send verification email
                    $email_sent = send_login_verification_email($email, $login_verification_token);

                    if ($email_sent) {
                        $response = [
                            "status" => "pending_verification",
                            "message" => "Please check your email to verify this login attempt. The verification link will expire in 15 minutes.",
                            "email" => $email
                        ];
                    } else {
                        $response = [
                            "status" => "error",
                            "message" => "Failed to send verification email. Please try again."
                        ];
                    }
                }
            } else {
                $response["message"] = "Invalid email or password";
            }
        } else {
            $response["message"] = "Invalid email or password";
        }
    } catch (PDOException $e) {
        $response["message"] = "Database error: " . $e->getMessage();
    }
}

// Set JSON header
header('Content-Type: application/json');
echo json_encode($response);
?>