<?php

require_once __DIR__ . '/../config/database.php';

function isIPBlocked(string $ip): bool {
    try {
        $conn = getDBConnection();

$stmt = $conn->prepare(
            "SELECT id FROM blocked_ips WHERE ip_address = ? AND is_active = 1"
        );
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $stmt->store_result();
        $blocked = $stmt->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $blocked;
    } catch (Exception $e) {
        return false;

    }
}

function blockIP(string $ip, string $reason = 'Manual block', ?int $blockedBy = null): void {
    $conn = getDBConnection();

$stmt = $conn->prepare(
        "INSERT INTO blocked_ips (ip_address, reason, blocked_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE is_active = 1, reason = ?, blocked_by = ?, blocked_at = NOW()"
    );
    $stmt->bind_param("ssissi", $ip, $reason, $blockedBy, $reason, $blockedBy);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function unblockIP(int $id): void {
    $conn = getDBConnection();

$stmt = $conn->prepare("UPDATE blocked_ips SET is_active = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

function showBlockedPage(): void {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Access Denied | EcoMonitor</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
        <link href="/environmental_monitor/assets/css/style.css" rel="stylesheet">
    </head>
    <body>
    <div class="auth-wrapper">
        <div class="card auth-card text-center" style="max-width:480px;">
            <div class="card-body">
                <i class="bi bi-shield-x" style="font-size:64px; color:var(--color-danger);"></i>
                <h3 style="margin-top:16px; font-weight:700;">Access Denied</h3>
                <p class="text-muted">Your IP address has been blocked from accessing this system.</p>
                <p class="text-muted" style="font-size:13px;">If you believe this is an error, contact the administrator.</p>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit();
}
?>
