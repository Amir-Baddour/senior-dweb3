<?php

require_once __DIR__ . '/../../../utils/cors.php';
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';

/**
 * Generate a simple JWT (demo-safe).
 * In production, use firebase/php-jwt.
 */
function generate_jwt(array $payload, string $secret, int $expiry_in_seconds = 3600): string
{
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $expire   = $issuedAt + $expiry_in_seconds;

    $payload = array_merge($payload, [
        'iat' => $issuedAt,
        'exp' => $expire
    ]);

    $base64Header  = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $base64Payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');

    $signature = hash_hmac(
        'sha256',
        $base64Header . "." . $base64Payload,
        $secret,
        true
    );

    $base64Signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

// 🔐 Change this in production
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";

$response = [
    "status"  => "error",
    "message" => "Something went wrong"
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode($response);
    exit;
}

// ---------- Input ----------
$email    = trim($_POST["email"] ?? '');
$password = $_POST["password"] ?? '';

if (!$email || !$password) {
    echo json_encode([
        "status"  => "error",
        "message" => "Email and password are required"
    ]);
    exit;
}

try {
    $usersModel = new UsersModel();

    // ---------- Find user by email ----------
    $user = null;
    foreach ($usersModel->getAllUsers() as $u) {
        if ($u['email'] === $email) {
            $user = $u;
            break;
        }
    }

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode([
            "status"  => "error",
            "message" => "Invalid email or password"
        ]);
        exit;
    }

    // ---------- Generate JWT ----------
    $payload = [
        "id"    => $user["id"],
        "email" => $user["email"],
        "role"  => $user["role"]
    ];

    $jwt = generate_jwt($payload, $jwt_secret, 3600);

    echo json_encode([
        "status"  => "success",
        "message" => "Login successful",
        "token"   => $jwt,
        "user"    => [
            "id"    => $user["id"],
            "email" => $user["email"],
            "role"  => $user["role"]
        ]
    ]);

} catch (Throwable $e) {
    error_log("Login error: " . $e->getMessage());
    echo json_encode([
        "status"  => "error",
        "message" => "Server error"
    ]);
}
