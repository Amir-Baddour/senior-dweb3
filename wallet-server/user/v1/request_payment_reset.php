<?php
// request_password_reset.php - Handles password reset requests securely.
header("Content-Type: application/json");

require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../models/PasswordResetsModel.php';
require_once __DIR__ . '/../../utils/MailService.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve and validate the email input
    $email = trim($_POST["email"]);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["error" => "Invalid email format"]);
        exit;
    }

    try {
        // Initialize models
        $usersModel = new UsersModel();
        $passwordResetsModel = new PasswordResetsModel();

        // Check if a user with the provided email exists
        $user = null;
        $allUsers = $usersModel->getAllUsers();
        foreach ($allUsers as $u) {
            if ($u['email'] === $email) {
                $user = $u;
                break;
            }
        }

        // Always return the same response for security reasons
        $responseMessage = "If an account with that email exists, a password reset link has been sent.";

        if ($user) {
            $user_id = $user['id'];
            // Generate a secure token and set expiration time (1 hour)
            $token = bin2hex(random_bytes(16));
            $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Insert the reset token into the password_resets table
            $passwordResetsModel->create($user_id, $token, $expires_at);

            // Build the reset link (update URL as needed)
            $resetLink = "http://localhost/digital-wallet-platform/wallet-client/reset_password.html?token=" . urlencode($token);

            // Send password reset email using MailService
            $mailer = new MailService();
            $subject = "Password Reset Request";
            $body = "
                <h1>Password Reset</h1>
                <p>We received a request to reset your password.</p>
                <p>Please click the link below to reset your password (this link will expire in 1 hour):</p>
                <p><a href='{$resetLink}'>Reset Password</a></p>
                <p>If you did not request a password reset, please ignore this email.</p>
            ";
            $mailer->sendMail($email, $subject, $body);
        }
        
        echo json_encode(["message" => $responseMessage]);
    } catch (PDOException $e) {
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "Invalid request method."]);
}
?>