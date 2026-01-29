<?php
ob_start();
require_once __DIR__ . '/../../../utils/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ---------- Includes ----------
require_once __DIR__ . '/../../../connection/db.php';
require_once __DIR__ . '/../../../models/UsersModel.php';
require_once __DIR__ . '/../../../models/UserProfilesModel.php';
require_once __DIR__ . '/../../../models/WalletsModel.php';

// PHPMailer
$autoload = __DIR__ . '/../../../../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$response = [
    "status" => "error",
    "message" => "Something went wrong"
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode($response);
    exit;
}

// ---------- Input ----------
$email            = trim($_POST["email"] ?? '');
$password         = $_POST["password"] ?? '';
$confirm_password = $_POST["confirm_password"] ?? '';

// ---------- Email Validation ----------
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid email format"
    ]);
    exit;
}

// ---------- Password Validation ----------
if (
    strlen($password) < 8 ||
    !preg_match('/[a-z]/', $password) ||
    !preg_match('/[A-Z]/', $password) ||
    !preg_match('/[0-9]/', $password) ||
    !preg_match('/[!@#$%^&]/', $password)
) {
    echo json_encode([
        "status" => "error",
        "message" => "Password must contain upper, lower, number and symbol"
    ]);
    exit;
}

// ---------- Password Match ----------
if ($password !== $confirm_password) {
    echo json_encode([
        "status" => "error",
        "message" => "Passwords do not match"
    ]);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $usersModel    = new UsersModel();
    $profilesModel = new UserProfilesModel();
    $walletsModel  = new WalletsModel();

    // ---------- Check Duplicate Email ----------
    foreach ($usersModel->getAllUsers() as $u) {
        if ($u['email'] === $email) {
            echo json_encode([
                "status" => "error",
                "message" => "Email already registered"
            ]);
            exit;
        }
    }

    // ---------- Create User ----------
    $userId = $usersModel->create($email, $hashedPassword, 1);

    // ---------- Create Profile ----------
    $name = explode('@', $email)[0];
    $profilesModel->create($userId, $name, null, '', '', '', '');

    // ---------- Create Wallet ----------
    $walletsModel->create($userId, 'USDT', 0.00);

    // ---------- Send Welcome Email ----------
    try {
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {

            $loginLink = "https://yourwallet0.vercel.app/login.html";

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = '9f9f14001@smtp-brevo.com';
            $mail->Password   = 'RkWndDBs7phYKfG2';
            $mail->Port       = 587;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('amirbaddour675@gmail.com', 'Digital Wallet');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = "Welcome to Digital Wallet 🎉";

            $mail->Body = "
                <h2>Welcome to Digital Wallet</h2>
                <p>Your account has been created successfully.</p>
                <p>
                    <a href='{$loginLink}'
                       style='display:inline-block;
                              padding:12px 20px;
                              background:#2563eb;
                              color:#ffffff;
                              text-decoration:none;
                              border-radius:6px;'>
                        Login to your account
                    </a>
                </p>
                <p>If you did not register, please ignore this email.</p>
            ";

            $mail->AltBody = "Login here: {$loginLink}";
            $mail->send();
        }
    } catch (Throwable $e) {
        error_log("Register email error: " . $e->getMessage());
    }

    echo json_encode([
        "status" => "success",
        "message" => "Registration successful! Check your email and login."
    ]);

} catch (Throwable $e) {
    error_log("Register error: " . $e->getMessage());
    echo json_encode($response);
}
