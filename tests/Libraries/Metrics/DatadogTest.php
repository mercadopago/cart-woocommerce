<?php

namespace MercadoPago\Woocommerce\Tests\Libraries\Metrics;

use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class DatadogTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();

        if (!defined('MP_VERSION')) {
            define('MP_VERSION', '8.2.0');
        }

        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }

        WP_Mock::userFunction('site_url', [
            'return' => 'https://test-store.com',
        ]);

        $GLOBALS['woocommerce'] = (object) ['version' => '9.0.0'];
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        unset($GLOBALS['woocommerce']);
    }

    public function testSendEventBuildsCorrectUrlWithDefaultTeam(): void
    {
        $capturedUrl = null;

        WP_Mock::userFunction('wp_json_encode', [
            'return' => function ($data) {
                return json_encode($data);
            },
        ]);

        WP_Mock::userFunction('wp_remote_post', [
            'times' => 1,
            'return' => [],
        ])->andReturnUsing(function ($url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return [];
        });

        Datadog::getInstance()->sendEvent('test_event', 'test_value');

        $this->assertEquals(
            'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/smb/test_event',
            $capturedUrl
        );
    }

    public function testSendEventUsesCustomTeamFromDetails(): void
    {
        $capturedUrl = null;

        WP_Mock::userFunction('wp_json_encode', [
            'return' => function ($data) {
                return json_encode($data);
            },
        ]);

        WP_Mock::userFunction('wp_remote_post', [
            'times' => 1,
            'return' => [],
        ])->andReturnUsing(function ($url) use (&$capturedUrl) {
            $capturedUrl = $url;
            return [];
        });

        Datadog::getInstance()->sendEvent('test_event', 'test_value', null, null, ['team' => 'core']);

        $this->assertEquals(
            'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/core/test_event',
            $capturedUrl
        );
    }

    public function testSendEventBuildsCorrectPayload(): void
    {
        $capturedArgs = null;

        WP_Mock::userFunction('wp_json_encode', [
            'return' => function ($data) {
                return json_encode($data);
            },
        ]);

        WP_Mock::userFunction('wp_remote_post', [
            'times' => 1,
            'return' => [],
            'args' => [
                WP_Mock\Functions::type('string'),
                WP_Mock\Functions::type('array'),
            ],
        ])->andReturnUsing(function ($url, $args) use (&$capturedArgs) {
            $capturedArgs = $args;
            return [];
        });

        Datadog::getInstance()->sendEvent('mp_api_error', '400', 'POST /v1/payments failed', 'pix', [
            'api_route'   => '/v1/payments',
            'http_method' => 'POST',
            'http_status' => 400,
        ]);

        $this->assertNotNull($capturedArgs);
        $this->assertFalse($capturedArgs['blocking']);
        $this->assertEquals(3, $capturedArgs['timeout']);
        $this->assertEquals('application/json', $capturedArgs['headers']['Content-Type']);

        $payload = json_decode($capturedArgs['body'], true);

        $this->assertEquals('400', $payload['value']);
        $this->assertEquals('POST /v1/payments failed', $payload['message']);
        $this->assertEquals(MP_VERSION, $payload['plugin_version']);
        $this->assertEquals('woocommerce', $payload['platform']['name']);
        $this->assertEquals('9.0.0', $payload['platform']['version']);
        $this->assertEquals('https://test-store.com', $payload['platform']['url']);
        $this->assertEquals('pix', $payload['details']['payment_method']);
        $this->assertEquals('/v1/payments', $payload['details']['api_route']);
        $this->assertEquals('POST', $payload['details']['http_method']);
        $this->assertEquals(400, $payload['details']['http_status']);
    }

    public function testSendEventOmitsMessageWhenNull(): void
    {
        $capturedArgs = null;

        WP_Mock::userFunction('wp_json_encode', [
            'return' => function ($data) {
                return json_encode($data);
            },
        ]);

        WP_Mock::userFunction('wp_remote_post', [
            'times' => 1,
            'return' => [],
            'args' => [
                WP_Mock\Functions::type('string'),
                WP_Mock\Functions::type('array'),
            ],
        ])->andReturnUsing(function ($url, $args) use (&$capturedArgs) {
            $capturedArgs = $args;
            return [];
        });

        Datadog::getInstance()->sendEvent('test_event', 'value_only');

        $payload = json_decode($capturedArgs['body'], true);

        $this->assertArrayNotHasKey('message', $payload);
        $this->assertArrayNotHasKey('details', $payload);
    }

    public function testSendEventOmitsDetailsWhenEmpty(): void
    {
        $capturedArgs = null;

        WP_Mock::userFunction('wp_json_encode', [
            'return' => function ($data) {
                return json_encode($data);
            },
        ]);

        WP_Mock::userFunction('wp_remote_post', [
            'times' => 1,
            'return' => [],
            'args' => [
                WP_Mock\Functions::type('string'),
                WP_Mock\Functions::type('array'),
            ],
        ])->andReturnUsing(function ($url, $args) use (&$capturedArgs) {
            $capturedArgs = $args;
            return [];
        });

        Datadog::getInstance()->sendEvent('test_event', 'value', 'msg');

        $payload = json_decode($capturedArgs['body'], true);

        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayNotHasKey('details', $payload);
    }

    public function testSendEventSilentlyHandlesExceptions(): void
    {
        WP_Mock::userFunction('wp_json_encode', [
            'return' => function () {
                throw new \Exception('encoding failed');
            },
        ]);

        WP_Mock::userFunction('wp_remote_post', ['times' => 0]);

        Datadog::getInstance()->sendEvent('test_event', 'value');

        $this->assertTrue(true);
    }
}
