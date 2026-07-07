<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';

requireAdmin();

$filterAction = sanitizeString($_GET['action'] ?? '');
$filterUser   = sanitizeString($_GET['user']   ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

$conn = getDBConnection();
$tableExists = false;
$rows = [];
$total = 0;
$distinctActions = [];

$check = $conn->query("SHOW TABLES LIKE 'security_logs'");
if ($check && $check->num_rows > 0) {
    $tableExists = true;

$actRes = $conn->query("SELECT DISTINCT action FROM security_logs ORDER BY action");
    while ($act = $actRes->fetch_assoc()) {
        $distinctActions[] = $act['action'];
    }

$where  = [];
    $params = [];
    $types  = '';

    if ($filterAction) {
        $where[]  = 'sl.action = ?';
        $params[] = $filterAction;
        $types   .= 's';
    }
    if ($filterUser) {
        $like = '%' . $filterUser . '%';
        $where[]  = 'u.name LIKE ?';
        $params[] = $like;
        $types   .= 's';
    }

    $whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt
         FROM security_logs sl
         LEFT JOIN users u ON sl.user_id = u.id
         $whereSQL"
    );
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
        "SELECT sl.*, u.name AS user_name, u.email AS user_email
         FROM security_logs sl
         LEFT JOIN users u ON sl.user_id = u.id
         $whereSQL
         ORDER BY sl.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}
$conn->close();

$totalPages = $total ? (int)ceil($total / $perPage) : 1;

$actionColors = [
    'successful_login'          => 'success',
    'jwt_generated'             => 'success',
    'api_readings_accessed'     => 'success',
    'failed_login'              => 'danger',
    'otp_failed'                => 'danger',
    'account_locked'            => 'danger',
    'ip_blocked'                => 'danger',
    'ip_unblocked'              => 'warning',
    'password_changed'          => 'warning',
    'password_reset_requested'  => 'warning',
    'logout'                    => 'secondary',
];

$pageTitle = 'Security Logs';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 32px 20px;">

    <div class="page-header" style="margin-bottom:28px;">
        <h2 style="font-weight:700; margin:0;">Security Logs</h2>
        <p class="text-muted" style="margin:4px 0 0;">Feature 11 — full audit trail of security events (logins, locks, blocks, password changes)</p>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            The <code>security_logs</code> table has not been created yet.
            Run the CREATE TABLE SQL from <code>security/logger.php</code> in phpMyAdmin first.
        </div>
    <?php else: ?>

<div class="card mb-4">
        <div class="card-body">
            <form method="GET" style="display:grid; grid-template-columns:2fr 2fr auto; gap:12px; align-items:end;">
                <div>
                    <label class="form-label">Event Type</label>
                    <select class="form-control" name="action">
                        <option value="">All Events</option>
                        <?php foreach ($distinctActions as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>"
                                <?php echo $filterAction === $a ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">User Name</label>
                    <input type="text" class="form-control" name="user"
                           value="<?php echo htmlspecialchars($filterUser); ?>"
                           placeholder="Filter by user name">
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="/environmental_monitor/admin/security_logs.php" class="btn btn-outline">
                        <i class="bi bi-x"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

<div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h5 style="margin:0;"><i class="bi bi-journal-text"></i> Security Event Log</h5>
            <span class="badge badge-secondary"><?php echo number_format($total); ?> events</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($rows)): ?>
                <div style="padding:40px; text-align:center; color:var(--color-text-muted);">
                    <i class="bi bi-journal" style="font-size:32px; display:block; margin-bottom:8px;"></i>
                    No security events found.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table" style="margin:0; font-size:13px;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Event</th>
                                <th>User</th>
                                <th>IP Address</th>
                                <th>Details</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $color = $actionColors[$row['action']] ?? 'secondary'; ?>
                            <tr>
                                <td style="color:var(--color-text-muted);"><?php echo $row['id']; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $color; ?>">
                                        <?php echo htmlspecialchars($row['action']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['user_name']): ?>
                                        <strong><?php echo htmlspecialchars($row['user_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($row['user_email'] ?? ''); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Guest</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($row['ip_address'] ?? '—'); ?></code></td>
                                <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                    title="<?php echo htmlspecialchars($row['details'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($row['details'] ?? '—'); ?>
                                </td>
                                <td style="color:var(--color-text-muted); white-space:nowrap;">
                                    <?php echo htmlspecialchars($row['created_at']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

<?php if ($totalPages > 1): ?>
                    <div style="padding:16px; display:flex; justify-content:center; gap:8px;">
                        <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
                            <?php $q = http_build_query(array_merge($_GET, ['page' => $p])); ?>
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
