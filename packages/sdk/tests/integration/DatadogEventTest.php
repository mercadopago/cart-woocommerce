<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class DatadogEventTest extends TestCase
{
    private function loadDatadogEvent()
    {
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

        $datadogEvent = $sdk->getDatadogEventInstance();

        $details = [
            "payment_id" => "123456"
        ];
    
        $datadogEvent->value = "success";
        $datadogEvent->message = "mensagem vinda do teste de integração da SDK de PHP";
        $datadogEvent->plugin_version = "1.0.0";
        $datadogEvent->platform->name = "core";
        $datadogEvent->platform->version = "1.2.0";
        $datadogEvent->platform->uri = "/integration_test";
        $datadogEvent->platform->url = "https://...";
        $datadogEvent->details = $details;

        return $datadogEvent;
    }

    public function testDatadogRegisteredWithSuccess()
    {
        $datadogEvent = $this->loadDatadogEvent();

        $response = json_decode(json_encode($datadogEvent->register(array("team" => "core", "event_type" => "integration_test"))));

        $this->assertEquals($response->code, 'success');
        $this->assertEquals($response->message, 'Event registered successfully');
        $this->assertEquals($response->status, '200');
    }
}
