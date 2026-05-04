<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\MetricContext;
use Mockery;
use PHPUnit\Framework\TestCase;

class MetricContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        unset($GLOBALS['mercadopago']);
    }

    public function testBuildApiErrorDetailsReturnsBaseDetailsWhenNoMercadopago(): void
    {
        $details = MetricContext::buildApiErrorDetails('/v1/payments');

        $this->assertEquals([
            'team'      => 'big',
            'api_route' => '/v1/payments',
        ], $details);
    }

    public function testBuildApiErrorDetailsStripsQueryParametersFromApiRoute(): void
    {
        $uri = '/ppcore/prod/configurations-api/onboarding/v1/integration/123?code_verifier=secret-value';

        $details = MetricContext::buildApiErrorDetails($uri);

        $this->assertEquals(
            '/ppcore/prod/configurations-api/onboarding/v1/integration/123',
            $details['api_route']
        );
        $this->assertStringNotContainsString('code_verifier', $details['api_route']);
        $this->assertStringNotContainsString('secret-value', $details['api_route']);
    }

    public function testBuildApiErrorDetailsPreservesPathWhenNoQueryString(): void
    {
        $details = MetricContext::buildApiErrorDetails('/v1/payments/123456');

        $this->assertEquals('/v1/payments/123456', $details['api_route']);
    }

    public function testBuildApiErrorDetailsStripsMultipleQueryParametersFromApiRoute(): void
    {
        $uri = '/v1/preferences?foo=bar&baz=qux';

        $details = MetricContext::buildApiErrorDetails($uri);

        $this->assertEquals('/v1/preferences', $details['api_route']);
    }

    public function testBuildApiErrorDetailsIncludesSellerDetailsWhenMercadopagoPassed(): void
    {
        $mp = $this->makeMercadopagoMock('MLB', false, 'cust-123');

        $details = MetricContext::buildApiErrorDetails('/v1/payments', $mp);

        $this->assertEquals([
            'team'        => 'big',
            'api_route'   => '/v1/payments',
            'site_id'     => 'MLB',
            'environment' => 'prod',
            'cust_id'     => 'cust-123',
        ], $details);
    }

    public function testBuildApiErrorDetailsFallsBackToGlobalMercadopago(): void
    {
        $GLOBALS['mercadopago'] = $this->makeMercadopagoMock('MLA', true, 'cust-456');

        $details = MetricContext::buildApiErrorDetails('/v1/preferences');

        $this->assertEquals([
            'team'        => 'big',
            'api_route'   => '/v1/preferences',
            'site_id'     => 'MLA',
            'environment' => 'homol',
            'cust_id'     => 'cust-456',
        ], $details);
    }

    public function testBuildApiErrorDetailsPrefersParameterOverGlobal(): void
    {
        $GLOBALS['mercadopago'] = $this->makeMercadopagoMock('GLOBAL_SITE', false, 'global-cust');
        $override = $this->makeMercadopagoMock('PARAM_SITE', true, 'param-cust');

        $details = MetricContext::buildApiErrorDetails('/v1/test', $override);

        $this->assertEquals('PARAM_SITE', $details['site_id']);
        $this->assertEquals('homol', $details['environment']);
        $this->assertEquals('param-cust', $details['cust_id']);
    }

    private function makeMercadopagoMock(string $siteId, bool $isTestMode, string $custId): object
    {
        $sellerConfig = Mockery::mock();
        $sellerConfig->shouldReceive('getSiteId')->andReturn($siteId);
        $sellerConfig->shouldReceive('getCustIdFromAT')->andReturn($custId);

        $storeConfig = Mockery::mock();
        $storeConfig->shouldReceive('isTestMode')->andReturn($isTestMode);

        return (object) [
            'sellerConfig' => $sellerConfig,
            'storeConfig'  => $storeConfig,
        ];
    }
}
