<?php

if (session_status() === PHP_SESSION_NONE) {

session_set_cookie_params([
        'lifetime' => 0,

        'path'     => '/',
        'secure'   => false,

        'httponly' => true,

        'samesite' => 'Strict',

    ]);
    session_start();
}

require_once __DIR__ . '/../security/ip_blocker.php';
$currentIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (isIPBlocked($currentIP)) {
    showBlockedPage();

}

define('SESSION_TIMEOUT', 1800);

if (isset($_SESSION['user_id'])) {
    $lastActivity = $_SESSION['last_activity'] ?? time();

    if ((time() - $lastActivity) > SESSION_TIMEOUT) {

session_unset();
        session_destroy();
        session_start();

        $_SESSION['session_expired'] = true;
        header("Location: /environmental_monitor/auth/login.php?expired=1");
        exit();
    }

$_SESSION['last_activity'] = time();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: /environmental_monitor/auth/login.php");
        exit();
    }
}

function requireAdmin(): void {
    if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
        header("Location: /environmental_monitor/auth/login.php");
        exit();
    }
}

function getCurrentUser(): ?array {
    if (isLoggedIn()) {
        return [
            'id'    => $_SESSION['user_id'],
            'name'  => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role'  => $_SESSION['user_role']
        ];
    }
    return null;
}

function completeLogin(array $user): void {

session_regenerate_id(true);

    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_name']     = $user['name'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time']    = time();
}
?>
