<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/mongo_db.php';

requireLogin();

$pageTitle = 'Dashboard';

$mysql = getDBConnection();
$mongo = getMongoCollection();

$activeAlerts = $mysql->query(
    "SELECT COUNT(*) as count FROM alerts WHERE status = 'active'"
)->fetch_assoc()['count'];

$recentAlerts = $mysql->query("
    SELECT a.*, l.name as location_name
    FROM alerts a
    JOIN locations l ON a.location_id = l.id
    WHERE a.status = 'active'
    ORDER BY a.created_at DESC
    LIMIT 5
");

$mysql->close();

$avgPipeline = [
    ['$group' => [
        '_id'           => null,

        'avg_temp'      => ['$avg' => '$temperature'],
        'avg_humidity'  => ['$avg' => '$humidity'],
        'avg_aqi'       => ['$avg' => '$aqi'],
        'avg_co2'       => ['$avg' => '$co2'],
        'avg_pollution' => ['$avg' => '$pollution'],
        'total'         => ['$sum' => 1]
    ]]
];

$avgResult = $mongo->aggregate($avgPipeline);
$averages  = null;
foreach ($avgResult as $row) {
    $averages = $row;

}

$hasReadings   = $averages && (int)$averages['total'] > 0;
$avg_temp      = $hasReadings ? round((float)$averages['avg_temp'],      1) : 'N/A';
$avg_humidity  = $hasReadings ? round((float)$averages['avg_humidity'],  1) : 'N/A';
$avg_aqi       = $hasReadings ? round((float)$averages['avg_aqi'],       0) : 'N/A';
$avg_co2       = $hasReadings ? round((float)$averages['avg_co2'],       0) : 'N/A';
$avg_pollution = $hasReadings ? round((float)$averages['avg_pollution'], 1) : 'N/A';
$totalReadings = $hasReadings ? (int)$averages['total'] : 0;

$cutoff14 = new MongoDB\BSON\UTCDateTime((time() - 14 * 24 * 60 * 60) * 1000);

$trendPipeline = [
    ['$match' => ['recorded_at' => ['$gte' => $cutoff14]]],

    ['$group' => [

'_id'          => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$recorded_at']],
        'avg_temp'     => ['$avg' => '$temperature'],
        'avg_aqi'      => ['$avg' => '$aqi'],
        'avg_humidity' => ['$avg' => '$humidity'],
    ]],
    ['$sort' => ['_id' => 1]]

];

$trendResult   = $mongo->aggregate($trendPipeline);
$trendDates    = [];
$trendTemps    = [];
$trendAqi      = [];
$trendHumidity = [];

foreach ($trendResult as $row) {
    $trendDates[]    = date('M j', strtotime($row['_id']));
    $trendTemps[]    = round((float)$row['avg_temp'],     1);
    $trendAqi[]      = round((float)$row['avg_aqi'],      0);
    $trendHumidity[] = round((float)$row['avg_humidity'], 1);
}

$weatherPipeline = [
    ['$group' => [
        '_id'   => '$weather_condition',
        'count' => ['$sum' => 1]
    ]],
    ['$sort' => ['count' => -1]]
];

$weatherResult = $mongo->aggregate($weatherPipeline);
$weatherLabels = [];
$weatherCounts = [];
foreach ($weatherResult as $row) {
    $weatherLabels[] = $row['_id'];
    $weatherCounts[] = (int) $row['count'];
}

$latestPipeline = [
    ['$sort'        => ['recorded_at' => -1]],
    ['$group'       => ['_id' => '$location_id', 'doc' => ['$first' => '$$ROOT']]],
    ['$replaceRoot' => ['newRoot' => '$doc']],
    ['$sort'        => ['location_name' => 1]]
];

$latestResult = $mongo->aggregate($latestPipeline);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header">
        <h2><i class="bi bi-speedometer2 text-success" style="margin-right:8px"></i>Environmental Overview</h2>
        <p class="text-muted mb-0">
            Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
            Last updated: <?php echo date('F j, Y H:i'); ?>
            <span class="badge badge-success" style="margin-left:8px">MySQL</span>
            <span class="badge" style="margin-left:4px; background:#4DB33D; color:#fff;">MongoDB</span>
        </p>
    </div>

    <div class="row mb-4">
        <div class="col-2">
            <div class="card stat-card">
                <div class="text-center">
                    <div class="stat-icon mb-2" style="margin-left:auto;margin-right:auto;background-color:rgba(220,53,69,0.1);color:var(--color-danger)">
                        <i class="bi bi-thermometer-half"></i>
                    </div>
                    <div class="stat-number text-danger"><?php echo $avg_temp; ?>°</div>
                    <div class="stat-label">Avg Temperature</div>
                </div>
            </div>
        </div>
        <div class="col-2">
            <div class="card stat-card">
                <div class="text-center">
                    <div class="stat-icon mb-2" style="margin-left:auto;margin-right:auto;background-color:rgba(13,202,240,0.1);color:var(--color-info)">
                        <i class="bi bi-droplet-half"></i>
                    </div>
                    <div class="stat-number" style="color:var(--color-info)"><?php echo $avg_humidity; ?>%</div>
                    <div class="stat-label">Avg Humidity</div>
                </div>
            </div>
        </div>
        <div class="col-2">
            <div class="card stat-card">
                <div class="text-center">
                    <div class="stat-icon mb-2" style="margin-left:auto;margin-right:auto;background-color:rgba(255,193,7,0.1);color:var(--color-warning)">
                        <i class="bi bi-wind"></i>
                    </div>
                    <div class="stat-number" style="color:var(--color-warning)"><?php echo $avg_aqi; ?></div>
                    <div class="stat-label">Avg AQI</div>
                </div>
            </div>
        </div>
        <div class="col-2">
            <div class="card stat-card">
                <div class="text-center">
                    <div class="stat-icon mb-2" style="margin-left:auto;margin-right:auto;background-color:rgba(108,117,125,0.1);color:var(--color-secondary)">
                        <i class="bi bi-cloud-fog2"></i>
                    </div>
                    <div class="stat-number" style="color:var(--color-secondary)"><?php echo $avg_co2; ?></div>
                    <div class="stat-label">Avg CO₂ ppm</div>
                </div>
            </div>
        </div>
        <div class="col-2">
            <div class="card stat-card">
                <div class="text-center">
                    <div class="stat-icon mb-2" style="margin-left:auto;margin-right:auto;background-color:rgba(26,29,35,0.1);color:var(--color-dark)">
                        <i class="bi bi-moisture"></i>
                    </div>
                    <div class="stat-number" style="color:var(--color-dark)"><?php echo $avg_pollution; ?></div>
                    <div class="stat-label">Avg Pollution</div>
                </div>
            </div>
        </div>
        <div class="col-2">
            <div class="card stat-card">
                <div class="text-center">
                    <div class="stat-icon mb-2" style="margin-left:auto;margin-right:auto;background-color:rgba(220,53,69,0.1);color:var(--color-danger)">
                        <i class="bi bi-bell-fill"></i>
                    </div>
                    <div class="stat-number text-danger"><?php echo $activeAlerts; ?></div>
                    <div class="stat-label">Active Alerts</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-8">
            <div class="card chart-card">
                <h6 class="mb-3" style="font-weight:700">
                    <i class="bi bi-graph-up text-success" style="margin-right:8px"></i>
                    14-Day Temperature & AQI Trend
                    <span style="font-size:11px; color:#4DB33D;">(MongoDB aggregate)</span>
                </h6>
                <canvas id="trendChart" height="100"></canvas>
            </div>
        </div>
        <div class="col-4">
            <div class="card chart-card">
                <h6 class="mb-3" style="font-weight:700">
                    <i class="bi bi-cloud-sun text-warning" style="margin-right:8px"></i>
                    Weather Distribution
                    <span style="font-size:11px; color:#4DB33D;">(MongoDB aggregate)</span>
                </h6>
                <canvas id="weatherChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card table-card">
                <div class="card-header" style="background-color:#fff">
                    <h6 class="mb-0" style="font-weight:700">
                        <i class="bi bi-geo-alt text-success" style="margin-right:8px"></i>
                        Latest Reading per Station
                        <span style="font-size:11px; color:#4DB33D;">(MongoDB $group $first)</span>
                    </h6>
                </div>
                <div class="card-body" style="padding:0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Station</th>
                                    <th>Sensor</th>
                                    <th>Temp</th>
                                    <th>Humidity</th>
                                    <th>AQI</th>
                                    <th>CO₂</th>
                                    <th>Pollution</th>
                                    <th>Weather</th>
                                    <th>Wind</th>
                                    <th>Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latestResult as $row):
                                    $aqi = (int)$row['aqi'];
                                    $aqiClass = $aqi <= 50 ? 'aqi-good' : ($aqi <= 100 ? 'aqi-moderate' : ($aqi <= 150 ? 'aqi-unhealthy' : 'aqi-hazardous'));
                                    $icons = [
                                        'Sunny'  => 'bi-sun-fill text-warning',
                                        'Cloudy' => 'bi-cloud-fill text-secondary',
                                        'Rainy'  => 'bi-cloud-rain-fill text-info',
                                        'Foggy'  => 'bi-cloud-fog2-fill text-secondary',
                                        'Stormy' => 'bi-cloud-lightning-fill text-dark',
                                    ];
                                    $icon    = $icons[$row['weather_condition']] ?? 'bi-cloud';
                                    $dateStr = date('M j, H:i', $row['recorded_at']->toDateTime()->getTimestamp());
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['location_name']); ?></strong>
                                        <div class="text-muted" style="font-size:13px"><?php echo htmlspecialchars($row['city']); ?></div>
                                    </td>
                                    <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['sensor_code']); ?></span></td>
                                    <td><?php echo $row['temperature']; ?>°C</td>
                                    <td><?php echo $row['humidity']; ?>%</td>
                                    <td><span class="badge <?php echo $aqiClass; ?>"><?php echo $aqi; ?></span></td>
                                    <td><?php echo $row['co2']; ?> ppm</td>
                                    <td><?php echo $row['pollution']; ?> µg/m³</td>
                                    <td><i class="bi <?php echo $icon; ?>" style="margin-right:4px"></i><?php echo $row['weather_condition']; ?></td>
                                    <td><?php echo $row['wind_speed']; ?> km/h</td>
                                    <td><?php echo $dateStr; ?></td>
                                </tr>
                                <?php endforeach; ?>
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
const trendDates  = <?php echo json_encode($trendDates); ?>;
const trendTemps  = <?php echo json_encode($trendTemps); ?>;
const trendAqi    = <?php echo json_encode($trendAqi); ?>;
const weatherLbls = <?php echo json_encode($weatherLabels); ?>;
const weatherCnts = <?php echo json_encode($weatherCounts); ?>;

const chartColors = {
    temp:    'rgba(220,53,69,0.9)',
    tempFill:'rgba(220,53,69,0.1)',
    aqi:     'rgba(255,193,7,0.9)',
    aqiFill: 'rgba(255,193,7,0.1)',
    weather: ['rgba(255,193,7,0.8)','rgba(108,117,125,0.8)','rgba(13,202,240,0.8)','rgba(173,181,189,0.8)','rgba(52,58,64,0.8)']
};

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: trendDates,
        datasets: [
            { label: 'Temperature (°C)', data: trendTemps, borderColor: chartColors.temp, backgroundColor: chartColors.tempFill, tension: 0.4, fill: true, pointRadius: 4 },
            { label: 'AQI',              data: trendAqi,   borderColor: chartColors.aqi,  backgroundColor: chartColors.aqiFill,  tension: 0.4, fill: true, pointRadius: 4 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: false } } }
});

new Chart(document.getElementById('weatherChart'), {
    type: 'doughnut',
    data: {
        labels: weatherLbls,
        datasets: [{ data: weatherCnts, backgroundColor: chartColors.weather, borderWidth: 2 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
