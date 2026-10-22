<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Hooks\Template;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

class EndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider endpointProvider */
    public function testEndpointRegistrationPreservesNamespaceAndCallback(string $method, string $endpoint, string $expectedAction): void
    {
        $callback = [new Template(), 'getWoocommerceTemplate'];
        $registered = false;
        WP_Mock::onActionAdded($expectedAction)->with($callback, 10, 1)
            ->perform(function () use (&$registered) {
                $registered = true;
            });

        (new Endpoints())->$method($endpoint, $callback);

        $this->assertTrue($registered, 'The real Endpoints adapter must register the exact platform action and callback.');
    }

    public static function endpointProvider(): array
    {
        return [
            'authenticated WordPress AJAX' => ['registerAjaxEndpoint', 'mp_fixture_Endpoint', 'wp_ajax_mp_fixture_Endpoint'],
            'WooCommerce AJAX' => ['registerWCAjaxEndpoint', 'mp_fixture_Endpoint', 'wc_ajax_mp_fixture_Endpoint'],
            'WooCommerce API normalizes case' => ['registerApiEndpoint', 'WC_WooMercadoPago_Custom_Gateway', 'woocommerce_api_wc_woomercadopago_custom_gateway'],
        ];
    }
}
