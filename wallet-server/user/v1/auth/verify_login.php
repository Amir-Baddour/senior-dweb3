<?php

ob_start();
require_once __DIR__ . '/../../../utils/cors.php';

/**
 * Generate JWT
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
 * Get pending login from file - FIXED VERSION
 */
function get_pending_login($token) {
    // Use the same fixed directory as in login.php
    $file = __DIR__ . '/../../../temp/pending_logins/' . $token . '.json';
    
    error_log("Looking for pending login at: " . $file);
    error_log("File exists: " . (file_exists($file) ? 'yes' : 'no'));
    
    if (!file_exists($file)) {
        return null;
    }
    
    $data = json_decode(file_get_contents($file), true);
    
    error_log("Found pending login data: " . json_encode($data));
    
    // Delete the file after reading
    unlink($file);
    
    return $data;
}

$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['token'])) {
    $token = $_GET['token'];
    
    error_log("Verification attempt with token: " . $token);
    
    $pending = get_pending_login($token);
    
    if ($pending) {
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
                    .token-box { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; word-break: break-all; font-family: monospace; font-size: 12px; max-height: 150px; overflow-y: auto; }
                    .button { display: inline-block; padding: 12px 30px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; transition: background-color 0.3s; cursor: pointer; border: none; font-size: 16px; }
                    .button:hover { background-color: #45a049; }
                    .info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="success-icon">✓</div>
                    <h1>Login Verified Successfully!</h1>
                    <p>Your login has been verified. You will be redirected to your dashboard automatically.</p>
                    <div class="info">
                        <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($pending['email']); ?></p>
                    </div>
                    <div class="token-box">
                        <strong>Your JWT Token:</strong><br>
                        <?php echo htmlspecialchars($jwt); ?>
                    </div>
                    <button class="button" onclick="completeLogin()">Continue to Dashboard</button>
                </div>
                <script>
                    const token = '<?php echo $jwt; ?>';
                    const user = {
                        id: <?php echo $pending['user_id']; ?>,
                        email: '<?php echo htmlspecialchars($pending['email']); ?>',
                        role: <?php echo $pending['role']; ?>,
                        is_validated: <?php echo $pending['is_validated']; ?>
                    };
                    
                    function completeLogin() {
                        // Store token in localStorage
                        try {
                            localStorage.setItem('jwt', token);
                            localStorage.setItem('userId', user.id);
                            localStorage.setItem('userEmail', user.email);
                            localStorage.setItem('userRole', user.role);
                            
                            // Notify parent window if opened in popup
                            if (window.opener) {
                                window.opener.postMessage({
                                    type: 'login_verified',
                                    token: token,
                                    user: user
                                }, '*');
                                window.close();
                            } else {
                                // Redirect to dashboard
                                window.location.href = '/dashboard.html';
                            }
                        } catch(e) {
                            console.error('Storage error:', e);
                            alert('Please copy the token and return to the login page');
                        }
                    }
                    
                    // Auto-redirect after 3 seconds
                    setTimeout(completeLogin, 3000);
                </script>
            </body>
            </html>
            <?php
        } else {
            // Token expired
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
        // Invalid or already used token
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
                <p>This verification link is invalid or has already been used. Please try logging in again.</p>
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