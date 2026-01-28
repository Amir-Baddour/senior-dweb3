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

// PHPMailer (Brevo)
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
if (strlen($password) < 8) {
    $response["message"] = "Password must be at least 8 characters";
    echo json_encode($response);
    exit;
}
if (!preg_match('/[a-z]/', $password)) {
    $response["message"] = "Password must contain a lowercase letter";
    echo json_encode($response);
    exit;
}
if (!preg_match('/[A-Z]/', $password)) {
    $response["message"] = "Password must contain an uppercase letter";
    echo json_encode($response);
    exit;
}
if (!preg_match('/[0-9]/', $password)) {
    $response["message"] = "Password must contain a number";
    echo json_encode($response);
    exit;
}
if (!preg_match('/[!@#$%^&]/', $password)) {
    $response["message"] = "Password must contain a symbol (!@#$%^&)";
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
    $usersModel    = new UsersModel();
    $profilesModel = new UserProfilesModel();
    $walletsModel  = new WalletsModel();

    // ---------- Check Email Exists ----------
    foreach ($usersModel->getAllUsers() as $u) {
        if ($u['email'] === $email) {
            $response["message"] = "Email already registered";
            echo json_encode($response);
            exit;
        }
    }

    // ---------- Create User ----------
    $userId = $usersModel->create($email, $hashed_password, 0);

    // ---------- Create Profile ----------
    $name = explode('@', $email)[0];
    $profilesModel->create($userId, $name, null, '', '', '', '');

    // ---------- Create Wallet ----------
    $walletsModel->create($userId, 'USDT', 0.00);

    // ---------- Welcome Email (NON-FATAL) ----------
    $emailSent  = false;
    $emailError = null;

    try {
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {

            $loginLink = "http://localhost/digital-wallet-plateform/wallet-client/login.html";

            $subject = "Welcome to Digital Wallet";
            $htmlBody = "
                <h2>Welcome to Digital Wallet 🎉</h2>
                <p>Your account and wallet have been created successfully.</p>
                <p>Click the link below to login:</p>
                <p><a href='{$loginLink}'>Login to your account</a></p>
                <hr>
                <p>This email will be used for transaction notifications.</p>
            ";

            $altBody = "Welcome to Digital Wallet. Login here: {$loginLink}";

            // ✅ Brevo SMTP (same as deposit.php)
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
            $emailSent = true;
        }
    } catch (Throwable $e) {
        $emailError = $e->getMessage();
        error_log('register email error: ' . $emailError);
    }

    // ---------- Success ----------
    $response = [
        "status"    => "success",
        "message"   => "Registration successful. Check your email to login.",
        "emailSent" => $emailSent
    ];

} catch (Throwable $e) {
    $response["message"] = "Server error";
}

echo json_encode($response);
