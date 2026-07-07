<?php

require_once __DIR__ . '/../config/database.php';

function logActivity(?int $userId, string $action, string $details = ''): void {
    try {
        $conn      = getDBConnection();

$stmt = $conn->prepare(
            "INSERT INTO security_logs (user_id, action, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?)"
        );
        $ip        = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $stmt->bind_param("issss", $userId, $action, $ip, $userAgent, $details);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {

}
}
?>
