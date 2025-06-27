<?php
header("Content-Type: application/json");

// Include DB connection and required models
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/VerificationsModel.php';

/**
 * Generate a simple JWT (for demonstration purposes).
 * In production, consider using firebase/php-jwt.
 */
function generate_jwt(array $payload, string $secret, int $expiry_in_seconds = 3600): string
{
    // Set standard header and claims
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $issuedAt = time();
    $expire = $issuedAt + $expiry_in_seconds;
    $payload = array_merge($payload, [
        'iat' => $issuedAt,
        'exp' => $expire
    ]);

    // Base64Url encode header and payload
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

    // Create and encode signature
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

// Replace with your secure secret key
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY";
$response = ["status" => "error", "message" => "Something went wrong"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve input values
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    try {
        // Initialize models
        $usersModel = new UsersModel();
        $verificationsModel = new VerificationsModel();

        // Lookup user by email
        $allUsers = $usersModel->getAllUsers();
        $user = null;
        foreach ($allUsers as $u) {
            if ($u['email'] === $email) {
                $user = $u;
                break;
            }
        }

        if ($user) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Fetch user's verification status
                $verification = $verificationsModel->getVerificationByUserId($user['id']);
                $is_validated = $verification ? $verification['is_validated'] : 0;

                // Prevent login for unvalidated admin accounts
                if ($user['role'] == 1 && $is_validated == 0) {
                    $response["message"] = "Admin account is not validated. Please contact support.";
                } else {
                    // Generate JWT token with user data
                    $payload = [
                        "id" => $user["id"],
                        "email" => $user["email"],
                        "role" => $user["role"],
                        "is_validated" => $is_validated
                    ];
                    $jwt = generate_jwt($payload, $jwt_secret, 3600); // Token valid for 1 hour

                    $response = [
                        "status" => "success",
                        "message" => "Login successful",
                        "token" => $jwt,
                        "user" => [
                            "id" => $user["id"],
                            "email" => $user["email"],
                            "role" => $user["role"],
                            "is_validated" => $is_validated
                        ]
                    ];
                }
            } else {
                $response["message"] = "Invalid email or password";
            }
        } else {
            $response["message"] = "Invalid email or password";
        }
    } catch (PDOException $e) {
        $response["message"] = "Database error: " . $e->getMessage();
    }
}

echo json_encode($response);
?>