<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/neo_db.php';

requireLogin();

$pageTitle = 'Network View';

$neo   = getNeoClient();
$error = null;

try {

$monitorsResult = $neo->run(
        'MATCH (u:User)-[:MONITORS]->(l:Location)
         RETURN u.name AS user_name, u.email AS email,
                l.name AS location_name, l.city AS city
         ORDER BY u.name, l.name'
    );

$containsResult = $neo->run(
        'MATCH (l:Location)-[:CONTAINS]->(s:Sensor)
         RETURN l.name AS location_name, l.city AS city, s.code AS sensor_code
         ORDER BY l.name, s.code'
    );

$triggersResult = $neo->run(
        'MATCH (s:Sensor)-[r:TRIGGERS]->(a:AlertType)
         RETURN s.code AS sensor_code, a.type AS alert_type,
                a.severity AS severity, r.count AS trigger_count
         ORDER BY r.count DESC'
    );

$summaryResult = $neo->run(
        'MATCH (n) WITH COUNT(n) AS nodes
         MATCH ()-[r]->() WITH nodes, COUNT(r) AS rels
         RETURN nodes, rels'
    );

    $summary = null;
    foreach ($summaryResult as $row) {
        $summary = $row;
    }

} catch (Exception $e) {
    $error = 'Neo4j connection failed: ' . $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header">
        <h2>
            <i class="bi bi-diagram-3" style="color:#6366f1; margin-right:8px;"></i>
            Network View
        </h2>
        <p class="text-muted mb-0">
            Graph relationships from Neo4j — how users, locations, sensors and alerts connect
            <span class="badge" style="margin-left:8px; background:#6366f1; color:#fff;">Neo4j</span>
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php else: ?>

<?php if ($summary): ?>
    <div class="row mb-4">
        <div class="col-3">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number" style="color:#6366f1;"><?php echo $summary->get('nodes'); ?></div>
                        <div class="stat-label">Total Nodes</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(99,102,241,0.1); color:#6366f1;">
                        <i class="bi bi-circle-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number" style="color:#6366f1;"><?php echo $summary->get('rels'); ?></div>
                        <div class="stat-label">Total Relationships</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(99,102,241,0.1); color:#6366f1;">
                        <i class="bi bi-arrow-left-right"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="card stat-card">
                <div style="font-size:13px; color:var(--color-text-muted); line-height:1.8;">
                    <strong style="color:#6366f1;">Graph structure:</strong>
                    &nbsp; (User)
                    <i class="bi bi-arrow-right" style="color:#6366f1;"></i>
                    (Location)
                    <i class="bi bi-arrow-right" style="color:#6366f1;"></i>
                    (Sensor)
                    <i class="bi bi-arrow-right" style="color:#6366f1;"></i>
                    (AlertType)
                    <br>
                    <span class="badge" style="background:#6366f1; color:#fff; margin-right:4px;">MONITORS</span>
                    <span class="badge" style="background:#6366f1; color:#fff; margin-right:4px;">CONTAINS</span>
                    <span class="badge" style="background:#6366f1; color:#fff;">TRIGGERS</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-4">

<div class="col-6">
            <div class="card table-card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-person-lines-fill" style="color:#6366f1;"></i>
                        Who Monitors Which Station
                    </h6>
                    <small class="text-muted" style="font-size:11px; font-family:monospace;">
                        MATCH (u:User)-[:MONITORS]->(l:Location)
                    </small>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Monitors Station</th>
                                    <th>City</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 0;
                                foreach ($monitorsResult as $row):
                                    $count++;
                                ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-person-circle" style="color:#6366f1; margin-right:4px;"></i>
                                        <?php echo htmlspecialchars($row->get('user_name')); ?>
                                    </td>
                                    <td class="text-muted" style="font-size:12px;">
                                        <?php echo htmlspecialchars($row->get('email')); ?>
                                    </td>
                                    <td>
                                        <i class="bi bi-geo-alt" style="color:#6366f1; margin-right:4px;"></i>
                                        <?php echo htmlspecialchars($row->get('location_name')); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row->get('city')); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if ($count === 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted" style="padding:32px 0;">
                                        No data — run the data generator first.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

<div class="col-6">
            <div class="card table-card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-cpu" style="color:#6366f1;"></i>
                        Sensors at Each Station
                    </h6>
                    <small class="text-muted" style="font-size:11px; font-family:monospace;">
                        MATCH (l:Location)-[:CONTAINS]->(s:Sensor)
                    </small>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Station</th>
                                    <th>City</th>
                                    <th>Sensor Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $count = 0;
                                foreach ($containsResult as $row):
                                    $count++;
                                ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-geo-alt" style="color:#6366f1; margin-right:4px;"></i>
                                        <?php echo htmlspecialchars($row->get('location_name')); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row->get('city')); ?></td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?php echo htmlspecialchars($row->get('sensor_code')); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if ($count === 0): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted" style="padding:32px 0;">
                                        No data — run the data generator first.
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

