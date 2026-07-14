<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

requireAdmin();

$conn = getDBConnection();

function tableExists(\mysqli $conn, string $name): bool {
    $res = $conn->query("SHOW TABLES LIKE '$name'");
    return $res && $res->num_rows > 0;
}

function fileExists2(string $path): bool {
    return file_exists($path);
}

$base = __DIR__ . '/..';

$checks = [
    'database' => [
        'otp_tokens'      => tableExists($conn, 'otp_tokens'),
        'login_attempts'  => tableExists($conn, 'login_attempts'),
        'password_resets' => tableExists($conn, 'password_resets'),
        'blocked_ips'     => tableExists($conn, 'blocked_ips'),
        'security_logs'   => tableExists($conn, 'security_logs'),
        'rate_limits'     => tableExists($conn, 'rate_limits'),
    ],
    'files' => [
        'config/sanitize.php'              => fileExists2("$base/config/sanitize.php"),
        'config/jwt.php'                   => fileExists2("$base/config/jwt.php"),
        'config/cors.php'                  => fileExists2("$base/config/cors.php"),
        'security/logger.php'              => fileExists2("$base/security/logger.php"),
        'security/ip_blocker.php'          => fileExists2("$base/security/ip_blocker.php"),
        'security/rate_limiter.php'        => fileExists2("$base/security/rate_limiter.php"),
        'auth/otp_verify.php'              => fileExists2("$base/auth/otp_verify.php"),
        'auth/forgot_password.php'         => fileExists2("$base/auth/forgot_password.php"),
        'auth/reset_password.php'          => fileExists2("$base/auth/reset_password.php"),
        'api/auth.php'                     => fileExists2("$base/api/auth.php"),
        'api/readings.php'                 => fileExists2("$base/api/readings.php"),
        'admin/api_demo.php'               => fileExists2("$base/admin/api_demo.php"),
        'admin/ip_management.php'          => fileExists2("$base/admin/ip_management.php"),
        'admin/login_attempts.php'         => fileExists2("$base/admin/login_attempts.php"),
        'admin/security_logs.php'          => fileExists2("$base/admin/security_logs.php"),
        'security/sql_injection_demo.php'  => fileExists2("$base/security/sql_injection_demo.php"),
        'includes/session.php'             => fileExists2("$base/includes/session.php"),
        'security/security_audit.php'      => fileExists2("$base/security/security_audit.php"),
    ],
];

$conn->close();

$pageTitle = 'Security Audit';
require_once __DIR__ . '/../includes/header.php';

