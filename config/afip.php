<?php

return [
    'cuit' => '27278968389',
    'env'  => 'homo',

    'cert' => __DIR__ . '/../certs/cert.pem',
    'key'  => __DIR__ . '/../certs/cert.key',

    'wsaa_wsdl_homo' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms?wsdl',
    'wsaa_wsdl_prod' => 'https://wsaa.afip.gov.ar/ws/services/LoginCms?wsdl',

    'service_default' => 'wsfe',
    'log_path'        => __DIR__ . '/../logs',

    'auth_token'      => 'CAMBIA_ESTE_TOKEN_LARGO_UNICO',
    'ta_margin'       => 120,
    'ta_file'         => __DIR__ . '/../logs/ta.xml',
];
