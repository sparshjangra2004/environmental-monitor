<?php
require_once __DIR__ . '/../includes/session.php';
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | EcoMonitor' : 'EcoMonitor'; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/environmental_monitor/assets/css/style.css?v=2" rel="stylesheet">
</head>
<body>

<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">

        <a class="navbar-brand fw-bold" href="/environmental_monitor/">
            <i class="bi bi-cloud-sun-fill text-success me-2"></i>EcoMonitor
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">

                <?php if ($user['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/environmental_monitor/admin/dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/environmental_monitor/admin/user.php">
                            <i class="bi bi-people me-1"></i>Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/environmental_monitor/admin/readings.php">
                            <i class="bi bi-database me-1"></i>Readings
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-shield-lock me-1"></i>Security
                        </a>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="/environmental_monitor/admin/login_attempts.php">
                                    <i class="bi bi-journal-x me-2"></i>Login Attempts
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/environmental_monitor/admin/security_logs.php">
                                    <i class="bi bi-journal-text me-2"></i>Security Logs
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/environmental_monitor/admin/ip_management.php">
                                    <i class="bi bi-slash-circle me-2"></i>IP Management
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="/environmental_monitor/admin/api_demo.php">
                                    <i class="bi bi-key me-2"></i>API Demo (JWT)
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/environmental_monitor/security/sql_injection_demo.php">
                                    <i class="bi bi-bug me-2"></i>SQL Injection Demo
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="/environmental_monitor/security/security_audit.php">
                                    <i class="bi bi-clipboard-check me-2"></i>Security Audit
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/environmental_monitor/user/dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i>Dashboard
                        </a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link" href="/environmental_monitor/user/monitoring.php">
                        <i class="bi bi-activity me-1"></i>Monitoring
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/environmental_monitor/user/alerts.php">
                        <i class="bi bi-bell me-1"></i>Alerts
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/environmental_monitor/user/network.php">
                        <i class="bi bi-diagram-3 me-1"></i>Network
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/environmental_monitor/user/reports.php">
                        <i class="bi bi-file-earmark-bar-graph me-1"></i>Reports
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?php echo htmlspecialchars($user['name']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="/environmental_monitor/user/profile.php">
                                <i class="bi bi-person me-2"></i>Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="/environmental_monitor/auth/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

    </div>
</nav>
<?php endif; ?>