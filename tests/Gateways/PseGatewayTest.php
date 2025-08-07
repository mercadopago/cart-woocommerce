<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\PseGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PseGatewayTest extends TestCase
{
    use WoocommerceMock;

    public function testGetCheckoutName()
    {
        $gateway = Mockery::mock(PseGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $result = $gateway->getCheckoutName();
        $this->assertEquals('checkout-pse', $result);
    }
}
