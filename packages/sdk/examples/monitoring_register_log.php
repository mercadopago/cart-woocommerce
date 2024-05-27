<?php

    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/../vendor/autoload.php');

    function debug($value)
    {
        echo "<pre>";
        print_r($value == "null" ? "Success!" : $value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId', 'publicKey' );

    $registerErrorLog = $sdk->getRegisterErrorLogInstance();

    $registerErrorLog->message = 'Sample error message';
    $registerErrorLog->stacktrace = 'monitoring_regiter_log.php';
    $registerErrorLog->location = 'save';
    $registerErrorLog->platform_version = phpversion();
    $registerErrorLog->module_version = "1.0.0";
    $registerErrorLog->user_agent = 'PHP SDK';
    $registerErrorLog->flow = 'sample-php-sdk';
    $registerErrorLog->runtime_version = phpversion();
    $registerErrorLog->os_version = "10";
    $registerErrorLog->browser_version = "Chrome";
    $registerErrorLog->uri = 'http://localhost';
    $registerErrorLog->url = 'http://localhost';
    $registerErrorLog->details = [
        'payment_id' => '123456789',
    ];

    debug(json_encode($registerErrorLog->save()));
?>