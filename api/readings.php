<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/mongo_db.php';
require_once __DIR__ . '/../config/sanitize.php';
require_once __DIR__ . '/../security/rate_limiter.php';
require_once __DIR__ . '/../security/logger.php';

checkRateLimit('api/readings');

$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (!str_starts_with($authHeader, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['error' => 'Authorization header missing or malformed. Use: Authorization: Bearer <token>']);
    exit();
}

$token   = substr($authHeader, 7);

$payload = validateJWT($token);

if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid or expired JWT token. Please authenticate at /api/auth.php']);
    exit();
}

$requestingUserId   = $payload['sub'];
$requestingUserRole = $payload['role'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use GET.']);
    exit();
}

$limit    = min((int)sanitizeInt($_GET['limit'] ?? 20), 100);

$limit    = max($limit, 1);
$location = sanitizeString($_GET['location'] ?? '');

try {
    $mongo      = getMongoCollection();
    $filter     = [];
    $findOptions = [
        'sort'  => ['timestamp' => -1],

        'limit' => $limit,
    ];

    if (!empty($location)) {

$filter['location_name'] = new MongoDB\BSON\Regex(preg_quote($location, '/'), 'i');
    }

    $cursor   = $mongo->find($filter, $findOptions);
    $readings = [];

    foreach ($cursor as $doc) {
        $readings[] = [
            'id'           => (string)$doc['_id'],
            'location'     => $doc['location_name']  ?? null,
            'sensor_code'  => $doc['sensor_code']    ?? null,
            'temperature'  => $doc['temperature']    ?? null,
            'humidity'     => $doc['humidity']       ?? null,
            'aqi'          => $doc['aqi']            ?? null,
            'co2'          => $doc['co2_level']      ?? null,
            'pm25'         => $doc['pm25']           ?? null,
            'timestamp'    => (string)($doc['timestamp'] ?? ''),
        ];
    }

    $total = $mongo->countDocuments($filter);

    logActivity($requestingUserId, 'api_readings_accessed', "Fetched $limit readings via API");

    echo json_encode([
        'readings' => $readings,
        'total'    => $total,
        'limit'    => $limit,
        'filter'   => $location ?: null,
        'requested_by' => [
            'user_id' => $requestingUserId,
            'role'    => $requestingUserRole,
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
