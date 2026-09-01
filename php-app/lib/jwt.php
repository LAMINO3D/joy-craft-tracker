<?php
/**
 * Implementation minimale JWT HS256 (sans dependance externe).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string
{
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return (string) base64_decode(strtr($data, '-_', '+/'), true);
}

function jwt_encode(array $payload): string
{
    $header  = ['alg' => 'HS256', 'typ' => 'JWT'];
    $now     = time();
    $payload = array_merge($payload, [
        'iss' => JWT_ISSUER,
        'iat' => $now,
        'exp' => $now + JWT_TTL,
    ]);

    $segments = [
        b64url_encode(json_encode($header, JSON_UNESCAPED_UNICODE)),
        b64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)),
    ];
    $signing   = implode('.', $segments);
    $signature = hash_hmac('sha256', $signing, JWT_SECRET, true);

    return $signing . '.' . b64url_encode($signature);
}

function jwt_decode(?string $token): ?array
{
    if (!$token) {
        return null;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$h, $p, $s] = $parts;

    $expected = b64url_encode(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    if (!hash_equals($expected, $s)) {
        return null;
    }

    $payload = json_decode(b64url_decode($p), true);
    if (!is_array($payload)) {
        return null;
    }
    if (($payload['exp'] ?? 0) < time() || ($payload['iss'] ?? '') !== JWT_ISSUER) {
        return null;
    }

    return $payload;
}
