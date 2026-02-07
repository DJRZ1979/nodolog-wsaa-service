<?php
require __DIR__ . '/../src/Logger.php';
require __DIR__ . '/../src/AfipWSAA.php';

header('Content-Type: application/json');

$config = require __DIR__ . '/../config/afip.php';
$logger = new Logger($config['log_path']);

// Autenticación por token
$authHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($authHeader !== $config['auth_token']) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// Leer JSON
$input   = json_decode(file_get_contents('php://input'), true) ?: [];
$service = $input['service'] ?? null;
$logger->log('wsaa.log', 'Punto A: antes del try');
try {
    $wsaa = new AfipWSAA($config, $logger);
    $ta   = $wsaa->obtenerTA($service);

    echo json_encode([
        'ok' => true,
        'ta' => base64_encode($ta->asXML()),
    ]);
} catch (Throwable $e) {
    $logger->log('wsaa.log', 'Error endpoint: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
