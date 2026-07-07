<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';
require_once __DIR__ . '/../security/logger.php';

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = '';
$valid   = false;
$userId  = null;

if ($token) {
    $conn = getDBConnection();

$stmt = $conn->prepare(
        "SELECT id, user_id FROM password_resets
         WHERE token = ? AND is_used = 0 AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if ($row) {
        $valid  = true;
        $userId = (int)$row['user_id'];
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm']  ?? '';

        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $conn   = getDBConnection();
            $hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $userId);
            $stmt->execute();
            $stmt->close();

$markStmt = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE token = ?");
            $markStmt->bind_param("s", $token);
            $markStmt->execute();
            $markStmt->close();

            $conn->close();

            logActivity($userId, 'password_changed', 'Password reset via token');
            $success = 'Password changed successfully! You can now log in.';
            $valid   = false;

        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | EcoMonitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/environmental_monitor/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="mb-4 text-center" style="width:100%; max-width:480px;">
        <i class="bi bi-cloud-sun-fill text-success" style="font-size:48px;"></i>
        <h2 class="text-white" style="font-weight:700; margin-top:8px;">EcoMonitor</h2>
        <p class="text-white-50">Set New Password</p>
    </div>

    <div style="width:100%; max-width:480px;">
        <div class="card auth-card">
            <div class="card-body">

                <h4 style="font-weight:700; margin-bottom:3px;">Reset Password</h4>
                <p class="text-muted mb-4">Enter your new password below</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                    <a href="/environmental_monitor/auth/login.php" class="btn btn-primary btn-block">
                        <i class="bi bi-box-arrow-in-right"></i> Go to Login
                    </a>
                <?php elseif ($valid): ?>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="password"
                                   placeholder="Minimum 6 characters" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm"
                                   placeholder="Repeat new password" required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            <i class="bi bi-lock-fill"></i> Set New Password
                        </button>
                    </form>
                <?php elseif (!$success): ?>
                    <p class="text-muted">
                        <a href="/environmental_monitor/auth/forgot_password.php" class="text-success">
                            Request a new reset link
                        </a>
                    </p>
                <?php endif; ?>

                <hr style="margin:24px 0; border-color:var(--color-border);">
                <p class="text-center text-muted mb-0">
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
