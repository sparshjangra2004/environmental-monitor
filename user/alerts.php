<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
requireLogin();

$pageTitle = 'Alerts';
$conn = getDBConnection();

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['user_role'] === 'admin') {
    $alert_id = intval($_POST['alert_id'] ?? 0);
    $action   = $_POST['action'] ?? '';

    if ($alert_id && in_array($action, ['acknowledged', 'resolved'])) {
        $resolved_at = $action === 'resolved' ? date('Y-m-d H:i:s') : null;

        if ($resolved_at) {
            $stmt = $conn->prepare("UPDATE alerts SET status = ?, resolved_at = ? WHERE id = ?");
            $stmt->bind_param("ssi", $action, $resolved_at, $alert_id);
        } else {
            $stmt = $conn->prepare("UPDATE alerts SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $action, $alert_id);
        }
        $stmt->execute();
        $stmt->close();
        $success = 'Alert status updated.';
    }
}

$status_filter   = $_GET['status']   ?? '';
$severity_filter = $_GET['severity'] ?? '';
$type_filter     = $_GET['type']     ?? '';

$where  = [];
$params = [];
$types  = '';

if ($status_filter) {
    $where[]  = "a.status = ?";
    $params[] = $status_filter;
    $types   .= 's';
}
if ($severity_filter) {
    $where[]  = "a.severity = ?";
    $params[] = $severity_filter;
    $types   .= 's';
}
if ($type_filter) {
    $where[]  = "a.alert_type = ?";
    $params[] = $type_filter;
    $types   .= 's';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$perPage     = 15;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM alerts a $whereSQL");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows  = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);
$countStmt->close();

$sql  = "
    SELECT a.*, l.name as location_name, l.city
    FROM alerts a
    JOIN locations l ON a.location_id = l.id
    $whereSQL
    ORDER BY
        FIELD(a.severity, 'critical','high','medium','low'),
        a.created_at DESC
    LIMIT ? OFFSET ?
";

$allParams = array_merge($params, [$perPage, $offset]);
$allTypes  = $types . 'ii';
$stmt      = $conn->prepare($sql);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$alerts = $stmt->get_result();
$stmt->close();

$summary = $conn->query("
    SELECT
        SUM(status = 'active')       as active,
        SUM(status = 'acknowledged') as acknowledged,
        SUM(status = 'resolved')     as resolved,
        SUM(severity = 'critical')   as critical,
        SUM(severity = 'high')       as high_count
    FROM alerts
")->fetch_assoc();

$conn->close();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header">
        <h2><i class="bi bi-bell text-warning" style="margin-right:8px;"></i>Alert Management</h2>
        <p class="text-muted mb-0">Monitor and manage environmental alerts across all stations</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle" style="margin-right:8px;"></i><?php echo $success; ?>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-4">
            <div class="card stat-card" style="border-left:3px solid var(--color-danger);">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-danger"><?php echo $summary['active']; ?></div>
                        <div class="stat-label">Active Alerts</div>
                    </div>
                    <div class="stat-icon" style="background-color:rgba(220,53,69,0.1);color:var(--color-danger);">
                        <i class="bi bi-exclamation-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card stat-card" style="border-left:3px solid var(--color-warning);">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number" style="color:var(--color-warning);"><?php echo $summary['acknowledged']; ?></div>
                        <div class="stat-label">Acknowledged</div>
                    </div>
                    <div class="stat-icon" style="background-color:rgba(255,193,7,0.1);color:var(--color-warning);">
                        <i class="bi bi-eye-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card stat-card" style="border-left:3px solid var(--color-primary);">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-success"><?php echo $summary['resolved']; ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                    <div class="stat-icon" style="background-color:rgba(25,135,84,0.1);color:var(--color-primary);">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row items-center">
                <div class="col-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active"       <?php echo $status_filter === 'active'       ? 'selected' : ''; ?>>Active</option>
                        <option value="acknowledged" <?php echo $status_filter === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                        <option value="resolved"     <?php echo $status_filter === 'resolved'     ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>
                <div class="col-3">
                    <label class="form-label">Severity</label>
                    <select name="severity" class="form-select">
                        <option value="">All Severities</option>
                        <option value="critical" <?php echo $severity_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="high"     <?php echo $severity_filter === 'high'     ? 'selected' : ''; ?>>High</option>
                        <option value="medium"   <?php echo $severity_filter === 'medium'   ? 'selected' : ''; ?>>Medium</option>
                        <option value="low"      <?php echo $severity_filter === 'low'      ? 'selected' : ''; ?>>Low</option>
                    </select>
                </div>
                <div class="col-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach (['high_temperature','low_temperature','high_aqi','high_pollution','high_humidity','storm_warning'] as $t): ?>
                            <option value="<?php echo $t; ?>" <?php echo $type_filter === $t ? 'selected' : ''; ?>>
                                <?php echo ucwords(str_replace('_', ' ', $t)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-3 flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel" style="margin-right:4px;"></i>Filter
                    </button>
                    <a href="/environmental_monitor/user/alerts.php" class="btn btn-outline btn-sm">
                        <i class="bi bi-x-circle" style="margin-right:4px;"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header flex justify-between items-center">
            <h6 class="mb-0">
                <i class="bi bi-table text-warning" style="margin-right:8px;"></i>
                Alerts
                <span class="badge badge-warning" style="margin-left:8px;"><?php echo $totalRows; ?></span>
            </h6>
            <input type="text" class="form-control" style="width:auto;"
                   id="alertSearch" placeholder="Search..."
                   onkeyup="searchTable('alertSearch','alertsTable')">
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table" id="alertsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Severity</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Created</th>
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
                            <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($alerts->num_rows > 0): ?>
                            <?php while ($row = $alerts->fetch_assoc()): ?>
                            <tr>
                                <td class="text-muted"><?php echo $row['id']; ?></td>
                                <td><?php echo ucwords(str_replace('_', ' ', $row['alert_type'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['location_name']); ?></strong>
                                    <div class="text-muted" style="font-size:12.8px;"><?php echo htmlspecialchars($row['city']); ?></div>
                                </td>
                                <td>
                                    <span class="badge severity-<?php echo $row['severity']; ?>">
                                        <?php echo ucfirst($row['severity']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['message']); ?></td>
                                <td>
                                    <?php
                                    $statusBadge = match($row['status']) {
                                        'active'       => 'badge-danger',
                                        'acknowledged' => 'badge-warning',
                                        'resolved'     => 'badge-success',
                                        default        => 'badge-secondary'
                                    };
                                    ?>
                                    <span class="badge <?php echo $statusBadge; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?php echo date('M j, H:i', strtotime($row['created_at'])); ?>
                                </td>
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                <td>
                                    <?php if ($row['status'] === 'active'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="alert_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action"   value="acknowledged">
                                            <button type="submit" class="btn btn-outline btn-sm" style="font-size:12px;padding:3.2px 8px;">
                                                <i class="bi bi-eye" style="margin-right:4px;"></i>Acknowledge
                                            </button>
                                        </form>
                                    <?php elseif ($row['status'] === 'acknowledged'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="alert_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action"   value="resolved">
                                            <button type="submit" class="btn btn-outline btn-sm" style="font-size:12px;padding:3.2px 8px;">
                                                <i class="bi bi-check" style="margin-right:4px;"></i>Resolve
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding:48px 0;">
                                    <i class="bi bi-check-circle text-success" style="font-size:28px;display:block;margin-bottom:8px;"></i>
                                    No alerts match your filters.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="border-top:1px solid var(--color-border);padding:16px 20px;">
            <div class="flex justify-between items-center">
                <small class="text-muted">
                    Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?>
                    of <?php echo $totalRows; ?> alerts
                </small>
                <nav>
                    <ul class="pagination mb-0">
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage - 1;
                                echo $status_filter   ? "&status=$status_filter"     : '';
                                echo $severity_filter ? "&severity=$severity_filter" : '';
                                echo $type_filter     ? "&type=$type_filter"         : '';
                                ?>"><i class="bi bi-chevron-left"></i></a>
                            </li>
                        <?php endif; ?>
                        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                            <li class="page-item <?php echo $p === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $p;
                                echo $status_filter   ? "&status=$status_filter"     : '';
                                echo $severity_filter ? "&severity=$severity_filter" : '';
                                echo $type_filter     ? "&type=$type_filter"         : '';
                                ?>"><?php echo $p; ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage + 1;
                                echo $status_filter   ? "&status=$status_filter"     : '';
                                echo $severity_filter ? "&severity=$severity_filter" : '';
                                echo $type_filter     ? "&type=$type_filter"         : '';
                                ?>"><i class="bi bi-chevron-right"></i></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
