<?php
// login.php - COMPLETE FIXED VERSION

ob_start();
session_start();

require_once __DIR__ . '/../../../utils/cors.php';
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/VerificationsModel.php';

// Uncomment when PHPMailer is configured
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;
// require __DIR__ . '/../../../vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

/**
 * Store pending login
 */
function store_pending_login($token, $data) {
    $dir = __DIR__ . '/../../../temp/pending_logins';
    
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            error_log("Failed to create directory: " . $dir);
            return false;
        }
    }
    
    $file = $dir . '/' . $token . '.json';
    $data['created_at'] = time();
    
    $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        error_log("Failed to write file: " . $file);
        return false;
    }
    
    error_log("Stored pending login: " . $file);
    
    // Clean up OLD files only (older than 20 minutes)
    foreach (glob($dir . '/*.json') as $oldFile) {
        if ($oldFile !== $file && filemtime($oldFile) < time() - 1200) {
            unlink($oldFile);
        }
    }
    
    return true;
}

/**
 * Send verification email using PHPMailer (SMTP)
 * UNCOMMENT THIS WHEN YOU CONFIGURE PHPMAILER
 */
function send_verification_email_smtp($email, $token, $base_url) {
    // STEP 1: Install PHPMailer: composer require phpmailer/phpmailer
    // STEP 2: Configure your SMTP settings below
    // STEP 3: Uncomment this entire function
    
    /*
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Configuration - CHANGE THESE
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';           // Your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@gmail.com';     // Your email
        $mail->Password   = 'your-app-password';        // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Email settings
        $mail->setFrom('noreply@yourwallet.com', 'Digital Wallet');
        $mail->addAddress($email);
        $mail->isHTML(true);
        
        $verification_link = $base_url . "/auth/verify_login.php?token=" . urlencode($token);
        
        $mail->Subject = 'Verify Your Login - Digital Wallet';
        $mail->Body    = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background-color: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
                .button { display: inline-block; padding: 15px 40px; background-color: #4CAF50; color: white !important; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Login Verification Required</h1>
                </div>
                <div class='content'>
                    <h2>Hello!</h2>
                    <p>We detected a login attempt to your Digital Wallet account.</p>
                    
                    <p><strong>Login Details:</strong></p>
                    <ul>
                        <li>📧 Email: {$email}</li>
                        <li>🕐 Time: " . date('Y-m-d H:i:s') . "</li>
                        <li>🌐 IP Address: " . $_SERVER['REMOTE_ADDR'] . "</li>
                    </ul>
                    
                    <p><strong>If this was you, click the button below to verify and complete your login:</strong></p>
                    
                    <center>
                        <a href='{$verification_link}' class='button'>✓ Yes, This Was Me - Verify Login</a>
                    </center>
                    
                    <div class='warning'>
                        <strong>⚠️ If this wasn't you:</strong>
                        <p>Do NOT click the button. Someone may be trying to access your account. Please change your password immediately and contact support.</p>
                    </div>
                    
                    <p style='color: #666; font-size: 14px;'>
                        <strong>Note:</strong> This verification link will expire in 15 minutes for security reasons.
                    </p>
                </div>
                <div class='footer'>
                    <p>This is an automated security message from Digital Wallet.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->send();
        error_log("Verification email sent successfully to: " . $email);
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
    */
    
    // TEMPORARY: Return false until PHPMailer is configured
    error_log("PHPMailer not configured. Email would be sent to: " . $email);
    return false;
}

/**
 * Fallback: Basic PHP mail() function (unreliable without mail server)
 */
function send_verification_email_basic($email, $token, $base_url) {
    $verification_link = $base_url . "/auth/verify_login.php?token=" . urlencode($token);
    
    $subject = "Verify Your Login - Digital Wallet";
    $message = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1>🔐 Login Verification Required</h1>
            </div>
            <div style='background-color: #f9f9f9; padding: 30px; border: 1px solid #ddd;'>
                <h2>Hello!</h2>
                <p>We detected a login attempt to your Digital Wallet account.</p>
                
                <p><strong>Login Details:</strong></p>
                <ul>
                    <li>📧 Email: {$email}</li>
                    <li>🕐 Time: " . date('Y-m-d H:i:s') . "</li>
                </ul>
                
                <p><strong>If this was you, click the link below to verify:</strong></p>
                <p><a href='{$verification_link}' style='display: inline-block; padding: 15px 40px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 8px;'>Verify Login</a></p>
                
                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
                    <strong>⚠️ If this wasn't you:</strong>
                    <p>Do NOT click the link. Change your password immediately.</p>
                </div>
                
                <p style='color: #666; font-size: 14px;'>This link expires in 15 minutes.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Digital Wallet <noreply@yourwallet.com>" . "\r\n";

    return @mail($email, $subject, $message, $headers);
}

$response = ["status" => "error", "message" => "Invalid request"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? '');
    $password = $_POST["password"] ?? '';
    
    if (empty($email) || empty($password)) {
        $response["message"] = "Email and password are required";
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

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

        if (!$user) {
            $response["message"] = "Invalid email or password";
        } elseif (!password_verify($password, $user['password'])) {
            $response["message"] = "Invalid email or password";
        } else {
            // Password is correct - now send verification email
            
            $verification = $verificationsModel->getVerificationByUserId($user['id']);
            $is_validated = $verification ? $verification['is_validated'] : 0;

            // Generate verification token
            $login_token = bin2hex(random_bytes(32));
            $token_expiry = time() + (15 * 60); // 15 minutes
            
            // Store pending login data
            $pending_data = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_validated' => $is_validated,
                'expiry' => $token_expiry
            ];
            
            if (!store_pending_login($login_token, $pending_data)) {
                $response["message"] = "Failed to create verification. Please try again.";
            } else {
                // Determine base URL
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                if (strpos($_SERVER['HTTP_HOST'], 'trycloudflare.com') !== false) {
                    $protocol = 'https';
                }
                $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/digital-wallet-plateform/wallet-server/user/v1';
                
                // Try to send email
                $email_sent = send_verification_email_smtp($email, $login_token, $base_url);
                
                // Fallback to basic mail if SMTP fails
                if (!$email_sent) {
                    $email_sent = send_verification_email_basic($email, $login_token, $base_url);
                }
                
                $verification_url = $base_url . "/auth/verify_login.php?token=" . $login_token;
                
                $response = [
                    "status" => "pending_verification",
                    "message" => $email_sent 
                        ? "Security verification required! We've sent a verification link to your email ({$email}). Please check your inbox and click the link to complete your login." 
                        : "Security verification required! Email service is not configured yet. Use the verification link below to complete your login.",
                    "email" => $email,
                    "email_sent" => $email_sent,
                    "verification_url" => $verification_url,
                    "expires_in" => "15 minutes",
                    "note" => "For security, please verify this login attempt was made by you."
                ];
                
                // Add debug info only in development
                if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
                    $response["debug_token"] = $login_token;
                    $response["debug_link"] = $verification_url;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $response["message"] = "An error occurred. Please try again.";
    }
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>