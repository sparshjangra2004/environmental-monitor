<?php

function sanitizeString(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail(string $input): string {
    $clean = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    return filter_var($clean, FILTER_VALIDATE_EMAIL) ? $clean : '';
}

function sanitizeInt(mixed $input): ?int {
    $val = filter_var($input, FILTER_VALIDATE_INT);
    return $val !== false ? (int)$val : null;
}

function sanitizeFloat(mixed $input): ?float {
    $val = filter_var($input, FILTER_VALIDATE_FLOAT);
    return $val !== false ? (float)$val : null;
}

function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

return hash_equals($_SESSION['csrf_token'], $token);
}

?> inside any <form>
function csrfField(): string {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}
?>
