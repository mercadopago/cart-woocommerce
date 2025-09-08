<?php

namespace MercadoPago\Woocommerce\Tests\Configs;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SellerTest extends TestCase
{
    use WoocommerceMock;

    public function testIsExpiredPublicKeyReturnsTrueWhenStatusIs401(): void
    {
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->once()->andReturn(401);

        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/plugins-credentials-wrapper/credentials?public_key=test_public_key', [])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $result = $seller->isExpiredPublicKey('test_public_key');

        $this->assertTrue($result);
    }

    public function testIsExpiredPublicKeyReturnsFalseWhenStatusIsNot401(): void
    {
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->once()->andReturn(200);

        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/plugins-credentials-wrapper/credentials?public_key=test_public_key', [])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $result = $seller->isExpiredPublicKey('test_public_key');

        $this->assertFalse($result);
    }
}
