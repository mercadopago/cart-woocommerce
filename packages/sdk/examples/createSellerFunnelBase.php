<?php

    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId', 'publicKey' );

    $createSellerFunnelBase = $sdk->getCreateSellerFunnelBaseInstance();

    $createSellerFunnelBase->platform_id = "123";
    $createSellerFunnelBase->shop_url = "http://localhost";
    $createSellerFunnelBase->platform_version = "1.0.0";
    $createSellerFunnelBase->plugin_version = "1.0.0";
    $createSellerFunnelBase->site_id = "MLB";

    debug(json_encode($createSellerFunnelBase->save()));
 ?>
 