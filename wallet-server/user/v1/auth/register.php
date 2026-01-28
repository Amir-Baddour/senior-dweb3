<?php
ob_start();
require_once __DIR__ . '/../../../utils/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ---------- Includes ----------
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/UserProfilesModel.php';
require_once __DIR__ . '/../../../models/WalletsModel.php';

// ---------- Default Response ----------
$response = [
    "status" => "error",
    "message" => "Something went wrong"
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode($response);
    exit;
}

// ---------- Get Inputs ----------
$email = trim($_POST["email"] ?? '');
$password = $_POST["password"] ?? '';
$confirm_password = $_POST["confirm_password"] ?? '';

// ---------- Email Validation ----------
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response["message"] = "Invalid email format";
    echo json_encode($response);
    exit;
}

// ---------- Password Validation ----------
if (strlen($password) < 8) {
    $response["message"] = "Password must be at least 8 characters";
    echo json_encode($response);
    exit;
}

if (!preg_match('/[A-Za-z]/', $password)) {
    $response["message"] = "Password must contain at least one letter";
    echo json_encode($response);
    exit;
}

if (!preg_match('/[0-9]/', $password)) {
    $response["message"] = "Password must contain at least one number";
    echo json_encode($response);
    exit;
}

// ---------- Password Match ----------
if ($password !== $confirm_password) {
    $response["message"] = "Passwords do not match";
    echo json_encode($response);
    exit;
}

// ---------- Hash Password ----------
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $usersModel = new UsersModel();
    $profilesModel = new UserProfilesModel();
    $walletsModel = new WalletsModel();

    // ---------- Check Email Exists ----------
    $users = $usersModel->getAllUsers();
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            $response["message"] = "Email already registered";
            echo json_encode($response);
            exit;
        }
    }

    // ---------- Create User ----------
    $user_id = $usersModel->create($email, $hashed_password, 0);

    // ---------- Create Profile ----------
    $name = explode('@', $email)[0];
    $profilesModel->create($user_id, $name, null, '', '', '', '');

    // ---------- Create Wallet ----------
    $walletsModel->create($user_id, 0.00, 0.00);

    // ---------- Success ----------
    $response = [
        "status" => "success",
        "message" => "Registration successful"
    ];

} catch (Exception $e) {
    $response["message"] = "Server error";
}

echo json_encode($response);
