<?php

    use MercadoPago\PP\Sdk\Sdk;

    require_once(__DIR__ . '/vendor/autoload.php');

    function debug($value){
        echo "<pre>";
        print_r($value);
        echo "</pre>";
    }

    $sdk = new Sdk( 'accessToken', 'platformId', 'productId', 'integratorId' );

    $preference = $sdk->getPreferenceInstance();

    $items = ["items" =>  
                [
                    "title" => "Dummy Title",
                    "description" => "Dummy description",
                    "picture_url" => "http://www.myapp.com/myimage.jpg",
                    "category_id" => "car_electronics",
                    "quantity" => 1,
                    "currency_id" => "BRL",
                    "unit_price" => 10.5
                ]
            ];
    
    $preference->items = $items;
    $preference->notification_url = "notification_url";
    $preference->external_reference = "external_reference";

    debug(json_encode($preference->save()));

 ?>
 