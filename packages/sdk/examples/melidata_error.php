<?php

    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId' );

    $melidataError = $sdk->getMelidataErrorInstance();

    $details = [
        "payment_id" => "123456"
    ];

    $melidataError->name = "nome do erro";
    $melidataError->message = "mensagem do erro";
    $melidataError->target = "error_name";
    $melidataError->plugin->version = "error_name";
    $melidataError->platform->name = "error_name";
    $melidataError->platform->version = "error_name";
    $melidataError->platform->uri = "error_name";
    $melidataError->platform->location = "error_name";
    $melidataError->details = $details;
   
    debug(json_encode($melidataError->registerError()));
 ?>
 