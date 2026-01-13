<?php
// verify_login.php - COMPLETE FIXED VERSION

ob_start();
require_once __DIR__ . '/../../../utils/cors.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
 * Get pending login from file
 */
function get_pending_login($token) {
    $dir = __DIR__ . '/../../../temp/pending_logins';
    $file = $dir . '/' . $token . '.json';
    
    error_log("Verifying login token: " . $token);
    error_log("Looking for file: " . $file);
    
    if (!file_exists($file)) {
        error_log("File not found!");
        return null;
    }
    
    $content = file_get_contents($file);
    if ($content === false) {
        error_log("Failed to read file");
        return null;
    }
    
    $data = json_decode($content, true);
    if ($data === null) {
        error_log("Failed to decode JSON");
        return null;
    }
    
    error_log("Successfully loaded pending login for user: " . $data['email']);
    
    // Delete the file after reading
    unlink($file);
    
    return $data;
}

$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $pending = get_pending_login($token);
    
    if ($pending) {
        // Check if token expired
        if (time() > $pending['expiry']) {
            // Token expired
            ob_end_clean();
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Verification Expired</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { 
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
                        min-height: 100vh; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center;
                        padding: 20px;
                    }
                    .container { 
                        background: white; 
                        padding: 40px; 
                        border-radius: 16px; 
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
                        max-width: 500px; 
                        text-align: center;
                    }
                    .error-icon { font-size: 64px; margin-bottom: 20px; }
                    h1 { color: #333; margin-bottom: 20px; font-size: 24px; }
                    p { color: #666; line-height: 1.6; margin-bottom: 15px; }
                    .button { 
                        display: inline-block; 
                        padding: 12px 30px; 
                        background-color: #f44336; 
                        color: white; 
                        text-decoration: none; 
                        border-radius: 8px; 
                        margin-top: 20px; 
                        cursor: pointer;
                        border: none;
                        font-size: 16px;
                    }
                    .button:hover { background-color: #d32f2f; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="error-icon">⏱️</div>
                    <h1>Verification Link Expired</h1>
                    <p>This verification link has expired (valid for 15 minutes).</p>
                    <p>For security reasons, please return to the login page and try again.</p>
                    <button class="button" onclick="window.close()">Close Window</button>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
        
        // Token is valid - generate JWT and redirect
        $payload = [
            "id" => $pending["user_id"],
            "email" => $pending["email"],
            "role" => $pending["role"],
            "is_validated" => $pending["is_validated"]
        ];
        $jwt = generate_jwt($payload, $jwt_secret, 3600);
        
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login Verified</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                    min-height: 100vh; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center;
                    padding: 20px;
                }
                .container { 
                    background: white; 
                    padding: 40px; 
                    border-radius: 16px; 
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
                    max-width: 500px; 
                    width: 100%;
                    text-align: center;
                    animation: slideIn 0.5s ease-out;
                }
                @keyframes slideIn {
                    from { transform: translateY(-30px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                .success-icon { 
                    font-size: 64px; 
                    color: #4CAF50; 
                    margin-bottom: 20px;
                    animation: checkmark 0.6s ease-in-out;
                }
                @keyframes checkmark {
                    0% { transform: scale(0); }
                    50% { transform: scale(1.2); }
                    100% { transform: scale(1); }
                }
                h1 { 
                    color: #333; 
                    margin-bottom: 16px;
                    font-size: 28px;
                }
                p { 
                    color: #666; 
                    margin-bottom: 20px; 
                    line-height: 1.6;
                    font-size: 16px;
                }
                .info { 
                    background: #e3f2fd; 
                    padding: 16px; 
                    border-radius: 8px; 
                    margin: 24px 0;
                    border-left: 4px solid #2196F3;
                }
                .info p {
                    margin: 0;
                    color: #1976D2;
                    font-weight: 500;
                }
                .countdown {
                    display: inline-block;
                    background: #4CAF50;
                    color: white;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-weight: bold;
                    margin: 16px 0;
                    font-size: 18px;
                }
                .button { 
                    display: inline-block; 
                    padding: 14px 32px; 
                    background-color: #4CAF50; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin-top: 20px; 
                    transition: all 0.3s; 
                    cursor: pointer; 
                    border: none; 
                    font-size: 16px;
                    font-weight: 600;
                    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
                }
                .button:hover { 
                    background-color: #45a049;
                    transform: translateY(-2px);
                    box-shadow: 0 6px 16px rgba(76, 175, 80, 0.5);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="success-icon">✓</div>
                <h1>Login Verified Successfully!</h1>
                <p>Your login has been verified. You're being redirected to your dashboard...</p>
                <div class="info">
                    <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($pending['email']); ?></p>
                </div>
                <p>Redirecting in <span class="countdown" id="countdown">3</span> seconds...</p>
                <button class="button" onclick="redirectNow()">Continue to Dashboard</button>
            </div>
            <script>
                const token = <?php echo json_encode($jwt); ?>;
                const user = {
                    id: <?php echo json_encode($pending['user_id']); ?>,
                    email: <?php echo json_encode($pending['email']); ?>,
                    role: <?php echo json_encode($pending['role']); ?>,
                    is_validated: <?php echo json_encode($pending['is_validated']); ?>
                };
                
                let countdown = 3;
                let redirecting = false;
                
                function detectFrontendUrl() {
                    if (window.opener) {
                        try {
                            return window.opener.location.origin;
                        } catch(e) {}
                    }
                    
                    if (document.referrer) {
                        try {
                            return new URL(document.referrer).origin;
                        } catch(e) {}
                    }
                    
                    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                        return 'http://localhost:5500';
                    }
                    
                    return 'https://yourwallet0.vercel.app';
                }
                
                const frontendUrl = detectFrontendUrl();
                
                function updateCountdown() {
                    const countdownEl = document.getElementById('countdown');
                    if (countdownEl && countdown > 0) {
                        countdownEl.textContent = countdown;
                        countdown--;
                    }
                }
                
                function redirectNow() {
                    if (redirecting) return;
                    redirecting = true;
                    
                    const redirectUrl = `${frontendUrl}/dashboard.html?token=${encodeURIComponent(token)}&userId=${encodeURIComponent(user.id)}&userEmail=${encodeURIComponent(user.email)}&userRole=${encodeURIComponent(user.role)}`;
                    
                    try {
                        localStorage.setItem('jwt', token);
                        localStorage.setItem('userId', user.id.toString());
                        localStorage.setItem('userEmail', user.email);
                        localStorage.setItem('userRole', user.role.toString());
                    } catch(e) {}
                    
                    if (window.opener && !window.opener.closed) {
                        try {
                            window.opener.postMessage({
                                type: 'login_verified',
                                token: token,
                                user: user
                            }, '*');
                            
                            setTimeout(() => {
                                window.close();
                                setTimeout(() => {
                                    window.location.href = redirectUrl;
                                }, 500);
                            }, 500);
                            return;
                        } catch(e) {}
                    }
                    
                    window.location.href = redirectUrl;
                }
                
                const countdownInterval = setInterval(() => {
                    updateCountdown();
                    if (countdown < 0) {
                        clearInterval(countdownInterval);
                        redirectNow();
                    }
                }, 1000);
                
                updateCountdown();
            </script>
        </body>
        </html>
        <?php
        exit;
        
    } else {
        // Invalid or already used token
        ob_end_clean();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Invalid Token</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
                    min-height: 100vh; 
                    display: flex; 
                    align-items: center; 
                    justify-content: center;
                    padding: 20px;
                }
                .container { 
                    background: white; 
                    padding: 40px; 
                    border-radius: 16px; 
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
                    max-width: 500px; 
                    text-align: center;
                }
                .error-icon { font-size: 64px; color: #f44336; margin-bottom: 20px; }
                h1 { color: #333; margin-bottom: 20px; font-size: 24px; }
                p { color: #666; line-height: 1.6; margin-bottom: 15px; }
                .button { 
                    display: inline-block; 
                    padding: 12px 30px; 
                    background-color: #f44336; 
                    color: white; 
                    text-decoration: none; 
                    border-radius: 8px; 
                    margin-top: 20px; 
                    cursor: pointer;
                    border: none;
                    font-size: 16px;
                }
                .button:hover { background-color: #d32f2f; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-icon">✗</div>
                <h1>Invalid Verification Token</h1>
                <p>This verification link is invalid or has already been used.</p>
                <p>Please return to the login page and try again.</p>
                <button class="button" onclick="window.close()">Close Window</button>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} else {
    ob_end_clean();
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
    exit;
}
?>