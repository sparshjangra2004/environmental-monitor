<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/mongo_db.php';

requireLogin();

$pageTitle = 'Reports';

$mysql = getDBConnection();
$mongo = getMongoCollection();

if (isset($_GET['export'])) {
    $type = $_GET['export'];

if ($type === 'environmental') {
        $location  = intval($_GET['location'] ?? 0);
        $date_from = $_GET['date_from'] ?? '';
        $date_to   = $_GET['date_to']   ?? '';

$filter = [];
        if ($location) {
            $filter['location_id'] = $location;
        }
        if ($date_from || $date_to) {
            $dateFilter = [];
            if ($date_from) {
                $dateFilter['$gte'] = new MongoDB\BSON\UTCDateTime(strtotime($date_from . ' 00:00:00') * 1000);
            }
            if ($date_to) {
                $dateFilter['$lte'] = new MongoDB\BSON\UTCDateTime(strtotime($date_to . ' 23:59:59') * 1000);
            }
            $filter['recorded_at'] = $dateFilter;
        }

$result = $mongo->find($filter, ['sort' => ['recorded_at' => -1]]);

$title   = 'Environmental Report ' . date('Y-m-d H:i');
        $logStmt = $mysql->prepare(
            "INSERT INTO reports (user_id, report_type, title) VALUES (?, 'environmental', ?)"
        );
        $logStmt->bind_param("is", $_SESSION['user_id'], $title);
        $logStmt->execute();
        $logStmt->close();

header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="environmental_report_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Sensor', 'Station', 'City', 'Country',
            'Temperature(°C)', 'Humidity(%)', 'AQI', 'CO2(ppm)',
            'Pollution', 'Weather', 'Wind(km/h)', 'Recorded At', 'Source'
        ]);

        foreach ($result as $row) {

$dateStr = date('Y-m-d H:i:s', $row['recorded_at']->toDateTime()->getTimestamp());
            fputcsv($out, [
                $row['sensor_code'],
                $row['location_name'],
                $row['city'],
                $row['country'],
                $row['temperature'],
                $row['humidity'],
                $row['aqi'],
                $row['co2'],
                $row['pollution'],
                $row['weather_condition'],
                $row['wind_speed'],
                $dateStr,
                'MongoDB'

            ]);
        }
        fclose($out);
        $mysql->close();
        exit();
    }

if ($type === 'alerts') {
        $result = $mysql->query("
            SELECT a.*, l.name as station, l.city
            FROM alerts a
            JOIN locations l ON a.location_id = l.id
            ORDER BY a.created_at DESC
        ");

        $title   = 'Alerts Report ' . date('Y-m-d H:i');
        $logStmt = $mysql->prepare(
            "INSERT INTO reports (user_id, report_type, title) VALUES (?, 'alerts', ?)"
        );
        $logStmt->bind_param("is", $_SESSION['user_id'], $title);
        $logStmt->execute();
        $logStmt->close();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="alerts_report_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Alert Type', 'Station', 'City', 'Severity', 'Message', 'Status', 'Created At', 'Resolved At', 'Source']);

        while ($row = $result->fetch_assoc()) {
            fputcsv($out, [
                $row['id'], $row['alert_type'], $row['station'], $row['city'],
                $row['severity'], $row['message'], $row['status'],
                $row['created_at'], $row['resolved_at'] ?? 'N/A',
                'MySQL'

            ]);
        }
        fclose($out);
        $mysql->close();
        exit();
    }
}

$locations = $mysql->query("SELECT id, name, city FROM locations ORDER BY name");

$totalReadings = $mongo->countDocuments([]);

$statsResult = $mongo->aggregate([
    ['$group' => [
        '_id'          => null,
        'avg_temp'     => ['$avg' => '$temperature'],
        'avg_aqi'      => ['$avg' => '$aqi'],
        'avg_humidity' => ['$avg' => '$humidity'],
        'min_date'     => ['$min' => '$recorded_at'],
        'max_date'     => ['$max' => '$recorded_at'],
    ]]
]);

$readingStats = null;
foreach ($statsResult as $row) {
    $readingStats = $row;
}

$hasReadings  = $totalReadings > 0 && $readingStats;
$avg_temp     = $hasReadings ? round((float)$readingStats['avg_temp'],     1) : 'N/A';
$avg_aqi      = $hasReadings ? round((float)$readingStats['avg_aqi'],      0) : 'N/A';
$avg_humidity = $hasReadings ? round((float)$readingStats['avg_humidity'], 1) : 'N/A';

$firstReading = $hasReadings ? date('M j, Y', $readingStats['min_date']->toDateTime()->getTimestamp()) : 'N/A';
$lastReading  = $hasReadings ? date('M j, Y', $readingStats['max_date']->toDateTime()->getTimestamp()) : 'N/A';

