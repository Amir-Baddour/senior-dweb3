<?php
ob_start();
header('Content-Type: application/json');

/* ===============================
   CORS
================================ */
require_once __DIR__ . '/../../utils/cors.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* ===============================
   Core includes
================================ */
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

/* ===============================
   Composer autoload
================================ */
require_once __DIR__ . '/../../../vendor/autoload.php';

/* ===============================
   Default response
================================ */
$response = [
    'status' => 'error',
    'message' => 'Unexpected error'
];

/* ===============================
   JWT authentication
================================ */
$headers = getallheaders();

if (empty($headers['Authorization'])) {
    echo json_encode(['status'=>'error','message'=>'Authorization header missing']);
    exit;
}

$parts = explode(' ', $headers['Authorization']);
$decoded = verify_jwt($parts[1] ?? '', 'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY');

if (!$decoded || empty($decoded['id'])) {
    echo json_encode(['status'=>'error','message'=>'Invalid or expired token']);
    exit;
}

$userId = (int)$decoded['id'];

/* ===============================
   File validation
================================ */
if (empty($_FILES['id_document'])) {
    echo json_encode(['status'=>'error','message'=>'No file uploaded']);
    exit;
}

$file = $_FILES['id_document'];

if ($file['size'] > 2 * 1024 * 1024) {
    echo json_encode(['status'=>'error','message'=>'File too large']);
    exit;
}

/* ===============================
   Save file
================================ */
$uploadDir = __DIR__ . '/../../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'id_' . $userId . '_' . time() . '.' . $ext;
$filePath = $uploadDir . $fileName;

move_uploaded_file($file['tmp_name'], $filePath);

/* ===============================
   Database
================================ */
$verifications = new VerificationsModel();
$usersModel = new UsersModel();

$existing = $verifications->getVerificationByUserId($userId);

if ($existing) {
    $verifications->update(
        $existing['id'],
        $userId,
        $fileName,
        0,
        'Verification resubmitted'
    );
    $response['message'] = 'Document updated successfully. Pending admin approval.';
} else {
    $verifications->create(
        $userId,
        $fileName,
        0,
        'Verification submitted'
    );
    $response['message'] = 'Document uploaded successfully. Pending admin approval.';
}

$response['status'] = 'success';

/* ===============================
   EMAIL (BREVO – REAL DELIVERY)
================================ */
$response['emailSent'] = false;

try {
    $user = $usersModel->getUserById($userId);
    $userEmail = $user['email'] ?? null;

    if ($userEmail) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = 'smtp-relay.brevo.com';
        $mail->SMTPAuth = true;

        // 🔑 Brevo SMTP credentials
        $mail->Username = '9f9f14001@smtp-brevo.com';
        $mail->Password = 'RKWndDBs/phYKfG2';

        $mail->Port = 587;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';

        // Must be a verified sender in Brevo
        $mail->setFrom('amirbaddour675@gmail.com', 'Digital Wallet');
        $mail->addAddress($userEmail);

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

echo json_encode($response);
