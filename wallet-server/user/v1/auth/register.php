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
require_once __DIR__ . '/../../../models/VerificationsModel.php';

// PHPMailer (Brevo SMTP)
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
    $response["message"] = "Invalid email format";
    echo json_encode($response);
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
    $response["message"] = "Password must contain upper, lower, number and symbol";
    echo json_encode($response);
    exit;
}

// ---------- Password Match ----------
if ($password !== $confirm_password) {
    $response["message"] = "Passwords do not match";
    echo json_encode($response);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    $usersModel         = new UsersModel();
    $profilesModel      = new UserProfilesModel();
    $walletsModel       = new WalletsModel();
    $verificationsModel = new VerificationsModel();

    // ---------- Check Duplicate Email ----------
    foreach ($usersModel->getAllUsers() as $u) {
        if ($u['email'] === $email) {
            $response["message"] = "Email already registered";
            echo json_encode($response);
            exit;
        }
    }

    // ---------- Create User ----------
    $userId = $usersModel->create($email, $hashedPassword, 0);

    // ---------- Create Profile ----------
    $name = explode('@', $email)[0];
    $profilesModel->create($userId, $name, null, '', '', '', '');

    // ---------- Create Wallet ----------
    $walletsModel->create($userId, 'USDT', 0.00);

    // ---------- Email Verification Record ----------
    $token = bin2hex(random_bytes(16));
    $verificationsModel->create($userId, $token, 0, 'Email not verified');

    // ---------- Verification Link ----------
    $verifyLink =
        ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') .
        '://' . $_SERVER['HTTP_HOST'] .
        '/digital-wallet-plateform/wallet-server/user/v1/auth/verify_email.php?token=' . $token;

    // ---------- Send Verification Email (NON-FATAL) ----------
    try {
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {

            $subject = "Verify your email";
            $htmlBody = "
                <h2>Welcome to Digital Wallet</h2>
                <p>Please verify your email to activate your account.</p>
                <p><a href='{$verifyLink}'>Verify Email</a></p>
                <p>If you did not register, ignore this email.</p>
            ";

            $altBody = "Verify your email: {$verifyLink}";

            // Brevo SMTP (same as deposit.php)
            $brevoLogin    = '9f9f14001@smtp-brevo.com';
            $brevoPassword = 'RkWndDBs7phYKfG2';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host       = 'smtp-relay.brevo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $brevoLogin;
            $mail->Password   = $brevoPassword;
            $mail->Port       = 587;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('amirbaddour675@gmail.com', 'Digital Wallet');
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $altBody;

            $mail->send();
        }
    } catch (Throwable $e) {
        error_log('register verification email error: ' . $e->getMessage());
    }

    // ---------- Success ----------
    $response = [
        "status"  => "success",
        "message" => "Registration successful. Please verify your email before login."
    ];

} catch (Throwable $e) {
    $response["message"] = "Server error";
}

echo json_encode($response);
