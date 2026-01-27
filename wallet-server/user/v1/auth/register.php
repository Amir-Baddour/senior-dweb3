<?php
ob_start();
require_once __DIR__ . '/../../../utils/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files and models
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/UserProfilesModel.php';
require_once __DIR__ . '/../../../models/WalletsModel.php';
require_once __DIR__ . '/../../../models/VerificationsModel.php';
require_once __DIR__ . '/../../../utils/MailService.php';
require_once __DIR__ . '/../../../utils/verify_jwt.php';

/**
 * Generate a simple JWT.
 */

function generate_jwt(array $payload, string $secret, int $expiry_in_seconds = 3600): string
{
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $expire   = $issuedAt + $expiry_in_seconds;

    // Add standard fields to payload
    $payload = array_merge($payload, [
        'iat' => $issuedAt,
        'exp' => $expire
    ]);

    // Base64Url encode header and payload
    $base64Header  = str_replace(['+', '/', '='], ['-', '', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '', ''], base64_encode(json_encode($payload)));

    // Create signature
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '', ''], base64_encode($signature));

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; 
$response = ["status" => "error", "message" => "Something went wrong"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["message"] = "Invalid email format";
        echo json_encode($response);
        exit;
    }
    if (strlen($password) < 6) {
        $response["message"] = "Password must be at least 6 characters";
        echo json_encode($response);
        exit;
    }
    if ($password !== $confirm_password) {
        $response["message"] = "Passwords do not match";
        echo json_encode($response);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Initialize models
        $usersModel = new UsersModel();
        $userProfilesModel = new UserProfilesModel();
        $walletsModel = new WalletsModel();
        $verificationsModel = new VerificationsModel();

        // Check for duplicate email
        $allUsers = $usersModel->getAllUsers();
        foreach ($allUsers as $u) {
            if ($u['email'] === $email) {
                $response["message"] = "Email is already registered";
                echo json_encode($response);
                exit;
            }
        }

        // Create user
        $user_id = $usersModel->create($email, $hashed_password, 0);
        $fullName = explode('@', $email)[0];

        $userProfilesModel->create($user_id, $fullName, null, '', '', '', '');
        $walletsModel->create($user_id, 0.00, 0.00);
        $verificationsModel->create($user_id, null, 0, 'User not verified yet');

        // Generate JWT
        $payload = ["id" => $user_id, "email" => $email, "role" => 0];
        $jwt = generate_jwt($payload, $jwt_secret, 3600);

        // Generate QR Code as base64 string
        $qrBase64 = MailService::generatePaymentQrBase64($user_id, 10.0);

        // Optionally save QR code image to server (public folder or logs)
        $qrDir = __DIR__ . '/../../../qrcodes/';
        if (!file_exists($qrDir)) {
            mkdir($qrDir, 0777, true);
        }
        file_put_contents($qrDir . "user_{$user_id}.png", base64_decode($qrBase64));

        // Final response
        $response = [
            "status" => "success",
            "message" => "Registration successful",
            "token" => $jwt,
            "user" => [
                "id" => $user_id,
                "email" => $email,
                "role" => 0
            ],
            "qr_code" => $qrBase64 // Optional: for frontend display
        ];
    } catch (PDOException $e) {
        $response["message"] = "Database error: " . $e->getMessage();
    }
}

echo json_encode($response);
?>
