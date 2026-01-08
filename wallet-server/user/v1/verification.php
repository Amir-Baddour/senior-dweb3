<?php
ob_start();
header('Content-Type: application/json');

/* =========================
   CORS
========================= */
require_once __DIR__ . '/../../utils/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* =========================
   Includes
========================= */
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

/* =========================
   Default response
========================= */
$response = [
    'status' => 'error',
    'message' => 'Something went wrong'
];

/* =========================
   Allow only POST
========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
    exit;
}

/* =========================
   JWT Auth
========================= */
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

$decoded = verify_jwt($auth[1], 'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY');

if (!$decoded || empty($decoded['id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or expired token'
    ]);
    exit;
}

$userId = (int)$decoded['id'];

/* =========================
   File validation
========================= */
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

/* =========================
   Save file
========================= */
$uploadDir = __DIR__ . '/../../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'id_' . $userId . '_' . time() . '.' . $ext;
$filePath = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'File upload failed'
    ]);
    exit;
}

/* =========================
   Database
========================= */
$verificationsModel = new VerificationsModel();
$usersModel = new UsersModel();

$existing = $verificationsModel->getVerificationByUserId($userId);

if ($existing) {
    $verificationsModel->update(
        $existing['id'],
        $userId,
        $fileName,
        0,
        'Verification resubmitted'
    );
    $response['message'] = 'Document updated successfully. Pending admin approval.';
} else {
    $verificationsModel->create(
        $userId,
        $fileName,
        0,
        'Verification submitted'
    );
    $response['message'] = 'Document uploaded successfully. Pending admin approval.';
}

$response['status'] = 'success';

/* =========================
   EMAIL (BREVO – SAFE)
========================= */
$response['emailSent'] = false;

try {
    $user = $usersModel->getUserById($userId);
    $userEmail = $user['email'] ?? null;

    if ($userEmail) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = getenv('BREVO_SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->AuthType = 'LOGIN';

        $mail->Username = getenv('BREVO_SMTP_USER');
        $mail->Password = getenv('BREVO_SMTP_PASS');

        $mail->Port = (int)getenv('BREVO_SMTP_PORT');
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 30;

        $mail->setFrom(
            getenv('BREVO_FROM_EMAIL'),
            getenv('BREVO_FROM_NAME')
        );

        $mail->addAddress($userEmail);

        $mail->isHTML(false);
        $mail->Subject = 'Verification Document Received';
        $mail->Body =
            "Hello,\n\n" .
            "Your verification document has been received successfully.\n\n" .
            "Status: Pending admin approval.\n\n" .
            "Digital Wallet Team";

        $mail->send();
        $response['emailSent'] = true;
    }
} catch (Throwable $e) {
    $response['emailError'] = $e->getMessage();
}

/* =========================
   Response
========================= */
echo json_encode($response);
