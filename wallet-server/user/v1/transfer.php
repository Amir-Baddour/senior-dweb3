<?php
// Set response headers for CORS and JSON content
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include dependencies and models
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../models/WalletsModel.php';
require_once __DIR__ . '/../../models/TransactionsModel.php';
require_once __DIR__ . '/../../models/TransactionLimitsModel.php';
require_once __DIR__ . '/../../utils/MailService.php';
require_once __DIR__ . '/../../utils/verify_jwt.php';

// Authenticate sender using JWT from the Authorization header
$headers = getallheaders();
if (!isset($headers['Authorization'])) {
    echo json_encode(["error" => "No authorization header provided."]);
    exit;
}
list($bearer, $jwt) = explode(' ', $headers['Authorization']);
if ($bearer !== 'Bearer' || !$jwt) {
    echo json_encode(["error" => "Invalid token format."]);
    exit;
}
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Use your secure secret here.
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(["error" => "Invalid or expired token."]);
    exit;
}
$sender_id = $decoded['id'];

// Read and validate JSON input data
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['recipient_email']) || !isset($data['amount'])) {
    echo json_encode(["error" => "Invalid input."]);
    exit;
}
$recipient_email = trim($data['recipient_email']);
$amount = floatval($data['amount']);
if ($amount <= 0) {
    echo json_encode(["error" => "Invalid transfer amount."]);
    exit;
}

try {
    // Initialize required models
    $usersModel = new UsersModel();
    $walletsModel = new WalletsModel();
    $transactionsModel = new TransactionsModel();
    $transactionLimitsModel = new TransactionLimitsModel();

    // Look up recipient by email (looping through all users for simplicity)
    $recipient = null;
    $allUsers = $usersModel->getAllUsers();
    foreach ($allUsers as $u) {
        if ($u['email'] === $recipient_email) {
            $recipient = $u;
            break;
        }
    }
    if (!$recipient) {
        echo json_encode(["error" => "Recipient not found."]);
        exit;
    }
    $recipient_id = $recipient['id'];
    if ($recipient_id == $sender_id) {
        echo json_encode(["error" => "You cannot transfer funds to yourself."]);
        exit;
    }

    // Check sender's wallet balance
    $sender_wallet = $walletsModel->getWalletByUserId($sender_id);
    if (!$sender_wallet || floatval($sender_wallet['balance']) < $amount) {
        echo json_encode(["error" => "Insufficient funds."]);
        exit;
    }

    // Retrieve sender's tier and corresponding transaction limits
    $user = $usersModel->getUserById($sender_id);
    $tier = $user ? $user['tier'] : 'regular';
    $limits = $transactionLimitsModel->getTransactionLimitByTier($tier);
    if (!$limits) {
        echo json_encode(["error" => "Transaction limits not defined for your tier"]);
        exit;
    }

    // Calculate current transfer/withdrawal usage (daily, weekly, monthly)
    try {
        $dailyStmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total 
            FROM transactions 
            WHERE sender_id = :user_id 
              AND DATE(created_at) = CURDATE() 
              AND transaction_type IN ('withdrawal','transfer')
        ");
        $dailyStmt->execute(['user_id' => $sender_id]);
        $dailyTotal = floatval($dailyStmt->fetch(PDO::FETCH_ASSOC)['total']);

        $weeklyStmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total 
            FROM transactions 
            WHERE sender_id = :user_id 
              AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) 
              AND transaction_type IN ('withdrawal','transfer')
        ");
        $weeklyStmt->execute(['user_id' => $sender_id]);
        $weeklyTotal = floatval($weeklyStmt->fetch(PDO::FETCH_ASSOC)['total']);

        $monthlyStmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total 
            FROM transactions 
            WHERE sender_id = :user_id 
              AND MONTH(created_at) = MONTH(CURDATE()) 
              AND YEAR(created_at) = YEAR(CURDATE()) 
              AND transaction_type IN ('withdrawal','transfer')
        ");
        $monthlyStmt->execute(['user_id' => $sender_id]);
        $monthlyTotal = floatval($monthlyStmt->fetch(PDO::FETCH_ASSOC)['total']);
    } catch (PDOException $e) {
        echo json_encode(["error" => $e->getMessage()]);
        exit;
    }

    // Enforce transaction limits
    if (($dailyTotal + $amount) > floatval($limits['daily_limit'])) {
        echo json_encode(["error" => "Daily transfer/withdrawal limit exceeded"]);
        exit;
    }
    if (($weeklyTotal + $amount) > floatval($limits['weekly_limit'])) {
        echo json_encode(["error" => "Weekly transfer/withdrawal limit exceeded"]);
        exit;
    }
    if (($monthlyTotal + $amount) > floatval($limits['monthly_limit'])) {
        echo json_encode(["error" => "Monthly transfer/withdrawal limit exceeded"]);
        exit;
    }

    // Begin transaction for atomicity
    $conn->beginTransaction();

    try {
        // Deduct amount from sender's wallet
        $newSenderBalance = floatval($sender_wallet['balance']) - $amount;
        $walletsModel->update($sender_wallet['id'], $sender_id, $newSenderBalance);

        // Credit recipient's wallet; create a wallet if needed
        $recipient_wallet = $walletsModel->getWalletByUserId($recipient_id);
        if (!$recipient_wallet) {
            $walletsModel->create($recipient_id, $amount);
        } else {
            $newRecipientBalance = floatval($recipient_wallet['balance']) + $amount;
            $walletsModel->update($recipient_wallet['id'], $recipient_id, $newRecipientBalance);
        }

        // Log the transfer transaction
        $transactionsModel->create($sender_id, $recipient_id, 'transfer', $amount);

        $conn->commit();

        // Send email confirmations to sender and recipient
        $mailer = new MailService();
        $subjectSender = "Transfer Confirmation";
        $bodySender = "
            <h1>Transfer Successful</h1>
            <p>You have transferred <strong>{$amount} USDT</strong> to <strong>{$recipient_email}</strong>.</p>
            <p>Your new balance is: <strong>{$newSenderBalance} USDT</strong></p>
        ";
        $mailer->sendMail($user['email'], $subjectSender, $bodySender);

        $subjectRecipient = "Funds Received";
        $bodyRecipient = "
            <h1>You've Received Funds</h1>
            <p>You have received <strong>{$amount} USDT</strong> from <strong>{$user['email']}</strong>.</p>
        ";
        $mailer->sendMail($recipient_email, $subjectRecipient, $bodyRecipient);

        echo json_encode(["message" => "Transfer successful.", "new_balance" => $newSenderBalance]);
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(["error" => "Transfer failed: " . $e->getMessage()]);
    }
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>