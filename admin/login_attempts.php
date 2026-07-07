<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';

requireAdmin();

$filterEmail  = sanitizeEmail($_GET['email']  ?? '');
$filterIP     = sanitizeString($_GET['ip']    ?? '');
$filterStatus = $_GET['status'] ?? '';

$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

$conn = getDBConnection();
$tableExists = false;
$rows = [];
$total = 0;
$summary = ['total' => 0, 'success' => 0, 'failed' => 0, 'locked' => 0];

$check = $conn->query("SHOW TABLES LIKE 'login_attempts'");
if ($check && $check->num_rows > 0) {
    $tableExists = true;

$where  = [];
    $params = [];
    $types  = '';

    if ($filterEmail) {
        $like = '%' . $filterEmail . '%';
        $where[]  = 'email LIKE ?';
        $params[] = $like;
        $types   .= 's';
    }
    if ($filterIP) {
        $like = '%' . $filterIP . '%';
        $where[]  = 'ip_address LIKE ?';
        $params[] = $like;
        $types   .= 's';
    }
    if ($filterStatus === '1' || $filterStatus === '0') {
        $where[]  = 'success = ?';
        $params[] = (int)$filterStatus;
        $types   .= 'i';
    }

    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM login_attempts $whereSQL");
    if ($params) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $total = $countStmt->get_result()->fetch_assoc()['cnt'];
    $countStmt->close();

$params[] = $perPage;
    $params[] = $offset;
    $types   .= 'ii';
    $stmt = $conn->prepare(
        "SELECT * FROM login_attempts $whereSQL ORDER BY attempted_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

$stats = $conn->query(
        "SELECT COUNT(*) AS total,
                SUM(success = 1) AS success,
                SUM(success = 0) AS failed
         FROM login_attempts"
    )->fetch_assoc();
    $summary['total']   = $stats['total']   ?? 0;
    $summary['success'] = $stats['success'] ?? 0;
    $summary['failed']  = $stats['failed']  ?? 0;

$lockedResult = $conn->query(
        "SELECT COUNT(DISTINCT email) AS cnt
         FROM login_attempts
         WHERE success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
         GROUP BY email
         HAVING COUNT(*) >= 5"
    );
    $summary['locked'] = $lockedResult ? $lockedResult->num_rows : 0;
}

$conn->close();
$totalPages = $total ? (int)ceil($total / $perPage) : 1;

$pageTitle = 'Login Attempts';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 32px 20px;">

    <div class="page-header" style="margin-bottom:28px;">
        <h2 style="font-weight:700; margin:0;">Login Attempts</h2>
        <p class="text-muted" style="margin:4px 0 0;">Feature 10 — monitor all login attempts, detect brute-force attacks</p>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            The <code>login_attempts</code> table has not been created yet.
            Run the CREATE TABLE SQL from <code>auth/login.php</code> in phpMyAdmin first.
        </div>
    <?php else: ?>

<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px;">
        <div class="card" style="text-align:center; padding:20px;">
            <div style="font-size:28px; font-weight:700;"><?php echo number_format($summary['total']); ?></div>
            <div class="text-muted" style="font-size:13px;">Total Attempts</div>
        </div>
        <div class="card" style="text-align:center; padding:20px; border-left:3px solid var(--color-primary);">
            <div style="font-size:28px; font-weight:700; color:var(--color-primary);"><?php echo number_format($summary['success']); ?></div>
            <div class="text-muted" style="font-size:13px;">Successful Logins</div>
        </div>
        <div class="card" style="text-align:center; padding:20px; border-left:3px solid var(--color-danger);">
            <div style="font-size:28px; font-weight:700; color:var(--color-danger);"><?php echo number_format($summary['failed']); ?></div>
            <div class="text-muted" style="font-size:13px;">Failed Attempts</div>
        </div>
        <div class="card" style="text-align:center; padding:20px; border-left:3px solid #f59e0b;">
            <div style="font-size:28px; font-weight:700; color:#f59e0b;"><?php echo $summary['locked']; ?></div>
            <div class="text-muted" style="font-size:13px;">Currently Locked Accounts</div>
        </div>
    </div>

<div class="card mb-4">
        <div class="card-body">
            <form method="GET" style="display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:12px; align-items:end;">
                <div>
                    <label class="form-label">Email</label>
                    <input type="text" class="form-control" name="email"
                           value="<?php echo htmlspecialchars($filterEmail); ?>"
                           placeholder="Filter by email">
                </div>
                <div>
                    <label class="form-label">IP Address</label>
                    <input type="text" class="form-control" name="ip"
                           value="<?php echo htmlspecialchars($filterIP); ?>"
                           placeholder="Filter by IP">
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select class="form-control" name="status">
                        <option value=""  <?php echo $filterStatus === ''  ? 'selected' : ''; ?>>All</option>
                        <option value="1" <?php echo $filterStatus === '1' ? 'selected' : ''; ?>>Success</option>
                        <option value="0" <?php echo $filterStatus === '0' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="/environmental_monitor/admin/login_attempts.php" class="btn btn-outline">
                        <i class="bi bi-x"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

<div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h5 style="margin:0;"><i class="bi bi-list-ul"></i> Attempts Log</h5>
            <span class="badge badge-secondary"><?php echo number_format($total); ?> records</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($rows)): ?>
                <div style="padding:40px; text-align:center; color:var(--color-text-muted);">
                    <i class="bi bi-journal-x" style="font-size:32px; display:block; margin-bottom:8px;"></i>
                    No login attempts found.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table" style="margin:0;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Email</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td style="font-size:12px; color:var(--color-text-muted);"><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['email'] ?? '—'); ?></td>
                                <td><code><?php echo htmlspecialchars($row['ip_address'] ?? '—'); ?></code></td>
                                <td>
                                    <?php if ($row['success']): ?>
                                        <span class="badge badge-success">Success</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size:13px; color:var(--color-text-muted);">
                                    <?php echo htmlspecialchars($row['attempted_at']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

<?php if ($totalPages > 1): ?>
                    <div style="padding:16px; display:flex; justify-content:center; gap:8px;">
                        <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                            <?php
                                $q = http_build_query(array_merge($_GET, ['page' => $p]));
                            ?>
                            <a href="?<?php echo $q; ?>"
                               class="btn <?php echo $p === $page ? 'btn-primary' : 'btn-outline'; ?>"
                               style="padding:6px 12px; min-width:36px; text-align:center;">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
