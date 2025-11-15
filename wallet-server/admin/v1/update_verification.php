<?php
// ✅ Use cors.php instead of hardcoded headers
error_log("[verification_requests.php] File loaded at " . date('Y-m-d H:i:s'));

require_once __DIR__ . '/../../../utils/cors.php';
error_log("[verification_requests.php] CORS file included");

// --- Include Dependencies ---
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/VerificationsModel.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../utils/MailService.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

$response = ["status" => "error", "message" => "Something went wrong"];

// --- Ensure POST Request ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // --- JWT Authentication ---
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        $response["message"] = "No authorization header provided.";
        echo json_encode($response);
        exit;
    }
    
    $auth_header = $headers['Authorization'];
    $parts = explode(' ', $auth_header);
    
    if (count($parts) !== 2 || $parts[0] !== 'Bearer') {
        $response["message"] = "Invalid token format.";
        echo json_encode($response);
        exit;
    }
    
    $jwt = $parts[1];
    $jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Must match login.php
    $decoded = verify_jwt($jwt, $jwt_secret);
    
    if (!$decoded) {
        $response["message"] = "Invalid or expired token.";
        echo json_encode($response);
        exit;
    }
    
    // --- Admin Authorization Check ---
    // ✅ Use string comparison since JWT stores role as string
    if (!isset($decoded['role']) || (string)$decoded['role'] !== '1') {
        $response["message"] = "Access denied. Admins only.";
        echo json_encode($response);
        exit;
    }
    
    // --- Read JSON Input ---
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    $user_id = $data["user_id"] ?? null;
    $is_validated = $data["is_validated"] ?? null;
    
    // Validate required parameters: user_id must be present and is_validated must be either 1 (approved) or -1 (rejected)
    if (!$user_id || !in_array($is_validated, [1, -1], true)) {
        $response["message"] = "Invalid request parameters.";
        echo json_encode($response);
        exit;
    }
    
    try {
        // --- Initialize Models ---
        $verificationsModel = new VerificationsModel();
        $usersModel = new UsersModel();
    
        // --- Fetch Verification Record ---
        $verification = $verificationsModel->getVerificationByUserId($user_id);
        if (!$verification) {
            echo json_encode(["status" => "error", "message" => "Verification record not found."]);
            exit;
        }
    
        // --- Update Verification Status ---
        $updated = $verificationsModel->update(
            $verification['id'],
            $verification['user_id'],
            $verification['id_document'],
            $is_validated,
            ($is_validated == 1) ? "User verified" : "Verification rejected"
        );
    
        if ($updated) {
            $response["status"] = "success";
            $response["message"] = ($is_validated == 1)
                ? "User verified successfully!"
                : "Verification request rejected.";
    
            // --- Send Email Notification if Approved ---
            if ($is_validated == 1) {
                $user = $usersModel->getUserById($user_id);
                $userEmail = $user ? $user['email'] : null;
    
                if ($userEmail) {
                    // ✅ Use dynamic URL based on environment
                    // Determine the base URL
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    
                    // Build the QR link dynamically
                    $qrLink = "{$protocol}://{$host}/digital-wallet-plateform/wallet-server/utils/generate_qr.php?recipient_id={$user_id}&amount=10";
    
                    $mailer = new MailService();
                    $subject = "Welcome to Our Platform!";
                    $body = "
                        <h1>Congratulations!</h1>
                        <p>Your account has been verified successfully.</p>
                        <p>You can now receive a special bonus by scanning or clicking the link below:</p>
                        <p><a href='{$qrLink}' target='_blank'>Click here to view your QR code</a></p>
                        <p>Welcome aboard!</p>
                    ";
    
                    $mailer->sendMail($userEmail, $subject, $body);
                }
            }
        } else {
            $response["message"] = "Database update failed.";
        }
    } catch (PDOException $e) {
        $response["message"] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $response["message"] = "Error: " . $e->getMessage();
    }
}

echo json_encode($response);
?>