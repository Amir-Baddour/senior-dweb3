<?php
declare(strict_types=1);

// USE ONE SECRET + HS256 ON BOTH SIGN & VERIFY
const JWT_SECRET = 'mydevsecret123456789';
const JWT_ALGO   = 'HS256';

function b64url_encode(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64url_decode(string $d): string { return base64_decode(strtr($d, '-_', '+/')); }

function jwt_sign(array $payload, int $ttlSeconds = 3600): string {
    $header = ['typ'=>'JWT','alg'=>JWT_ALGO];
    $now = time();
    $payload['iat'] = $payload['iat'] ?? $now;
    $payload['exp'] = $payload['exp'] ?? ($now + $ttlSeconds);
    $h64 = b64url_encode(json_encode($header,  JSON_UNESCAPED_SLASHES));
    $p64 = b64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $sig = hash_hmac('sha256', "$h64.$p64", JWT_SECRET, true);
    return "$h64.$p64.".b64url_encode($sig);
}

function jwt_verify(string $token): array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) throw new RuntimeException('Invalid token format');
    [$h64,$p64,$s64] = $parts;
    $header  = json_decode(b64url_decode($h64), true);
    $payload = json_decode(b64url_decode($p64), true);
    if (($header['alg'] ?? '') !== JWT_ALGO) throw new RuntimeException('Unsupported or missing alg');
    $expected = hash_hmac('sha256', "$h64.$p64", JWT_SECRET, true);
    if (!hash_equals($expected, b64url_decode($s64))) throw new RuntimeException('Signature mismatch');
    if (isset($payload['exp']) && time() >= (int)$payload['exp']) throw new RuntimeException('Token expired');
    return $payload;
}
