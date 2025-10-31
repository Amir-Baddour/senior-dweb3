<?php
declare(strict_types=1);
require_once __DIR__ . '/jwt.php';

function get_auth_header(): string {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (!empty($_ENV['HTTP_AUTHORIZATION'])) return $_ENV['HTTP_AUTHORIZATION'];
    if (function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k=>$v) if (strcasecmp($k,'Authorization')===0) return $v;
    }
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $k=>$v) if (strcasecmp($k,'Authorization')===0) return $v;
    }
    return '';
}

function verify_admin_jwt_from_request(): array {
    $authHeader = get_auth_header();
    if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        throw new RuntimeException('Missing token');
    }
    $payload = jwt_verify(trim($m[1])); // uses same secret/alg as login

    // Accept your numeric admin role "1" (or switch to 'admin' later)
    $role = $payload['role'] ?? null;
    $isAdmin = ($role === 'admin') || ($role === 1) || ($role === '1');
    if (!$isAdmin) throw new RuntimeException('Not authorized');

    return $payload;
}
