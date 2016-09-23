<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Cityware\Communication\Soap;

set_time_limit(0);
ini_set('soap.wsdl_cache_enabled', WSDL_CACHE_NONE);
ini_set('soap.wsdl_cache_limit', WSDL_CACHE_NONE);
ini_set('soap.wsdl_cache_ttl', WSDL_CACHE_NONE);
ini_set('soap.wsdl_cache', WSDL_CACHE_NONE);

use SoapVar;
use SoapHeader;
use SoapFault;
use Exception;
use Zend\Soap\Client as ZendSoapClient;

/**
 * Description of Client
 *
 * @author fsvxavier
 */
class Client {

    private $soapClient;
    private $oldSocketTimeOut;
    private $soapVars;

    public function __construct($wsdl = null, $soapOptions = Array()) {

        $this->oldSocketTimeOut = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 99999999);

        $this->soapClient = new ZendSoapClient(null, Array());
        if (!empty($wsdl)) {
            $this->soapClient->setWsdl($wsdl);
            $this->soapClient->setLocation($wsdl);
        }

        if (!empty($soapOptions)) {
            $this->soapClient->setOptions($soapOptions);
        }
    }
    
    public function setSoapOptions($soapOptions) {
        $this->soapClient->setOptions($soapOptions);
    }

    public function execute($metode, $options = Array()) {

        try {
            $objReturn = $this->soapClient->call($metode, $options);

            ini_set('default_socket_timeout', $this->oldSocketTimeOut);

            return $objReturn;
        } catch (SoapFault $exp) {
            throw new Exception('ERROR: [' . $exp->faultcode . '] ' . $exp->faultstring, 500);
        } catch (Exception $exp2) {
            throw new Exception('ERROR: ' . $exp2->getMessage(), 500);
        }
    }

    public function setSoapVars($params = Array(), $nameSpace) {
        $this->soapVars = new SoapVar($params, SOAP_ENC_OBJECT, NULL, $nameSpace, NULL, $nameSpace);
    }

    public function getSoapVars() {
        return $this->soapVars;
    }

    public function setSoapHeader($nameSpace, $name, $headerObjVals) {
        $header = new SoapHeader($nameSpace, $name, $headerObjVals, false);
        $this->soapClient->addSoapInputHeader($header);
    }

    public function getLastRequest() {
        return $this->soapClient->getLastRequest();
    }

    public function getLastResponse() {
        return $this->soapClient->getLastResponse();
    }

}
