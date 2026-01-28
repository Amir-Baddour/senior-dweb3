<?php
require_once __DIR__ . '/../../../connection/db.php';

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid verification link.");
}

try {
    // 1️⃣ Find verification record by token
    $stmt = $pdo->prepare(
        "SELECT * FROM verifications WHERE token = :token LIMIT 1"
    );
    $stmt->execute(['token' => $token]);
    $verification = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$verification) {
        die("Invalid or expired verification link.");
    }

    // 2️⃣ Mark email as verified
    $stmt = $pdo->prepare(
        "UPDATE verifications
         SET is_validated = 1, note = 'Email verified'
         WHERE id = :id"
    );
    $stmt->execute(['id' => $verification['id']]);

    // 3️⃣ Redirect user to FRONTEND login page (Vercel)
    header("Location: https://yourwallet0.vercel.app/login.html");
    exit;

} catch (Throwable $e) {
    error_log('verify_email error: ' . $e->getMessage());
    die("Server error. Please try again later.");
}
