<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';
require_once __DIR__ . '/../security/logger.php';

if (empty($_SESSION['pending_user'])) {
    header("Location: /environmental_monitor/auth/login.php");
    exit();
}

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: /environmental_monitor/" . ($role === 'admin' ? 'admin' : 'user') . "/dashboard.php");
    exit();
}

$pendingUser = $_SESSION['pending_user'];
$otpOnScreen = $_SESSION['pending_otp'];

$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logActivity(null, 'csrf_failure', 'CSRF failure on OTP form');
        $error = 'Security token invalid. Please try again.';
    } else {
        $submittedOtp = trim($_POST['otp_code'] ?? '');

        if (empty($submittedOtp)) {
            $error = 'Please enter the OTP code.';
        } else {
            $conn    = getDBConnection();
            $userId  = (int)$pendingUser['id'];

$stmt = $conn->prepare(
                "SELECT id, otp_code FROM otp_tokens
                 WHERE user_id = ? AND is_used = 0 AND expires_at > NOW()
                 ORDER BY created_at DESC LIMIT 1"
            );
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $otpRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$otpRow) {
                $error = 'OTP has expired. Please log in again.';
                unset($_SESSION['pending_user'], $_SESSION['pending_otp'], $_SESSION['pending_expires']);
                logActivity($userId, 'otp_failed', 'OTP expired for user ID: ' . $userId);
            } elseif ($submittedOtp !== $otpRow['otp_code']) {
                $error = 'Incorrect OTP. Please check and try again.';
                logActivity($userId, 'otp_failed', 'Wrong OTP entered for user ID: ' . $userId);
            } else {

$markStmt = $conn->prepare("UPDATE otp_tokens SET is_used = 1 WHERE id = ?");
                $markStmt->bind_param("i", $otpRow['id']);
                $markStmt->execute();
                $markStmt->close();

                $conn->close();

unset($_SESSION['pending_user'], $_SESSION['pending_otp'], $_SESSION['pending_expires']);

completeLogin($pendingUser);

                logActivity((int)$pendingUser['id'], 'successful_login', 'OTP verified for: ' . $pendingUser['email']);

                header("Location: /environmental_monitor/" . ($pendingUser['role'] === 'admin' ? 'admin' : 'user') . "/dashboard.php");
                exit();
            }

            if (isset($conn) && $conn) $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification | EcoMonitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/environmental_monitor/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="mb-4 text-center" style="width:100%; max-width:480px;">
        <i class="bi bi-cloud-sun-fill text-success" style="font-size:48px;"></i>
        <h2 class="text-white" style="font-weight:700; margin-top:8px;">EcoMonitor</h2>
        <p class="text-white-50">Two-Factor Authentication</p>
    </div>

    <div style="width:100%; max-width:480px;">
        <div class="card auth-card">
            <div class="card-body">

                <div class="text-center mb-4">
                    <i class="bi bi-shield-lock-fill" style="font-size:40px; color:var(--color-primary);"></i>
                    <h4 style="font-weight:700; margin-top:12px;">Enter OTP</h4>
                    <p class="text-muted">A 6-digit code has been sent to<br>
                        <strong><?php echo htmlspecialchars($pendingUser['email']); ?></strong>
                    </p>
                </div>

<div style="background:rgba(25,135,84,0.08); border:1px solid rgba(25,135,84,0.2); border-radius:8px; padding:16px; margin-bottom:20px; text-align:center;">
                    <small class="text-muted"><i class="bi bi-envelope-fill text-success"></i> OTP sent to your email (simulated)</small>
                    <div style="font-size:32px; font-weight:700; letter-spacing:8px; color:var(--color-primary); margin-top:8px;">
                        <?php echo htmlspecialchars($otpOnScreen); ?>
                    </div>
                    <small class="text-muted">Expires in 5 minutes</small>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="alert-close">&times;</button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrfField(); ?>

                    <div class="mb-4">
                        <label class="form-label">6-Digit OTP Code</label>
                        <input type="text" class="form-control" name="otp_code"
                               maxlength="6" pattern="[0-9]{6}"
                               placeholder="Enter 6-digit code"
                               style="font-size:24px; text-align:center; letter-spacing:8px;"
                               autofocus required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="bi bi-shield-check"></i> Verify OTP
                    </button>
                </form>

                <hr style="margin:24px 0; border-color:var(--color-border);">
                <p class="text-center text-muted mb-0" style="font-size:13px;">
                    <a href="/environmental_monitor/auth/login.php" class="text-success">
                        <i class="bi bi-arrow-left"></i> Back to login
                    </a>
                </p>

            </div>
        </div>
    </div>
</div>

<script src="/environmental_monitor/assets/js/main.js"></script>
</body>
</html>
