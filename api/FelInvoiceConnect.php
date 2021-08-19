<?php
require_once __DIR__.'/FelInvoiceException.php';
require_once __DIR__.'/FelInvoiceBasic.php';

/**
 * contains function for connecting against fel-invoice-connect api
 * @author Patric Eid
 *
 */
class FelInvoiceConnect extends FelInvoiceBasic {
    private $auth_user = "";
    private $auth_key = "";
    private $app_listen = 0;
    private $headers = array('Content-Type'=>'application/xml;charset=UTF-8');

    private $xmlOptions = array(
        'options'       => 0,
        'data_is_url'   => false,
        'ns'            => '',
        'is_prefix'     => false,
        'namespace'     => null
    );
    private $debugMode = false;

    function __construct() {
        $this->auth_user = get_option('fel_invoice_plugin_auth_user');
        $this->auth_key = get_option('fel_invoice_plugin_auth_key');
        $this->app_listen = get_option('fel_invoice_plugin_app_listen');
    }

    private function _setXmlOptions($options=0, $data_is_url=false, $ns='', $is_prefix=false, $namespace=null) {
        $this->xmlOptions['options'] = $options;
        $this->xmlOptions['data_is_url'] = $data_is_url;
        $this->xmlOptions['ns'] = $ns;
        $this->xmlOptions['is_prefix'] = $is_prefix;
        $this->xmlOptions['namespace'] = $namespace;
    }

    // Define a function that converts array to xml.
    private function _array_to_xml($array, $rootElement = null, $xml = null) {
        $_xml = $xml;

        // If there is no Root Element then insert root
        if ($_xml === null) {
            $_xml = new SimpleXMLElement($rootElement !== null ? $rootElement : '<root/>',
                $this->xmlOptions['options'],
                $this->xmlOptions['data_is_url'],
                $this->xmlOptions['ns'],
                $this->xmlOptions['is_prefix']
            );
            //$_xml = new SimpleXMLElement($rootElement !== null ? $rootElement : '<root/>');
        }

        // Visit all key value pair
        foreach ($array as $k => $v) {
            if($k === "@attributes") {
                foreach($v as $key=>$value) {
                    $_xml->addAttribute($key, $value);
                }
            } else if($k === "@item_key") {
            } else if($k === "@item_values") {
                foreach($v as $value) {
                    $this->_array_to_xml($value, $array['@item_key'], $_xml->addChild($array['@item_key'], null, $this->xmlOptions['namespace']));
                }
            } else {
                // If there is nested array then
                if (is_array($v)) {
                    // Call function for nested array
                    $this->_array_to_xml($v, $k, $_xml->addChild($k, null, $this->xmlOptions['namespace']));
                } else {
                    // Simply add child element.
                    $_xml->addChild($k, $v, $this->xmlOptions['namespace']);
                }
            }
        }
        return $_xml->asXML();
    }

    private function _xml_to_array($xml) {
        try {
            // Convert xml string into an object
            $new = simplexml_load_string($xml);
            // Convert into json
            $con = json_encode($new);
            // Convert into associative array
            $retArray = json_decode($con, true);
        } catch( Exception $ex ) {
            return false;
        }
        return $retArray;
    }

    /**
     * executes a get call
     * @param string $serviceUrl
     * @return array
     */
    private function _GET($serviceUrl) {
        return $this->setReturn(wp_remote_get(
            $serviceUrl,
            array('headers'=>$this->getHeader(),
                'method'=>'GET', 'timeout'=>60)
        ));
    }

    /**
     * executes a POST call
     * @param string $serviceUrl
     * @param string $postData
     * @return array
     */
    private function _POST($serviceUrl, $postData) {
        $headers = $this->getHeader();
//        $headers['Content-length'] = 137;
//        $headers['Host'] = 'dev.api.ifacere-fel.com';
//        $headers['Cache-Control'] = 'no-cache';
        return $this->setReturn(wp_remote_post(
            $serviceUrl,
            array('headers'=>$headers, 'body' => $postData,
                'method'=>'POST', 'timeout'=>60)
        ));
    }

    /**
     * executes a delete call
     * @param string $serviceUrl
     * @param string $postData
     * @return array
     */
    private function _DELETE($serviceUrl, $postData) {
        return $this->setReturn(wp_remote_post(
            $serviceUrl,
            array('headers'=>$this->getHeader(), 'body' => $postData,
                'method'=>'DELETE', 'timeout'=>60)
        ));
    }

