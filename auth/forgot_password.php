<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';
require_once __DIR__ . '/../security/logger.php';

$message     = '';
$error       = '';
$resetLink   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

if ($user) {

$token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

$clearStmt = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = ?");
                $clearStmt->bind_param("i", $user['id']);
                $clearStmt->execute();
                $clearStmt->close();

$insertStmt = $conn->prepare(
                    "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)"
                );
                $insertStmt->bind_param("iss", $user['id'], $token, $expiresAt);
                $insertStmt->execute();
                $insertStmt->close();

$resetLink = "http://localhost/environmental_monitor/auth/reset_password.php?token=" . urlencode($token);

                logActivity((int)$user['id'], 'password_reset_requested', "Reset token generated for: $email");
            }

            $conn->close();

$message = 'If that email is registered, a reset link has been generated below.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | EcoMonitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/environmental_monitor/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="mb-4 text-center" style="width:100%; max-width:480px;">
        <i class="bi bi-cloud-sun-fill text-success" style="font-size:48px;"></i>
        <h2 class="text-white" style="font-weight:700; margin-top:8px;">EcoMonitor</h2>
        <p class="text-white-50">Password Recovery</p>
    </div>

    <div style="width:100%; max-width:480px;">
        <div class="card auth-card">
            <div class="card-body">

                <h4 style="font-weight:700; margin-bottom:3px;">Forgot Password</h4>
                <p class="text-muted mb-4">Enter your email to receive a reset link</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($resetLink): ?>

                    <div style="background:rgba(25,135,84,0.08); border:1px solid rgba(25,135,84,0.3); border-radius:8px; padding:16px; margin-bottom:20px;">
                        <p style="font-size:13px; font-weight:600; color:var(--color-primary); margin-bottom:8px;">
                            <i class="bi bi-envelope-fill"></i> Reset link (simulated email):
                        </p>
                        <a href="<?php echo htmlspecialchars($resetLink); ?>"
                           style="font-size:12px; word-break:break-all; color:var(--color-primary);">
                            <?php echo htmlspecialchars($resetLink); ?>
                        </a>
                        <p class="text-muted mt-2 mb-0" style="font-size:12px;">
                            <i class="bi bi-clock"></i> This link expires in 1 hour.
                        </p>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrfField(); ?>

                    <div class="mb-4">
                        <label class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" name="email"
                                   placeholder="your@email.com" required autofocus>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="bi bi-send"></i> Generate Reset Link
                    </button>
                </form>

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
