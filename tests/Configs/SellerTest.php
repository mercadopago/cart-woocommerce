<?php

namespace MercadoPago\Woocommerce\Tests\Configs;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SellerTest extends TestCase
{
    use WoocommerceMock;

    private function buildSellerWithRequesterExpectation(
        string $expectedUri,
        array $expectedHeaders,
        bool $isTestMode,
        int $responseStatus = 200,
        array $responseData = [],
        string $productId = '',
        string $integratorId = ''
    ): Seller {
        Mockery::mock('alias:' . Device::class)
            ->shouldReceive('getDeviceProductId')
            ->andReturn($productId);

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn($responseStatus);
        $mockResponse->shouldReceive('getData')->andReturn($responseData);

        /** @var Requester&\Mockery\MockInterface $mockRequester */
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with($expectedUri, $expectedHeaders)
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('set')->andReturn(true);

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->andReturn(null);
        $mockCache->shouldReceive('setCache')->andReturn(null);

        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockStore->shouldReceive('isTestMode')->andReturn($isTestMode);
        $mockStore->shouldReceive('getIntegratorId')->andReturn($integratorId);

        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        return new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);
    }

    public function testUpdatePaymentMethodsUsesProdCoreEndpointWhenNotInTestMode(): void
    {
        $seller = $this->buildSellerWithRequesterExpectation(
            '/ppcore/prod/payment-methods/v1/payment-methods',
            ['x-platform-id: ' . MP_PLATFORM_ID, 'Authorization: test_public_key'],
            false
        );

        $seller->updatePaymentMethods('test_public_key');

        $this->addToAssertionCount(1);
    }

    public function testUpdatePaymentMethodsUsesBetaCoreEndpointWhenInTestMode(): void
    {
        $seller = $this->buildSellerWithRequesterExpectation(
            '/ppcore/beta/payment-methods/v1/payment-methods',
            ['x-platform-id: ' . MP_PLATFORM_ID, 'Authorization: test_public_key'],
            true
        );

        $seller->updatePaymentMethods('test_public_key');

        $this->addToAssertionCount(1);
    }

    public function testUpdatePaymentMethodsIncludesProductIdAndIntegratorIdWhenAvailable(): void
    {
        $seller = $this->buildSellerWithRequesterExpectation(
            '/ppcore/prod/payment-methods/v1/payment-methods',
            [
                'x-platform-id: ' . MP_PLATFORM_ID,
                'x-product-id: test_product_id',
                'x-integrator-id: test_integrator_id',
                'Authorization: test_public_key',
            ],
            false,
            200,
            [],
            'test_product_id',
            'test_integrator_id'
        );

        $seller->updatePaymentMethods('test_public_key');

        $this->addToAssertionCount(1);
    }

    public function testUpdatePaymentMethodsWithoutPublicKeyOmitsAuthorizationHeader(): void
    {
        Mockery::mock('alias:' . Device::class)
            ->shouldReceive('getDeviceProductId')
            ->andReturn('');

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn([]);

        /** @var Requester&\Mockery\MockInterface $mockRequester */
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with(
                '/ppcore/prod/payment-methods/v1/payment-methods',
                ['x-platform-id: ' . MP_PLATFORM_ID]
            )
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('set')->andReturn(true);
        $mockOptions->shouldReceive('get')->andReturn('');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->andReturn(null);
        $mockCache->shouldReceive('setCache')->andReturn(null);

        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockStore->shouldReceive('isTestMode')->andReturn(false);
        $mockStore->shouldReceive('getIntegratorId')->andReturn('');

        $mockLogsFile = Mockery::mock(File::class);
        $mockLogsFile->shouldReceive('warning')->once()->andReturn(null);

        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');
        $mockLogs->file = $mockLogsFile;

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $seller->updatePaymentMethods(null);

        $this->addToAssertionCount(1);
    }

    public function testIsExpiredPublicKeyReturnsTrueWhenStatusIs401(): void
    {
        /** @var Requester&\Mockery\MockInterface $mockRequester */
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
        /** @var Requester&\Mockery\MockInterface $mockRequester */
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