    /**
     * executes a put call
     * @param string $serviceUrl
     * @param string $postData
     * @return array
     */
    private function _PUT($serviceUrl, $postData) {
        return $this->setReturn(wp_remote_request(
            $serviceUrl,
            array('headers'=>$this->getHeader(), 'body' => $postData,
                'method'=>'PUT', 'timeout'=>60)
        ));
    }

    /**
     * returns headers array
     * @return array
     */
    private function getHeader() {
        return $this->headers;
    }

    /**
     * evaluates call response and returns an array with response information
     * @param  $response
     * @return array|stdClass
     */
    private function setReturn($response) {
        file_put_contents(dirname(__FILE__) . '/../debug/'.time().'-response.txt', print_r($response, true));
        file_put_contents(dirname(__FILE__) . '/../debug/'.time().'response-body.txt', print_r($response['body'], true));
        if (is_wp_error($response)) {
            $ret = array(
                'status' => 500,
                'error' => $response->get_error_message()
            );
            return $ret;
        }else {
            if(strpos($response['headers']['content-type'], 'xml') !== false) {
                $ret = $this->_xml_to_array($response['body']);
            } else {
                $ret = json_decode($response['body'], true);
            }
            file_put_contents(dirname(__FILE__) . '/../debug/'.time().'-response-array.txt', print_r($ret, true));
            return $ret;
        }
    }

