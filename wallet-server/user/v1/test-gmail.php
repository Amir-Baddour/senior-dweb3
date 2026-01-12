<?php
// test-resend.php

$apiKey = 're_QzowrxAp_DzoVNDUCdMhL5jovg7fdyCPw'; // Paste your API key from Step 1

$data = [
    'from' => 'onboarding@resend.dev', // Use this for testing
    'to' => ['amirbaddour675@gmail.com'],
    'subject' => 'Test Email - ' . date('H:i:s'),
    'html' => '<h1>Success!</h1><p>Your email is working perfectly.</p>'
];

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.resend.com/emails',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($httpCode === 200) {
    echo "✅ SUCCESS! Email sent.\n";
    echo "Response: " . $response . "\n";
} else {
    echo "❌ FAILED\n";
    echo "Response: " . $response . "\n";
}