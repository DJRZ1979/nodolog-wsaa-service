<?php

class AfipWSAA
{
    public function __construct(
        private array $conf,
        private Logger $logger
    ) {}

    public function obtenerTA(string $service = null): SimpleXMLElement
    {
        $service = $service ?: $this->conf['service_default'];

        // 1) Intentar leer TA cacheado
        if (file_exists($this->conf['ta_file'])) {
            $taXml = file_get_contents($this->conf['ta_file']);
            if ($taXml) {
                $ta = new SimpleXMLElement($taXml);
                $exp = strtotime((string)$ta->header->expirationTime);
                if ($exp - time() > $this->conf['ta_margin']) {
                    $this->logger->log('wsaa.log', 'TA cacheado válido');
                    return $ta;
                }
            }
        }

        $this->logger->log('wsaa.log', 'Generando nuevo TA');

        $env  = $this->conf['env'];
        $wsdl = $env === 'prod' ? $this->conf['wsaa_wsdl_prod'] : $this->conf['wsaa_wsdl_homo'];

        // 2) Generar TRA
        $tra = new SimpleXMLElement('<loginTicketRequest version="1.0"></loginTicketRequest>');
        $tra->addChild('header');
        $tra->header->addChild('uniqueId', time());
        $tra->header->addChild('generationTime', gmdate('Y-m-d\TH:i:s', time() - 60));
        $tra->header->addChild('expirationTime', gmdate('Y-m-d\TH:i:s', time() + 60));
        $tra->addChild('service', $service);

        $traFile = tempnam(sys_get_temp_dir(), 'tra');
        file_put_contents($traFile, $tra->asXML());

        $cmsFile = tempnam(sys_get_temp_dir(), 'cms');

        // Firmar TRA → OpenSSL genera PKCS7-MIME (no multipart)
        $ok = openssl_pkcs7_sign(
            $traFile,
            $cmsFile,
            'file://' . $this->conf['cert'],
            ['file://' . $this->conf['key'], ''],
            [],
            PKCS7_BINARY // SIN DETACHED
        );

        if (!$ok) {
            $this->logger->log('wsaa.log', 'Error al firmar TRA');
            throw new Exception('Error al firmar TRA');
        }

        $cmsData = file_get_contents($cmsFile);
        $this->logger->log('wsaa.log', "CMS bruto (S/MIME):\n" . $cmsData);

        // NORMALIZAR SALTOS DE LÍNEA
        $cmsData = str_replace("\r\n", "\n", $cmsData);

        // EXTRAER PKCS7 BASE64 DEL S/MIME (pkcs7-mime, sin boundaries)
        $marker = 'Content-Transfer-Encoding: base64';
        $pos = strpos($cmsData, $marker);

        if ($pos === false) {
            $this->logger->log('wsaa.log', 'No se encontró el header Content-Transfer-Encoding: base64');
            throw new Exception('No se pudo extraer PKCS7 del S/MIME');
        }

        // Fin de la línea del header
        $pos = strpos($cmsData, "\n", $pos);
        if ($pos === false) {
            $this->logger->log('wsaa.log', 'No se encontró fin de línea tras Content-Transfer-Encoding');
            throw new Exception('No se pudo extraer PKCS7 del S/MIME');
        }

        // Buscar línea en blanco que separa headers del base64
        $blankLine = strpos($cmsData, "\n\n", $pos);
        if ($blankLine === false) {
            $this->logger->log('wsaa.log', 'No se encontró línea en blanco tras los headers');
            throw new Exception('No se pudo extraer PKCS7 del S/MIME');
        }

        $start = $blankLine + 2;

        // Desde ahí hasta el final es el PKCS7 base64
        $cmsBase64 = substr($cmsData, $start);
        $cmsData   = trim($cmsBase64);

        if ($cmsData === '') {
            $this->logger->log('wsaa.log', 'El bloque PKCS7 base64 quedó vacío tras extraer');
            throw new Exception('No se pudo extraer PKCS7 del S/MIME');
        }

        $this->logger->log('wsaa.log', "CMS extraído (PKCS7 base64):\n" . $cmsData);

        // 3) Llamar a WSAA
        $client = new SoapClient($wsdl, ['trace' => 1, 'exceptions' => true]);
        $resp   = $client->loginCms(['in0' => $cmsData]);

        $this->logger->log('wsaa.log', "SOAP Request:\n" . $client->__getLastRequest());
        $this->logger->log('wsaa.log', "SOAP Response:\n" . $client->__getLastResponse());
        $this->logger->log('wsaa.log', "loginCmsReturn:\n" . $resp->loginCmsReturn);

        $ta = new SimpleXMLElement($resp->loginCmsReturn);

        // 4) Guardar TA en cache
        file_put_contents($this->conf['ta_file'], $ta->asXML());
        $this->logger->log('wsaa.log', 'TA obtenido y cacheado');

        return $ta;
    }
}