    /**
     * Mikalai-added
     * returns check auth key
     * @return bool
     */
    public function checkAuthKey()
    {
        $ret = $this->getToken();

        if(!empty($ret) && isset($ret['status']) && $ret['status']) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Mikalai-added
     * returns credit token object loaded from credits api
     * @return object
     */
    public function getToken() {
        $request = array(
            'usuario'   => $this->auth_user,
            'apikey'    => $this->auth_key
        );
        $this->_setXmlOptions();
        $request = $this->_array_to_xml($request, '<SolicitaTokenRequest/>');
        $ret = $this->_POST('https://dev.api.ifacere-fel.com/fel-dte-services/api/solicitarToken', $request);
        $ret_val = array(
            'status'    => false,
            'data'      => array(),
            'message'   => ''
        );
        if(isset($ret['error'])) {
            $ret_val['message'] = $ret['error'];
        } elseif (isset($ret['tipo_respuesta']) && $ret['tipo_respuesta'] == 1) {
            $ret_val['message'] = 'error al token';
            $ret_val['data'] = $ret;
        } else {
            $ret_val['status'] = true;
            $ret_val['message'] = 'token';
            $ret_val['data']['token'] = 'Bearer ' . $ret['token'];
            $ret_val['data']['vigencia'] = $ret['vigencia'];
        }
        return $ret_val;
    }

    public function factura($order_id) {
        $timeNow = $this->_get_now_time_hora();
        $order = wc_get_order( $order_id );
        $request = array(
            'dte:GTDocumento'   => array(
                'dte:SAT'   => array(
                    '@attributes' => array(
                        'ClaseDocumento' => "dte"
                    ),
                    'dte:DTE'   => array(
                        '@attributes' => array(
                            'ID'    => "DatosCertificados"
                        ),
                        'dte:DatosEmision' => array(
                            '@attributes' => array(
                                'ID'    => "DatosEmision"
                            ),
                            'dte:DatosGenerales' => array(
                                '@attributes' => array(
                                    'CodigoMoneda'      => "GTQ",
                                    'FechaHoraEmision'  => $timeNow,
                                    //'NumeroAcceso'      => '992996460',
                                    'Tipo'              => "FACT"
                                ),
                            ),
                            'dte:Emisor'    => array(
                                '@attributes' => array(
                                    'AfiliacionIVA'         => 'GEN',
                                    'CodigoEstablecimiento' => '1',
                                    'NITEmisor'             => '82280363',
                                    'NombreComercial'       => 'INNOVACIONES MEDICAS INTERNACIONALES, SOCIEDAD ANÓNIMA',
                                    'NombreEmisor'          => 'INNOVACIONES MEDICAS INTERNACIONALES, SOCIEDAD ANÓNIMA'
                                ),
                                'dte:DireccionEmisor'   => array(
                                    'dte:Direccion'     => $order->get_billing_address_1(),
                                    'dte:CodigoPostal'  => $order->get_billing_postcode(),
                                    'dte:Municipio'     => $order->get_billing_city(),
                                    'dte:Departamento'  => $order->get_billing_state(),
                                    'dte:Pais'          => $order->get_billing_country()
                                )
                            ),
                            'dte:Receptor'  => array(
                                '@attributes' => array(
                                    'CorreoReceptor'    => $order->get_billing_email(),
                                    'IDReceptor'        => '77253825',  // '77253825',
                                    'NombreReceptor'    => $order->get_shipping_first_name().' '.$order->get_shipping_last_name()
                                ),
                                'dte:DireccionReceptor' => array(
                                    'dte:Direccion'     => $order->get_shipping_address_1(),
                                    'dte:CodigoPostal'  => $order->get_shipping_postcode(),
                                    'dte:Municipio'     => $order->get_shipping_city(),
                                    'dte:Departamento'  => $order->get_shipping_state(),
                                    'dte:Pais'          => $order->get_shipping_country()
                                )
                            ),
                            'dte:Frases'    => array(
                                'dte:Frase' => array(
                                    '@attributes' => array(
                                        'CodigoEscenario'   => "1",
                                        'TipoFrase'         => "1"
                                    )
                                )
                            ),
                            'dte:Items' => array(
                                '@item_key'     => 'dte:Item',
                                '@item_values'  => array()
                            ),
                            'dte:Totales'   => array(
                                'dte:TotalImpuestos'    => array(
                                    'dte:TotalImpuesto' => array(
                                        '@attributes' => array(
                                            'NombreCorto'           => 'IVA',
                                            'TotalMontoImpuesto'    => $order->get_total_tax()
                                        )
                                    )
                                ),
                                'dte:GranTotal' => $order->get_subtotal()
                            ),
                        )
                    )
                )
            )
        );

        $items = $order->get_items();
        list(
            $request['dte:GTDocumento']['dte:SAT']['dte:DTE']['dte:DatosEmision']['dte:Items']['@item_values'],
            $request['dte:GTDocumento']['dte:SAT']['dte:DTE']['dte:DatosEmision']['dte:Totales']['dte:TotalImpuestos']['dte:TotalImpuesto']['@attributes']['TotalMontoImpuesto']) = $this->_fill_items_from($items);

        $this->_setXmlOptions(0, false, '', true, 'dte');
        $xmlItems = $this->_array_to_xml($request, '<root/>');

        $xmlItems  = str_replace('<dte:GTDocumento xmlns:dte="dte">', '<dte:GTDocumento xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0" xmlns:xd="http://www.w3.org/2000/09/xmldsig#" Version="0.1">', $xmlItems);
        $xmlItems  = str_replace('<?xml version="1.0"?>', '<?xml version="1.0" encoding="utf-8" standalone="no"?>', $xmlItems);
        $xmlItems  = str_replace('<root>', '', $xmlItems);
        $xmlItems  = str_replace('</root>', '', $xmlItems);
        $xmlItems = trim($xmlItems);

        $firmaDocumento = $this->_firmaDocumento($xmlItems);
        if($firmaDocumento['status']) {
            $registraDocumento = $this->_registraDocumento($firmaDocumento['data']['xml_dte']);
            if($registraDocumento['status']) {
                $xmlResponse = $registraDocumento['data']['xml_dte'];
                //$response = $this->_xml_to_array($xmlResponse);
                update_post_meta($order_id, '_fel_invoice_fecha_hora', $timeNow);
                update_post_meta($order_id, '_fel_invoice_uuid', $registraDocumento['data']['uuid']);
                return array(
                    'status' => true,
					'message' => 'documento procesador correctamente',
                    'data' => array(
                        'response' => $registraDocumento['data']['xml_dte'],
						'tipo_respuesta' => $registraDocumento['data']['tipo_respuesta'],
						'uuid' => $registraDocumento['data']['uuid']
                    )
                );
            } else {
                return array(
                    'status' => true,
                    'message' => 'error al registrar documento',
                    'data' => $registraDocumento
                );
            }
        } else {
            return array(
                'status' => true,
                'message' => 'error al firmar documento',
                'data' => $firmaDocumento
            );
        }
    }

    public function notaCredito($order_id) {
        $request = array(
            'dte:GTDocumento'   => array(
                '@attributes'   => array(
                    'xmlns:dte' => "http://www.sat.gob.gt/dte/fel/0.2.0",
                    'xmlns:xd'  => "http://www.w3.org/2000/09/xmldsig#",
                    'Version'   => "0.1"
                ),
                'dte:SAT'   => array(
                    '@attributes' => array(
                        'ClaseDocumento' => "dte"
                    ),
                    'dte:DTE'   => array(
                        '@attributes' => array(
                            'ID'    => "DatosCertificados"
                        ),
                        'dte:DatosEmision' => array(
                            '@attributes' => array(
                                'ID'    => "DatosEmision"
                            ),
                            'dte:DatosGenerales' => array(
                                '@attributes' => array(
                                    'CodigoMoneda'      => "GTQ",
                                    'FechaHoraEmision'  => 'req.body.datosGenerales.fechaEmision',
                                    'Tipo'              => "NCRE"
                                ),
                            ),
                            'dte:Emisor'    => array(
                                '@attributes' => array(
                                    'AfiliacionIVA'         => 'req.body.emisor.afiliacionIVA',
                                    'CodigoEstablecimiento' => 'req.body.emisor.codigoEstablecimiento',
                                    'NITEmisor'             => 'req.body.emisor.nit',
                                    'NombreComercial'       => 'req.body.emisor.NombreComercial',
                                    'NombreEmisor'          => 'req.body.emisor.nombreEmisor'
                                ),
                                'dte:DireccionEmisor'   => array(
                                    'dte:Direccion'     => 'req.body.emisor.direccionEmisor.direccion',
                                    'dte:CodigoPostal'  => 'req.body.emisor.direccionEmisor.codigoPostal',
                                    'dte:Municipio'     => 'req.body.emisor.direccionEmisor.municipio',
                                    'dte:Departamento'  => 'req.body.emisor.direccionEmisor.departamento',
                                    'dte:Pais'          => 'req.body.emisor.direccionEmisor.pais'
                                ),
                                'dte:Receptor'  => array(
                                    '@attributes' => array(
                                        'CorreoReceptor'    => 'req.body.receptor.correoReceptor',
                                        'IDReceptor'        => 'req.body.receptor.idReceptor',
                                        'NombreReceptor'    => 'req.body.receptor.nombreReceptor'
                                    ),
                                    'dte:DireccionReceptor' => array(
                                        'dte:Direccion'     => 'req.body.receptor.direccionReceptor.direccion',
                                        'dte:CodigoPostal'  => 'req.body.receptor.direccionReceptor.codigoPostal',
                                        'dte:Municipio'     => 'req.body.receptor.direccionReceptor.municipio',
                                        'dte:Departamento'  => 'req.body.receptor.direccionReceptor.departamento',
                                        'dte:Pais'          => 'req.body.receptor.direccionReceptor.pais'
                                    )
                                ),
                                'dte:Items' => array(
                                    'dte:Item'  => array()
                                ),
                                'dte:Totales'   => array(
                                    'dte:TotalImpuestos'    => array(
                                        'dte:TotalImpuesto' => array(
                                            '@attributes' => array(
                                                'NombreCorto'           => 'req.body.totales.totalImpuestos.nombreCorto',
                                                'TotalMontoImpuesto'    => 'req.body.totales.totalImpuestos.totalMontoImpuesto'
                                            )
                                        )
                                    ),
                                    'dte:GranTotal' => 'req.body.totales.granTotal'
                                ),
                                'dte:Complementos'  => array(
                                    'dte:Complemento'   => array(
                                        '@attributes' => array(
                                            'IDComplemento'     => "1",
											'NombreComplemento' => "NOTA CREDITO",
											'URIComplemento'    => "http://www.sat.gob.gt/face2/ComplementoReferenciaNota/0.1.0"
                                        ),
										'cno:ReferenciasNota'   => array(
                                            '@attributes' => array(
                                                'xmlns:cno'                         => "http://www.sat.gob.gt/face2/ComplementoReferenciaNota/0.1.0",
												'FechaEmisionDocumentoOrigen'       => 'req.body.complementos.fechaEmisionDocumentoOrigen',
												'MotivoAjuste'                      => 'req.body.complementos.motivoAjuste',
												'NumeroAutorizacionDocumentoOrigen' => 'req.body.complementos.numeroAutorizacionDocumentoOrigen',
												'Version'                           => "1"
                                            )
                                        )
                                    )
                                )
                            ),
                        )
                    )
                )
            )
        );

        $xmlItems = $this->_array_to_xml($request, '<root/>');
        file_put_contents(dirname(__FILE__) . '/../debug/'.time().'-notacredito-.txt', $xmlItems);

        $firmaDocumento = $this->_firmaDocumento($xmlItems);
    }

    public function notaAbono($order_id) {
        $request = array(
            'dte:GTDocumento'   => array(
                '@attributes'   => array(
                    'xmlns:dte' => "http://www.sat.gob.gt/dte/fel/0.2.0",
                    'xmlns:xd'  => "http://www.w3.org/2000/09/xmldsig#",
                    'Version'   => "0.1"
                ),
                'dte:SAT'   => array(
                    '@attributes' => array(
                        'ClaseDocumento' => "dte"
                    ),
                    'dte:DTE'   => array(
                        '@attributes' => array(
                            'ID'    => "DatosCertificados"
                        ),
                        'dte:DatosEmision' => array(
                            '@attributes' => array(
                                'ID'    => "DatosEmision"
                            ),
                            'dte:DatosGenerales' => array(
                                '@attributes' => array(
                                    'CodigoMoneda'      => "GTQ",
                                    'FechaHoraEmision'  => 'req.body.datosGenerales.fechaEmision',
                                    'Tipo'              => "NABN"
                                ),
                            ),
                            'dte:Emisor'    => array(
                                '@attributes' => array(
                                    'AfiliacionIVA'         => 'req.body.emisor.afiliacionIVA',
                                    'CodigoEstablecimiento' => 'req.body.emisor.codigoEstablecimiento',
                                    'NITEmisor'             => 'req.body.emisor.nit',
                                    'NombreComercial'       => 'req.body.emisor.NombreComercial',
                                    'NombreEmisor'          => 'req.body.emisor.nombreEmisor'
                                ),
                                'dte:DireccionEmisor'   => array(
                                    'dte:Direccion'     => 'req.body.emisor.direccionEmisor.direccion',
                                    'dte:CodigoPostal'  => 'req.body.emisor.direccionEmisor.codigoPostal',
                                    'dte:Municipio'     => 'req.body.emisor.direccionEmisor.municipio',
                                    'dte:Departamento'  => 'req.body.emisor.direccionEmisor.departamento',
                                    'dte:Pais'          => 'req.body.emisor.direccionEmisor.pais'
                                ),
                                'dte:Receptor'  => array(
                                    '@attributes' => array(
                                        'CorreoReceptor'    => 'req.body.receptor.correoReceptor',
                                        'IDReceptor'        => 'req.body.receptor.idReceptor',
                                        'NombreReceptor'    => 'req.body.receptor.nombreReceptor'
                                    ),
                                    'dte:DireccionReceptor' => array(
                                        'dte:Direccion'     => 'req.body.receptor.direccionReceptor.direccion',
                                        'dte:CodigoPostal'  => 'req.body.receptor.direccionReceptor.codigoPostal',
                                        'dte:Municipio'     => 'req.body.receptor.direccionReceptor.municipio',
                                        'dte:Departamento'  => 'req.body.receptor.direccionReceptor.departamento',
                                        'dte:Pais'          => 'req.body.receptor.direccionReceptor.pais'
                                    )
                                ),
                                'dte:Items' => array(
                                    'dte:Item'  => array()
                                ),
                                'dte:Totales'   => array(
                                    'dte:GranTotal' => 'req.body.totales.granTotal'
                                )
                            ),
                        )
                    )
                )
            )
        );

        $xmlItems = $this->_array_to_xml($request, '<root/>');
        file_put_contents(dirname(__FILE__) . '/../debug/'.time().'-notaabono-.txt', $xmlItems);

        $firmaDocumento = $this->_firmaDocumento($xmlItems);
    }

    public function anulacion($order_id) {
        $timeNow = $this->_get_now_time_hora();
        $fechaHoraCreated = get_post_meta($order_id, '_fel_invoice_fecha_hora', true);
        $fechaUUID = get_post_meta($order_id, '_fel_invoice_uuid', true);
        if(!empty($fechaHoraCreated) && !empty($fechaUUID)) {
            $request = array(
                'ns:GTAnulacionDocumento' => array(
                    'ns:SAT' => array(
                        'ns:AnulacionDTE' => array(
                            '@attributes' => array(
                                'ID' => "DatosCertificados"
                            ),
                            'ns:DatosGenerales' => array(
                                '@attributes' => array(
                                    'ID' => "DatosAnulacion",
                                    'NumeroDocumentoAAnular' => $fechaUUID,
                                    'NITEmisor' => '82280363',
                                    'IDReceptor' => '77253825',
                                    'FechaEmisionDocumentoAnular' => $fechaHoraCreated,
                                    'FechaHoraAnulacion' => $timeNow,
                                    'MotivoAnulacion' => 'Anulacion'
                                )
                            )
                        )
                    )
                )
            );

            $this->_setXmlOptions(0, false, '', true, 'ns');
            $xmlCancel = $this->_array_to_xml($request, '<root/>');
            $xmlCancel = str_replace('<ns:GTAnulacionDocumento xmlns:ns="ns">', '<ns:GTAnulacionDocumento xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:ns="http://www.sat.gob.gt/dte/fel/0.1.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="0.1">', $xmlCancel);
            $xmlCancel = str_replace('<?xml version="1.0"?>', '<?xml version="1.0" encoding="utf-8"?>', $xmlCancel);
            $xmlCancel = str_replace('<root>', '', $xmlCancel);
            $xmlCancel = str_replace('</root>', '', $xmlCancel);
            $xmlItems = trim($xmlCancel);
            file_put_contents(dirname(__FILE__) . '/../debug/' . time() . '-anulacion-.txt', $xmlCancel);

            $firmaDocumento = $this->_firmaDocumento($xmlCancel);
            if ($firmaDocumento['status']) {
                $anulaDocumento = $this->_anulaDocumento($firmaDocumento['data']['xml_dte']);
                if ($anulaDocumento['status']) {
                    $xmlResponse = $anulaDocumento['data']['xml_dte'];
                    //$response = $this->_xml_to_array($xmlResponse);
                    update_post_meta($order_id, '_fel_invoice_fecha_hora_cancel', $timeNow);
                    update_post_meta($order_id, '_fel_invoice_uuid_cancel', $anulaDocumento['data']['uuid']);
                    return array(
                        'status' => true,
                        'message' => 'documento procesador correctamente',
                        'data' => array(
                            'response' => $anulaDocumento['data']['xml_dte'],
                            'tipo_respuesta' => $anulaDocumento['data']['tipo_respuesta'],
                            'uuid' => $anulaDocumento['data']['uuid']
                        )
                    );
                } else {
                    return array(
                        'status' => true,
                        'message' => 'error al registrar documento',
                        'data' => $anulaDocumento
                    );
                }
            } else {
                return array(
                    'status' => true,
                    'message' => 'error al firmar documento',
                    'data' => $firmaDocumento
                );
            }
        }
    }

    private function _get_now_time_hora() {
        $original_timezone = date_default_timezone_get();

        // @codingStandardsIgnoreStart
        date_default_timezone_set( 'America/Guatemala' );
        //$ret_val = date('c');
        $ret_val = date("c", strtotime(date("Y-m-d h:i:sa")));
        date_default_timezone_set( $original_timezone );
        return $ret_val;
    }

    private function _fill_items_from($items) {
        $dteItems = array();
        $line_index = 0;
        $dump = '';
        $total_tax = 0;
        foreach ( $items as $item ) {
            $line_index++;
            $product        = $item->get_product();
            $sale_price     = $product->get_sale_price() ? $product->get_sale_price() : $product->get_price();
            $sale_price     = number_format($sale_price, 4);
            $tmp_tax1       = number_format($sale_price * $item->get_quantity() / 1.12, 4);
            $tmp_tax2 = $sale_price * $item->get_quantity() - $tmp_tax1;
            $total_tax += $tmp_tax2;
            $dteItem = array(
                '@attributes'   => array(
                    'BienOServicio' => "B",
                    'NumeroLinea'   => $line_index
                ),
                'dte:Cantidad'          => $item->get_quantity(),
                'dte:UnidadMedida'      => 'UN',
                'dte:Descripcion'       => $item->get_name(),
                'dte:PrecioUnitario'    => number_format($item->get_subtotal() / $item->get_quantity(), 4),
                'dte:Precio'            => $item->get_subtotal(),
                'dte:Descuento'         => $item->get_subtotal() - $item->get_total(),
                'dte:Impuestos'         => array(
                    'dte:Impuesto'  => array(
                        'dte:NombreCorto'           => 'IVA',
                        'dte:CodigoUnidadGravable'  => 1,
                        'dte:MontoGravable'         => $tmp_tax1,
                        'dte:MontoImpuesto'         => $tmp_tax2
                    )
                ),
                'dte:Total'         => $item->get_subtotal()
            );
            $dteItems[] = $dteItem;
            $dump.=print_r($product, true).'\n\r';
        }
        file_put_contents(dirname(__FILE__) . '/../debug/'.time().'-dump.txt', $dump);

        return array($dteItems, $total_tax);
    }
    
    private function _firmaDocumento($xml, $token='') {
        if(empty($token)) {
            $token = $this->getToken();
            $token = $token['data']['token'];
        }
        $this->headers = array(
            'Content-Type'  => 'application/xml;charset=UTF-8',
            'Authorization'  => $token
        );
        $request = '<?xml version="1.0" encoding="UTF-8"?><FirmaDocumentoRequest id="A3FD2363-05C2-AB7B-373D-56C08CF892B6"><xml_dte><![CDATA['.$xml.']]></xml_dte></FirmaDocumentoRequest>';
        file_put_contents(dirname(__FILE__) . '/../debug/'.time().'-firma.txt', $request);
        $ret = $this->_POST('https://dev.api.soluciones-mega.com/api/solicitaFirma', $request);

        $ret_val = array(
            'status'    => false,
            'data'      => array(),
            'message'   => ''
        );
        if(isset($ret['error'])) {
            $ret_val['message'] = $ret['error'];
        } elseif (isset($ret['tipo_respuesta']) && $ret['tipo_respuesta'] == 1) {
            $ret_val['message'] = 'error al firmar documento';
            $ret_val['data'] = $ret;
        } else {
            $ret_val['status'] = true;
            $ret_val['message'] = 'documento firmado';
            $ret_val['data'] = $ret;
        }
        return $ret_val;
    }

    private function _registraDocumento($xml, $token='') {
        if(empty($token)) {
            $token = $this->getToken();
            $token = $token['data']['token'];
        }
        $this->headers = array(
            'Content-Type'  => 'application/xml;charset=UTF-8',
            'Authorization'  => $token
        );
        $request = '<?xml version="1.0" encoding="UTF-8"?>
						<RegistraDocumentoXMLRequest id="166437D6-0BE3-467C-947C-EC8018DB0A03">
							<xml_dte>
								<![CDATA['.$xml.']]>
							</xml_dte>
						</RegistraDocumentoXMLRequest>';
        file_put_contents(dirname(__FILE__) . '/../debug/'.time().'-register.txt', $request);
        $ret = $this->_POST('https://dev2.api.ifacere-fel.com/api/registrarDocumentoXML', $request);

        $ret_val = array(
            'status'    => false,
            'data'      => array(),
            'message'   => ''
        );
        if(isset($ret['error'])) {
            $ret_val['message'] = $ret['error'];
        } elseif (isset($ret['tipo_respuesta']) && $ret['tipo_respuesta'] == 1) {
            $ret_val['message'] = 'error al registrar documento';
            $ret_val['data'] = $ret;
        } else {
            $ret_val['status'] = true;
            $ret_val['message'] = 'documento registrado';
            $ret_val['data'] = $ret;
        }
        return $ret_val;
    }

    private function _anulaDocumento($xml, $token='') {
        if(empty($token)) {
            $token = $this->getToken();
            $token = $token['data']['token'];
        }
        $this->headers = array(
            'Content-Type'  => 'application/xml;charset=UTF-8',
            'Authorization'  => $token
        );
        $request = '<?xml version=\'1.0\' encoding=\'UTF-8\'?>
							<AnulaDocumentoXMLRequest id="B10DC019-A68E-4977-85A8-848F623C518C">
								<xml_dte><![CDATA['.$xml.']]></xml_dte>
							</AnulaDocumentoXMLRequest>';
        file_put_contents(dirname(__FILE__) . '/../debug/'.time().'-ann.txt', $request);
        $ret = $this->_POST('https://dev2.api.ifacere-fel.com/api/anularDocumentoXML', $request);

        $ret_val = array(
            'status'    => false,
            'data'      => array(),
            'message'   => ''
        );
        if(isset($ret['error'])) {
            $ret_val['message'] = $ret['error'];
        } elseif (isset($ret['tipo_respuesta']) && $ret['tipo_respuesta'] == 1) {
            $ret_val['message'] = 'error al anula documento';
            $ret_val['data'] = $ret;
        } else {
            $ret_val['status'] = true;
            $ret_val['message'] = 'documento anula';
            $ret_val['data'] = $ret;
        }
        return $ret_val;
    }
}
