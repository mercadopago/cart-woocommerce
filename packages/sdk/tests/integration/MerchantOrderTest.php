<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class MerchantOrderTest extends TestCase
{
    private function loadSdk()
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

        return $sdk;
    }

    public function testGetMerchantOrders()
    {
        $sdk = $this->loadSdk();
        $merchantOrder = $sdk->getMerchantOrderInstance();
        $response = $merchantOrder->getMerchantOrders();

        $this->assertNotNull($response[0]["id"]);
        $this->assertNotEmpty($response[0]["status"]);
    }
}

