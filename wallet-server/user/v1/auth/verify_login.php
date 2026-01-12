<?php

ob_start();
require_once __DIR__ . '/../../../utils/cors.php';

/**
 * Generate a simple JWT (for demonstration purposes).
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

// Replace with the same secret key used in login.php
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";

session_start();

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if there's a pending login
    if (isset($_SESSION['pending_login'])) {
        $pending = $_SESSION['pending_login'];
        
        // Verify token matches
        if ($pending['token'] === $token) {
            // Check if token hasn't expired
            if (time() <= $pending['expiry']) {
                // Generate JWT token
                $payload = [
                    "id" => $pending["user_id"],
                    "email" => $pending["email"],
                    "role" => $pending["role"],
                    "is_validated" => $pending["is_validated"]
                ];
                $jwt = generate_jwt($payload, $jwt_secret, 3600);
                
                // Clear the pending login
                unset($_SESSION['pending_login']);
                
                // Return success page with JWT token
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Login Verified</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; text-align: center; }
                        .success-icon { font-size: 64px; color: #4CAF50; margin-bottom: 20px; }
                        h1 { color: #333; margin-bottom: 20px; }
                        p { color: #666; margin-bottom: 20px; line-height: 1.6; }
                        .token-box { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; word-break: break-all; font-family: monospace; font-size: 12px; }
                        .button { display: inline-block; padding: 12px 30px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; transition: background-color 0.3s; }
                        .button:hover { background-color: #45a049; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="success-icon">✓</div>
                        <h1>Login Verified Successfully!</h1>
                        <p>Your login has been verified. You can now access your account.</p>
                        <div class="token-box">
                            <strong>Your JWT Token:</strong><br>
                            <?php echo htmlspecialchars($jwt); ?>
                        </div>
                        <p><small>Copy this token and use it in your application, or the app will automatically log you in.</small></p>
                        <a href="#" class="button" onclick="closeWindow()">Close Window</a>
                    </div>
                    <script>
                        // Store token in localStorage if accessed from same origin
                        try {
                            localStorage.setItem('jwt_token', '<?php echo $jwt; ?>');
                            // Notify parent window if opened in popup
                            if (window.opener) {
                                window.opener.postMessage({
                                    type: 'login_verified',
                                    token: '<?php echo $jwt; ?>',
                                    user: {
                                        id: <?php echo $pending['user_id']; ?>,
                                        email: '<?php echo htmlspecialchars($pending['email']); ?>',
                                        role: <?php echo $pending['role']; ?>,
                                        is_validated: <?php echo $pending['is_validated']; ?>
                                    }
                                }, '*');
                            }
                        } catch(e) {
                            console.log('Could not store token:', e);
                        }
                        
                        function closeWindow() {
                            window.close();
                            // If window doesn't close, redirect
                            setTimeout(() => {
                                window.location.href = '/';
                            }, 100);
                        }
                    </script>
                </body>
                </html>
                <?php
            } else {
                // Token expired
                unset($_SESSION['pending_login']);
                ?>
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Verification Expired</title>
                    <style>
                        * { margin: 0; padding: 0; box-sizing: border-box; }
                        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                        .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; text-align: center; }
                        .error-icon { font-size: 64px; color: #f44336; margin-bottom: 20px; }
                        h1 { color: #333; margin-bottom: 20px; }
                        p { color: #666; line-height: 1.6; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="error-icon">⏱</div>
                        <h1>Verification Link Expired</h1>
                        <p>This verification link has expired. Please try logging in again.</p>
                    </div>
                </body>
                </html>
                <?php
            }
        } else {
            // Invalid token
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Invalid Token</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                    .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; text-align: center; }
                    .error-icon { font-size: 64px; color: #f44336; margin-bottom: 20px; }
                    h1 { color: #333; margin-bottom: 20px; }
                    p { color: #666; line-height: 1.6; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="error-icon">✗</div>
                    <h1>Invalid Verification Token</h1>
                    <p>This verification link is invalid. Please try logging in again.</p>
                </div>
            </body>
            </html>
            <?php
        }
    } else {
        // No pending login
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>No Pending Login</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; text-align: center; }
                .error-icon { font-size: 64px; color: #f44336; margin-bottom: 20px; }
                h1 { color: #333; margin-bottom: 20px; }
                p { color: #666; line-height: 1.6; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-icon">⚠</div>
                <h1>No Pending Login Found</h1>
                <p>There is no pending login session. Please try logging in again.</p>
            </div>
        </body>
        </html>
        <?php
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
}
?>