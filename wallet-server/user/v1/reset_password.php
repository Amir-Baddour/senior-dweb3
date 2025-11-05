<?php
ob_start();

require_once __DIR__ . '/../../utils/cors.php';

require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../models/PasswordResetsModel.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Retrieve input values
    $token = trim($_POST["token"]);
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    // Validate password requirements
    if (strlen($new_password) < 6) {
        echo json_encode(["error" => "Password must be at least 6 characters"]);
        exit;
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(["error" => "Passwords do not match"]);
        exit;
    }
    if (empty($token)) {
        echo json_encode(["error" => "Invalid token"]);
        exit;
    }

    try {
        // Initialize models
        $usersModel = new UsersModel();
        $passwordResetsModel = new PasswordResetsModel();

        // Retrieve and validate the reset token record
        $resetData = $passwordResetsModel->getResetByToken($token);
        if (!$resetData) {
            echo json_encode(["error" => "Invalid or expired token"]);
            exit;
        }
        if (strtotime($resetData['expires_at']) < time()) {
            echo json_encode(["error" => "Token has expired"]);
            exit;
        }

        // Hash the new password and update the user's record
        $user_id = $resetData['user_id'];
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $userData = $usersModel->getUserById($user_id);
        if (!$userData) {
            echo json_encode(["error" => "User not found"]);
            exit;
        }
        $updated = $usersModel->update(
            $user_id, 
            $userData['email'], 
            $hashed_password, 
            $userData['role']
        );

        if ($updated) {
            // Invalidate the reset token after successful update
            $passwordResetsModel->delete($resetData['id']);
            echo json_encode(["message" => "Password reset successful"]);
        } else {
            echo json_encode(["error" => "Failed to update password"]);
        }
    } catch (PDOException $e) {
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["error" => "Invalid request method."]);
}
?>