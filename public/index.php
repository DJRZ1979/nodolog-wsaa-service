<?php
require __DIR__ . '/../src/Logger.php';
require __DIR__ . '/../src/AfipWSAA.php';
if ($_SERVER['REQUEST_URI'] === '/debug-openssl') {
    echo "<pre>";
    echo "OpenSSL loaded? " . (extension_loaded('openssl') ? "SI" : "NO") . "\n";
    echo "OpenSSL version: " . OPENSSL_VERSION_TEXT . "\n";
    echo "</pre>";
    exit;
}
header('Content-Type: application/json');

$config = require __DIR__ . '/../config/afip.php';
$logger = new Logger($config['log_path']);
if ($_SERVER['REQUEST_URI'] === '/debug-certs') {
    echo "<pre>";
    echo "Cert path: " . $config['cert'] . "\n";
    echo "Key path: " . $config['key'] . "\n";
    echo "Cert exists? " . (file_exists($config['cert']) ? "SI" : "NO") . "\n";
    echo "Key exists? " . (file_exists($config['key']) ? "SI" : "NO") . "\n";
    echo "</pre>";
    exit;
}
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

try {
    $wsaa = new AfipWSAA($config, $logger);
    $ta   = $wsaa->obtenerTA($service);

    echo json_encode([
        'ok' => true,
        'ta' => base64_encode($ta->asXML()),
    ]);
} catch (Exception $e) {
    $logger->log('wsaa.log', 'Error endpoint: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