<div class="card table-card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="bi bi-bell" style="color:#6366f1;"></i>
                Which Sensors Trigger Which Alerts
            </h6>
            <small class="text-muted" style="font-size:11px; font-family:monospace;">
                MATCH (s:Sensor)-[r:TRIGGERS]->(a:AlertType) RETURN s.code, a.type, a.severity, r.count ORDER BY r.count DESC
            </small>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sensor</th>
                            <th>Alert Type</th>
                            <th>Severity</th>
                            <th>Times Triggered</th>
                            <th>Frequency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $allRows  = [];
                        $maxCount = 1;
                        foreach ($triggersResult as $row) {
                            $allRows[] = $row;
                            if ($row->get('trigger_count') > $maxCount) {
                                $maxCount = $row->get('trigger_count');
                            }
                        }
                        if (count($allRows) === 0):
                        ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted" style="padding:32px 0;">
                                No data — run the data generator first.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($allRows as $row):
                            $sev      = $row->get('severity');
                            $sevClass = $sev === 'critical' ? 'badge-danger' : ($sev === 'high' ? 'badge-warning' : 'badge-secondary');
                            $pct      = round(($row->get('trigger_count') / $maxCount) * 100);
                        ?>
                        <tr>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo htmlspecialchars($row->get('sensor_code')); ?>
                                </span>
                            </td>
                            <td><?php echo ucwords(str_replace('_', ' ', $row->get('alert_type'))); ?></td>
                            <td>
                                <span class="badge <?php echo $sevClass; ?>">
                                    <?php echo ucfirst($sev); ?>
                                </span>
                            </td>
                            <td><?php echo $row->get('trigger_count'); ?></td>
                            <td style="width:160px;">
                                <div style="height:6px; background:var(--color-border); border-radius:4px; overflow:hidden;">
                                    <div style="height:100%; background:#6366f1; width:<?php echo $pct; ?>%; border-radius:4px;"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<div class="card mb-4" style="border-left:4px solid #6366f1;">
        <div class="card-body">
            <h6 style="color:#6366f1;"><i class="bi bi-info-circle"></i> How Cypher queries work</h6>
            <div class="row">
                <div class="col-4">
                    <code style="font-size:12px; color:#6366f1;">MATCH (u:User)-[:MONITORS]->(l:Location)</code>
                    <p class="text-muted mt-1" style="font-size:13px;">
                        Find a User node connected to a Location node via a MONITORS relationship. The arrow shows direction.
                    </p>
                </div>
                <div class="col-4">
                    <code style="font-size:12px; color:#6366f1;">MERGE (n:Node {id: $id})</code>
                    <p class="text-muted mt-1" style="font-size:13px;">
                        Create the node if it doesn't exist, find it if it does. Like INSERT OR UPDATE in MySQL.
                    </p>
                </div>
                <div class="col-4">
                    <code style="font-size:12px; color:#6366f1;">RETURN s.code, r.count ORDER BY r.count DESC</code>
                    <p class="text-muted mt-1" style="font-size:13px;">
                        Return specific properties from nodes (s.code) and relationships (r.count), sorted highest first.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
