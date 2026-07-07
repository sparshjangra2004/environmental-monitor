<?php

define('JWT_SECRET', 'ecomonitor_jwt_secret_key_2024_change_in_production');
define('JWT_EXPIRY', 3600);

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}

function generateJWT(int $userId, string $role): string {

$header = base64url_encode(json_encode([
        'alg' => 'HS256',

        'typ' => 'JWT'

    ]));

$payload = base64url_encode(json_encode([
        'sub'     => $userId,
        'role'    => $role,
        'iat'     => time(),
        'exp'     => time() + JWT_EXPIRY
    ]));

$signature = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );

    return "$header.$payload.$signature";
}

function validateJWT(string $token): ?array {
    $parts = explode('.', $token);

if (count($parts) !== 3) {
        return null;
    }

    [$header, $payload, $receivedSig] = $parts;

$expectedSig = base64url_encode(
        hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)
    );

if (!hash_equals($expectedSig, $receivedSig)) {
        return null;

    }

$data = json_decode(base64url_decode($payload), true);

    if (!$data || !isset($data['exp'])) {
        return null;
    }

if (time() > $data['exp']) {
        return null;

    }

    return $data;

}
?>
