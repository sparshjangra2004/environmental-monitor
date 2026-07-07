<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/sanitize.php';
require_once __DIR__ . '/../security/ip_blocker.php';
require_once __DIR__ . '/../security/logger.php';

requireAdmin();

$flash = '';
$flashType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $flash = 'Security token invalid.';
        $flashType = 'danger';
    } else {
        $action = $_POST['action'];

        if ($action === 'block') {
            $ip     = sanitizeString($_POST['ip_address'] ?? '');
            $reason = sanitizeString($_POST['reason']     ?? 'Manually blocked by admin');

            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                $flash = 'Invalid IP address format.';
                $flashType = 'danger';
            } else {
                blockIP($ip, $reason, $_SESSION['user_id']);
                logActivity($_SESSION['user_id'], 'ip_blocked', "Admin blocked IP: $ip — $reason");
                $flash = "IP address $ip has been blocked.";
            }

        } elseif ($action === 'unblock') {
            $id = sanitizeInt($_POST['block_id'] ?? 0);
            if ($id) {
                unblockIP((int)$id);
                logActivity($_SESSION['user_id'], 'ip_unblocked', "Admin unblocked IP record ID: $id");
                $flash = "IP address has been unblocked.";
            }
        }
    }
}

$conn = getDBConnection();
$rows = [];
$tableExists = false;

$check = $conn->query("SHOW TABLES LIKE 'blocked_ips'");
if ($check && $check->num_rows > 0) {
    $tableExists = true;
    $result = $conn->query(
        "SELECT b.*, u.name AS blocked_by_name
         FROM blocked_ips b
         LEFT JOIN users u ON b.blocked_by = u.id
         ORDER BY b.blocked_at DESC
         LIMIT 200"
    );
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}
$conn->close();

$pageTitle = 'IP Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 32px 20px;">

    <div class="page-header" style="margin-bottom:28px;">
        <h2 style="font-weight:700; margin:0;">IP Management</h2>
        <p class="text-muted" style="margin:4px 0 0;">Feature 9 — block and unblock IP addresses from accessing the system</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flashType; ?> auto-dismiss">
            <i class="bi bi-<?php echo $flashType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <?php echo htmlspecialchars($flash); ?>
        </div>
    <?php endif; ?>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            The <code>blocked_ips</code> table has not been created yet.
            Run the CREATE TABLE SQL from <code>security/ip_blocker.php</code> in phpMyAdmin first.
        </div>
    <?php endif; ?>

<div class="card mb-4">
        <div class="card-header">
            <h5 style="margin:0;"><i class="bi bi-shield-x"></i> Block an IP Address</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="block">
                <div style="display:grid; grid-template-columns:1fr 2fr auto; gap:12px; align-items:end;">
                    <div>
                        <label class="form-label">IP Address</label>
                        <input type="text" class="form-control" name="ip_address"
                               placeholder="192.168.1.100" required
                               pattern="^(\d{1,3}\.){3}\d{1,3}$|^([0-9a-fA-F:]+)$">
                    </div>
                    <div>
                        <label class="form-label">Reason</label>
                        <input type="text" class="form-control" name="reason"
                               placeholder="e.g. Repeated failed login attempts" value="Manually blocked by admin">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-slash-circle"></i> Block IP
                        </button>
                    </div>
                </div>
            </form>

            <div style="margin-top:12px; padding:12px; background:var(--color-bg); border-radius:8px; border:1px solid var(--color-border);">
                <p class="text-muted" style="font-size:13px; margin:0;">
                    <i class="bi bi-info-circle"></i>
                    <strong>Your IP address:</strong>
                    <code><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?></code>
                    — do not block your own IP!
                </p>
            </div>
        </div>
    </div>

<div class="card">
        <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <h5 style="margin:0;"><i class="bi bi-list-ul"></i> IP Block List</h5>
            <span class="badge badge-secondary"><?php echo count($rows); ?> records</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($rows)): ?>
                <div style="padding:40px; text-align:center; color:var(--color-text-muted);">
                    <i class="bi bi-shield-check" style="font-size:32px; display:block; margin-bottom:8px;"></i>
                    No blocked IPs yet.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="table" style="margin:0;">
                        <thead>
                            <tr>
                                <th>IP Address</th>
                                <th>Reason</th>
                                <th>Blocked At</th>
                                <th>Blocked By</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($row['ip_address']); ?></code></td>
                                <td><?php echo htmlspecialchars($row['reason'] ?? '—'); ?></td>
                                <td style="font-size:13px; color:var(--color-text-muted);">
                                    <?php echo htmlspecialchars($row['blocked_at']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['blocked_by_name'] ?? 'System'); ?></td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <span class="badge badge-danger">Blocked</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['is_active']): ?>
                                        <form method="POST" style="display:inline;"
                                              onsubmit="return confirm('Unblock this IP?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="unblock">
                                            <input type="hidden" name="block_id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-outline" style="padding:4px 10px; font-size:12px;">
                                                <i class="bi bi-check-circle"></i> Unblock
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:13px;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
