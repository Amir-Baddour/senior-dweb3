<?php
header("Content-Type: application/json");

// Include database connection and UsersModel
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';

/**
 * Minimal JWT generation function.
 * For production, consider using a library like firebase/php-jwt.
 *
 * @param array $payload The data to encode in the token.
 * @param string $secret The secret key used for signing.
 * @param int $expiry_in_seconds The token's validity period in seconds.
 * @return string The generated JWT.
 */
function generate_jwt(array $payload, string $secret, int $expiry_in_seconds = 3600): string {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $expire = $issuedAt + $expiry_in_seconds;

    // Merge standard claims into the payload
    $payload = array_merge($payload, [
        'iat' => $issuedAt,
        'exp' => $expire
    ]);

    // Encode header and payload using Base64Url encoding
    $base64Header  = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

    // Create and encode the signature
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

// Secure secret key (preferably set via environment variables)
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";

// Default response
$response = ["status" => "error", "message" => "Something went wrong"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve input data
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    try {
        // Initialize the UsersModel and search for the user by email
        $usersModel = new UsersModel();
        $user = null;
        $allUsers = $usersModel->getAllUsers();
        foreach ($allUsers as $u) {
            if ($u['email'] === $email) {
                $user = $u;
                break;
            }
        }

        // Check if user exists and is an admin (role == 1)
        if ($user && $user['role'] == 1) {
            if (password_verify($password, $user['password'])) {
                // Generate a JWT token for successful admin login
                $payload = [
                    "id"    => $user["id"],
                    "email" => $user["email"],
                    "role"  => $user["role"]
                ];
                $token = generate_jwt($payload, $jwt_secret, 3600); // Token valid for 1 hour

                $response = ["status" => "success", "message" => "Login successful", "token" => $token];
            } else {
                $response["message"] = "Invalid email or password";
            }
        } else {
            $response["message"] = "Access denied. Only admins can log in.";
        }
    } catch (PDOException $e) {
        $response["message"] = "Database error: " . $e->getMessage();
    }
}

echo json_encode($response);
?>