<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class PreferenceTest extends TestCase
{
    private function loadPreference()
    {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $accessToken = $envVars['ACCESS_TOKEN'] ?? null;
        $sdk = new Sdk(
            $accessToken,
            'ppcoreinternal',
            'ppcoreinternal',
            ''
        );

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
        $notificationUrl = $envVars['NOTIFICATION_URL'] ?? null;
        $preference->items = $items;
        $preference->notification_url = $notificationUrl;
        $preference->external_reference = "external_reference";

        return $preference;
    }


    public function testPreferenceSucces()
    {
        $preference = $this->loadPreference();
        $response = json_decode(json_encode($preference->save()));
        $this->assertEquals($response->external_reference, "external_reference");
        $this->assertEquals($response->items[0]->unit_price, 10.5);
    }

    public function testPreferenceWithoutUnitPrice()
    {
        $preference = $this->loadPreference();

        $items = ["items" =>  
            [
                "title" => "Dummy Title",
                "description" => "Dummy description",
                "picture_url" => "http://www.myapp.com/myimage.jpg",
                "category_id" => "car_electronics",
                "quantity" => 1,
                "currency_id" => "BRL",
                "unit_price" => null
            ]
        ];

        $preference->items = $items;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('items.1.unit_price must be a number');

        $preference->save();
    }

    public function testPreferenceWithoutQuantity()
    {
        $preference = $this->loadPreference();

        $items = ["items" =>  
            [
                "title" => "Dummy Title",
                "description" => "Dummy description",
                "picture_url" => "http://www.myapp.com/myimage.jpg",
                "category_id" => "car_electronics",
                "quantity" => null,
                "currency_id" => "BRL",
                "unit_price" => 10.5
            ]
        ];

        $preference->items = $items;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('items.1.quantity must be a number');

        $preference->save();
    }
}