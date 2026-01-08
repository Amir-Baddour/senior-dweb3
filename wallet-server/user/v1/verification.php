<?php
ob_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../../utils/cors.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* ===============================
   Core dependencies
================================ */
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

/* ===============================
   Composer autoload (CORRECT PATH)
================================ */
$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) {
    error_log('[verification.php] Composer autoload not found');
    echo json_encode([
        'status' => 'error',
        'message' => 'Server configuration error'
    ]);
    exit;
}
require_once $autoload;

/* ===============================
   Default response
================================ */
$response = [
    'status' => 'error',
    'message' => 'Something went wrong'
];

/* ===============================
   Only POST allowed
================================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

/* ===============================
   JWT authentication
================================ */
$headers = getallheaders();

if (empty($headers['Authorization'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Authorization header missing'
    ]);
    exit;
}

$auth = explode(' ', $headers['Authorization']);
if (count($auth) !== 2 || $auth[0] !== 'Bearer') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid token format'
    ]);
    exit;
}

$jwtSecret = 'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY';
$decoded = verify_jwt($auth[1], $jwtSecret);

if (!$decoded || empty($decoded['id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or expired token'
    ]);
    exit;
}

$userId = (int)$decoded['id'];

/* ===============================
   Validate uploaded file
================================ */
if (empty($_FILES['id_document'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No file uploaded'
    ]);
    exit;
}

$file = $_FILES['id_document'];
$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid file type'
    ]);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode([
        'status' => 'error',
        'message' => 'File too large (max 2MB)'
    ]);
    exit;
}

/* ===============================
   Store file
================================ */
$uploadDir = __DIR__ . '/../../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName  = 'id_' . $userId . '_' . time() . '.' . $extension;
$filePath  = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'File upload failed'
    ]);
    exit;
}

/* ===============================
   Database update
================================ */
$verifications = new VerificationsModel();
$usersModel    = new UsersModel();

$existing = $verifications->getVerificationByUserId($userId);

if ($existing) {
    $saved = $verifications->update(
        $existing['id'],
        $userId,
        $fileName,
        0,
        'Verification resubmitted'
    );
    $response['message'] = 'Document updated successfully. Pending admin approval.';
} else {
    $saved = $verifications->create(
        $userId,
        $fileName,
        0,
        'Verification submitted'
    );
    $response['message'] = 'Document uploaded successfully. Pending admin approval.';
}

if (!$saved) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error'
    ]);
    exit;
}

$response['status'] = 'success';

/* ===============================
   Send email (Gmail-safe)
================================ */
$response['emailSent'] = false;

try {
    $user = $usersModel->getUserById($userId);
    $userEmail = $user['email'] ?? null;

    if ($userEmail && class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'amirbaddour675@gmail.com';
        $mail->Password = 'lqtkykunvmmuhsvj';
        $mail->Port = 587;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom('amirbaddour675@gmail.com', 'Digital Wallet');
        $mail->addReplyTo('amirbaddour675@gmail.com', 'Digital Wallet');
        $mail->Sender = 'amirbaddour675@gmail.com';

        $mail->addAddress($userEmail);
        $mail->isHTML(true);

        $mail->Subject = 'Verification Document Received';
        $mail->Body = "
            <p>Hello,</p>
            <p>Your verification document has been received.</p>
            <p><strong>File:</strong> {$fileName}</p>
            <p>Status: Pending review.</p>
        ";
        $mail->AltBody = "Your verification document has been received.";

        $mail->send();
        $response['emailSent'] = true;
    }
} catch (Throwable $e) {
    error_log('[verification.php] Email error: ' . $e->getMessage());
    $response['emailError'] = $e->getMessage();
}

/* ===============================
   Final response
================================ */
echo json_encode($response);
