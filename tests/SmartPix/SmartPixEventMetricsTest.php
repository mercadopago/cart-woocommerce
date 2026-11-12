<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixEventMetrics;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricPayload;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricSchema;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixMetricSink;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Gateway-shaped Smart Pix events: one Datadog event with the store identity and, for the two
 * measured spans, one dedicated distribution through the closed Smart Pix schema.
 */
class SmartPixEventMetricsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const IDENTITY = ['site_id' => 'MLB', 'environment' => 'prod', 'cust_id' => '123456'];

    /** @var \Mockery\MockInterface&Datadog */
    private $datadog;

    /** @var \Mockery\MockInterface&SmartPixMetricSink */
    private $sink;

    /** @var list<array<int, mixed>> */
    private array $events = [];

    /** @var list<array<int, mixed>> */
    private array $distributions = [];

    protected function setUp(): void
    {
        $this->events = $this->distributions = [];
        $this->datadog = Mockery::mock(Datadog::class);
        $this->datadog->shouldReceive('sendEvent')->andReturnUsing(function (...$arguments): void {
            $this->events[] = $arguments;
        })->byDefault();
        $this->sink = Mockery::mock(SmartPixMetricSink::class);
        $this->sink->shouldNotReceive('count');
        $this->sink->shouldReceive('distribution')->andReturnUsing(function (...$arguments): void {
            $this->distributions[] = $arguments;
        })->byDefault();
    }

    public function testEveryEventCarriesTheStoreIdentityAndExplicitDetailsWin(): void
    {
        $this->emitter()->send('smart_pix_status_access', 'invalid_nonce', '', ['environment' => 'homol']);

        $this->assertSame([[
            'smart_pix_status_access', '1', '', 'smart_transfer', [
                'payment_method' => 'smart_transfer', 'event' => 'status_access_denied', 'reason' => 'invalid_nonce',
                'site_id' => 'MLB', 'environment' => 'homol', 'cust_id' => '123456', 'missing_fields' => 'sdk_instance_id',
            ],
        ]], $this->events);
        $this->assertSame([], $this->distributions);
    }

    /** @dataProvider thankyouResultProvider */
    public function testThankyouReceiptSendsOnlyOneDedicatedDistribution(string $state): void
    {
        $this->emitter()->send(SmartPixMetricSchema::THANKYOU_RESULT_TIME, $state, '12.345s');
        $this->assertSame([], $this->events);
        $this->assertCount(1, $this->distributions);
        $this->assertSame(SmartPixMetricSchema::THANKYOU_RESULT_TIME, $this->distributions[0][0]);
        $this->assertSame(12.345, $this->distributions[0][1]);
    }

    public static function thankyouResultProvider(): array
    {
        return [['approved'], ['rejected']];
    }

    public function testThankyouReceiptRejectsUnknownStateAndMalformedDuration(): void
    {
        $this->emitter()->send(SmartPixMetricSchema::THANKYOU_RESULT_TIME, 'cancelled', '42s');
        $this->emitter()->send(SmartPixMetricSchema::THANKYOU_RESULT_TIME, 'approved', 'NaNs');
        $this->assertSame([], $this->events);
        $this->assertSame([], $this->distributions);
    }

    public function testThankyouDistributionUsesTheNumericSecondsTransportAndStoreIdentity(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        $posted = [];
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function ($url, $arguments) use (&$posted): array {
            $posted[] = [$url, json_decode($arguments['body'], true)];
            return [];
        });
        $sink = new \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixCoreMonitorMetricSink(
            'prod', '9.8.1', '8.9.5', null, '123456', 'https://store.example.test'
        );
        (new SmartPixEventMetrics($this->datadog, new SmartPixTelemetry($sink)))
            ->send(SmartPixMetricSchema::THANKYOU_RESULT_TIME, 'approved', '12.345s');
        $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/metric/datadog/smb/smart_pix_thankyou_result_time', $posted[0][0]);
        $this->assertSame(12.345, $posted[0][1]['value']);
        $this->assertSame('12345', $posted[0][1]['details']['request_time']);
        $this->assertSame('https://store.example.test', $posted[0][1]['platform']['url']);
        $this->assertSame('thankyou_result_displayed', $posted[0][1]['details']['event']);
        $this->assertSame([], $this->events);
        WP_Mock::tearDown();
    }

    public function testOnlyTheClosedNonEmptyStringIdentityIsKept(): void
    {
        $emitter = $this->emitter([
            'site_id' => 'MLB', 'environment' => '', 'cust_id' => 123456,
            'access_token' => 'SYNTHETIC_PRIVATE_TOKEN', 'url' => 'https://store.example.test',
        ]);

        $emitter->send('smart_pix_status_access', 'invalid_nonce', '');

        $this->assertSame([
            'payment_method' => 'smart_transfer', 'event' => 'status_access_denied', 'reason' => 'invalid_nonce',
            'site_id' => 'MLB', 'missing_fields' => 'sdk_instance_id',
        ], $this->events[0][4]);
        $this->assertStringNotContainsString('SYNTHETIC', (string) json_encode($this->events));
    }

    public function testEventsWithoutAGatewayShapeAreNotSent(): void
    {
        $this->datadog->shouldNotReceive('sendEvent');

        $this->emitter()->send('smart_pix_pending_status', 'pending', 'unknown');
        $this->emitter()->send(SmartPixMetricPayload::STATUS_OBSERVED, 'pending', '42s', ['source' => 'poll']);

        $this->assertSame([], $this->distributions);
    }

    public function testGatewayEventsForwardTheOptionalPageContextOutsideDetails(): void
    {
        $uri = 'https://store.example.test/checkout/';
        $emitter = new SmartPixEventMetrics($this->datadog, new SmartPixTelemetry($this->sink), self::IDENTITY, $uri);
        $emitter->send('smart_pix_status_access', 'invalid_nonce', '');

        $this->assertNull($this->events[0][5]);
        $this->assertSame($uri, $this->events[0][6]);
        $this->assertArrayNotHasKey('platform_uri', $this->events[0][4]);
    }

    /** @dataProvider surfaceProvider */
    public function testSucceededCreationAlsoMeasuresItsDurationOnTheSurface(string $surface): void
    {
        $this->emitter()->send('smart_pix_order_creation', 'pending', '0.432s', ['surface' => $surface]);

        $this->assertSame('smart_pix_order_creation', $this->events[0][0]);
        $this->assertSame($surface, $this->events[0][2]);
        $this->assertSame('order_creation_succeeded', $this->events[0][4]['event']);
        $this->assertSame([[
            SmartPixMetricSchema::ORDER_CREATION_TIME,
            0.432,
            ['operation' => 'create', 'source' => 'sync_response', 'surface' => $surface,
                'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'outcome' => 'success'],
        ]], $this->distributions);
    }

    public static function surfaceProvider(): array
    {
        return ['classic' => ['classic'], 'blocks' => ['blocks'], 'order pay' => ['order_pay']];
    }

    public function testImmediatelyApprovedCreationIsStillMeasuredAsASucceededCreation(): void
    {
        $this->emitter()->send('smart_pix_order_creation', 'pending', '1.5s', ['surface' => 'classic', 'payment_status' => 'approved']);

        $this->assertSame('approved', $this->events[0][4]['payment_status']);
        $this->assertSame(SmartPixMetricSchema::ORDER_CREATION_TIME, $this->distributions[0][0]);
        $this->assertSame(1.5, $this->distributions[0][1]);
    }

    /** @dataProvider unmeasuredCreationProvider */
    public function testFailedRejectedOrIndeterminateCreationIsNotMeasured(string $value, array $details): void
    {
        $this->emitter()->send('smart_pix_order_creation', $value, '0.432s', $details);

        $this->assertCount(1, $this->events);
        $this->assertNotSame('order_creation_succeeded', $this->events[0][4]['event'] ?? null);
        $this->assertSame([], $this->distributions);
    }

    public static function unmeasuredCreationProvider(): array
    {
        $classic = ['surface' => 'classic'];
        return [
            'indeterminate' => ['indeterminate', $classic],
            'technical failure' => ['timeout', $classic],
            'unexpected error' => ['unexpected_error', $classic],
            'buyer rejection' => ['rejected', $classic],
            'insufficient balance' => ['insufficient_balance', $classic],
        ];
    }

    /** @dataProvider invalidSurfaceProvider */
    public function testSucceededCreationWithoutAKnownSurfaceIsSentButNotMeasured(array $details): void
    {
        $this->emitter()->send('smart_pix_order_creation', 'pending', '0.432s', $details);

        // The surface travels as the event message and is the distribution's closed surface.
        $this->assertSame('order_creation_succeeded', $this->events[0][4]['event']);
        $this->assertSame([], $this->distributions);
    }

    public static function invalidSurfaceProvider(): array
    {
        return [
            'without surface' => [[]],
            'unknown surface' => [['surface' => 'SYNTHETIC_SURFACE']],
        ];
    }

    /** @dataProvider approvalSourceProvider */
    public function testFirstObservedApprovalIsMeasuredWithTheMappedOperationAndSource(string $observer, string $operation, string $source): void
    {
        $this->emitter()->send(SmartPixMetricPayload::STATUS_OBSERVED, 'approved', '42s', ['source' => $observer]);

        $this->assertSame($observer, $this->events[0][4]['source']);
        $this->assertSame('42000', $this->events[0][4]['pooling_time']);
        $this->assertSame([[
            SmartPixMetricSchema::PAYMENT_APPROVAL_TIME,
            42.0,
            ['operation' => $operation, 'source' => $source, 'surface' => 'server',
                'payment_method' => 'smart_pix', 'site_id' => 'MLB', 'outcome' => 'success'],
        ]], $this->distributions);
    }

    public static function approvalSourceProvider(): array
    {
        return [
            'status poll' => ['poll', 'get', 'pending_query'],
            'thank-you page' => ['thankyou', 'get', 'internal'],
            'notification' => ['notification', 'notification', 'notification'],
            'server task' => ['server', 'get', 'internal'],
            'checkout response' => ['checkout', 'get', 'internal'],
        ];
    }

    /** @dataProvider approvalSourceProvider */
    public function testRejectedPaymentHasItsOwnNumericDurationAndOriginalReason(string $observer, string $operation, string $source): void
    {
        $this->emitter()->send(SmartPixMetricPayload::STATUS_OBSERVED, 'rejected', '42s', [
            'source' => $observer, 'reason' => 'rejected_by_regulations', 'payment_status_detail' => 'rejected_by_regulations',
        ]);
        $this->assertSame('rejected_by_regulations', $this->events[0][4]['reason']);
        $this->assertArrayNotHasKey('duration_bucket', $this->events[0][4]);
        $this->assertSame(SmartPixMetricSchema::PAYMENT_REJECTION_TIME, $this->distributions[0][0]);
        $this->assertSame(42.0, $this->distributions[0][1]);
        $this->assertSame($operation, $this->distributions[0][2]['operation']);
        $this->assertSame($source, $this->distributions[0][2]['source']);
    }

    /** @dataProvider unmeasuredObservationProvider */
    public function testOtherTerminalOutcomesOrUnknownObserversAreNotMeasured(string $state, array $details): void
    {
        $this->emitter()->send(SmartPixMetricPayload::STATUS_OBSERVED, $state, '42s', $details);

        $this->assertCount(1, $this->events);
        $this->assertSame([], $this->distributions);
    }

    public static function unmeasuredObservationProvider(): array
    {
        return [
            'cancelled by the notification' => ['cancelled', ['source' => 'notification']],
            'approved without source' => ['approved', []],
            'approved from an internal schema source' => ['approved', ['source' => 'pending_query']],
            'approved from an unknown source' => ['approved', ['source' => 'SYNTHETIC_SOURCE']],
            'approved from a non-string source' => ['approved', ['source' => ['poll']]],
        ];
    }

    /** @dataProvider unparsableDurationProvider */
    public function testUnparsableOrZeroDurationsAreNotMeasured(string $duration): void
    {
        $emitter = $this->emitter();

        $emitter->send('smart_pix_order_creation', 'pending', $duration, ['surface' => 'classic']);
        $emitter->send(SmartPixMetricPayload::STATUS_OBSERVED, 'approved', $duration, ['source' => 'poll']);

        $this->assertCount(2, $this->events);
        $this->assertSame([], $this->distributions);
    }

    public static function unparsableDurationProvider(): array
    {
        return [
            'unknown' => ['unknown'], 'empty' => [''], 'without unit' => ['42'], 'negative' => ['-1s'],
            'too precise' => ['0.4321s'], 'exponent' => ['1e3s'], 'surrounding space' => [' 42s'],
            'trailing newline' => ["42s\n"],
        ];
    }

    public function testTerminalOutcomeInTheCreationSecondIsMeasuredAsZero(): void
    {
        $this->emitter()->send(SmartPixMetricPayload::STATUS_OBSERVED, 'rejected', '0s', ['source' => 'notification']);
        $this->assertSame(SmartPixMetricSchema::PAYMENT_REJECTION_TIME, $this->distributions[0][0]);
        $this->assertSame(0.0, $this->distributions[0][1]);
    }

    public function testEventTransportFailureIsSwallowedAndSkipsTheDistribution(): void
    {
        $this->datadog->shouldReceive('sendEvent')->once()->andThrow(new \Error('SYNTHETIC_PRIVATE_TRANSPORT'));
        $this->sink->shouldNotReceive('distribution');

        $this->emitter()->send(SmartPixMetricPayload::STATUS_OBSERVED, 'approved', '42s', ['source' => 'poll']);

        $this->assertSame([], $this->distributions);
    }

    public function testDistributionTransportFailureIsSwallowedAfterTheEvent(): void
    {
        $this->sink->shouldReceive('distribution')->once()->andThrow(new \RuntimeException('SYNTHETIC_PRIVATE_TRANSPORT'));

        $this->emitter()->send('smart_pix_order_creation', 'pending', '0.432s', ['surface' => 'classic']);

        $this->assertCount(1, $this->events);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider storeModeEnvironmentProvider
     */
    public function testFromPluginTagsEventsAndDistributionsWithTheStoreIdentity(bool $testMode, string $environment): void
    {
        WP_Mock::setUp();
        $seller = Mockery::mock();
        $seller->shouldReceive('getSiteId')->andReturn('MLB');
        $seller->shouldReceive('getCustIdFromAT')->andReturn('123456');
        $store = Mockery::mock(\MercadoPago\Woocommerce\Configs\Store::class);
        $store->shouldReceive('isTestMode')->andReturn($testMode);
        WP_Mock::userFunction('site_url', ['return' => 'https://store.example.test']);
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        $posted = [];
        WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function (string $url, array $arguments) use (&$posted): array {
            $posted[] = [$url, json_decode($arguments['body'], true, 8, JSON_THROW_ON_ERROR)];
            return [];
        });
        $plugin = (object) ['sellerConfig' => $seller, 'storeConfig' => $store, 'woocommerce' => (object) ['version' => '9.8.1']];

        SmartPixEventMetrics::fromPlugin($plugin, $this->datadog)
            ->send(SmartPixMetricPayload::STATUS_OBSERVED, 'approved', '42s', ['source' => 'notification']);

        // Orders keeps the production scope; only the tag marks a test-mode store as a test environment.
        $this->assertSame(['site_id' => 'MLB', 'environment' => $environment, 'cust_id' => '123456'], array_intersect_key(
            $this->events[0][4],
            self::IDENTITY
        ));
        $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/metric/datadog/smb/smart_pix_payment_approval_time', $posted[0][0]);
        $this->assertSame(42, $posted[0][1]['value']);
        $this->assertSame(['name' => 'woocommerce', 'version' => '9.8.1', 'url' => 'https://store.example.test'], $posted[0][1]['platform']);
        $this->assertSame('123456', $posted[0][1]['details']['cust_id']);
        $this->assertSame($environment, $posted[0][1]['details']['environment']);
        WP_Mock::tearDown();
    }

    public static function storeModeEnvironmentProvider(): array
    {
        return ['test mode' => [true, 'homol'], 'production mode' => [false, 'prod']];
    }

    public function testFromPluginWithoutReadableIdentityStillSendsTheEvent(): void
    {
        $seller = Mockery::mock();
        $seller->shouldReceive('getSiteId')->andThrow(new \RuntimeException('SYNTHETIC_PRIVATE_CONFIG'));
        $seller->shouldReceive('getCustIdFromAT')->andThrow(new \RuntimeException('SYNTHETIC_PRIVATE_CONFIG'));
        $store = Mockery::mock(\MercadoPago\Woocommerce\Configs\Store::class);
        $store->shouldReceive('isTestMode')->andReturn(false);
        // An unusable WooCommerce version leaves the distribution sink disabled.
        $plugin = (object) ['sellerConfig' => $seller, 'storeConfig' => $store];

        SmartPixEventMetrics::fromPlugin($plugin, $this->datadog)->send('smart_pix_status_access', 'invalid_nonce', '');

        $this->assertSame([
            'payment_method' => 'smart_transfer', 'event' => 'status_access_denied', 'reason' => 'invalid_nonce',
            'missing_fields' => 'sdk_instance_id',
        ], $this->events[0][4]);
    }

    /** @param array<string, mixed> $identity */
    private function emitter(array $identity = self::IDENTITY): SmartPixEventMetrics
    {
        return new SmartPixEventMetrics($this->datadog, new SmartPixTelemetry($this->sink), $identity);
    }
}