$features = [
    [
        'id' => 1,
        'name' => 'OTP Two-Factor Authentication',
        'description' => 'After password verification, a 6-digit OTP is generated and shown on screen (simulated email). Login only completes after correct OTP entry.',
        'files' => ['auth/otp_verify.php'],
        'tables' => ['otp_tokens'],
        'how' => 'str_pad(random_int(0, 999999), 6, \'0\', STR_PAD_LEFT) — cryptographically secure random 6-digit code stored with 5-min expiry and a "used" flag.',
    ],
    [
        'id' => 2,
        'name' => 'Forgot Password / Reset Flow',
        'description' => '64-character hex token generated with bin2hex(random_bytes(32)), stored with 1-hour expiry. Same success message whether email exists or not (prevents email enumeration).',
        'files' => ['auth/forgot_password.php', 'auth/reset_password.php'],
        'tables' => ['password_resets'],
        'how' => 'bin2hex(random_bytes(32)) → 64-char token. Token is single-use (is_used flag). Old tokens invalidated on new request.',
    ],
    [
        'id' => 3,
        'name' => 'JWT API Authentication',
        'description' => 'Manual JWT implementation (no library). Structure: header.payload.signature — all base64url encoded. HMAC-SHA256 signature prevents tampering.',
        'files' => ['config/jwt.php', 'api/auth.php', 'api/readings.php', 'admin/api_demo.php'],
        'tables' => [],
        'how' => 'generateJWT() builds header+payload JSON, signs with hash_hmac(sha256, ...). validateJWT() re-signs and compares with hash_equals() to prevent timing attacks.',
    ],
    [
        'id' => 4,
        'name' => 'Session Security',
        'description' => 'session_regenerate_id(true) on login prevents session fixation. 30-minute inactivity timeout. Secure cookie params (httponly, samesite=Strict).',
        'files' => ['includes/session.php'],
        'tables' => [],
        'how' => 'session_set_cookie_params() with httponly+samesite before session_start(). last_activity timestamp updated each request; if gap > 1800s, destroy+redirect.',
    ],
    [
        'id' => 5,
        'name' => 'SQL Injection Prevention',
        'description' => 'Every database query uses MySQLi prepared statements with bind_param(). No string concatenation of user input into SQL queries anywhere in the project.',
        'files' => ['security/sql_injection_demo.php'],
        'tables' => [],
        'how' => '$stmt = $conn->prepare("SELECT ... WHERE id = ?"); $stmt->bind_param("i", $id); — the ? placeholder is never interpreted as SQL.',
    ],
    [
        'id' => 6,
        'name' => 'Input Sanitization',
        'description' => 'sanitizeString(), sanitizeEmail(), sanitizeInt(), sanitizeFloat() — each strips/validates input before it reaches any business logic.',
        'files' => ['config/sanitize.php'],
        'tables' => [],
        'how' => 'sanitizeString: strip_tags + htmlspecialchars_decode + trim. sanitizeEmail: filter_var(FILTER_SANITIZE_EMAIL) + validate. Output always uses htmlspecialchars() for XSS prevention.',
    ],
    [
        'id' => 7,
        'name' => 'CSRF Protection',
        'description' => 'Every POST form includes a hidden csrf_token field. Server validates with hash_equals() (timing-safe) against $_SESSION[\'csrf_token\'].',
        'files' => ['config/sanitize.php'],
        'tables' => [],
        'how' => 'generateCSRFToken(): bin2hex(random_bytes(32)) stored in session. csrfField(): outputs <input type="hidden">. validateCSRFToken(): hash_equals() comparison.',
    ],
    [
        'id' => 8,
        'name' => 'CORS Protection',
        'description' => 'API endpoints only accept cross-origin requests from whitelisted localhost origins. OPTIONS preflight handled. Content-Type forced to application/json.',
        'files' => ['config/cors.php'],
        'tables' => [],
        'how' => 'Allowlist checked against $_SERVER[HTTP_ORIGIN]. Access-Control-Allow-Origin only set to the matched origin. Browsers enforce this — requests from other origins are blocked.',
    ],
    [
        'id' => 9,
        'name' => 'IP Blocking',
        'description' => 'Admin can block any IP. Every page load checks the blocked_ips table. Blocked IPs see a 403 page and cannot proceed anywhere in the app.',
        'files' => ['security/ip_blocker.php', 'admin/ip_management.php'],
        'tables' => ['blocked_ips'],
        'how' => 'isIPBlocked() called from includes/session.php on every request. If blocked: outputs 403 HTML page and exit(). Soft-delete model (is_active flag, not DELETE).',
    ],
    [
        'id' => 10,
        'name' => 'Login Attempt Monitoring',
        'description' => 'Every login attempt is recorded. After 5 failures within 15 minutes, the account is locked out for that window. Admin can view full attempt log.',
        'files' => ['admin/login_attempts.php'],
        'tables' => ['login_attempts'],
        'how' => 'COUNT failed attempts WHERE email=? AND attempted_at > NOW()-15min. If ≥5: show lockout message, log account_locked, stop. All attempts recorded with IP + success flag.',
    ],
    [
        'id' => 11,
        'name' => 'Security Event Logging',
        'description' => 'logActivity() inserts a row into security_logs for every significant event: logins, failures, OTP attempts, password changes, IP blocks, JWT issuance.',
        'files' => ['security/logger.php', 'admin/security_logs.php'],
        'tables' => ['security_logs'],
        'how' => 'logActivity(?int $userId, string $action, string $details): inserts user_id, action, IP, user_agent, details. Silently ignores DB errors — logging never crashes the app.',
    ],
    [
        'id' => 12,
        'name' => 'Rate Limiting',
        'description' => 'API endpoints are rate-limited to 60 requests/minute per IP per endpoint. Exceeding the limit returns HTTP 429 with a JSON error.',
        'files' => ['security/rate_limiter.php'],
        'tables' => ['rate_limits'],
        'how' => 'INSERT ... ON DUPLICATE KEY UPDATE request_count = request_count + 1. If window_start < NOW()-60s: reset count. If count > 60: http_response_code(429) and exit().',
    ],
    [
        'id' => 13,
        'name' => 'Security Audit Checklist',
        'description' => 'This page — dynamically checks database tables, implementation files, and reports the live status of all 13 security features.',
        'files' => ['security/security_audit.php'],
        'tables' => [],
        'how' => 'file_exists() for each implementation file. SHOW TABLES LIKE for each required SQL table. Green = live, Red = missing.',
    ],
];

$totalChecks = 0;
$passedChecks = 0;
foreach ($features as $f) {
    foreach ($f['files'] as $file) {
        $totalChecks++;
        if ($checks['files'][$file] ?? false) $passedChecks++;
    }
    foreach ($f['tables'] as $table) {
        $totalChecks++;
        if ($checks['database'][$table] ?? false) $passedChecks++;
    }
}
?>

<div class="container" style="padding: 32px 20px;">

    <div class="page-header" style="margin-bottom:28px;">
        <h2 style="font-weight:700; margin:0;">Security Audit</h2>
        <p class="text-muted" style="margin:4px 0 0;">Feature 13 — live status of all 13 security features</p>
    </div>

