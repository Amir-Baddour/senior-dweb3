<?php
ob_start();
header('Content-Type: application/json');

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
   Composer autoload (CORRECT)
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
   JWT
================================ */
$headers = getallheaders();

if (empty($headers['Authorization'])) {
    echo json_encode(['status'=>'error','message'=>'Missing Authorization header']);
    exit;
}

$token = explode(' ', $headers['Authorization'])[1] ?? null;
$decoded = verify_jwt($token, 'CHANGE_THIS_TO_A_RANDOM_SECRET_KEY');

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

$allowed = ['image/jpeg','image/png','application/pdf'];
if (!in_array($file['type'], $allowed)) {
    echo json_encode(['status'=>'error','message'=>'Invalid file type']);
    exit;
}

/* ===============================
   Save file
================================ */
$uploadDir = __DIR__ . '/../../uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$fileName = 'id_' . $userId . '_' . time() . '.' . $ext;

move_uploaded_file($file['tmp_name'], $uploadDir . $fileName);

/* ===============================
   DB
================================ */
$verificationModel = new VerificationsModel();
$usersModel = new UsersModel();

$existing = $verificationModel->getVerificationByUserId($userId);

if ($existing) {
    $verificationModel->update(
        $existing['id'],
        $userId,
        $fileName,
        0,
        'Verification resubmitted'
    );
    $response['message'] = 'Document updated successfully. Pending admin approval.';
} else {
    $verificationModel->create(
        $userId,
        $fileName,
        0,
        'Verification submitted'
    );
    $response['message'] = 'Document uploaded successfully. Pending admin approval.';
}

$response['status'] = 'success';

/* ===============================
   EMAIL (GMAIL-SAFE, FINAL)
================================ */
$response['emailSent'] = false;

try {
    $user = $usersModel->getUserById($userId);
    $userEmail = $user['email'] ?? null;

    if ($userEmail) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'amirbaddour675@gmail.com';
        $mail->Password = 'lqtkykunvmmuhsvj';
        $mail->Port = 587;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';

        // 🚨 NO display name — Gmail requirement
        $mail->setFrom('amirbaddour675@gmail.com');
        $mail->addAddress($userEmail);

        $mail->isHTML(false);
        $mail->Subject = 'Verification Document Received';
        $mail->Body =
            "Your verification document has been received.\n\n" .
            "File: {$fileName}\n" .
            "Status: Pending review.";

        $mail->send();
        $response['emailSent'] = true;
    }
} catch (Throwable $e) {
    $response['emailError'] = $e->getMessage();
}

/* ===============================
   Final
================================ */
echo json_encode($response);
