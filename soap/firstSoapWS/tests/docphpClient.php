<?php

include app_path() . '/VoipHunt/Helpers/MulticajaHelper.php';

class Multicaja
{
    private static $bizurl = '';
    private static $sessionId = '';
    private static $webpay = null;
    protected static $xml;

    public function __construct()
    {
        //self::$bizurl = \Config::get('app.APG_URL');
        //self::$sessionId = uniqid();
        self::$xml = new MulticajaHelper();
    }

    public static function createOrder($order_id = '')
    {

        //crea xml,  no se está utilizando...
        //$xml = self::$xml->generateXML();

        //$wsdl = 'https://www.multicaja.cl/bdpcows/CreateOrderWebService?wsdl';
        //$wsdl = 'https://www.mcdesaqa.cl/bdpcows/CreateOrderWebService?wsdl';

        $wsdl = \Config::get('app.WSDL');
        
        /**
         * ingreso de credenciales de autorizacion, otorgados por multicaja
         */
        $options = [
            'login' => \Config::get('app.login'),
            'password' => \Config::get('app.password'),
            'trace' => 1,
        ];

        $order = Order::find($order_id);

        $expiration_seconds = 3600*24*2-3600;

        $day_unixtime_candidate = strtotime("+ $expiration_seconds SECONDS", time());

        $order->expiration_at = date('Y-m-d H:i:s', $day_unixtime_candidate);

        $order->save();

        /**
         * array asociativo con el valor de las etiquetas del xml
         */
        $params = array(
            "ecOrderId" => $order_id,
            "commerceId" => \Config::get('app.commerceId'),
            "branchId" => \Config::get('app.branchId'),
            "totalAmount" => $order->amount,
            "generalDescription" => "Econocargo",
            "requestDuration" => $expiration_seconds+3600,
            "goBackUrl" => \Config::get('app.APG_URL') . "/result/multicaja/" . $order_id,
            "notificationUrl" => \Config::get('app.APG_URL') . "/ipn/multicaja",
            "product" => array(
                "name" => "Flete y/o Mudanza",
                "description" => "Flete y/o Mudanza en todo Santiago",
                "unitPrice" => $order->amount,
                "quantity" => 1,
                "totalPrice" => $order->amount,
            ),

        );
        d($wsdl, 'WSDL DE SALIDA:  ');
        d($options, 'OPTIONS DE SALIDA:  ');
        d($params, 'PARAMETROS DE SALIDA:  ');
        /**
         *conectando con el servicio multicaja y obteniendo respuesta
         */
        try
        {
            $client = new SoapClient($wsdl, $options);
            d($client);

            $response = $client->createOrder($params);
            //$response = $client->__soapCall("createOrder", array($params)); //alternativa para llamar a funcion del servicio

            d($response);
            $createOrderResult = $response->createOrderResult;
            d($createOrderResult);

        }
        catch (Exception $e)
        {
            $x = $e->getMessage();

            d($x);
        }

        d($client->__getLastResponse());
        d($client->__getLastRequestHeaders());
        d($client->__getLastResponseHeaders());

        return $createOrderResult;
    }

    /**
     * Notifies a payment. funcion que extrae arreglo desde el xml entrante
     */

    public static function parseXml($xml)
    {

        d($xml, 'XML ENTRANTE: ');

        $xmlContent = simplexml_load_string($xml, null, 0, 'S', true);

        $objectXml = $xmlContent->Body->children('ns2', true);

        if (isset($objectXml->notifyPendingOrder))
        {
            $pendingOrder = (array) $objectXml->notifyPendingOrder;
            return self::notifyPendingOrder($pendingOrder);
        }
        else
        {
            ;
            $notifyPayment = (array) $objectXml->children();
            return self::notifyPayment($notifyPayment);
        }
        /**
         * Funcion que consulta por el status de la orden
         */
        //$xmlDataStatus = self::getOrderStatus($xmlData);

        //d($xmlDataStatus, 'SE AGREGA EL STATUS DE LA ORDEN');
    }

    protected static function notifyPendingOrder($pendingOrder)
    {

        d($pendingOrder, 'ARREGLO RESULTANTE DEL XML ENTRANTE NOTIFY PENDING ORDER: ');

        if ( ! isset($pendingOrder['mcOrderId']) || ! isset($pendingOrder['ecOrderId']))
        {
            throw new Exception("faltan parametros en la solicitud");
        }

        $xmlResponse = self::$xml->generateXMLPendingOrder();

        d($pendingOrder);

        $order = Order::find($pendingOrder['ecOrderId']);

        $order->gateway_result = json_encode($pendingOrder);

        d($order->gateway_result, 'ACTUALIZANDO ORDER APG CAMPO gateway_result CON DATOS DE NOTIFY PENDING ORDER: ');

        $order->save();

        $pendingOrder['type'] = 'pendingOrder';
        $pendingOrder['xmlResponse'] = $xmlResponse;

        return $pendingOrder;
    }

    protected static function notifyPayment($notifyPayment)
    {

        d($notifyPayment, 'ARREGLO RESULTANTE DEL XML ENTRANTE NOTIFY PAYMENT: ');

        if ( ! isset($notifyPayment['mcOrderId']) || ! isset($notifyPayment['ecOrderId']))
        {
            throw new Exception("faltan parametros en la solicitud");
        }

        $xmlResponse = self::$xml->generateXMLNotifyPayment();
        $order = Order::find($notifyPayment['ecOrderId']);

        $notifyPayment['gateway_result'] = $order->gateway_result;
        $notifyPayment['type'] = 'notifyPayment';
        $notifyPayment['xmlResponse'] = $xmlResponse;

        return $notifyPayment;
    }

    /**
     * Gets the order status. funcion que consulta por el estatus actual de la orden
     */
    protected static function getOrderStatus($xmlData)
    {

        $wsdl = 'https://www.mcdesaqa.cl/BDPGetOrderStatus/GetOrderStatusWebService?wsdl';

        $options = [
            'login' => 'econocargo',
            'password' => '7dt0fBgK',
            'trace' => 1,
        ];

        $params = [
            'commerceId' => 96981140,
            'branchId' => 'A128',
            'mcOrderId' => $xmlData['mcOrderId'],
        ];
        try
        {
            $client = new SoapClient($wsdl, $options);
            d($client);

            $response = $client->getOrderStatus($params);
            //$response = $client->__soapCall("createOrder", array($params)); //alternativa para llamar a funcion del servicio

            $getOrderStatusResult = $response->getOrderStatusResult;
            d($getOrderStatusResult);
        }
        catch (Exception $e)
        {
            $x = $e->getMessage();

            d($x);
        }

        $xmlData['orderStatus'] = $getOrderStatusResult->orderStatus;
        $xmlData['description'] = $getOrderStatusResult->description;

        return $xmlData;
    }
}