<div class="card mb-4" style="border-left:4px solid <?php echo $passedChecks === $totalChecks ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;">
        <div class="card-body" style="display:flex; align-items:center; gap:24px;">
            <div style="font-size:48px; font-weight:700; color:<?php echo $passedChecks === $totalChecks ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;">
                <?php echo $passedChecks; ?>/<?php echo $totalChecks; ?>
            </div>
            <div>
                <strong style="font-size:18px;">
                    <?php echo $passedChecks === $totalChecks ? 'All security checks passing' : 'Some checks need attention'; ?>
                </strong>
                <p class="text-muted" style="margin:4px 0 0;">
                    <?php echo $totalChecks - $passedChecks; ?> missing
                    (<?php echo $totalChecks - $passedChecks === 0 ? 'all tables created and all files implemented' : 'see red badges below — usually means a SQL table needs to be created in phpMyAdmin'; ?>)
                </p>
            </div>
        </div>
    </div>

<div class="card mb-4">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-database"></i> Database Tables</h5>
        </div>
        <div class="card-body">
            <div style="display:flex; flex-wrap:wrap; gap:12px;">
                <?php foreach ($checks['database'] as $table => $exists): ?>
                    <div style="display:flex; align-items:center; gap:8px; padding:10px 16px;
                                background:var(--color-bg); border-radius:8px;
                                border:1px solid <?php echo $exists ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;">
                        <i class="bi bi-<?php echo $exists ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
                        <code style="font-size:13px;"><?php echo $table; ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (in_array(false, array_values($checks['database']), true)): ?>
                <div class="alert alert-warning" style="margin-top:16px;">
                    <i class="bi bi-exclamation-triangle"></i>
                    Missing tables need to be created in <strong>phpMyAdmin</strong>.
                    Find the CREATE TABLE SQL in the corresponding PHP file (listed in each feature below).
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php foreach ($features as $f): ?>
        <?php
        $filesPassed  = count(array_filter(array_map(fn($file) => $checks['files'][$file] ?? false, $f['files'])));
        $tablesPassed = count(array_filter(array_map(fn($tbl)  => $checks['database'][$tbl] ?? false, $f['tables'])));
        $totalF = count($f['files']) + count($f['tables']);
        $passedF = $filesPassed + $tablesPassed;
        $allPass = $passedF === $totalF;
        ?>
        <div class="card mb-3" style="border-left:3px solid <?php echo $allPass ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;">
            <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
                <h6 style="margin:0;">
                    <span style="color:var(--color-text-muted); margin-right:8px;">Feature <?php echo $f['id']; ?></span>
                    <?php echo htmlspecialchars($f['name']); ?>
                </h6>
                <span class="badge badge-<?php echo $allPass ? 'success' : 'danger'; ?>">
                    <?php echo $allPass ? 'ACTIVE' : 'INCOMPLETE'; ?>
                </span>
            </div>
            <div class="card-body">
                <p style="margin:0 0 12px; font-size:14px;"><?php echo htmlspecialchars($f['description']); ?></p>

                <div style="background:var(--color-bg); border-radius:6px; padding:10px 14px; margin-bottom:12px; font-size:12px;">
                    <strong>How it works:</strong> <?php echo htmlspecialchars($f['how']); ?>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <?php foreach ($f['files'] as $file): ?>
                        <?php $ok = $checks['files'][$file] ?? false; ?>
                        <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px;
                                     padding:4px 10px; border-radius:4px;
                                     background:<?php echo $ok ? 'rgba(25,135,84,0.1)' : 'rgba(220,53,69,0.1)'; ?>;
                                     border:1px solid <?php echo $ok ? 'rgba(25,135,84,0.3)' : 'rgba(220,53,69,0.3)'; ?>;">
                            <i class="bi bi-file-code" style="color:<?php echo $ok ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;"></i>
                            <code><?php echo htmlspecialchars($file); ?></code>
                            <i class="bi bi-<?php echo $ok ? 'check' : 'x'; ?>" style="color:<?php echo $ok ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;"></i>
                        </span>
                    <?php endforeach; ?>
                    <?php foreach ($f['tables'] as $table): ?>
                        <?php $ok = $checks['database'][$table] ?? false; ?>
                        <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px;
                                     padding:4px 10px; border-radius:4px;
                                     background:<?php echo $ok ? 'rgba(25,135,84,0.1)' : 'rgba(220,53,69,0.1)'; ?>;
                                     border:1px solid <?php echo $ok ? 'rgba(25,135,84,0.3)' : 'rgba(220,53,69,0.3)'; ?>;">
                            <i class="bi bi-table" style="color:<?php echo $ok ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;"></i>
                            <code><?php echo htmlspecialchars($table); ?></code>
                            <i class="bi bi-<?php echo $ok ? 'check' : 'x'; ?>" style="color:<?php echo $ok ? 'var(--color-primary)' : 'var(--color-danger)'; ?>;"></i>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
