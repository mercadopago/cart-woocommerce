<?php

    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId', 'publicKey' );

    $datadogEvent = $sdk->getDatadogEventInstance();

    $details = [
        "payment_id" => "123456"
    ];

    $datadogEvent->value = "success";
    $datadogEvent->message = "mensagem vinda do teste de integração da SDK de PHP";
    $datadogEvent->plugin_version = "1.0.0";
    $datadogEvent->platform->name = "core";
    $datadogEvent->platform->version = "1.2.0";
    $datadogEvent->platform->uri = "/integration_test";
    $datadogEvent->platform->url = "https://...";
    $datadogEvent->details = $details;
   
    debug(json_encode($datadogEvent->register(array("team" => "core", "event_type"=> "unit_test"))));
 ?>
 