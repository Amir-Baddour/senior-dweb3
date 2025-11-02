<?php
// Enable CORS (for local development)

require_once __DIR__ . '/../../utils/cors.php';
$allowed = [
  'https://web03-phi.vercel.app',                           // Your Vercel frontend
  'https://faces-wood-energy-catalog.trycloudflare.com',    // Your new tunnel URL
  'http://localhost',
  'http://127.0.0.1'
];

// Read user message
$data = json_decode(file_get_contents("php://input"), true);
$user_message = strtolower(trim($data["message"] ?? ""));

// Define static responses
$responses = [
  "hi" => "Hello! How can I assist you today?",
  "hello" => "Hi there! What can I help you with?",
  "transactions" => "You can view all your transactions in the transactions tab, including deposits, withdrawals, and transfers.",
  "deposits" => "To deposit funds, go to the deposit button(top right), enter the amount, and confirm the transaction. Your wallet balance will be updated once the deposit is successful.",
  "withdraw" => "To withdraw funds, visit the withdraw button(top right), enter the amount, and confirm. Withdrawals are subject to daily, weekly, and monthly limits depending on your tier.",
  "transfers" => "You can transfer funds to another user by providing the recipient's email and the amount. The system processes your transfer based on your available balance and tier limits.",
  "transaction limits" => "Once you reach a limit for the current period (daily, weekly, or monthly), you must wait until it resets to continue transacting. If you have any questions about raising your limits, contact support.",
  "regular tier" => "Regular Tier Limits:\n- Daily: 500 USDT\n- Weekly: 2,000 USDT\n- Monthly: 5,000 USDT",
  "vip tier" => "VIP Tier Limits:\n- Daily: 1,000 USDT\n- Weekly: 5,000 USDT\n- Monthly: 10,000 USDT",
  "reset password" => "Click on \"Forgot Password?\" on the login page, enter your email, and follow the instructions sent via email. You'll be able to create a new password.",
  "thanks" => "You're welcome! If you have any more questions, feel free to ask.",
  "thank you" => "No problem! I'm here to help. Let me know if you need anything else.",
  "help" => "Sure! What do you need help with? You can ask about transactions, deposits, withdrawals, transfers, limits, or password reset.",
  "support" => "For support, please contact our customer service via email at support@example.com or call us at +1234567890.",
  "contact support" => "If you need to contact support, please email us at amirbaddour675@gmail.com or call our support line at +1234567890.",
  "faq" => "You can find answers to common questions in our FAQ section on the website. If you have a specific question, feel free to ask!",
  "transaction history" => "You can view your transaction history in the transactions tab. It includes all deposits, withdrawals, and transfers you've made.",
  "balance" => "Your current wallet balance is displayed on the dashboard. If you need to check your balance, just look at the top of the page.",
  "account" => "You can manage your account settings in the account section. Here you can update your profile, change your password, and view your transaction history.",
  "security" => "For security tips, make sure to use a strong password, enable two-factor authentication, and never share your account details with anyone.",
  "privacy" => "We take your privacy seriously. Please review our privacy policy on the website for details on how we handle your data.",
  "terms" => "You can read our terms of service on the website. It outlines your rights and responsibilities as a user.",
  "feedback" => "We appreciate your feedback! Please let us know how we can improve our services by emailing us at amirbaddour675@gmail.com or using the feedback form on our website.",
];

// Match and respond
$reply = "Sorry, I didn’t understand your question. Please ask about: Transactions, Deposits, Withdrawals, Transfers, Limits, or Password Reset.";

foreach ($responses as $keyword => $response) {
  if (strpos($user_message, $keyword) !== false) {
    $reply = $response;
    break;
  }
}

// Send reply
echo json_encode(["reply" => $reply]);
