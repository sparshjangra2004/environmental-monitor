<?php

require_once __DIR__ . '/../config/cors.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/jwt.php';
require_once __DIR__ . '/../config/sanitize.php';
require_once __DIR__ . '/../security/rate_limiter.php';
require_once __DIR__ . '/../security/logger.php';
require_once __DIR__ . '/../security/ip_blocker.php';

$clientIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (isIPBlocked($clientIP)) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Your IP address has been blocked.']);
    exit();
}

checkRateLimit('api/auth');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;

}

$email    = sanitizeEmail($input['email']    ?? '');
$password = trim($input['password'] ?? '');

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required.']);
    exit();
}

$conn = getDBConnection();

$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.password, u.status, r.name AS role
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.email = ?
");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password'])) {
    http_response_code(401);
    logActivity(null, 'failed_login', "API auth failed for: $email");
    echo json_encode(['error' => 'Invalid credentials.']);
    exit();
}

$token = generateJWT((int)$user['id'], $user['role']);

logActivity((int)$user['id'], 'jwt_generated', "JWT issued for: {$user['email']}");

http_response_code(200);
echo json_encode([
    'token'      => $token,
    'token_type' => 'Bearer',
    'expires_in' => JWT_EXPIRY,
    'user'       => [
        'id'    => $user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ]
]);
?>
