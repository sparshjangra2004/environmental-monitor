<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/mongo_db.php';

requireLogin();

$pageTitle = 'Environmental Monitoring';

$mysql = getDBConnection();
$mongo = getMongoCollection();

$location_filter = $_GET['location'] ?? '';
$weather_filter  = $_GET['weather']  ?? '';
$date_from       = $_GET['date_from'] ?? '';
$date_to         = $_GET['date_to']   ?? '';

$filter = [];

if ($location_filter) {

$filter['location_id'] = (int) $location_filter;
}

if ($weather_filter) {
    $filter['weather_condition'] = $weather_filter;
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

$perPage     = 15;
$currentPage = max(1, intval($_GET['page'] ?? 1));
$offset      = ($currentPage - 1) * $perPage;

$totalRows  = $mongo->countDocuments($filter);
$totalPages = ceil($totalRows / $perPage);

$readings = $mongo->find(
    $filter,
    ['sort' => ['recorded_at' => -1], 'skip' => $offset, 'limit' => $perPage]
);

$locations = $mysql->query(
    "SELECT id, name, city FROM locations WHERE status = 'active' ORDER BY name"
);

$mysql->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header flex justify-between items-center">
        <div>
            <h2><i class="bi bi-activity text-success"></i> Environmental Monitoring</h2>
            <p class="text-muted" style="margin-bottom:0;">
                Live sensor readings —
                <span class="badge" style="background:#4DB33D; color:#fff;">MongoDB</span>
                <?php echo number_format($totalRows); ?> records found
            </p>
        </div>
        <a href="/environmental_monitor/user/reports.php" class="btn btn-outline">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row gap-3 items-center">
                <div class="col-3">
                    <label class="form-label">Location</label>
                    <select name="location" class="form-select">
                        <option value="">All Stations</option>
                        <?php while ($loc = $locations->fetch_assoc()): ?>
                            <option value="<?php echo $loc['id']; ?>"
                                <?php echo $location_filter == $loc['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['name']); ?> — <?php echo htmlspecialchars($loc['city']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-2">
                    <label class="form-label">Weather</label>
                    <select name="weather" class="form-select">
                        <option value="">All Weather</option>
                        <?php foreach (['Sunny','Cloudy','Rainy','Foggy','Stormy'] as $w): ?>
                            <option value="<?php echo $w; ?>" <?php echo $weather_filter === $w ? 'selected' : ''; ?>>
                                <?php echo $w; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control"
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control"
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-3 flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel"></i> Filter
                    </button>
                    <a href="/environmental_monitor/user/monitoring.php" class="btn btn-outline btn-sm">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header flex justify-between items-center">
            <h6 style="margin:0;">
                <i class="bi bi-table text-success"></i>
                Sensor Readings
                <span class="badge" style="background:#4DB33D; color:#fff;"><?php echo number_format($totalRows); ?></span>
            </h6>
            <input type="text" class="form-control" style="width:auto;"
                   placeholder="Search table..."
                   onkeyup="searchTable('monitorSearch','monitorTable')"
                   id="monitorSearch">
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table" id="monitorTable">
                    <thead>
                        <tr>
                            <th>Sensor</th><th>Station</th><th>Temp °C</th><th>Humidity %</th>
                            <th>AQI</th><th>CO₂ ppm</th><th>Pollution</th><th>Weather</th>
                            <th>Wind km/h</th><th>Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rowCount = 0;
                        foreach ($readings as $row):
                            $rowCount++;
                            $aqi      = (int) $row['aqi'];
                            $aqiClass = $aqi <= 50 ? 'aqi-good' : ($aqi <= 100 ? 'aqi-moderate' : ($aqi <= 150 ? 'aqi-unhealthy' : 'aqi-hazardous'));

$dateStr   = date('M j, H:i', $row['recorded_at']->toDateTime()->getTimestamp());
                            $temp      = $row['temperature'];
                            $tempColor = $temp > 35 ? 'text-danger' : ($temp > 25 ? 'text-warning' : 'text-success');
                            $icons     = ['Sunny'=>'bi-sun-fill text-warning','Cloudy'=>'bi-cloud-fill text-muted','Rainy'=>'bi-cloud-rain-fill text-info','Foggy'=>'bi-cloud-fog2-fill text-muted','Stormy'=>'bi-cloud-lightning-fill'];
                            $icon      = $icons[$row['weather_condition']] ?? 'bi-cloud';
                        ?>
                        <tr>
                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($row['sensor_code']); ?></span></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['location_name']); ?></strong>
                                <div class="text-muted" style="font-size:13px;"><?php echo htmlspecialchars($row['city']); ?></div>
                            </td>
                            <td><span class="<?php echo $tempColor; ?>"><?php echo $temp; ?>°</span></td>
                            <td><?php echo $row['humidity']; ?>%</td>
                            <td><span class="badge <?php echo $aqiClass; ?>"><?php echo $aqi; ?></span></td>
                            <td><?php echo $row['co2']; ?></td>
                            <td><?php echo $row['pollution']; ?></td>
                            <td><i class="bi <?php echo $icon; ?>"></i> <?php echo $row['weather_condition']; ?></td>
                            <td><?php echo $row['wind_speed']; ?></td>
                            <td class="text-muted" style="font-size:13px;"><?php echo $dateStr; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($rowCount === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted" style="padding:48px 0;">
                                <i class="bi bi-inbox" style="font-size:32px; display:block; margin-bottom:8px;"></i>
                                No readings match your filters.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="card-footer" style="padding:16px 20px; border-top:1px solid var(--color-border);">
            <div class="flex justify-between items-center">
                <small class="text-muted">
                    Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRows); ?>
                    of <?php echo number_format($totalRows); ?> records
                </small>
                <nav><ul class="pagination" style="margin:0;">
                    <?php if ($currentPage > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $currentPage-1; echo $location_filter?"&location=$location_filter":''; echo $weather_filter?"&weather=$weather_filter":''; echo $date_from?"&date_from=$date_from":''; echo $date_to?"&date_to=$date_to":''; ?>"><i class="bi bi-chevron-left"></i></a></li>
                    <?php endif; ?>
                    <?php for ($p = max(1,$currentPage-2); $p <= min($totalPages,$currentPage+2); $p++): ?>
                        <li class="page-item <?php echo $p===$currentPage?'active':''; ?>"><a class="page-link" href="?page=<?php echo $p; echo $location_filter?"&location=$location_filter":''; echo $weather_filter?"&weather=$weather_filter":''; echo $date_from?"&date_from=$date_from":''; echo $date_to?"&date_to=$date_to":''; ?>"><?php echo $p; ?></a></li>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <li class="page-item"><a class="page-link" href="?page=<?php echo $currentPage+1; echo $location_filter?"&location=$location_filter":''; echo $weather_filter?"&weather=$weather_filter":''; echo $date_from?"&date_from=$date_from":''; echo $date_to?"&date_to=$date_to":''; ?>"><i class="bi bi-chevron-right"></i></a></li>
                    <?php endif; ?>
                </ul></nav>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
