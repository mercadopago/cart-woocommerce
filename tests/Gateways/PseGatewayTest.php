<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\PseGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PseGatewayTest extends TestCase
{
    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetCheckoutName()
    {
        $gateway = Mockery::mock(PseGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $result = $gateway->getCheckoutName();
        $this->assertEquals('checkout-pse', $result);
    }
}
