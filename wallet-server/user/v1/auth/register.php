<?php
header("Content-Type: application/json");

// Include required files and models
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/UserProfilesModel.php';
require_once __DIR__ . '/../../../models/WalletsModel.php';
require_once __DIR__ . '/../../../models/VerificationsModel.php';
require_once __DIR__ . '/../../../utils/MailService.php';
require_once __DIR__ . '/../../../utils/verify_jwt.php'; // Adjust path if needed

/**
 * Generate a simple JWT.
 * In production, consider using firebase/php-jwt for robust handling.
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
    $base64Header  = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

    // Create signature
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Replace with your secure secret key
$response = ["status" => "error", "message" => "Something went wrong"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve and validate input
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

        // Check if email is already registered
        $allUsers = $usersModel->getAllUsers();
        $emailExists = false;
        foreach ($allUsers as $u) {
            if ($u['email'] === $email) {
                $emailExists = true;
                break;
            }
        }

        if ($emailExists) {
            $response["message"] = "Email is already registered";
        } else {
            // Create a new user (role=0 for normal users)
            $user_id = $usersModel->create($email, $hashed_password, 0);

            // Use portion of email before '@' as default full name
            $fullName = explode('@', $email)[0];

            // Insert default user profile
            $userProfilesModel->create(
                $user_id,
                $fullName,
                null,   // date_of_birth
                '',     // phone_number
                '',     // street_address
                '',     // city
                ''      // country
            );

            // Create a wallet record with a starting balance of 0
            $walletsModel->create($user_id, 0.00);

            // Create a verification record with is_validated = 0 and no id_document
            $verificationsModel->create(
                $user_id,
                null,   // id_document
                0,      // is_validated
                'User not verified yet'
            );

            // Generate a JWT token valid for 1 hour
            $payload = [
                "id" => $user_id,
                "email" => $email,
                "role" => 0 // normal user
            ];
            $jwt = generate_jwt($payload, $jwt_secret, 3600);

            // Return success response with token and user info
            $response = [
                "status" => "success",
                "message" => "Registration successful",
                "token" => $jwt,
                "user" => [
                    "id" => $user_id,
                    "email" => $email,
                    "role" => 0
                ]
            ];
        }
    } catch (PDOException $e) {
        $response["message"] = "Database error: " . $e->getMessage();
    }
}

echo json_encode($response);
?>