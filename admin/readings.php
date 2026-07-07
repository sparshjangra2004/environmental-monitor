<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/mongo_db.php';

requireAdmin();

$pageTitle = 'Readings';

$mysql = getDBConnection();
$mongo = getMongoCollection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $flashSuccess = '';
    $flashError   = '';

if ($action === 'add') {
        $sensor_id   = intval($_POST['sensor_id']   ?? 0);
        $location_id = intval($_POST['location_id'] ?? 0);

$stmt = $mysql->prepare("
            SELECT sm.sensor_code, l.name AS location_name, l.city, l.country
            FROM sensor_metadata sm
            JOIN locations l ON l.id = sm.location_id
            WHERE sm.id = ? AND l.id = ?
        ");
        $stmt->bind_param("ii", $sensor_id, $location_id);
        $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$meta) {
            $flashError = 'Invalid sensor or location. Make sure sensor belongs to that location.';
        } else {

$mongo->insertOne([
                'sensor_id'         => $sensor_id,
                'sensor_code'       => $meta['sensor_code'],
                'location_id'       => $location_id,
                'location_name'     => $meta['location_name'],
                'city'              => $meta['city'],
                'country'           => $meta['country'],
                'temperature'       => (float) ($_POST['temperature'] ?? 0),
                'humidity'          => (float) ($_POST['humidity']    ?? 0),
                'aqi'               => (int)   ($_POST['aqi']         ?? 0),
                'co2'               => (int)   ($_POST['co2']         ?? 0),
                'pollution'         => (float) ($_POST['pollution']   ?? 0),
                'weather_condition' => $_POST['weather_condition'] ?? 'Sunny',
                'wind_speed'        => (float) ($_POST['wind_speed']  ?? 0),

'recorded_at'       => new MongoDB\BSON\UTCDateTime(time() * 1000),
            ]);
            $flashSuccess = 'New reading inserted into MongoDB successfully.';
        }
    }

if ($action === 'edit') {
        $doc_id = trim($_POST['doc_id'] ?? '');
        try {

$objectId = new MongoDB\BSON\ObjectId($doc_id);

$mongo->updateOne(
                ['_id' => $objectId],
                ['$set' => [
                    'temperature'       => (float) ($_POST['temperature'] ?? 0),
                    'humidity'          => (float) ($_POST['humidity']    ?? 0),
                    'aqi'               => (int)   ($_POST['aqi']         ?? 0),
                    'co2'               => (int)   ($_POST['co2']         ?? 0),
                    'pollution'         => (float) ($_POST['pollution']   ?? 0),
                    'weather_condition' => $_POST['weather_condition'] ?? 'Sunny',
                    'wind_speed'        => (float) ($_POST['wind_speed']  ?? 0),
                ]]
            );
            $flashSuccess = 'Reading updated successfully.';
        } catch (Exception $e) {
            $flashError = 'Update failed: invalid document ID.';
        }
    }

if ($action === 'delete') {
        $doc_id = trim($_POST['doc_id'] ?? '');
        try {

$objectId = new MongoDB\BSON\ObjectId($doc_id);

$mongo->deleteOne(['_id' => $objectId]);
            $flashSuccess = 'Reading deleted from MongoDB.';
        } catch (Exception $e) {
            $flashError = 'Delete failed: invalid document ID.';
        }
    }

$_SESSION['flash_success'] = $flashSuccess;
    $_SESSION['flash_error']   = $flashError;
    header('Location: /environmental_monitor/admin/readings.php');
    exit();
}

$success = $_SESSION['flash_success'] ?? '';
$error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$location_filter = $_GET['location_filter'] ?? '';
$currentPage     = max(1, intval($_GET['page'] ?? 1));
$perPage         = 10;
$offset          = ($currentPage - 1) * $perPage;

$filter = [];
if ($location_filter) {

$filter['location_name'] = $location_filter;
}

$totalDocs  = $mongo->countDocuments($filter);
$totalPages = ceil($totalDocs / $perPage);

$documents = $mongo->find(
    $filter,
    ['sort' => ['recorded_at' => -1], 'skip' => $offset, 'limit' => $perPage]
);

