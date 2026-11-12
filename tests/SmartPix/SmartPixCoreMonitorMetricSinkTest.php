<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixCoreMonitorMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class SmartPixCoreMonitorMetricSinkTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    public function testCreationRecoverySendsOneBoundedCountPayload(): void
    {
        $details = $this->recoveryDetails();
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) use ($details) {
            $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/smb/smart_pix_checkout_creation_recovered', $url);
            $this->assertSame(['blocking', 'timeout', 'redirection', 'headers', 'body'], array_keys($arguments));
            $this->assertFalse($arguments['blocking']);
            $this->assertSame(3, $arguments['timeout']);
            $this->assertSame(0, $arguments['redirection']);
            $this->assertSame(['Content-Type' => 'application/json'], $arguments['headers']);
            $payload = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame([
                'value' => '1', 'message' => 'classic', 'plugin_version' => '8.4.0',
                'platform' => ['name' => 'woocommerce', 'version' => '11.1.0'],
                'details' => ['payment_method' => 'smart_transfer', 'site_id' => 'MLB',
                    'event' => 'checkout_recovered_after_creation_started', 'reason' => 'result_unknown',
                    'environment' => 'homol', 'missing_fields' => 'sdk_instance_id'],
            ], $payload);
            foreach ($payload['details'] as $detail) {
                $this->assertIsString($detail);
            }
            return [];
        });
        (new SmartPixCoreMonitorMetricSink('homol', '11.1.0', '8.4.0'))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $details);
    }

    public function testUnreadableBodyRejectionTravelsAsTheDeclaredMessageOfInvalidPayload(): void
    {
        $details = ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) use ($details) {
            $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/smb/smart_pix_server_notification_rejected', $url);
            $this->assertSame([
                'value' => '1', 'message' => 'server', 'plugin_version' => '8.4.0',
                'platform' => ['name' => 'woocommerce', 'version' => '11.1.0'],
                'details' => ['payment_method' => 'smart_transfer', 'site_id' => 'MLB',
                    'event' => 'notification_rejected', 'reason' => 'unreadable_body',
                    'environment' => 'homol', 'missing_fields' => 'sdk_instance_id'],
            ], json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR));
            return [];
        });
        $telemetry = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry(
            new SmartPixCoreMonitorMetricSink('homol', '11.1.0', '8.4.0')
        );

        $telemetry->notificationRejected('invalid_payload', 'unreadable_body');
    }

    public function testDistributionUsesANumericValueAndConfiguredEnvironment(): void
    {
        $details = $this->details() + ['outcome' => 'success', 'environment' => 'homol'];
        $expected = ['payment_method' => 'smart_transfer', 'site_id' => 'MLB',
            'event' => 'create_processing_succeeded', 'environment' => 'prod', 'request_time' => '125',
            'missing_fields' => 'sdk_instance_id'];
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) use ($expected) {
            $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/metric/datadog/smb/smart_pix_server_processing_time', $url);
            $payload = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            $this->assertIsFloat($payload['value']);
            $this->assertSame(0.125, $payload['value']);
            $this->assertSame(['value', 'plugin_version', 'platform', 'details'], array_keys($payload));
            $this->assertSame(['name' => 'woocommerce', 'version' => '11.1.0'], $payload['platform']);
            $this->assertSame($expected, $payload['details']);
            foreach ($payload['details'] as $detail) {
                $this->assertIsString($detail);
            }
            return [];
        });
        (new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'))
            ->distribution(SmartPixMetricSchema::TIME, 0.125, $details);
    }

    /** @dataProvider invalidConfiguration */
    public function testInvalidConfigurationEmitsNothing(string $environment, string $woocommerceVersion, string $pluginVersion): void
    {
        $this->denyTransport();
        $sink = new SmartPixCoreMonitorMetricSink($environment, $woocommerceVersion, $pluginVersion, null, '12345', 'https://store.example.test');
        $sink->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
        $sink->distribution(SmartPixMetricSchema::TIME, 0.125, $this->details());
        $this->addToAssertionCount(1);
    }

    public static function invalidConfiguration(): array
    {
        return [
            'missing environment' => ['', '11.1.0', '8.4.0'],
            'unknown environment' => ['staging', '11.1.0', '8.4.0'],
            'destination injection' => ['https://synthetic.invalid', '11.1.0', '8.4.0'],
            'missing version' => ['prod', '', '8.4.0'],
            'store URL in version' => ['prod', 'https://synthetic.invalid', '8.4.0'],
            'identifier in plugin version' => ['prod', '11.1.0', 'SYNTHETIC_PRIVATE_OWNER'],
            'overlong version' => ['prod', '11.1.0', str_repeat('1', 33)],
            'arbitrary release suffix' => ['prod', '11.1.0', '8.4.0-PRIVATE_ACCOUNT'],
        ];
    }

    /** @dataProvider forbiddenDetails */
    public function testUnboundedOrPrivateDetailsAreDenied(array $extra): void
    {
        $this->denyTransport();
        $details = array_replace($this->details(), $extra);
        $sink = new SmartPixCoreMonitorMetricSink('homol', '11.1.0', '8.4.0');
        $sink->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', array_replace($this->recoveryDetails(), $extra));
        $sink->distribution(SmartPixMetricSchema::TIME, 0.125, $details + ['outcome' => 'success']);
        $sink->distribution(SmartPixMetricSchema::ORDER_CREATION_TIME, 0.125, $details + ['outcome' => 'success']);
        $this->addToAssertionCount(1);
    }

    public static function forbiddenDetails(): array
    {
        $cases = [];
        foreach (
            ['owner', 'attempt_key', 'attempt_hash', 'order_id', 'payment_id', 'customer_id', 'access_token',
                'url', 'uri', 'sdk_flow', 'raw_exception', 'duration', 'team'] as $field
        ) {
            $cases[$field] = [[$field => 'SYNTHETIC_PRIVATE_VALUE']];
        }
        $cases['unbounded value'] = [['source' => 'SYNTHETIC_PRIVATE_VALUE']];
        $cases['invalid environment detail'] = [['environment' => 'SYNTHETIC_PRIVATE_VALUE']];
        $cases['non-string value'] = [['site_id' => ['MLB']]];
        return $cases;
    }

    /** @dataProvider invalidDurations */
    public function testInvalidNumericDurationsDoNotReachTransport(float $seconds): void
    {
        $this->denyTransport();
        (new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'))
            ->distribution(SmartPixMetricSchema::TIME, $seconds, $this->details() + ['outcome' => 'success']);
        $this->addToAssertionCount(1);
    }

    public static function invalidDurations(): array
    {
        return ['zero' => [0.0], 'negative' => [-0.125], 'NaN' => [NAN], 'infinity' => [INF], 'negative infinity' => [-INF]];
    }

    public function testUnknownNamesValuesAndUnsupportedDurationOperationsAreDenied(): void
    {
        $this->denyTransport();
        $sink = new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0');
        $sink->count('../SYNTHETIC_PRIVATE_VALUE', 'pending', $this->details());
        $sink->count(SmartPixMetricSchema::CREATION_RECOVERY, 'SYNTHETIC_PRIVATE_VALUE', $this->recoveryDetails());
        // The removed creation counter is unknown, and a counter is never a distribution.
        $sink->count('smart_pix_server_create_result', 'pending', $this->details());
        $sink->count('smart_pix_server_effect_admission', 'acquired', array_replace($this->details(), ['operation' => 'apply']));
        $sink->distribution(SmartPixMetricSchema::CREATION_RECOVERY, 0.125, $this->recoveryDetails() + ['outcome' => 'success']);
        $sink->distribution(SmartPixMetricSchema::TIME, 0.125, array_replace($this->details(), ['operation' => 'get', 'outcome' => 'success']));
        // The dedicated distributions measure completed spans of their own operation only.
        $sink->distribution(SmartPixMetricSchema::ORDER_CREATION_TIME, 0.125, $this->details() + ['outcome' => 'failure']);
        $sink->distribution(SmartPixMetricSchema::PAYMENT_APPROVAL_TIME, 0.125, $this->details() + ['outcome' => 'success']);
        $this->addToAssertionCount(1);
    }

    /** @dataProvider encodingFailures */
    public function testEncodingFailureNeverAttemptsTransport($encoded): void
    {
        WP_Mock::userFunction('wp_json_encode', ['times' => 1, 'return' => $encoded]);
        WP_Mock::userFunction('wp_remote_post', ['times' => 0]);
        (new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
        $this->addToAssertionCount(1);
    }

    public static function encodingFailures(): array
    {
        return ['false' => [false], 'empty' => [''], 'null' => [null]];
    }

    public function testEncodingExceptionIsIgnoredWithoutTransportOrLogging(): void
    {
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andThrow(new \Error('SYNTHETIC_PRIVATE_DIAGNOSTIC'));
        WP_Mock::userFunction('wp_remote_post', ['times' => 0]);
        (new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
        $this->addToAssertionCount(1);
    }

    public function testTransportThrowableIsIgnoredWithoutRetryOrLogging(): void
    {
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andThrow(new \Error('SYNTHETIC_PRIVATE_DIAGNOSTIC'));
        (new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
        $this->addToAssertionCount(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testWordPressErrorReturnIsIgnoredWithoutRetry(): void
    {
        if (!class_exists('WP_Error')) {
            Mockery::mock('alias:WP_Error');
        }
        $error = new \WP_Error();
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1, 'return' => $error]);
        (new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
        $this->assertInstanceOf(\WP_Error::class, $error);
    }

    public function testAuthenticatedNotificationFetchPropagatesRequestAndSdkCorrelationWithoutChangingTransport(): void
    {
        $context = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext();
        $sink = new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0', $context);
        $telemetry = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry($sink, null, $context);
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_meta')->with('_mp_smart_pix_sdk_instance_id', true)->andReturn('sdk-original');
        $telemetry->observeOrder($order);
        $http = Mockery::mock(\MercadoPago\Woocommerce\Api\Http\HttpClientInterface::class);
        $response = new \MercadoPago\Woocommerce\Api\Http\HttpResponse(503, ['X-Request-ID' => 'request-core-current']);
        $http->shouldReceive('request')->once()->with('GET', '/plugins-platforms/v1/notification/order/PPORD123', [], null)->andReturn($response);
        $observed = new \MercadoPago\Woocommerce\SmartPix\Adapters\Http\SmartPixNotificationHttpClient($http, $telemetry);
        $this->assertSame($response, $observed->request('GET', '/plugins-platforms/v1/notification/order/PPORD123'));
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post')->once()->andReturnUsing(function ($url, $args): array {
            $details = json_decode($args['body'], true)['details'];
            $this->assertSame('sdk-original', $details['sdk_instance_id']);
            $this->assertSame('request-core-current', $details['request_id']);
            $this->assertSame('GET /plugins-platforms/v1/notification/order/{id}', $details['api_route']);
            $this->assertSame('http_5xx', $details['reason']);
            $this->assertArrayNotHasKey('missing_fields', $details);
            return [];
        });
        $telemetry->error('notification', 'http_5xx', 'server', 'notification');
    }

    public function testAuthenticatedNotificationTransportFailureIsReportedAsUnavailableAndRethrownUnchanged(): void
    {
        $context = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext();
        $sink = new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0', $context);
        $telemetry = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry($sink, null, $context);
        $failure = new \MercadoPago\Woocommerce\Api\Http\TransportException('http_request_failed', 'cURL error 28: Operation timed out');
        $http = Mockery::mock(\MercadoPago\Woocommerce\Api\Http\HttpClientInterface::class);
        $http->shouldReceive('request')->once()->with('GET', '/plugins-platforms/v1/notification/order/PPORD123', [], null)->andThrow($failure);
        $sent = [];
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post')->once()->andReturnUsing(function ($url, $args) use (&$sent): array {
            $sent = [$url, json_decode($args['body'], true)];
            return [];
        });
        $observed = new \MercadoPago\Woocommerce\SmartPix\Adapters\Http\SmartPixNotificationHttpClient($http, $telemetry);

        try {
            $observed->request('GET', '/plugins-platforms/v1/notification/order/PPORD123');
            $this->fail('The transport failure must reach the notification caller.');
        } catch (\MercadoPago\Woocommerce\Api\Http\TransportException $caught) {
            $this->assertSame($failure, $caught);
        }

        $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/smb/' . SmartPixMetricSchema::ERROR, $sent[0]);
        $this->assertSame('unavailable', $sent[1]['details']['reason']);
        $this->assertSame('notification_processing_failed', $sent[1]['details']['event']);
        $this->assertSame('GET /plugins-platforms/v1/notification/order/{id}', $sent[1]['details']['api_route']);
        $this->assertStringContainsString('[http_request_failed]: cURL error 28: Operation timed out', $sent[1]['message']);
    }

    public function testTransportFailureOutsideTheNotificationFetchIsRethrownWithoutTelemetry(): void
    {
        $telemetry = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry(new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'));
        $failure = new \MercadoPago\Woocommerce\Api\Http\TransportException('http_request_failed');
        $http = Mockery::mock(\MercadoPago\Woocommerce\Api\Http\HttpClientInterface::class);
        $http->shouldReceive('request')->once()->with('POST', '/plugins-platforms/v1/notification/order/PPORD123', [], '{}')->andThrow($failure);
        WP_Mock::userFunction('wp_remote_post', ['times' => 0]);
        $observed = new \MercadoPago\Woocommerce\SmartPix\Adapters\Http\SmartPixNotificationHttpClient($http, $telemetry);

        $this->expectExceptionObject($failure);
        $observed->request('POST', '/plugins-platforms/v1/notification/order/PPORD123', [], '{}');
    }

    public function testOrderCreationTimeTravelsAsItsOwnDistribution(): void
    {
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) {
            $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/metric/datadog/smb/smart_pix_order_creation_time', $url);
            $this->assertSame([
                'value' => 0.432, 'plugin_version' => '8.4.0',
                'platform' => ['name' => 'woocommerce', 'version' => '11.1.0'],
                'details' => ['payment_method' => 'smart_transfer', 'site_id' => 'MLB', 'event' => 'order_creation_succeeded',
                    'environment' => 'prod', 'request_time' => '432', 'missing_fields' => 'sdk_instance_id'],
            ], json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR));
            return [];
        });
        (new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'))
            ->distribution(SmartPixMetricSchema::ORDER_CREATION_TIME, 0.432, array_replace($this->details(), ['surface' => 'blocks', 'outcome' => 'success']));
    }

    public function testPaymentApprovalTimeTravelsAsItsOwnDistribution(): void
    {
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) {
            $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/metric/datadog/smb/smart_pix_payment_approval_time', $url);
            $payload = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame(42, $payload['value']);
            $this->assertSame(['payment_method' => 'smart_transfer', 'site_id' => 'MLB', 'event' => 'payment_status_observed',
                'payment_status' => 'approved', 'environment' => 'homol', 'request_time' => '42000',
                'missing_fields' => 'sdk_instance_id'], $payload['details']);
            return [];
        });
        (new SmartPixCoreMonitorMetricSink('homol', '11.1.0', '8.4.0'))->distribution(
            SmartPixMetricSchema::PAYMENT_APPROVAL_TIME,
            42.0,
            ['operation' => 'notification', 'source' => 'notification', 'surface' => 'server',
                'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'outcome' => 'success']
        );
    }

    public function testValidStoreIdentityIsSentWithCountsAndDistributions(): void
    {
        $payloads = [];
        WP_Mock::userFunction('wp_json_encode', ['times' => 2])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 2])->andReturnUsing(function ($url, $arguments) use (&$payloads) {
            $payloads[] = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            return [];
        });
        $sink = new SmartPixCoreMonitorMetricSink('homol', '11.1.0', '8.4.0', null, '123456789', 'https://store.example.test/shop');

        $sink->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
        $sink->distribution(SmartPixMetricSchema::TIME, 0.125, $this->details() + ['outcome' => 'success']);

        $this->assertCount(2, $payloads);
        foreach ($payloads as $payload) {
            $this->assertSame(
                ['name' => 'woocommerce', 'version' => '11.1.0', 'url' => 'https://store.example.test/shop'],
                $payload['platform']
            );
            $this->assertSame('123456789', $payload['details']['cust_id']);
            // Identity is added before correlation, so the correlation markers still close the details.
            $this->assertSame('missing_fields', array_key_last($payload['details']));
        }
    }

    public function testPageContextIsSeparateFromStoreUrlForCountsAndDistributions(): void
    {
        $payloads = [];
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 2])->andReturnUsing(function ($url, $arguments) use (&$payloads) {
            $payloads[] = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            return [];
        });
        $sink = new SmartPixCoreMonitorMetricSink(
            'homol',
            '11.1.0',
            '8.4.0',
            null,
            '123456789',
            'https://store.example.test',
            'https://store.example.test/checkout/order-received/42/?key=wc_order_SYNTHETIC#token'
        );
        $sink->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
        $sink->distribution(SmartPixMetricSchema::TIME, 0.125, $this->details() + ['outcome' => 'success']);

        foreach ($payloads as $payload) {
            $this->assertSame('https://store.example.test', $payload['platform']['url']);
            $this->assertSame('https://store.example.test/checkout/order-received/', $payload['platform']['uri']);
            $this->assertStringNotContainsString('SYNTHETIC', json_encode($payload));
        }
    }

    /** @dataProvider invalidStoreIdentityProvider */
    public function testInvalidOrAbsentStoreIdentityIsOmittedWithoutDisablingTheSink(?string $custId, ?string $url): void
    {
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) {
            $payload = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame(['name' => 'woocommerce', 'version' => '11.1.0'], $payload['platform']);
            $this->assertArrayNotHasKey('cust_id', $payload['details']);
            $this->assertStringNotContainsString('SYNTHETIC', $arguments['body']);
            return [];
        });

        (new SmartPixCoreMonitorMetricSink('homol', '11.1.0', '8.4.0', null, $custId, $url))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
    }

    public static function invalidStoreIdentityProvider(): array
    {
        return [
            'absent' => [null, null],
            'empty' => ['', ''],
            'non-numeric customer and relative URL' => ['SYNTHETIC_CUSTOMER', '/SYNTHETIC_PATH'],
            'signed customer and non-HTTP URL' => ['-123', 'javascript:SYNTHETIC'],
            'overlong customer and URL without host' => [str_repeat('1', 21), 'https://'],
            'customer with suffix and URL with spaces' => ["123\n", 'https://store.example.test/SYNTHETIC PATH'],
            'decimal customer and FTP URL' => ['12.5', 'ftp://store.example.test/SYNTHETIC'],
            'overlong URL' => ['SYNTHETIC', 'https://store.example.test/' . str_repeat('a', 300) . 'SYNTHETIC'],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFromPluginReadsTheSellerAccountAndStoreUrl(): void
    {
        $_SERVER['REQUEST_URI'] = '/?wc-ajax=mp_smart_pix_order_status';
        $_SERVER['HTTP_REFERER'] = 'https://store.example.test/checkout/order-received/42/?key=wc_order_SYNTHETIC';
        $seller = Mockery::mock();
        $seller->shouldReceive('getCustIdFromAT')->once()->andReturn('987654');
        WP_Mock::userFunction('site_url', ['times' => 1, 'return' => 'https://store.example.test']);
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) {
            $payload = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            $this->assertSame('https://store.example.test', $payload['platform']['url']);
            $this->assertSame('https://store.example.test/checkout/order-received/', $payload['platform']['uri']);
            $this->assertSame('987654', $payload['details']['cust_id']);
            $this->assertSame('prod', $payload['details']['environment']);
            return [];
        });

        SmartPixCoreMonitorMetricSink::fromPlugin($this->plugin(false, $seller))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider unavailableStoreIdentityProvider
     */
    public function testFromPluginWithoutStoreIdentityStillEmits(string $case): void
    {
        $seller = null;
        if ($case === 'seller throws') {
            $seller = Mockery::mock();
            $seller->shouldReceive('getCustIdFromAT')->once()->andThrow(new \Error('SYNTHETIC_PRIVATE_DIAGNOSTIC'));
        } elseif ($case === 'non-string identity') {
            $seller = Mockery::mock();
            $seller->shouldReceive('getCustIdFromAT')->once()->andReturn(987654);
            WP_Mock::userFunction('site_url', ['times' => 1, 'return' => false]);
        }
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        $posted = [];
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) use (&$posted) {
            $posted[] = [$url, json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR)];
            return [];
        });

        SmartPixCoreMonitorMetricSink::fromPlugin($this->plugin(true, $seller))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());

        // Assert outside the transport callback: the sink swallows transport exceptions.
        $this->assertCount(1, $posted);
        [$url, $payload] = $posted[0];
        $this->assertArrayNotHasKey('url', $payload['platform']);
        $this->assertArrayNotHasKey('cust_id', $payload['details']);
        // A test-mode store is tagged as a test environment but still reports to the production Monitor.
        $this->assertSame('homol', $payload['details']['environment']);
        $this->assertStringStartsWith('https://api.mercadopago.com/ppcore/prod/monitor/v1/event/', $url);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTheEnvironmentTagFollowsTheSmartPixOrdersEnvironment(): void
    {
        define('MP_SMART_PIX_ENV', 'homologation');
        $seller = Mockery::mock();
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCustIdFromAT')->andReturn('123456');
        WP_Mock::userFunction('site_url', ['return' => 'https://store.example.test']);
        $posted = [];
        WP_Mock::userFunction('wp_json_encode', ['return' => static fn($value) => json_encode($value)]);
        WP_Mock::userFunction('wp_remote_post', ['return' => static function ($url, $args) use (&$posted) {
            $posted[] = $args['body'];
            return [];
        }]);

        SmartPixCoreMonitorMetricSink::fromPlugin($this->plugin(false, $seller))
            ->count(SmartPixMetricSchema::CREATION_RECOVERY, 'result_unknown', $this->recoveryDetails());

        $this->assertCount(1, $posted);
        $this->assertStringContainsString('"environment":"homol"', $posted[0]);
    }

    /** @dataProvider fractionalDurationProvider */
    public function testDistributionRequestTimeIsAFixedDecimalMillisecondString(float $seconds, string $expected): void
    {
        $sent = [];
        WP_Mock::userFunction('wp_json_encode', ['times' => 1])->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) use (&$sent) {
            $sent[] = json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR);
            return [];
        });

        (new SmartPixCoreMonitorMetricSink('prod', '11.1.0', '8.4.0'))->distribution(
            SmartPixMetricSchema::ORDER_CREATION_TIME,
            $seconds,
            array_replace($this->details(), ['surface' => 'blocks', 'outcome' => 'success'])
        );

        $this->assertCount(1, $sent);
        $this->assertSame($expected, $sent[0]['details']['request_time']);
    }

    public static function fractionalDurationProvider(): array
    {
        return ['sub-millisecond' => [0.00001, '0.01'], 'float noise' => [0.1 + 0.2, '300'], 'fraction' => [0.4325, '432.5']];
    }

    public static function unavailableStoreIdentityProvider(): array
    {
        // In a separate process `site_url` is undefined unless the case mocks it.
        return ['container without seller' => ['container without seller'], 'seller throws' => ['seller throws'],
            'non-string identity' => ['non-string identity']];
    }

    /** @param object|null $seller */
    private function plugin(bool $testMode, $seller): object
    {
        $store = Mockery::mock(\MercadoPago\Woocommerce\Configs\Store::class);
        $store->shouldReceive('isTestMode')->andReturn($testMode);
        $plugin = (object) ['storeConfig' => $store, 'woocommerce' => (object) ['version' => '11.1.0']];
        if ($seller !== null) {
            $plugin->sellerConfig = $seller;
        }
        return $plugin;
    }

    private function denyTransport(): void
    {
        WP_Mock::userFunction('wp_json_encode', ['times' => 0]);
        WP_Mock::userFunction('wp_remote_post', ['times' => 0]);
    }

    /** @return array<string, string> */
    private function recoveryDetails(): array
    {
        return ['operation' => 'create', 'source' => 'internal', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
    }

    /** @return array<string, string> */
    private function details(): array
    {
        return ['operation' => 'create', 'source' => 'sync_response', 'surface' => 'classic',
            'payment_method' => 'smart_pix', 'site_id' => 'MLB'];
    }
}
