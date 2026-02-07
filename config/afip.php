<?php

return [
    'cuit' => '27278968389',
    'env'  => 'homo',

    // Certificados (estas rutas están perfectas)
    'cert' => __DIR__ . '/../certs/cert.pem',
    'key'  => __DIR__ . '/../certs/cert.key',

    // WSDL
    'wsaa_wsdl_homo' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl',
    'wsaa_wsdl_prod' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl',

    // Servicio por defecto
    'service_default' => 'wsfe',

    // LOGS y TA en /tmp (Render permite escritura aquí)
    'log_path' => '/tmp',
    'ta_file'  => '/tmp/ta.xml',

    // Seguridad
    'auth_token' => 'b7f2e1c9a4d8f0e3b1c7d9a2f4e6b8c1',

    // Margen de validez del TA
    'ta_margin' => 120,
];
