<?php
function verify_jwt(string $jwt, string $secret)
{
    // Split the token into header, payload, and signature
    $tokenParts = explode('.', $jwt);
    if (count($tokenParts) !== 3) {
        return false;
    }
    list($base64Header, $base64Payload, $base64Signature) = $tokenParts;

    // Decode header, payload, and signature using Base64Url
    $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Header)), true);
    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);
    $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Signature));

    // Ensure the token uses HS256 algorithm
    if ($header['alg'] !== 'HS256') {
        return false;
    }

    // Generate a valid signature using the provided secret
    $validSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secret, true);
    if (!hash_equals($validSignature, $signature)) {
        return false;
    }

    // Check if the token has expired
    if ($payload['exp'] < time()) {
        return false;
    }

    // Token is valid; return its payload
    return $payload;
}