$totalReadings = $mongo->countDocuments([]);

$locationNames = $mongo->distinct('location_name', []);
sort($locationNames);

$locations = $mysql->query("SELECT id, name, city FROM locations WHERE status = 'active' ORDER BY name");
$sensors   = $mysql->query("SELECT sm.id, sm.sensor_code, sm.location_id, l.name as location_name FROM sensor_metadata sm JOIN locations l ON l.id = sm.location_id WHERE sm.status = 'active' ORDER BY sm.sensor_code");

$sensorList = [];
while ($s = $sensors->fetch_assoc()) {
    $sensorList[] = $s;
}

$mysql->close();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">

    <div class="page-header flex justify-between items-center">
        <div>
            <h2>
                <i class="bi bi-database" style="color:#4DB33D; margin-right:8px;"></i>
                MongoDB CRUD
            </h2>
            <p class="text-muted mb-0">
                Manage environmental readings directly in MongoDB
                <span class="badge" style="margin-left:8px; background:#4DB33D; color:#fff;">MongoDB</span>
            </p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addModal')">
            <i class="bi bi-plus-circle"></i> Add Reading
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="alert-close">&times;</button>
        </div>
    <?php endif; ?>

<div class="row mb-4">
        <div class="col-4">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number" style="color:#4DB33D;"><?php echo number_format($totalReadings); ?></div>
                        <div class="stat-label">Total Documents</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(77,179,61,0.1); color:#4DB33D;">
                        <i class="bi bi-database"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-success"><?php echo count($locationNames); ?></div>
                        <div class="stat-label">Locations in Collection</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(25,135,84,0.1); color:var(--color-primary);">
                        <i class="bi bi-geo-alt"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card stat-card">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="stat-number text-warning"><?php echo number_format($totalDocs); ?></div>
                        <div class="stat-label">Filtered Documents</div>
                    </div>
                    <div class="stat-icon" style="background:rgba(255,193,7,0.1); color:var(--color-warning);">
                        <i class="bi bi-funnel"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

<div class="card table-card">
        <div class="card-header flex justify-between items-center">
            <h6 class="mb-0">
                <i class="bi bi-table text-success"></i>
                environmental_readings Collection
                <small class="text-muted" style="font-size:12px; margin-left:6px;">
                    (<?php echo number_format($totalDocs); ?> documents)
                </small>
            </h6>

            <form method="GET" class="flex gap-2">
                <select name="location_filter" class="form-select" style="width:auto;">
                    <option value="">All Locations</option>
                    <?php foreach ($locationNames as $ln): ?>
                        <option value="<?php echo htmlspecialchars($ln); ?>"
                            <?php echo $location_filter === $ln ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ln); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <?php if ($location_filter): ?>
                    <a href="/environmental_monitor/admin/mongo_crud.php" class="btn btn-outline btn-sm">
                        <i class="bi bi-x"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="font-size:11px;">MongoDB _id</th>
                            <th>Sensor</th>
                            <th>Location</th>
                            <th>Temp °C</th>
                            <th>Humidity %</th>
                            <th>AQI</th>
                            <th>CO₂</th>
                            <th>Weather</th>
                            <th>Recorded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rowCount = 0;
                        foreach ($documents as $doc):
                            $rowCount++;
                            $docId   = (string) $doc['_id'];

                            $aqi     = (int) $doc['aqi'];
                            $aqiClass = $aqi <= 50 ? 'aqi-good' : ($aqi <= 100 ? 'aqi-moderate' : ($aqi <= 150 ? 'aqi-unhealthy' : 'aqi-hazardous'));
                            $dateStr  = date('M j, H:i', $doc['recorded_at']->toDateTime()->getTimestamp());
                        ?>
                        <tr>
                            <td>

                                <code style="font-size:10px; color:#4DB33D;">
                                    <?php echo substr($docId, 0, 8) . '...'; ?>
                                </code>
                            </td>
                            <td>
                                <span class="badge badge-secondary">
                                    <?php echo htmlspecialchars($doc['sensor_code']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($doc['location_name']); ?></strong>
                                <div class="text-muted" style="font-size:12px;"><?php echo htmlspecialchars($doc['city']); ?></div>
                            </td>
                            <td><?php echo $doc['temperature']; ?>°</td>
                            <td><?php echo $doc['humidity']; ?>%</td>
                            <td><span class="badge <?php echo $aqiClass; ?>"><?php echo $aqi; ?></span></td>
                            <td><?php echo $doc['co2']; ?></td>
                            <td><?php echo $doc['weather_condition']; ?></td>
                            <td class="text-muted" style="font-size:12px;"><?php echo $dateStr; ?></td>
                            <td>
                                <div class="flex gap-1">

                                    <button type="button"
                                            class="btn btn-sm btn-outline"
                                            title="Edit"
                                            onclick="openEditModal(
                                                '<?php echo $docId; ?>',
                                                <?php echo $doc['temperature']; ?>,
                                                <?php echo $doc['humidity']; ?>,
                                                <?php echo $doc['aqi']; ?>,
                                                <?php echo $doc['co2']; ?>,
                                                <?php echo $doc['pollution']; ?>,
                                                '<?php echo htmlspecialchars($doc['weather_condition'], ENT_QUOTES); ?>',
                                                <?php echo $doc['wind_speed']; ?>
                                            )">
                                        <i class="bi bi-pencil"></i>
                                    </button>

