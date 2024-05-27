<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class PreferenceTest extends TestCase
{

    private function loadPreferenceSdk() {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $accessToken = $envVars['ACCESS_TOKEN'] ?? null;
        $publicKey = $envVars['PUBLIC_KEY'] ?? null;
        $sdk = new Sdk(
            $accessToken,
            'ppcoreinternal',
            'ppcoreinternal',
            '',
            $publicKey
        );
        return $sdk->getPreferenceInstance(); 
    }

    private function loadPreference()
    {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $preference = $this->loadPreferenceSdk();
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

    public function testGetPreferenceSucces()
    {
        $preferenceSdkInstance = $this->loadPreferenceSdk();
        $preference = $this->loadPreference();
        $responseSave = json_decode(json_encode($preference->save()));
    
        $responseRead = json_decode(json_encode($preferenceSdkInstance->read(array(
            "id" => $responseSave->id,
        ))));

        $this->assertEquals($responseRead->items[0]->unit_price, $responseSave->items[0]->unit_price);
        $this->assertEquals($responseRead->external_reference, $responseSave->external_reference);
    }

    public function testGetPreferenceNotFound()
    {
        $preferenceSdkInstance = $this->loadPreferenceSdk();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The preference with identifier 123 was not found');

        $responseRead = json_decode(json_encode($preferenceSdkInstance->read(array(
            "id" => "123",
        ))));        
    }
}