<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';
require_once __DIR__ . '/../security/logger.php';

if (isLoggedIn()) {
    $role = $_SESSION['user_role'];
    header("Location: /environmental_monitor/" . ($role === 'admin' ? 'admin' : 'user') . "/dashboard.php");
    exit();
}

$sessionExpired = isset($_GET['expired']);
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        logActivity(null, 'csrf_failure', 'CSRF failure on login form from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        $error = 'Security token invalid. Please try again.';
    } else {

$email    = sanitizeEmail($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $conn = getDBConnection();

$lockStmt = $conn->prepare(
                "SELECT COUNT(*) AS fail_count
                 FROM login_attempts
                 WHERE email = ? AND success = 0
                 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            );
            $lockStmt->bind_param("s", $email);
            $lockStmt->execute();
            $lockResult  = $lockStmt->get_result()->fetch_assoc();
            $lockStmt->close();
            $failCount   = (int)($lockResult['fail_count'] ?? 0);

            if ($failCount >= 5) {
                $error = 'Account locked due to too many failed attempts. Try again in 15 minutes.';
                logActivity(null, 'account_locked', "Email: $email locked after $failCount failed attempts");
            } else {

$stmt = $conn->prepare("
                    SELECT u.id, u.name, u.email, u.password, u.status, r.name AS role
                    FROM users u
                    JOIN roles r ON u.role_id = r.id
                    WHERE u.email = ?
                ");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $user = $stmt->get_result()->fetch_assoc();
                $stmt->close();

$logStmt = $conn->prepare(
                    "INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)"
                );

                if (!$user) {
                    $error = 'No account found with that email.';
                    $s = 0;
                    $logStmt->bind_param("ssi", $email, $ip, $s);
                    $logStmt->execute();
                    logActivity(null, 'failed_login', "No account: $email");
                } elseif ($user['status'] !== 'active') {
                    $error = 'Your account is inactive. Contact admin.';
                    $s = 0;
                    $logStmt->bind_param("ssi", $email, $ip, $s);
                    $logStmt->execute();
                    logActivity(null, 'failed_login', "Inactive account: $email");
                } elseif (!password_verify($password, $user['password'])) {
                    $error = 'Incorrect password.';
                    $s = 0;
                    $logStmt->bind_param("ssi", $email, $ip, $s);
                    $logStmt->execute();
                    logActivity(null, 'failed_login', "Wrong password for: $email");
                } else {

$s = 1;
                    $logStmt->bind_param("ssi", $email, $ip, $s);
                    $logStmt->execute();

$otp        = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expiresAt  = date('Y-m-d H:i:s', time() + 300);

$clearStmt = $conn->prepare("UPDATE otp_tokens SET is_used = 1 WHERE user_id = ?");
                    $clearStmt->bind_param("i", $user['id']);
                    $clearStmt->execute();
                    $clearStmt->close();

$otpStmt = $conn->prepare(
                        "INSERT INTO otp_tokens (user_id, otp_code, expires_at) VALUES (?, ?, ?)"
                    );
                    $otpStmt->bind_param("iss", $user['id'], $otp, $expiresAt);
                    $otpStmt->execute();
                    $otpStmt->close();

$_SESSION['pending_user']    = $user;
                    $_SESSION['pending_otp']     = $otp;

                    $_SESSION['pending_expires'] = time() + 300;

                    logActivity((int)$user['id'], 'otp_requested', "OTP sent to: {$user['email']}");

                    $conn->close();
                    header("Location: /environmental_monitor/auth/otp_verify.php");
                    exit();
                }

                $logStmt->close();
            }

            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | EcoMonitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/environmental_monitor/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="mb-4 text-center" style="width:100%; max-width:480px;">
        <i class="bi bi-cloud-sun-fill text-success" style="font-size:48px;"></i>
        <h2 class="text-white" style="font-weight:700; margin-top:8px;">EcoMonitor</h2>
        <p class="text-white-50">Smart Environmental Monitoring Platform</p>
    </div>

    <div style="width:100%; max-width:480px;">
        <div class="card auth-card">
            <div class="card-body">

                <h4 style="font-weight:700; margin-bottom:3px;">Welcome back</h4>
                <p class="text-muted mb-4">Sign in to your account</p>

                <?php if ($sessionExpired): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-clock"></i> Session expired due to inactivity. Please sign in again.
                        <button type="button" class="alert-close">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="alert-close">&times;</button>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrfField();  ?>

                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" name="email"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                   placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" name="password"
                                   id="passwordField" placeholder="Enter your password" required>
                            <button class="btn btn-outline" type="button" onclick="togglePassword()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

<div class="mb-4 text-end" style="font-size:13px;">
                        <a href="/environmental_monitor/auth/forgot_password.php" class="text-success">
                            Forgot your password?
                        </a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>
                </form>

                <hr style="margin:24px 0; border-color:var(--color-border);">

                <p class="text-center text-muted mb-0">
                    Don't have an account?
                    <a href="/environmental_monitor/auth/register.php" class="text-success" style="font-weight:600;">Register here</a>
                </p>

            </div>
        </div>
    </div>
</div>

<script src="/environmental_monitor/assets/js/main.js"></script>
<script>
function togglePassword() {
    const field = document.getElementById('passwordField');
    const icon  = document.getElementById('eyeIcon');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>
</body>
</html>