<form method="POST" style="display:inline;"
                                          onsubmit="return confirm('Delete this MongoDB document?\n\nID: <?php echo $docId; ?>\n\nThis cannot be undone.')">
                                        <input type="hidden" name="action"  value="delete">
                                        <input type="hidden" name="doc_id" value="<?php echo $docId; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if ($rowCount === 0): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted" style="padding:48px 0;">
                                <i class="bi bi-inbox" style="font-size:32px; display:block; margin-bottom:8px;"></i>
                                No documents found.
                                <?php if ($location_filter): ?>
                                    <div style="margin-top:8px;">
                                        <a href="/environmental_monitor/admin/mongo_crud.php" class="btn btn-outline btn-sm">Clear filter</a>
                                    </div>
                                <?php endif; ?>
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
                    Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalDocs); ?>
                    of <?php echo number_format($totalDocs); ?> documents
                </small>
                <nav>
                    <ul class="pagination" style="margin:0;">
                        <?php if ($currentPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage - 1; echo $location_filter ? '&location_filter=' . urlencode($location_filter) : ''; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                            <li class="page-item <?php echo $p === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $p; echo $location_filter ? '&location_filter=' . urlencode($location_filter) : ''; ?>">
                                    <?php echo $p; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $currentPage + 1; echo $location_filter ? '&location_filter=' . urlencode($location_filter) : ''; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<div class="modal-backdrop" id="addModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <h5>
                <i class="bi bi-plus-circle" style="color:#4DB33D;"></i>
                Add New Reading to MongoDB
            </h5>
            <button type="button" class="modal-close" onclick="closeModal('addModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Location</label>
                        <select name="location_id" id="addLocationId" class="form-select" onchange="filterSensors()" required>
                            <option value="">Select location</option>
                            <?php foreach ($sensorList as $s): ?>

                            <?php endforeach; ?>
                            <?php

$locationArr = [];
                            foreach ($sensorList as $s) {
                                $locationArr[$s['location_id']] = $s['location_name'];
                            }
                            foreach ($locationArr as $lid => $lname): ?>
                                <option value="<?php echo $lid; ?>"><?php echo htmlspecialchars($lname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Sensor</label>
                        <select name="sensor_id" id="addSensorId" class="form-select" required>
                            <option value="">Select sensor</option>
                            <?php foreach ($sensorList as $s): ?>
                                <option value="<?php echo $s['id']; ?>"
                                        data-location="<?php echo $s['location_id']; ?>">
                                    <?php echo htmlspecialchars($s['sensor_code']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Temperature (°C)</label>
                        <input type="number" step="0.1" name="temperature" class="form-control" placeholder="e.g. 28.5" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Humidity (%)</label>
                        <input type="number" step="0.1" name="humidity" class="form-control" placeholder="e.g. 65.0" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-4">
                        <label class="form-label">AQI</label>
                        <input type="number" name="aqi" class="form-control" placeholder="e.g. 85" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">CO₂ (ppm)</label>
                        <input type="number" name="co2" class="form-control" placeholder="e.g. 450" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Pollution (µg/m³)</label>
                        <input type="number" step="0.1" name="pollution" class="form-control" placeholder="e.g. 12.5" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Weather Condition</label>
                        <select name="weather_condition" class="form-select" required>
                            <?php foreach (['Sunny','Cloudy','Rainy','Foggy','Stormy'] as $w): ?>
                                <option value="<?php echo $w; ?>"><?php echo $w; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Wind Speed (km/h)</label>
                        <input type="number" step="0.1" name="wind_speed" class="form-control" placeholder="e.g. 15.0" required>
                    </div>
                </div>

                <div style="background:rgba(77,179,61,0.08); border-radius:8px; padding:12px; font-size:13px; color:#4DB33D;">
                    <i class="bi bi-info-circle" style="margin-right:6px;"></i>
                    <strong>MongoDB:</strong> A new document will be created with an auto-generated ObjectId (_id).
                    The <code>recorded_at</code> timestamp will be set to right now.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Insert Document
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="editModal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <h5>
                <i class="bi bi-pencil" style="color:#4DB33D;"></i>
                Edit MongoDB Document
            </h5>
            <button type="button" class="modal-close" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action"  value="edit">

            <input type="hidden" name="doc_id"  id="editDocId">
            <div class="modal-body">

                <div class="mb-3" style="background:rgba(77,179,61,0.08); border-radius:8px; padding:12px;">
                    <small class="text-muted">Editing document ID:</small><br>
                    <code style="color:#4DB33D; font-size:12px;" id="editDocIdDisplay"></code>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Temperature (°C)</label>
                        <input type="number" step="0.1" name="temperature" id="editTemp" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Humidity (%)</label>
                        <input type="number" step="0.1" name="humidity" id="editHumidity" class="form-control" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-4">
                        <label class="form-label">AQI</label>
                        <input type="number" name="aqi" id="editAqi" class="form-control" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">CO₂ (ppm)</label>
                        <input type="number" name="co2" id="editCo2" class="form-control" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Pollution</label>
                        <input type="number" step="0.1" name="pollution" id="editPollution" class="form-control" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Weather Condition</label>
                        <select name="weather_condition" id="editWeather" class="form-select" required>
                            <?php foreach (['Sunny','Cloudy','Rainy','Foggy','Stormy'] as $w): ?>
                                <option value="<?php echo $w; ?>"><?php echo $w; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Wind Speed (km/h)</label>
                        <input type="number" step="0.1" name="wind_speed" id="editWind" class="form-control" required>
                    </div>
                </div>

                <div style="background:#fff3cd; border-radius:8px; padding:12px; font-size:13px; color:#856404;">
                    <i class="bi bi-info-circle" style="margin-right:6px;"></i>
                    Only sensor readings are updated. Location, sensor, and timestamp are not changed.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Update Document
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const allSensors = <?php echo json_encode($sensorList); ?>;

function filterSensors() {
    const locationId = parseInt(document.getElementById('addLocationId').value);
    const sensorSelect = document.getElementById('addSensorId');

    sensorSelect.innerHTML = '<option value="">Select sensor</option>';

    allSensors.forEach(function(s) {
        if (s.location_id == locationId) {
            const opt = document.createElement('option');
            opt.value       = s.id;
            opt.textContent = s.sensor_code;
            sensorSelect.appendChild(opt);
        }
    });
}

function openEditModal(docId, temp, humidity, aqi, co2, pollution, weather, wind) {
    document.getElementById('editDocId').value       = docId;
    document.getElementById('editDocIdDisplay').textContent = docId;

    document.getElementById('editTemp').value         = temp;
    document.getElementById('editHumidity').value     = humidity;
    document.getElementById('editAqi').value          = aqi;
    document.getElementById('editCo2').value          = co2;
    document.getElementById('editPollution').value    = pollution;
    document.getElementById('editWeather').value      = weather;
    document.getElementById('editWind').value         = wind;

    openModal('editModal');
}
</script>
