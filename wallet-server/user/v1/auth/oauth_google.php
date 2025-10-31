<?php
/**
 * Verify Google ID token and issue your app's JWT.
 * Compatible with current `users` table:
 * id, email, password, role (0/1), is_validated (0/1), created_at, updated_at
 */

header("Content-Type: application/json");

// === CONFIG ===
$GOOGLE_CLIENT_ID = "795971820374-r745mbqe2tkget78iqo6fv29hlpapdpk.apps.googleusercontent.com"; // must match login.html
$JWT_SECRET       = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // use env in production
$AUTO_PROVISION   = true; // set to false if you want to require pre-existing users

// === INCLUDES ===
require_once __DIR__ . '/../../../connection/db.php';           // exposes $conn (PDO)
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/VerificationsModel.php';

// === HELPERS ===
function json_fail($message, $code = 400){
    http_response_code($code);
    echo json_encode(["status"=>"error","message"=>$message], JSON_UNESCAPED_SLASHES);
    exit;
}

/** Minimal JWT (same style as login.php) */
function generate_jwt(array $payload, string $secret, int $expiry_in_seconds = 3600): string {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $expire   = $issuedAt + $expiry_in_seconds;
    $payload  = array_merge($payload, ['iat'=>$issuedAt, 'exp'=>$expire]);

    $b64 = fn($s) => str_replace(['+','/','='], ['-','_',''], base64_encode($s));
    $h   = $b64($header);
    $p   = $b64(json_encode($payload));
    $sig = $b64(hash_hmac('sha256', "$h.$p", $secret, true));
    return "$h.$p.$sig";
}

function http_get_json($url){
    // Prefer cURL; fallback to file_get_contents if allowed
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) { curl_close($ch); return null; }
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) return null;
        return json_decode($resp, true);
    } else {
        $resp = @file_get_contents($url);
        if ($resp === false) return null;
        return json_decode($resp, true);
    }
}

// === INPUT ===
// Expect JSON: { "credential": "<google_id_token>" }
$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);
$id_token = $data['credential'] ?? null;
if (!$id_token) json_fail("Missing Google credential (id_token).");

// === VERIFY GOOGLE ID TOKEN ===
// Simple approach via tokeninfo. For high-scale apps, verify JWT signature locally.
$tokenInfo = http_get_json("https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token));
if (!$tokenInfo) json_fail("Unable to verify Google token.", 401);

$aud = $tokenInfo['aud'] ?? null;
$iss = $tokenInfo['iss'] ?? null;
$exp = isset($tokenInfo['exp']) ? (int)$tokenInfo['exp'] : 0;

if ($aud !== $GOOGLE_CLIENT_ID) json_fail("Audience mismatch.", 401);
if (!in_array($iss, ["accounts.google.com","https://accounts.google.com"], true)) json_fail("Invalid issuer.", 401);
if ($exp <= time()) json_fail("ID token expired.", 401);

// Extract minimal info
$email          = $tokenInfo['email'] ?? null;
$email_verified = filter_var($tokenInfo['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
if (!$email || !$email_verified) json_fail("Email not available or not verified.", 401);

// === LOAD / CREATE USER ===
try {
    // Models (mirror your login.php pattern)
    $usersModel = new UsersModel();
    $verModel   = new VerificationsModel();

    // Find user by email using the same approach your login.php uses
    $allUsers = $usersModel->getAllUsers();
    $user = null;
    foreach ($allUsers as $u) {
        if (isset($u['email']) && $u['email'] === $email) { $user = $u; break; }
    }

    if (!$user) {
        if (!$AUTO_PROVISION) {
            json_fail("No account associated with this Google email. Please sign up first.", 409);
        }

        // Auto-provision with minimal fields your table supports
        // password: random hash; role: 0 (user); is_validated: default 0 per schema
        $randomHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        // Use $conn from db.php
        if (!isset($conn) || !($conn instanceof PDO)) {
            json_fail("Database connection not available.", 500);
        }

        $stmt = $conn->prepare("
            INSERT INTO users (email, password, role)
            VALUES (:email, :password, :role)
        ");
        $stmt->execute([
            ':email'    => $email,
            ':password' => $randomHash,
            ':role'     => 0 // normal user
        ]);

        $newId = (int)$conn->lastInsertId();

        // Fetch inserted user back
        $stmt = $conn->prepare("SELECT id, email, password, role, is_validated FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $newId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) json_fail("User auto-provision failed.", 500);
    }

    // === VERIFICATION RULES (align with your login.php) ===
    // Your login.php reads verification from VerificationsModel (not users.is_validated).
    $verification = $verModel->getVerificationByUserId($user['id']);
    $is_validated = $verification ? (int)$verification['is_validated'] : 0;

    // Block unvalidated admins, as in login.php
    if ((int)$user['role'] === 1 && $is_validated === 0) {
        json_fail("Admin account is not validated. Please contact support.", 403);
    }

    // === ISSUE YOUR APP'S JWT ===
    $payload = [
        "id"           => (int)$user["id"],
        "email"        => $user["email"],
        "role"         => (int)$user["role"],
        "is_validated" => $is_validated
    ];
    $jwt = generate_jwt($payload, $JWT_SECRET, 3600); // 1 hour

    echo json_encode([
        "status"  => "success",
        "message" => "Login successful (Google)",
        "token"   => $jwt,
        "user"    => [
            "id"           => (int)$user["id"],
            "email"        => $user["email"],
            "role"         => (int)$user["role"],
            "is_validated" => $is_validated
        ]
    ], JSON_UNESCAPED_SLASHES);
    exit;

} catch (Throwable $e) {
    json_fail("Server error: " . $e->getMessage(), 500);
}
