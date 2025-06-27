<?php
header("Content-Type: application/json");

// Include required files and models
require_once __DIR__ . '/../../connection/db.php';
require_once __DIR__ . '/../../models/UsersModel.php';
require_once __DIR__ . '/../../models/TransactionLimitsModel.php';
require_once __DIR__ . '/../../utils/verify_jwt.php'; // Adjust path if needed

// --- JWT Authentication ---
// Retrieve and validate the JWT from the Authorization header
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
$jwt_secret = "CHANGE_THIS_TO_A_RANDOM_SECRET_KEY"; // Use a secure secret in production
$decoded = verify_jwt($jwt, $jwt_secret);
if (!$decoded) {
    echo json_encode(["error" => "Invalid or expired token."]);
    exit;
}
$userId = $decoded['id'];

// --- Fetch User Tier and Limits ---
// Initialize models and retrieve user's tier from the users table, then fetch transaction limits based on tier
try {
    $usersModel = new UsersModel();
    $transactionLimitsModel = new TransactionLimitsModel();

    $user = $usersModel->getUserById($userId);
    $tier = $user ? $user['tier'] : 'regular';

    $limits = $transactionLimitsModel->getTransactionLimitByTier($tier);
    if (!$limits) {
        echo json_encode(["error" => "Transaction limits not defined for your tier"]);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
    exit;
}

// --- Calculate Transaction Usage ---
// Query the database to calculate the total used amounts for withdrawals and transfers for daily, weekly, and monthly periods.
try {
    $dailyStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total 
        FROM transactions 
        WHERE sender_id = :user_id 
          AND DATE(created_at) = CURDATE() 
          AND transaction_type IN ('withdrawal', 'transfer')
    ");
    $dailyStmt->execute(['user_id' => $userId]);
    $dailyUsed = floatval($dailyStmt->fetch(PDO::FETCH_ASSOC)['total']);

    $weeklyStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total 
        FROM transactions 
        WHERE sender_id = :user_id 
          AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1) 
          AND transaction_type IN ('withdrawal', 'transfer')
    ");
    $weeklyStmt->execute(['user_id' => $userId]);
    $weeklyUsed = floatval($weeklyStmt->fetch(PDO::FETCH_ASSOC)['total']);

    $monthlyStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total 
        FROM transactions 
        WHERE sender_id = :user_id 
          AND MONTH(created_at) = MONTH(CURDATE()) 
          AND YEAR(created_at) = YEAR(CURDATE()) 
          AND transaction_type IN ('withdrawal', 'transfer')
    ");
    $monthlyStmt->execute(['user_id' => $userId]);
    $monthlyUsed = floatval($monthlyStmt->fetch(PDO::FETCH_ASSOC)['total']);
} catch (PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
    exit;
}

// --- Calculate Remaining Limits ---
// Determine the remaining amounts by subtracting used amounts from the allowed limits.
$dailyRemaining = max(floatval($limits['daily_limit']) - $dailyUsed, 0);
$weeklyRemaining = max(floatval($limits['weekly_limit']) - $weeklyUsed, 0);
$monthlyRemaining = max(floatval($limits['monthly_limit']) - $monthlyUsed, 0);

// --- Return the Results ---
// Output the calculated usage and remaining amounts as a JSON response.
echo json_encode([
    "dailyUsed" => $dailyUsed,
    "dailyLimit" => floatval($limits['daily_limit']),
    "dailyRemaining" => $dailyRemaining,
    "weeklyUsed" => $weeklyUsed,
    "weeklyLimit" => floatval($limits['weekly_limit']),
    "weeklyRemaining" => $weeklyRemaining,
    "monthlyUsed" => $monthlyUsed,
    "monthlyLimit" => floatval($limits['monthly_limit']),
    "monthlyRemaining" => $monthlyRemaining
]);
?>