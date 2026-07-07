<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/mongo_db.php';

requireAdmin();

$pageTitle = 'Admin Dashboard';

$mysql = getDBConnection();

$mongo = getMongoCollection();

$totalUsers     = $mysql->query("SELECT COUNT(*) as c FROM users WHERE role_id = 2")->fetch_assoc()['c'];
$totalSensors   = $mysql->query("SELECT COUNT(*) as c FROM sensor_metadata")->fetch_assoc()['c'];
$totalLocations = $mysql->query("SELECT COUNT(*) as c FROM locations")->fetch_assoc()['c'];
$activeAlerts   = $mysql->query("SELECT COUNT(*) as c FROM alerts WHERE status = 'active'")->fetch_assoc()['c'];

$recentAlerts = $mysql->query("
    SELECT a.*, l.name as location_name
    FROM alerts a
    JOIN locations l ON a.location_id = l.id
    ORDER BY a.created_at DESC
    LIMIT 5
");

$mysql->close();

$totalReadings = $mongo->countDocuments([]);

$locationPipeline = [
    ['$group' => [
        '_id'      => '$location_name',

        'avg_temp' => ['$avg' => '$temperature'],
        'avg_aqi'  => ['$avg' => '$aqi'],
        'total'    => ['$sum' => 1]
    ]],
    ['$sort' => ['_id' => 1]]

];

$locationStats  = $mongo->aggregate($locationPipeline);
$chartLocations = [];
$chartTemps     = [];
$chartAqi       = [];

foreach ($locationStats as $row) {
    $chartLocations[] = $row['_id'];

    $chartTemps[]     = round((float) $row['avg_temp'], 1);
    $chartAqi[]       = round((float) $row['avg_aqi'],  0);
}

$recentReadings = $mongo->find(
    [],

    ['sort' => ['recorded_at' => -1], 'limit' => 10]
);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header flex justify-between items-center">
        <div>
            <h2><i class="bi bi-speedometer2 text-success"></i> Admin Dashboard</h2>
            <p class="text-muted mb-0">
                Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                Today is <?php echo date('l, F j, Y'); ?>
                <span class="badge badge-success">MySQL</span>
                <span class="badge" style="background:#4DB33D; color:#fff;">MongoDB</span>
            </p>
        </div>
        <div>
            <a href="/environmental_monitor/simulator/generate.php" class="btn btn-primary">
                <i class="bi bi-lightning-fill"></i> Generate Data
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-3">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-success"><?php echo $totalUsers; ?></div>
                        <div class="stat-label">Registered Users</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-success"><?php echo $totalSensors; ?></div>
                        <div class="stat-label">Active Sensors</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-cpu-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-danger"><?php echo $activeAlerts; ?></div>
                        <div class="stat-label">Active Alerts</div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                </div>
            </div>
        </div>
        <div class="col-3">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>

                        <div class="stat-number text-warning"><?php echo number_format($totalReadings); ?></div>
                        <div class="stat-label">Total Readings <span style="font-size:11px; color:#4DB33D;">(MongoDB)</span></div>
                    </div>
                    <div class="stat-icon"><i class="bi bi-activity"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <div class="card chart-card">
                <h6 class="mb-3">
                    <i class="bi bi-thermometer-half text-danger"></i>
                    Average Temperature by Station
                    <span style="font-size:11px; color:#4DB33D;">(MongoDB aggregate)</span>
                </h6>
                <canvas id="tempChart" height="120"></canvas>
            </div>
        </div>
        <div class="col-6">
            <div class="card chart-card">
                <h6 class="mb-3">
                    <i class="bi bi-wind text-info"></i>
                    Average AQI by Station
                    <span style="font-size:11px; color:#4DB33D;">(MongoDB aggregate)</span>
                </h6>
                <canvas id="aqiChart" height="120"></canvas>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card table-card">
                <div class="card-header flex justify-between items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-table text-success"></i>
                        Recent Readings
                        <span style="font-size:11px; color:#4DB33D;">(MongoDB find)</span>
                    </h6>
                    <input type="text" class="form-control" style="width:auto;"
                           placeholder="Search..."
                           onkeyup="searchTable('readingSearch','readingsTable')"
                           id="readingSearch">
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table class="table" id="readingsTable">
                            <thead>
                                <tr>
                                    <th>Sensor</th>
                                    <th>Location</th>
                                    <th>Temp (°C)</th>
                                    <th>Humidity (%)</th>
                                    <th>AQI</th>
                                    <th>CO₂ (ppm)</th>
                                    <th>Weather</th>
                                    <th>Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rowCount = 0;
                                foreach ($recentReadings as $row):
                                    $rowCount++;
                                    $aqi = (int) $row['aqi'];
                                    $aqiClass = $aqi <= 50 ? 'aqi-good' : ($aqi <= 100 ? 'aqi-moderate' : ($aqi <= 150 ? 'aqi-unhealthy' : 'aqi-hazardous'));

$dateStr = date('M j, H:i', $row['recorded_at']->toDateTime()->getTimestamp());
                                ?>
                                <tr>
                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['sensor_code']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['location_name']); ?>, <?php echo htmlspecialchars($row['city']); ?></td>
                                    <td><?php echo $row['temperature']; ?>°</td>
                                    <td><?php echo $row['humidity']; ?>%</td>
                                    <td><span class="badge <?php echo $aqiClass; ?>"><?php echo $aqi; ?></span></td>
                                    <td><?php echo $row['co2']; ?></td>
                                    <td><?php echo $row['weather_condition']; ?></td>
                                    <td><?php echo $dateStr; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if ($rowCount === 0): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted" style="padding:32px 0;">
                                        <i class="bi bi-inbox" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                                        No readings yet. Click "Generate Data".
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

    <div class="row">
        <div class="col-12">
            <div class="card table-card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-bell text-warning"></i>
                        Recent Alerts
                        <span class="badge badge-success" style="margin-left:6px;">MySQL</span>
                    </h6>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Severity</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recentAlerts->num_rows > 0): ?>
                                    <?php while ($row = $recentAlerts->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo ucwords(str_replace('_', ' ', $row['alert_type'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['location_name']); ?></td>
                                        <td>
                                            <span class="badge severity-<?php echo $row['severity']; ?>">
                                                <?php echo ucfirst($row['severity']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['message']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['status'] === 'active' ? 'badge-danger' : ($row['status'] === 'acknowledged' ? 'badge-warning' : 'badge-success'); ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, H:i', strtotime($row['created_at'])); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted" style="padding:32px 0;">No alerts at this time.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const locations = <?php echo json_encode($chartLocations); ?>;
const avgTemps  = <?php echo json_encode($chartTemps); ?>;
const avgAqi    = <?php echo json_encode($chartAqi); ?>;

const sharedBarOptions = {
    responsive: true,
    plugins: { legend: { display: false } },
    scales:  { y: { beginAtZero: true } }
};

new Chart(document.getElementById('tempChart'), {
    type: 'bar',
    data: {
        labels: locations,
        datasets: [{
            label: 'Avg Temperature (°C)',
            data: avgTemps,
            backgroundColor: 'rgba(220,53,69,0.7)',
            borderColor: 'rgba(220,53,69,1)',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: sharedBarOptions
});

new Chart(document.getElementById('aqiChart'), {
    type: 'bar',
    data: {
        labels: locations,
        datasets: [{
            label: 'Avg AQI',
            data: avgAqi,
            backgroundColor: 'rgba(13,202,240,0.7)',
            borderColor: 'rgba(13,202,240,1)',
            borderWidth: 1,
            borderRadius: 6
        }]
    },
    options: sharedBarOptions
});
</script>
