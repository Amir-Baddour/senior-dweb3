<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");


$client_id = getenv('795971820374-r745mbqe2tkget78iqo6fv29hlpapdpk.apps.googleusercontent.com');
$client_secret =getenv('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');
$redirect_uri = "http://localhost/digital-wallet-plateform/wallet-client/oauth2-callback.html";

$data = json_decode(file_get_contents("php://input"), true);
$code = $data['code'] ?? null;

if (!$code) {
    echo json_encode(["status" => "error", "message" => "No code received"]);
    exit;
}

// Exchange code for access token
$token_response = file_get_contents("https://oauth2.googleapis.com/token", false, stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query([
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code'
        ])
    ]
]));

$token_data = json_decode($token_response, true);
$access_token = $token_data['access_token'] ?? null;

if (!$access_token) {
    echo json_encode(["status" => "error", "message" => "Token exchange failed"]);
    exit;
}

// Fetch user info
$user_info = json_decode(file_get_contents("https://www.googleapis.com/oauth2/v1/userinfo?alt=json&access_token=$access_token"), true);
$email = $user_info['email'] ?? null;

if ($email) {
    require_once __DIR__ . '/../../../connection/db.php';
    require_once __DIR__ . '/../../../models/UsersModel.php';

    $usersModel = new UsersModel();
    $user = null;
    foreach ($usersModel->getAllUsers() as $u) {
        if ($u['email'] === $email) {
            $user = $u;
            break;
        }
    }

    if ($user) {
        // Login existing user
        $payload = [
            "id" => $user["id"],
            "email" => $user["email"],
            "role" => $user["role"]
        ];

        $jwt = generate_jwt($payload, "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY", 3600);
        echo json_encode([
            "status" => "success",
            "token" => $jwt
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "No matching account found"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "User info not retrieved"]);
}

function generate_jwt($payload, $secret, $exp) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $payload['iat'] = $issuedAt;
    $payload['exp'] = $issuedAt + $exp;

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
    $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $secret, true);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
}
?>
