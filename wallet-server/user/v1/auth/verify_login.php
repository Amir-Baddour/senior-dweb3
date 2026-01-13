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
 * Get pending login from file
 */
function get_pending_login($token) {
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

/**
 * Determine the frontend URL based on the request
 */
function get_frontend_url() {
    $host = $_SERVER['HTTP_HOST'];
    
    if (strpos($host, 'trycloudflare.com') !== false || strpos($host, 'cloudflare.com') !== false) {
        return 'https://yourwallet0.vercel.app';
    }
    
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        return 'http://localhost:5500';
    }
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $host;
}

$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['token'])) {
    $token = $_GET['token'];
    
    error_log("Verification attempt with token: " . $token);
    
    $pending = get_pending_login($token);
    
    if ($pending) {
        if (time() <= $pending['expiry']) {
            $payload = [
                "id" => $pending["user_id"],
                "email" => $pending["email"],
                "role" => $pending["role"],
                "is_validated" => $pending["is_validated"]
            ];
            $jwt = generate_jwt($payload, $jwt_secret, 3600);
            
            $frontend_url = get_frontend_url();
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
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
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
                    .button:active {
                        transform: translateY(0);
                    }
                    .loading {
                        display: inline-block;
                        margin-left: 8px;
                    }
                    .spinner {
                        border: 2px solid #f3f3f3;
                        border-top: 2px solid #4CAF50;
                        border-radius: 50%;
                        width: 16px;
                        height: 16px;
                        animation: spin 1s linear infinite;
                        display: inline-block;
                        vertical-align: middle;
                    }
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="success-icon">✓</div>
                    <h1>Login Verified Successfully!</h1>
                    <p>Your login has been verified and authenticated.</p>
                    <div class="info">
                        <p><strong>Logged in as:</strong> <?php echo htmlspecialchars($pending['email']); ?></p>
                    </div>
                    <p>Redirecting in <span class="countdown" id="countdown">3</span> seconds...</p>
                    <button class="button" onclick="redirectNow()">
                        Continue to Dashboard
                        <span class="loading" id="loading" style="display:none;">
                            <span class="spinner"></span>
                        </span>
                    </button>
                </div>
                <script>
                    const token = <?php echo json_encode($jwt); ?>;
                    const user = {
                        id: <?php echo json_encode($pending['user_id']); ?>,
                        email: <?php echo json_encode($pending['email']); ?>,
                        role: <?php echo json_encode($pending['role']); ?>,
                        is_validated: <?php echo json_encode($pending['is_validated']); ?>
                    };
                    const frontendUrl = <?php echo json_encode($frontend_url); ?>;
                    
                    let countdown = 3;
                    let redirecting = false;
                    
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
                        
                        console.log('🚀 Starting redirect process...');
                        
                        // Show loading state
                        const button = document.querySelector('.button');
                        const loading = document.getElementById('loading');
                        if (button) {
                            button.disabled = true;
                            button.style.opacity = '0.7';
                        }
                        if (loading) loading.style.display = 'inline-block';
                        
                        // Build redirect URL with token as query parameter
                        const redirectUrl = `${frontendUrl}/dashboard.html?token=${encodeURIComponent(token)}&userId=${encodeURIComponent(user.id)}&userEmail=${encodeURIComponent(user.email)}&userRole=${encodeURIComponent(user.role)}`;
                        
                        console.log('📍 Redirect URL:', redirectUrl);
                        console.log('🔑 Token:', token);
                        console.log('👤 User:', user);
                        
                        // Attempt to save to localStorage (will fail cross-origin, but try anyway)
                        try {
                            localStorage.setItem('jwt', token);
                            localStorage.setItem('userId', user.id.toString());
                            localStorage.setItem('userEmail', user.email);
                            localStorage.setItem('userRole', user.role.toString());
                            console.log('✅ Saved to localStorage (same origin)');
                        } catch(e) {
                            console.log('ℹ️ Cannot use localStorage (cross-origin):', e.message);
                        }
                        
                        // If opened as popup, try to communicate with parent window
                        if (window.opener && !window.opener.closed) {
                            try {
                                console.log('📤 Sending message to parent window...');
                                window.opener.postMessage({
                                    type: 'login_verified',
                                    token: token,
                                    user: user
                                }, '*');
                                console.log('✅ Message sent to parent');
                                
                                // Wait for parent to receive message, then close
                                setTimeout(() => {
                                    console.log('🔒 Closing popup...');
                                    window.close();
                                    
                                    // Fallback: if window didn't close, redirect
                                    setTimeout(() => {
                                        console.log('⚠️ Popup did not close, redirecting...');
                                        window.location.href = redirectUrl;
                                    }, 500);
                                }, 500);
                                return;
                            } catch(e) {
                                console.error('❌ Failed to communicate with parent:', e);
                            }
                        }
                        
                        // Direct redirect (main flow)
                        console.log('➡️ Performing direct redirect...');
                        window.location.href = redirectUrl;
                    }
                    
                    // Start countdown
                    const countdownInterval = setInterval(() => {
                        updateCountdown();
                        if (countdown < 0) {
                            clearInterval(countdownInterval);
                            redirectNow();
                        }
                    }, 1000);
                    
                    // Initial countdown display
                    updateCountdown();
                </script>
            </body>
            </html>
            <?php
        } else {
            // Token expired
            $frontend_url = get_frontend_url();
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Verification Expired</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
                    .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; text-align: center; }
                    .error-icon { font-size: 64px; color: #f44336; margin-bottom: 20px; }
                    h1 { color: #333; margin-bottom: 20px; }
                    p { color: #666; line-height: 1.6; margin-bottom: 20px; }
                    .button { display: inline-block; padding: 12px 30px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; transition: background-color 0.3s; }
                    .button:hover { background-color: #45a049; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="error-icon">⏱</div>
                    <h1>Verification Link Expired</h1>
                    <p>This verification link has expired (valid for 15 minutes). Please try logging in again to receive a new verification link.</p>
                    <a href="<?php echo htmlspecialchars($frontend_url); ?>/login.html" class="button">Back to Login</a>
                </div>
            </body>
            </html>
            <?php
        }
    } else {
        // Invalid or already used token
        $frontend_url = get_frontend_url();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Invalid Token</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
                .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); max-width: 500px; text-align: center; }
                .error-icon { font-size: 64px; color: #f44336; margin-bottom: 20px; }
                h1 { color: #333; margin-bottom: 20px; }
                p { color: #666; line-height: 1.6; margin-bottom: 20px; }
                .button { display: inline-block; padding: 12px 30px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; transition: background-color 0.3s; }
                .button:hover { background-color: #45a049; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-icon">✗</div>
                <h1>Invalid Verification Token</h1>
                <p>This verification link is invalid or has already been used. Please try logging in again to receive a new verification link.</p>
                <a href="<?php echo htmlspecialchars($frontend_url); ?>/login.html" class="button">Back to Login</a>
            </div>
        </body>
        </html>
        <?php
    }
} else {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Invalid request"]);
}
?>