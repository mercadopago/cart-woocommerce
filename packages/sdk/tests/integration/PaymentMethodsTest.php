<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class PaymentMethodsTest extends TestCase
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

    public function testGetPaymentMethods()
    {
        $sdk = $this->loadSdk();
        $paymentMethods = $sdk->getPaymentMethodsInstance();
        $response = $paymentMethods->getPaymentMethods()->jsonSerialize();
        $this->assertTrue(count($response) > 0);
        $this->assertNotEmpty($response[0]->id);
        $this->assertNotNull($response[0]->secure_thumbnail);
        $this->assertEquals($response, $paymentMethods->payment_methods->jsonSerialize());
    }

    public function testGetPaymentMethodsByGroupBy()
    {
        $sdk = $this->loadSdk();
        $paymentMethods = $sdk->getPaymentMethodsInstance();
        $response = $paymentMethods->getPaymentMethodsByGroupBy('id'); 
        $this->assertTrue(array_key_exists('visa', $response));
        $this->assertEquals($response, $paymentMethods->grouped_payment_methods);
    }
}

