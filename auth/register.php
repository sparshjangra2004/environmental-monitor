<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';

if (isLoggedIn()) {
    header("Location: /environmental_monitor/user/dashboard.php");
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Security token invalid. Please try again.';
        header("Location: /environmental_monitor/auth/register.php");
        exit();
    }

    $name     = sanitizeString($_POST['name'] ?? '');
    $email    = sanitizeEmail($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $conn = getDBConnection();

        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'An account with that email already exists.';
        } else {
            $stmt->close();
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO users (name, email, password, role_id)
                VALUES (?, ?, ?, 2)
            ");
            $stmt->bind_param("sss", $name, $email, $hashed);

            if ($stmt->execute()) {
                $success = 'Account created successfully! You can now log in.';
            } else {
                $error = 'Something went wrong. Please try again.';
            }
        }

        $stmt->close();
        $conn->close();
    }

    $_SESSION['flash_error']   = $error;
    $_SESSION['flash_success'] = $success;
    if ($error) {
        $_SESSION['flash_old_name']  = $name;
        $_SESSION['flash_old_email'] = $email;
    }
    header("Location: /environmental_monitor/auth/register.php");
    exit();
}

$error      = $_SESSION['flash_error']   ?? '';
$success    = $_SESSION['flash_success'] ?? '';
$oldName    = $_SESSION['flash_old_name']  ?? '';
$oldEmail   = $_SESSION['flash_old_email'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['flash_success'], $_SESSION['flash_old_name'], $_SESSION['flash_old_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | EcoMonitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/environmental_monitor/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="mb-4 text-center" style="width:100%; max-width:480px;">
        <i class="bi bi-cloud-sun-fill text-success" style="font-size: 48px;"></i>
        <h2 class="text-white" style="font-weight:700; margin-top:8px;">EcoMonitor</h2>
        <p class="text-white-50">Smart Environmental Monitoring Platform</p>
    </div>

    <div style="width:100%; max-width:480px;">
        <div class="card auth-card">
            <div class="card-body">

                <h4 style="font-weight:700; margin-bottom:3px;">Create account</h4>
                <p class="text-muted mb-4">Join the monitoring platform</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="alert-close">&times;</button>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        <a href="/environmental_monitor/auth/login.php" style="font-weight:600;">Sign in now</a>
                    </div>
                <?php endif; ?>

                <form method="POST" onsubmit="return validateRegister()">
                    <?php echo csrfField(); ?>
                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="name"
                                    value="<?php echo htmlspecialchars($oldName); ?>"
                                    placeholder="Your full name" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" name="email"
                                    value="<?php echo htmlspecialchars($oldEmail); ?>"
                                    placeholder="you@example.com" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" name="password"
                                    id="passwordField" placeholder="Minimum 6 characters" required>
                            <button class="btn btn-outline" type="button" onclick="togglePassword()">
                                <i class="bi bi-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Confirm password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" name="confirm_password"
                                    id="confirmField" placeholder="Repeat your password" required>
                        </div>
                    </div>

                    <div id="regError" class="text-danger" style="font-size:13.6px; display:none; margin-bottom:16px;"></div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="bi bi-person-plus"></i> Create Account
                    </button>
                </form>

                <hr style="margin: 24px 0; border-color: var(--color-border);">

                <p class="text-center text-muted mb-0">
                    Already have an account?
                    <a href="/environmental_monitor/auth/login.php" class="text-success" style="font-weight:600;">Sign in here</a>
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

function validateRegister() {
    const name     = document.querySelector('input[name="name"]').value.trim();
    const email    = document.querySelector('input[name="email"]').value.trim();
    const password = document.getElementById('passwordField').value;
    const confirm  = document.getElementById('confirmField').value;
    const errorDiv = document.getElementById('regError');

    if (!name || !email || !password || !confirm) {
        errorDiv.textContent = 'Please fill in all fields.';
        errorDiv.style.display = 'block';
        return false;
    }
    if (password.length < 6) {
        errorDiv.textContent = 'Password must be at least 6 characters.';
        errorDiv.style.display = 'block';
        return false;
    }
    if (password !== confirm) {
        errorDiv.textContent = 'Passwords do not match.';
        errorDiv.style.display = 'block';
        return false;
    }
    errorDiv.style.display = 'none';
    return true;
}
</script>
</body>
</html>
