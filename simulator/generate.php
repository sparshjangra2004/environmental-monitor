<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../config/mongo_db.php';

require_once __DIR__ . '/../config/neo_db.php';

requireAdmin();

$mysql = getDBConnection();

$mongo = getMongoCollection();

$sensors = $mysql->query("
    SELECT sm.id as sensor_id, sm.sensor_code, sm.location_id,
           l.name as location_name, l.city, l.country
    FROM sensor_metadata sm
    JOIN locations l ON sm.location_id = l.id
    WHERE sm.status = 'active'
");

$sensorList = [];
while ($row = $sensors->fetch_assoc()) {
    $sensorList[] = $row;
}

$weatherConditions = ['Sunny', 'Cloudy', 'Rainy', 'Foggy', 'Stormy'];

$mongo->deleteMany([]);

$mysql->query("DELETE FROM alerts");

$readings_inserted = 0;
$alerts_generated  = 0;

$alertStmt = $mysql->prepare("
    INSERT INTO alerts (location_id, alert_type, severity, message)
    VALUES (?, ?, ?, ?)
");

foreach ($sensorList as $sensor) {
    for ($i = 0; $i < 60; $i++) {

        $hoursAgo  = rand(1, 720);

$timestamp = time() - ($hoursAgo * 3600);

        $temperature = round(rand(150, 400) / 10, 1);
        $humidity    = round(rand(300, 950) / 10, 1);
        $aqi         = rand(20, 200);
        $co2         = rand(400, 1200);
        $pollution   = round(rand(10, 150) / 10, 1);
        $wind_speed  = round(rand(0, 600) / 10, 1);
        $weather     = $weatherConditions[array_rand($weatherConditions)];

$mongo->insertOne([
            'sensor_id'         => (int) $sensor['sensor_id'],
            'sensor_code'       => $sensor['sensor_code'],
            'location_id'       => (int) $sensor['location_id'],
            'location_name'     => $sensor['location_name'],
            'city'              => $sensor['city'],
            'country'           => $sensor['country'],
            'temperature'       => (float) $temperature,
            'humidity'          => (float) $humidity,
            'aqi'               => (int) $aqi,
            'co2'               => (int) $co2,
            'pollution'         => (float) $pollution,
            'weather_condition' => $weather,
            'wind_speed'        => (float) $wind_speed,

'recorded_at'       => new MongoDB\BSON\UTCDateTime($timestamp * 1000),
        ]);
        $readings_inserted++;

$alert_type = null;
        $severity   = null;
        $message    = null;

        if ($temperature > 35) {
            $alert_type = 'high_temperature';
            $severity   = $temperature > 38 ? 'critical' : 'high';
            $message    = "High temperature: {$temperature}°C at {$sensor['location_name']}";
        } elseif ($aqi > 150) {
            $alert_type = 'high_aqi';
            $severity   = $aqi > 175 ? 'critical' : 'high';
            $message    = "Dangerous AQI: {$aqi} at {$sensor['location_name']}";
        } elseif ($aqi > 100) {
            $alert_type = 'high_aqi';
            $severity   = 'medium';
            $message    = "Moderate AQI: {$aqi} at {$sensor['location_name']}";
        } elseif ($weather === 'Stormy') {
            $alert_type = 'storm_warning';
            $severity   = 'medium';
            $message    = "Storm at {$sensor['location_name']}, {$sensor['city']}";
        } elseif ($pollution > 12) {
            $alert_type = 'high_pollution';
            $severity   = 'high';
            $message    = "High pollution: {$pollution} µg/m³ at {$sensor['location_name']}";
        }

        if ($alert_type) {
            $alertStmt->bind_param("isss", $sensor['location_id'], $alert_type, $severity, $message);
            $alertStmt->execute();
            $alerts_generated++;
        }
    }
}

$alertStmt->close();

$neo        = getNeoClient();
$neo_nodes  = 0;
$neo_rels   = 0;

$neo->run('MATCH (n) DETACH DELETE n');

$mysql2     = getDBConnection();
$locations2 = $mysql2->query("SELECT id, name, city, country FROM locations WHERE status = 'active'");
while ($loc = $locations2->fetch_assoc()) {

$neo->run(
        'MERGE (l:Location {id: $id}) SET l.name = $name, l.city = $city, l.country = $country',
        ['id' => (int)$loc['id'], 'name' => $loc['name'], 'city' => $loc['city'], 'country' => $loc['country']]
    );
    $neo_nodes++;
}

$sensors2 = $mysql2->query("SELECT id, sensor_code, location_id FROM sensor_metadata WHERE status = 'active'");
while ($sensor = $sensors2->fetch_assoc()) {

$neo->run(
        'MERGE (s:Sensor {id: $id}) SET s.code = $code',
        ['id' => (int)$sensor['id'], 'code' => $sensor['sensor_code']]
    );
    $neo_nodes++;

$neo->run(
        'MATCH (l:Location {id: $loc_id}), (s:Sensor {id: $sensor_id})
         MERGE (l)-[:CONTAINS]->(s)',
        ['loc_id' => (int)$sensor['location_id'], 'sensor_id' => (int)$sensor['id']]
    );
    $neo_rels++;
}

$users2 = $mysql2->query("SELECT id, name, email FROM users WHERE status = 'active'");
while ($user = $users2->fetch_assoc()) {
    $neo->run(
        'MERGE (u:User {id: $id}) SET u.name = $name, u.email = $email',
        ['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email']]
    );
    $neo_nodes++;

$neo->run(
        'MATCH (u:User {id: $user_id}), (l:Location)
         MERGE (u)-[:MONITORS]->(l)',
        ['user_id' => (int)$user['id']]
    );
    $neo_rels++;
}

$alertTypes2 = $mysql2->query("
    SELECT a.alert_type, a.severity, sm.id as sensor_id, sm.sensor_code,
           COUNT(*) as trigger_count
    FROM alerts a
    JOIN locations l ON a.location_id = l.id
    JOIN sensor_metadata sm ON sm.location_id = l.id
    GROUP BY a.alert_type, a.severity, sm.id, sm.sensor_code
    LIMIT 50
");
while ($at = $alertTypes2->fetch_assoc()) {
    $alertNodeId = $at['alert_type'] . '_' . $at['severity'];

$neo->run(
        'MERGE (a:AlertType {uid: $uid}) SET a.type = $type, a.severity = $severity',
        ['uid' => $alertNodeId, 'type' => $at['alert_type'], 'severity' => $at['severity']]
    );

$neo->run(
        'MATCH (s:Sensor {id: $sensor_id}), (a:AlertType {uid: $uid})
         MERGE (s)-[r:TRIGGERS]->(a)
         SET r.count = $count',
        ['sensor_id' => (int)$at['sensor_id'], 'uid' => $alertNodeId, 'count' => (int)$at['trigger_count']]
    );
    $neo_rels++;
    $neo_nodes++;
}

$mysql2->close();

?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Generator | EcoMonitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/environmental_monitor/assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4 mb-4 ">
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-check-circle-fill text-success" style="font-size:64px;"></i>
                <h3 class="mt-3">Sample Data Generated!</h3>
                <p class="text-muted">Environmental readings and alerts have been refreshed</p>
                <hr>
                <div class="row text-center mb-4 mt-4">
                    <div class="col-4">
                        <div class="text-success" style="font-size:32px; font-weight:bold;"><?php echo $readings_inserted; ?></div>
                        <div class="text-muted">Readings → MongoDB</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:32px; font-weight:bold; color:var(--color-warning);"><?php echo $alerts_generated; ?></div>
                        <div class="text-muted">Alerts → MySQL</div>
                    </div>
                    <div class="col-4">
                        <div style="font-size:32px; font-weight:bold; color:#6366f1;"><?php echo $neo_nodes; ?> / <?php echo $neo_rels; ?></div>
                        <div class="text-muted">Nodes / Relationships → Neo4j</div>
                    </div>
                </div>

                <div class="text-start mb-4" style="background:var(--color-light-bg); border-radius:8px; padding:16px;">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="badge" style="background:#4DB33D; color:#fff;">MongoDB</span>
                        <span>Environmental readings stored as JSON documents</span>
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="badge badge-success">MySQL</span>
                        <span>Users, Locations, Sensors, Alerts stored relationally</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge" style="background:#6366f1; color:#fff;">Neo4j</span>
                        <span>User→Location→Sensor→Alert relationships stored as a graph</span>
                    </div>
                </div>

                <a href="/environmental_monitor/admin/dashboard.php" class="btn btn-primary">
                    <i class="bi bi-speedometer2" style="margin-right:6px;"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
