<?php

include_once '../../lib/nusoap.php';
include '../functions/sum.php';


$service  = new soap_server();

$ns = 'urn:servicioWebNuSoap';
$service->configureWSDL('firstService', $ns);

$service->schemaTargetNamespeace = $ns;

$service->register('getResult', ['num1' => 'xsd:integer', 'num2' => 'xsd:integer'], ['response' => 'xsd:string'], $ns);
function getResult($num1, $num2)
{

    $function = new sums();
    $success = true;
    try
    {
        $response = $function->getResult($num1, $num2);
    } catch (Exception $e) {
        $success = false;
    }

    if (success) {
        return $response;
    }

    return 'success false';

    return 'hola';

}

//$HTTP_ROW_POST_DATA = isset($HTTP_ROW_POST_DATA) ? $HTTP_ROW_POST_DATA : '';
$service->service(file_get_contents("php://input"));

