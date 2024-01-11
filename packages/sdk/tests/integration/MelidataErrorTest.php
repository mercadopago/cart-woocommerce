<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class MelidataErrorTest extends TestCase
{
    private function loadMelidataError()
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

        return $melidataError;
    }


    public function testMelidataErrorRegisteredWithSuccess()
    {
        $melidataError = $this->loadMelidataError();

        $response = json_decode(json_encode($melidataError->registerError()));

        $this->assertEquals($response->code, 'created');
        $this->assertEquals($response->message, 'created');
        $this->assertEquals($response->status, '201');
    }
}
