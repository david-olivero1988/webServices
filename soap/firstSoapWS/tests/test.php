<?php
require_once '../../lib/nusoap.php';

$client = new nusoap_client('http://localhost/webServices/soap/firstSoapWS/ws/service.php?wsdl',true);

$num1 = 20;
$num2 = 30;

$params = ['num1' => $num1, 'num2'=>$num2];

$response = $client->call('getResult',$params);

print_r($response);

