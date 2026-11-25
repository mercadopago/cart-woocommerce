<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Order\OrderStatus;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CoreNotificationBodyReadTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const LIMIT = 16384;
    private const WRAPPER = 'corenotificationbodytest';

    private array $metrics = [];
    private array $responses = [];
    private bool $telemetryFails = false;
    private int $metricAttempts = 0;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        WP_Mock::userFunction('site_url', ['return' => 'https://synthetic-store.invalid']);
        WP_Mock::userFunction('wp_json_encode', ['return' => static function ($payload) {
            return json_encode($payload);
        }]);
        WP_Mock::userFunction('wp_remote_post', ['return' => function ($url, $options) {
            $this->metricAttempts++;
            if ($this->telemetryFails) {
                throw new \Error('SYNTHETIC_TELEMETRY_SECRET');
            }
            $this->metrics[] = [$url, json_decode($options['body'], true)];
            return [];
        }]);
        WP_Mock::userFunction('wp_send_json', ['return' => function ($response, $status): void {
            $this->responses[] = [$response, $status];
        }]);
    }

    protected function tearDown(): void
    {
        if (in_array(self::WRAPPER, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::WRAPPER);
        }
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public static function admittedBodyProvider(): array
    {
        $cases = [];
        foreach (['P-123', 'M-456', 'PPORDSYNTHETIC001'] as $id) {
            foreach ([self::LIMIT - 1, self::LIMIT] as $size) {
                $string = json_encode($id);
                $cases[$id . ' string ' . $size] = [$string . str_repeat(' ', $size - strlen($string)), $id];
                $object = json_encode(['padding' => '', 'notification_id' => $id]);
                $body = str_replace('"padding":""', '"padding":"' . str_repeat('x', $size - strlen($object)) . '"', $object);
                $cases[$id . ' object id at end ' . $size] = [$body, $id];
            }
        }
        return $cases;
    }

    /** @dataProvider admittedBodyProvider */
    public function testPublicExtractorReadsRealStreamsAndPreservesAdmittedFormats(string $body, string $id): void
    {
        $stream = $this->memoryStream($body);
        $notification = $this->notification();
        $notification->shouldReceive('openInputStream')->once()->andReturn($stream);

        $this->assertSame($id, $notification->getNotificationId());
        $this->assertFalse(is_resource($stream));
        $this->assertSame([], $this->responses);
        $this->assertSame([], $this->metrics);
    }

    public static function oversizedBodyProvider(): array
    {
        $cases = [];
        foreach (['P-123', 'M-456', 'PPORDSYNTHETIC001'] as $id) {
            foreach ([false, true] as $idAtEnd) {
                foreach ([self::LIMIT + 1, 4 * self::LIMIT] as $size) {
                    $data = $idAtEnd ? ['padding' => '', 'notification_id' => $id] : ['notification_id' => $id, 'padding' => ''];
                    $json = json_encode($data);
                    $body = str_replace('"padding":""', '"padding":"' . str_repeat('x', $size - strlen($json)) . '"', $json);
                    $cases[$id . ($idAtEnd ? ' late ' : ' early ') . $size] = [$body];
                }
            }
            $string = json_encode($id);
            $cases[$id . ' Bifrost string'] = [$string . str_repeat(' ', self::LIMIT + 1 - strlen($string))];
        }
        $cases['malformed JSON'] = [str_repeat('{', 4 * self::LIMIT)];
        return $cases;
    }

    /** @dataProvider oversizedBodyProvider */
    public function testEndpointRefusesRealOversizedStreamsBeforeAnyFetchOrOrderEffect(string $body): void
    {
        $stream = $this->memoryStream($body);
        $notification = $this->notification();
        $notification->shouldReceive('openInputStream')->once()->andReturn($stream);

        $notification->handleReceivedNotification([]);

        $this->assertFalse(is_resource($stream));
        $this->assertSizeRejection();
    }

    /**
     * @testWith [null]
     *           ["1"]
     *           ["0"]
     */
    public function testActualSizeWinsOverAbsentOrUnderstatedContentLength(?string $declared): void
    {
        $original = $_SERVER['CONTENT_LENGTH'] ?? null;
        try {
            if ($declared === null) {
                unset($_SERVER['CONTENT_LENGTH']);
            } else {
                $_SERVER['CONTENT_LENGTH'] = $declared;
            }
            $notification = $this->notification();
            $notification->shouldReceive('openInputStream')->once()
                ->andReturn($this->memoryStream(str_repeat('x', 4 * self::LIMIT)));

            $notification->handleReceivedNotification([]);

            $this->assertSizeRejection();
        } finally {
            if ($original === null) {
                unset($_SERVER['CONTENT_LENGTH']);
            } else {
                $_SERVER['CONTENT_LENGTH'] = $original;
            }
        }
    }

    public function testExtractorRefusesOversizedBodiesWithoutSendingResponsesOrMetrics(): void
    {
        $stream = $this->memoryStream('"P-123"' . str_repeat(' ', self::LIMIT));
        $notification = $this->notification();
        $notification->shouldReceive('openInputStream')->once()->andReturn($stream);

        $this->assertNull($notification->getNotificationId());
        $this->assertFalse(is_resource($stream));
        $this->assertSame([], $this->metrics);
        $this->assertSame([], $this->responses);
    }

    public static function readBudgetProvider(): array
    {
        return [[null, self::LIMIT + 1], [PHP_INT_MAX, self::LIMIT + 1], [17, 17], [0, 0], [-1, 0]];
    }

    /** @dataProvider readBudgetProvider */
    public function testRealReaderCapsOptionalBudgetsAndNeverTreatsMinusOneAsUnlimited(?int $requested, int $expected): void
    {
        $notification = $this->notification();
        $stream = null;
        if ($expected > 0) {
            $stream = $this->memoryStream(str_repeat('x', 4 * self::LIMIT));
            $notification->shouldReceive('openInputStream')->once()->andReturn($stream);
        } else {
            $notification->shouldNotReceive('openInputStream');
        }
        $reader = new \ReflectionMethod(CoreNotification::class, 'getInput');
        $reader->setAccessible(true);

        $this->assertSame(str_repeat('x', $expected), $reader->invoke($notification, $requested));
        $this->assertFalse(is_resource($stream));
    }

    public function testLargeGeneratedStreamIsNotDrainedAndIsClosed(): void
    {
        CoreNotificationGeneratedStream::$consumed = 0;
        CoreNotificationGeneratedStream::$closed = 0;
        CoreNotificationGeneratedStream::$throwOnRead = false;
        $this->assertTrue(stream_wrapper_register(self::WRAPPER, CoreNotificationGeneratedStream::class));
        $stream = fopen(self::WRAPPER . '://body', 'rb');
        $notification = $this->notification();
        $notification->shouldReceive('openInputStream')->once()->andReturn($stream);

        $notification->handleReceivedNotification([]);

        $this->assertSizeRejection();
        // PHP may prefetch whole 8192-byte chunks for a user wrapper, beyond the returned string.
        $prefetchBound = (int) ceil((self::LIMIT + 1) / 8192) * 8192;
        $this->assertGreaterThanOrEqual(self::LIMIT + 1, CoreNotificationGeneratedStream::$consumed);
        $this->assertLessThanOrEqual($prefetchBound, CoreNotificationGeneratedStream::$consumed);
        $this->assertSame(1, CoreNotificationGeneratedStream::$closed);
    }

    public function testStreamIsClosedEvenWhenTheReadThrows(): void
    {
        CoreNotificationGeneratedStream::$consumed = 0;
        CoreNotificationGeneratedStream::$closed = 0;
        CoreNotificationGeneratedStream::$throwOnRead = true;
        $this->assertTrue(stream_wrapper_register(self::WRAPPER, CoreNotificationGeneratedStream::class));
        $stream = fopen(self::WRAPPER . '://body', 'rb');
        $notification = $this->notification();
        $notification->shouldReceive('openInputStream')->once()->andReturn($stream);

        try {
            $notification->getNotificationId();
            $this->fail('The native stream failure should propagate.');
        } catch (\RuntimeException $failure) {
            $this->assertSame('Synthetic read failure', $failure->getMessage());
        }
        $this->assertFalse(is_resource($stream));
        $this->assertSame(1, CoreNotificationGeneratedStream::$closed);
    }

    public function testFailedStreamOpenKeepsTheEmptyInputResult(): void
    {
        $notification = $this->notification();
        $notification->shouldReceive('openInputStream')->once()->andReturn(false);

        $this->assertNull($notification->getNotificationId());
        $this->assertSame([], $this->responses);
    }

    public function testNativeEmptyInputHasNoIdResponseOrMetric(): void
    {
        $notification = $this->notification();

        $this->assertNull($notification->getNotificationId());
        $this->assertSame([], $this->responses);
        $this->assertSame([], $this->metrics);
        $this->assertSame(0, $this->metricAttempts);
    }

    public function testTelemetryErrorStillProducesTheSameRejectionWithoutSensitiveContent(): void
    {
        $this->telemetryFails = true;
        $body = '{"notification_id":"P-123","private":"SYNTHETIC_BODY_SECRET' . str_repeat('x', self::LIMIT) . '"}';
        $notification = $this->notification();
        $notification->shouldReceive('openInputStream')->once()->andReturn($this->memoryStream($body));

        $notification->handleReceivedNotification([]);

        $this->assertSame([[['status' => 413, 'message' => 'Notification body too large.'], 413]], $this->responses);
        $this->assertSame([], $this->metrics);
        $this->assertSame(1, $this->metricAttempts);
    }

    private function assertSizeRejection(): void
    {
        $this->assertSame([[['status' => 413, 'message' => 'Notification body too large.'], 413]], $this->responses);
        $this->assertCount(1, $this->metrics);
        $this->assertSame(1, $this->metricAttempts);
        [$url, $payload] = $this->metrics[0];
        $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/core_notification_rejected', $url);
        $this->assertSame('body_too_large', $payload['value']);
        $this->assertSame(['team' => 'big', 'reason' => 'body_too_large'], $payload['details']);
        $this->assertSame(['value', 'plugin_version', 'platform', 'details'], array_keys($payload));
        $this->assertSame('https://synthetic-store.invalid', $payload['platform']['url']);
    }

    /** @return resource */
    private function memoryStream(string $body)
    {
        $stream = fopen('php://memory', 'w+b');
        fwrite($stream, $body);
        rewind($stream);
        return $stream;
    }

    private function notification()
    {
        $logs = Mockery::mock(Logs::class);
        $logs->file = Mockery::mock(File::class);
        $logs->file->shouldReceive('info')->andReturnNull();
        $logs->file->shouldNotReceive('error');
        $client = Mockery::mock(NotificationsClient::class);
        $client->shouldReceive('find')->never()->andThrow(new \Error('Unexpected Core notification lookup'));
        $orderStatus = Mockery::mock(OrderStatus::class);
        $orderStatus->shouldNotReceive('processStatus');

        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('SYNTHETIC_ACCESS_TOKEN');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getIntegratorId')->andReturn('');

        // Only the stream opener and external dependencies are replaced; reader/handler/emitter are real.
        return Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            $orderStatus,
            $seller,
            $store,
            $client,
        ])->makePartial()->shouldAllowMockingProtectedMethods();
    }
}

/** Generates a logical 1 GiB body without allocating it, to detect accidental stream draining. */
class CoreNotificationGeneratedStream
{
    public $context;
    public static int $consumed = 0;
    public static int $closed = 0;
    public static bool $throwOnRead = false;

    public function stream_open($path, $mode, $options, &$openedPath): bool
    {
        return true;
    }

    public function stream_read($count)
    {
        if (self::$throwOnRead) {
            throw new \RuntimeException('Synthetic read failure');
        }
        $length = min($count, 1073741824 - self::$consumed);
        self::$consumed += $length;
        return str_repeat('x', $length);
    }

    public function stream_eof(): bool
    {
        return self::$consumed >= 1073741824;
    }

    public function stream_stat(): array
    {
        return [];
    }

    public function stream_close(): void
    {
        self::$closed++;
    }
}
