<?php

    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId' );

    //Payment with two Cards
    $multipayment = $sdk->getMultipaymentInstance();

    //For each new call it will be necessary to create a card token
    $transaction_info = ["transaction_info" =>
                             
                                [
                                    "transaction_amount" => 50,
                                    "installments" => 1,
                                    "token" => "732057aed78e3a571eaacdffe7a388d5",
                                    "payment_method_id" => "master"
                                ],
                                [
                                    "transaction_amount" => 40,
                                    "installments" => 1,
                                    "token" => "eb01f5336d50f365f0f449ee117dfa46",
                                    "payment_method_id" => "amex"
                                ]
                        ];

    $multipayment->payment_method_id = "pp_multiple_payments";
    $multipayment->transaction_info = $transaction_info;
    $multipayment->transaction_amount = 90;
    $multipayment->date_of_expiration = "2024-03-30T20:10:00.000+0000";
    $multipayment->description = "Ergonomic Silk Shirt";

    $multipayment->payer->email = "test_user_98934401@testuser.com";

    debug(json_encode($multipayment->save()));
 ?>
 