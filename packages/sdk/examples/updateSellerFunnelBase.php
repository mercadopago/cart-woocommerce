<?php

    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId', 'publicKey' );

    $updateSellerFunnelBase = $sdk->getUpdateSellerFunnelBaseInstance();

    $updateSellerFunnelBase->id = "id";
    $updateSellerFunnelBase->cpp_token = "token";
    $updateSellerFunnelBase->is_added_production_credential = true;
    $updateSellerFunnelBase->is_added_test_credential = true;
    $updateSellerFunnelBase->product_id = "4das56";
    $updateSellerFunnelBase->cust_id = "123";
    $updateSellerFunnelBase->application_id = "123";
    $updateSellerFunnelBase->plugin_mode = "prod";
    $updateSellerFunnelBase->is_deleted = false;
    $updateSellerFunnelBase->accepted_payments = ["bolbradesco", "pix"];
    $updateSellerFunnelBase->is_disabled = false;

    debug(json_encode($updateSellerFunnelBase->update()));
 ?>
 