<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\YapeGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class YapeGatewayTest extends TestCase
{
    use WoocommerceMock;

    public function testGetCheckoutName()
    {
        $gateway = Mockery::mock(YapeGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $result = $gateway->getCheckoutName();
        $this->assertEquals('checkout-yape', $result);
    }
}
