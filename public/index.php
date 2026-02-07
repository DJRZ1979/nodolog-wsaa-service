<?php
// ======================================================
// DEBUG: endpoint para ver wsaa
// ======================================================
if (str_starts_with($_SERVER['REQUEST_URI'], '/debug-wsaa')) {
    $path = '/tmp/wsaa.log';
    header('Content-Type: text/plain');
    echo "Existe? " . (file_exists($path) ? "SI" : "NO") . "\n\n";
    echo file_exists($path) ? file_get_contents($path) : "(no existe)";
    exit;
}
// ======================================================
// DEBUG: endpoint para ver headers
// ======================================================
if (str_starts_with($_SERVER['REQUEST_URI'], '/debug-headers')) {
    header('Content-Type: text/plain');
    print_r($_SERVER);
    exit;
}

// ======================================================
// DEBUG: endpoint para ver /tmp/debug.txt SIN autenticación
// ======================================================
if (str_starts_with($_SERVER['REQUEST_URI'], '/debug-file')) {
    $path = '/tmp/debug.txt';
    header('Content-Type: text/plain');
    echo "Existe? " . (file_exists($path) ? "SI" : "NO") . "\n\n";
    echo file_exists($path) ? file_get_contents($path) : "(no existe)";
    exit;
}

// ======================================================
// DEBUG: traza inicial ANTES de cualquier require
// ======================================================
file_put_contents('/tmp/debug.txt', "URI RECIBIDO: " . $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
file_put_contents('/tmp/debug.txt', "A - inicio\n", FILE_APPEND);

// Cargar clases
require_once __DIR__ . '/../src/Logger.php';
file_put_contents('/tmp/debug.txt', "B - Logger.php cargado\n", FILE_APPEND);

require_once __DIR__ . '/../src/AfipWSAA.php';
file_put_contents('/tmp/debug.txt', "C - AfipWSAA.php cargado\n", FILE_APPEND);

// Cargar config
$config = require __DIR__ . '/../config/afip.php';
file_put_contents('/tmp/debug.txt', "D - config cargado\n", FILE_APPEND);

// Instanciar logger
$logger = new Logger($config['log_path']);
file_put_contents('/tmp/debug.txt', "E - Logger instanciado\n", FILE_APPEND);

header('Content-Type: application/json');

// ======================================================
// Autenticación — usando X-Auth-Token (Cloudflare-friendly)
// ======================================================
$authHeader =
    $_SERVER['HTTP_X_AUTH_TOKEN']
    ?? $_SERVER['REDIRECT_HTTP_X_AUTH_TOKEN']
    ?? '';

if ($authHeader !== $config['auth_token']) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

// ======================================================
// Leer JSON
// ======================================================
$input   = json_decode(file_get_contents('php://input'), true) ?: [];
$service = $input['service'] ?? null;

$logger->log('wsaa.log', 'Punto A: antes del try');

// ======================================================
// Lógica principal
// ======================================================
try {
    $logger->log('wsaa.log', 'Punto B: instanciando AfipWSAA');
    $wsaa = new AfipWSAA($config, $logger);

    $logger->log('wsaa.log', 'Punto C: llamando a obtenerTA');
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
