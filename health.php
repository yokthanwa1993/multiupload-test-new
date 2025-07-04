<?php
// health.php - Health check endpoint for Easypanel
header('Content-Type: application/json');
http_response_code(200);

$status = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];

// Check if required directories exist
$uploads_dir = __DIR__ . '/uploads/';
$credentials_dir = __DIR__ . '/credentials/';

$status['uploads_dir'] = is_dir($uploads_dir) && is_writable($uploads_dir) ? 'OK' : 'ERROR';
$status['credentials_dir'] = is_dir($credentials_dir) && is_writable($credentials_dir) ? 'OK' : 'ERROR';

echo json_encode($status, JSON_PRETTY_PRINT);
?> 