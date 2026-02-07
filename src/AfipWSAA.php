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

        $ok = openssl_pkcs7_sign(
            $traFile,
            $cmsFile,
            'file://' . $this->conf['cert'],
            ['file://' . $this->conf['key'], ''],
            [],
            PKCS7_BINARY | PKCS7_DETACHED
        );

        if (!$ok) {
            $this->logger->log('wsaa.log', 'Error al firmar TRA');
            throw new Exception('Error al firmar TRA');
        }

        $cmsData = file_get_contents($cmsFile);
        $cmsData = preg_replace("~.*?-----BEGIN PKCS7-----~s", "-----BEGIN PKCS7-----", $cmsData);
        $cmsData = preg_replace("~-----END PKCS7-----.*~s", "-----END PKCS7-----", $cmsData);
        $cmsData = trim($cmsData);

        $client = new SoapClient($wsdl, ['trace' => 1, 'exceptions' => true]);
        $resp   = $client->loginCms(['in0' => $cmsData]);
        $this->logger->log('wsaa.log', "CMS enviado:\n" . $cmsData);
        $this->logger->log('wsaa.log', "CMS enviado:\n" . $cmsData);

        $this->logger->log('wsaa.log', "SOAP Request:\n" . $client->__getLastRequest());
        $this->logger->log('wsaa.log', "SOAP Response:\n" . $client->__getLastResponse());
        $this->logger->log('wsaa.log', "loginCmsReturn:\n" . $resp->loginCmsReturn);
        
        $ta = new SimpleXMLElement($resp->loginCmsReturn);

        // 3) Guardar TA en cache
        file_put_contents($this->conf['ta_file'], $ta->asXML());
        $this->logger->log('wsaa.log', 'TA obtenido y cacheado');

        return $ta;
    }
}
