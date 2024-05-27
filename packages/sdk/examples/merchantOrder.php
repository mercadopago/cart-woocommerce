<?php
    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId', 'publicKey' );

    $merchantOrder = $sdk->getMerchantOrderInstance();

    debug(json_encode($merchantOrder->getMerchantOrders()));
?>
