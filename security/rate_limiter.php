<?php

require_once __DIR__ . '/../config/database.php';

define('RATE_LIMIT_MAX',    60);

define('RATE_LIMIT_WINDOW', 60);

function checkRateLimit(string $endpoint): void {
    $ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $conn = getDBConnection();

    try {

$stmt = $conn->prepare(
            "SELECT id, request_count, window_start
             FROM rate_limits
             WHERE ip_address = ? AND endpoint = ?"
        );
        $stmt->bind_param("ss", $ip, $endpoint);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {

$stmt = $conn->prepare(
                "INSERT INTO rate_limits (ip_address, endpoint, request_count, window_start)
                 VALUES (?, ?, 1, NOW())"
            );
            $stmt->bind_param("ss", $ip, $endpoint);
            $stmt->execute();
            $stmt->close();
        } else {
            $windowAge = time() - strtotime($row['window_start']);

            if ($windowAge > RATE_LIMIT_WINDOW) {

$stmt = $conn->prepare(
                    "UPDATE rate_limits SET request_count = 1, window_start = NOW()
                     WHERE ip_address = ? AND endpoint = ?"
                );
                $stmt->bind_param("ss", $ip, $endpoint);
                $stmt->execute();
                $stmt->close();
            } elseif ($row['request_count'] >= RATE_LIMIT_MAX) {

$conn->close();
                http_response_code(429);
                header('Content-Type: application/json');
                header('Retry-After: ' . (RATE_LIMIT_WINDOW - $windowAge));
                echo json_encode([
                    'error'       => 'Too Many Requests',
                    'message'     => 'Rate limit exceeded. Max ' . RATE_LIMIT_MAX . ' requests per minute.',
                    'retry_after' => RATE_LIMIT_WINDOW - $windowAge
                ]);
                exit();
            } else {

$stmt = $conn->prepare(
                    "UPDATE rate_limits SET request_count = request_count + 1
                     WHERE ip_address = ? AND endpoint = ?"
                );
                $stmt->bind_param("ss", $ip, $endpoint);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Exception $e) {

}

    $conn->close();
}
?>