$alertStats = $mysql->query("
    SELECT COUNT(*) as total, SUM(status='active') as active,
           SUM(status='resolved') as resolved, SUM(severity='critical') as critical
    FROM alerts
")->fetch_assoc();

$reportHistory = $mysql->query("
    SELECT r.*, u.name as user_name
    FROM reports r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.generated_at DESC
    LIMIT 20
");

$mysql->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header">
        <h2><i class="bi bi-file-earmark-bar-graph text-success" style="margin-right:8px;"></i>Reports</h2>
        <p class="text-muted mb-0">
            Environmental data from MongoDB · Alert data from MySQL
            <span class="badge" style="margin-left:8px; background:#4DB33D; color:#fff;">MongoDB</span>
            <span class="badge badge-success" style="margin-left:4px;">MySQL</span>
        </p>
    </div>

    <div class="row">
        <div class="col-8">

<div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-thermometer-half text-success" style="margin-right:8px;"></i>
                        Environmental Data Report
                        <span class="badge" style="margin-left:8px; background:#4DB33D; color:#fff;">MongoDB</span>
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3" style="font-size:14px;">
                        Exports sensor readings from MongoDB. CSV includes a "Source: MongoDB" column.
                    </p>
                    <form method="GET" class="row">
                        <input type="hidden" name="export" value="environmental">
                        <div class="col-4">
                            <label class="form-label">Station</label>
                            <select name="location" class="form-select">
                                <option value="">All Stations</option>
                                <?php while ($loc = $locations->fetch_assoc()): ?>
                                    <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['name']); ?> — <?php echo htmlspecialchars($loc['city']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control">
                        </div>
                        <div class="col-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control">
                        </div>
                        <div class="col-2" style="display:flex; align-items:flex-end;">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="bi bi-download"></i> Export CSV
                            </button>
                        </div>
                    </form>
                </div>
            </div>

<div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-bell text-warning" style="margin-right:8px;"></i>
                        Alerts Report
                        <span class="badge badge-success" style="margin-left:8px;">MySQL</span>
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3" style="font-size:14px;">Exports all alerts from MySQL.</p>
                    <a href="?export=alerts" class="btn btn-primary">
                        <i class="bi bi-download"></i> Export Alerts CSV
                    </a>
                </div>
            </div>

<div class="card table-card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-clock-history text-muted"></i> Recent Export History</h6>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr><th>Report</th><th>Type</th><th>Generated By</th><th>Date</th></tr>
                            </thead>
                            <tbody>
                                <?php if ($reportHistory->num_rows > 0): ?>
                                    <?php while ($row = $reportHistory->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['report_type'] === 'environmental' ? '' : 'badge-warning'; ?>" style="<?php echo $row['report_type'] === 'environmental' ? 'background:#4DB33D;color:#fff;' : ''; ?>">
                                                <?php echo ucfirst($row['report_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                                        <td><?php echo date('M j, Y H:i', strtotime($row['generated_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted" style="padding:32px 0;">No exports yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-4">

<div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-database" style="color:#4DB33D; margin-right:8px;"></i>
                        MongoDB Reading Stats
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table" style="margin:0;">
                        <tr><td class="text-muted">Total Readings</td><td><strong><?php echo number_format($totalReadings); ?></strong></td></tr>
                        <tr><td class="text-muted">Avg Temperature</td><td><strong><?php echo $avg_temp; ?>°C</strong></td></tr>
                        <tr><td class="text-muted">Avg AQI</td><td><strong><?php echo $avg_aqi; ?></strong></td></tr>
                        <tr><td class="text-muted">Avg Humidity</td><td><strong><?php echo $avg_humidity; ?>%</strong></td></tr>
                        <tr><td class="text-muted">First Reading</td><td><strong><?php echo $firstReading; ?></strong></td></tr>
                        <tr><td class="text-muted">Last Reading</td><td><strong><?php echo $lastReading; ?></strong></td></tr>
                    </table>
                </div>
            </div>

<div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-bell text-warning" style="margin-right:8px;"></i>
                        MySQL Alert Stats
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table" style="margin:0;">
                        <tr><td class="text-muted">Total Alerts</td><td><strong><?php echo $alertStats['total']; ?></strong></td></tr>
                        <tr><td class="text-muted">Active</td><td><strong class="text-danger"><?php echo $alertStats['active']; ?></strong></td></tr>
                        <tr><td class="text-muted">Resolved</td><td><strong class="text-success"><?php echo $alertStats['resolved']; ?></strong></td></tr>
                        <tr><td class="text-muted">Critical</td><td><strong class="text-danger"><?php echo $alertStats['critical']; ?></strong></td></tr>
                    </table>
                </div>
            </div>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
