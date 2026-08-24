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
        $details = MetricContext::buildBaseMetricDetails('/v1/payments');

        $this->assertEquals([
            'team'      => 'big',
            'api_route' => '/v1/payments',
        ], $details);
    }

    public function testBuildApiErrorDetailsStripsQueryParametersAndParameterizesIdSegments(): void
    {
        $uri = '/ppcore/prod/configurations-api/onboarding/v1/integration/123?code_verifier=secret-value';

        $details = MetricContext::buildBaseMetricDetails($uri);

        $this->assertEquals(
            '/ppcore/prod/configurations-api/onboarding/v1/integration/{id}',
            $details['api_route']
        );
        $this->assertStringNotContainsString('code_verifier', $details['api_route']);
        $this->assertStringNotContainsString('secret-value', $details['api_route']);
        $this->assertStringNotContainsString('123', $details['api_route']);
    }

    public function testBuildApiErrorDetailsParameterizesNumericSegments(): void
    {
        $details = MetricContext::buildBaseMetricDetails('/v1/payments/123456');

        // Numeric path segments are parameterized — keeps api_route low-cardinality.
        $this->assertEquals('/v1/payments/{id}', $details['api_route']);
    }

    public function testBuildApiErrorDetailsStripsMultipleQueryParametersFromApiRoute(): void
    {
        $uri = '/v1/preferences?foo=bar&baz=qux';

        $details = MetricContext::buildBaseMetricDetails($uri);

        $this->assertEquals('/v1/preferences', $details['api_route']);
    }

    public function testBuildApiErrorDetailsIncludesSellerDetailsWhenMercadopagoPassed(): void
    {
        $mp = $this->makeMercadopagoMock('MLB', false, 'cust-123');

        $details = MetricContext::buildBaseMetricDetails('/v1/payments', $mp);

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

        $details = MetricContext::buildBaseMetricDetails('/v1/preferences');

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

        $details = MetricContext::buildBaseMetricDetails('/v1/test', $override);

        $this->assertEquals('PARAM_SITE', $details['site_id']);
        $this->assertEquals('homol', $details['environment']);
        $this->assertEquals('param-cust', $details['cust_id']);
    }

    /* ───────── parameterizeApiRoute — heuristic 1: pure numeric ───────── */

    public function testParameterizeApiRouteHeuristic1ShortInteger(): void
    {
        // Any purely numeric string is treated as an ID regardless of length.
        $this->assertSame('/v1/orders/{id}', MetricContext::parameterizeApiRoute('/v1/orders/42'));
    }

    public function testParameterizeApiRouteHeuristic1LongInteger(): void
    {
        $this->assertSame(
            '/applications/{id}',
            MetricContext::parameterizeApiRoute('/applications/9999999999')
        );
    }

    /* ───────── parameterizeApiRoute — heuristic 2: UUID ───────── */

    public function testParameterizeApiRouteHeuristic2UuidV4IsReplaced(): void
    {
        $this->assertSame(
            '/v1/tokens/{id}',
            MetricContext::parameterizeApiRoute('/v1/tokens/8a1f8e1f-91dc-4d9d-9c9f-1e2d3c4b5a6f')
        );
    }

    public function testParameterizeApiRouteHeuristic2UuidUppercaseIsReplaced(): void
    {
        $this->assertSame(
            '/v1/tokens/{id}',
            MetricContext::parameterizeApiRoute('/v1/tokens/8A1F8E1F-91DC-4D9D-9C9F-1E2D3C4B5A6F')
        );
    }

    /* ───────── parameterizeApiRoute — heuristic 3: digit + separator ───────── */

    public function testParameterizeApiRouteHeuristic3LowercaseWithHyphenAndDigitIsReplaced(): void
    {
        // No uppercase → heuristics 2 and 4 do not fire; digit + hyphen + len > 4 → heuristic 3 fires.
        $this->assertSame(
            '/v1/tokens/{id}',
            MetricContext::parameterizeApiRoute('/v1/tokens/tok-abc-12345')
        );
    }

    public function testParameterizeApiRouteHeuristic3LowercaseWithUnderscoreAndDigitIsReplaced(): void
    {
        $this->assertSame(
            '/v1/tokens/{id}',
            MetricContext::parameterizeApiRoute('/v1/tokens/ref_abc123xyz')
        );
    }

    public function testParameterizeApiRouteHeuristic3ShortSegmentWithDigitIsNotReplaced(): void
    {
        // Length ≤ 4 even with digit and separator — not treated as ID (e.g. "a-1", len=3).
        $this->assertSame('/v2/a-1', MetricContext::parameterizeApiRoute('/v2/a-1'));
    }

    /* ───────── parameterizeApiRoute — heuristic 4: uppercase + separator ───────── */

    public function testParameterizeApiRouteHeuristic4UppercasePrefixWithoutDigitIsReplaced(): void
    {
        // CPP-WSUB-xyz: has uppercase (C) and hyphen but no digit → only heuristic 4 fires.
        $this->assertSame(
            '/automatic-payments/v2/subscriptions/{id}/payment-methods/default',
            MetricContext::parameterizeApiRoute('/automatic-payments/v2/subscriptions/CPP-WSUB-xyz/payment-methods/default')
        );
    }

    public function testParameterizeApiRouteHeuristic4UppercasePrefixWithDigitIsReplaced(): void
    {
        // CPP-WSUB-abc-123: heuristics 3 and 4 both match — either is sufficient.
        $this->assertSame(
            '/automatic-payments/v2/subscriptions/{id}',
            MetricContext::parameterizeApiRoute('/automatic-payments/v2/subscriptions/CPP-WSUB-abc-123')
        );
    }

    /* ───────── parameterizeApiRoute — no ID (keyword-only paths) ───────── */

    public function testParameterizeApiRouteKeywordOnlyPathIsUnchanged(): void
    {
        // All segments are lowercase keywords with no digits — nothing is replaced.
        $this->assertSame(
            '/automatic-payments/v2/intents/cit',
            MetricContext::parameterizeApiRoute('/automatic-payments/v2/intents/cit')
        );
        $this->assertSame(
            '/automatic-payments/v2/intents/mit',
            MetricContext::parameterizeApiRoute('/automatic-payments/v2/intents/mit')
        );
        $this->assertSame(
            '/v1/payment-methods/default',
            MetricContext::parameterizeApiRoute('/v1/payment-methods/default')
        );
    }

    /* ───────── parameterizeApiRoute — multiple IDs in one path ───────── */

    public function testParameterizeApiRouteReplacesMultipleIdSegments(): void
    {
        // Two ID segments at distinct positions — both must be replaced independently.
        $this->assertSame(
            '/v1/subscriptions/{id}/events/{id}',
            MetricContext::parameterizeApiRoute('/v1/subscriptions/CPP-WSUB-abc-123/events/8a1f8e1f-91dc-4d9d-9c9f-1e2d3c4b5a6f')
        );
    }

    /* ───────── parameterizeApiRoute — absolute URL + query string ───────── */

    public function testParameterizeApiRouteStripsAbsoluteHostPrefix(): void
    {
        $this->assertSame(
            '/automatic-payments/v2/subscriptions/{id}/payment-methods',
            MetricContext::parameterizeApiRoute('https://api.mercadopago.com/automatic-payments/v2/subscriptions/CPP-WSUB-abc-123/payment-methods')
        );
    }

    public function testBuildApiErrorDetailsTemplatesApV2SubscriptionRoutesEvenWithQueryString(): void
    {
        $details = MetricContext::buildBaseMetricDetails(
            '/automatic-payments/v2/subscriptions/CPP-WSUB-real-id/payment-methods?foo=bar'
        );

        $this->assertSame('/automatic-payments/v2/subscriptions/{id}/payment-methods', $details['api_route']);
    }

    public function testBuildBaseMetricDetailsSkipsSiteIdButKeepsOtherFieldsWhenReentrant(): void
    {
        $ref  = new \ReflectionClass(MetricContext::class);
        $prop = $ref->getProperty('fetchingSiteId');
        $prop->setAccessible(true);
        $prop->setValue(null, true);

        $mp = $this->makeMercadopagoMock('MLB', false, 'cust-123');

        try {
            $details = MetricContext::buildBaseMetricDetails('/users/me', $mp);
        } finally {
            $prop->setValue(null, false);
        }

        $this->assertArrayNotHasKey('site_id', $details);
        $this->assertSame('prod', $details['environment']);
        $this->assertSame('cust-123', $details['cust_id']);
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
