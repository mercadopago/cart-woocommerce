<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WC_Order;
use WP_Mock;

class SmartPixCreationStoreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_cache_delete', ['return' => true]);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    public function testAnAdmissionCannotBeRepeatedOrOverwrittenByADifferentAttempt(): void
    {
        $rows = [];
        $store = new SmartPixCreationStore($this->database($rows));
        $order = $this->order();
        $this->assertTrue($store->begin($order, 'ATTEMPT'));
        $this->assertFalse($store->begin($order, 'ATTEMPT'));
        $this->assertTrue(SmartPixCreationStore::hasStarted($order));
        $this->expectException(RuntimeException::class);
        $store->begin($order, 'OTHER_ATTEMPT');
    }

    public function testTheAdmissionTimeIsWrittenOnceAndNeverMovedLater(): void
    {
        $rows = [];
        $store = new SmartPixCreationStore($this->database($rows));
        $order = $this->order();
        $before = time();
        $this->assertNull(SmartPixCreationStore::startedAt($order));
        $this->assertTrue($store->begin($order, 'ATTEMPT'));
        $startedAt = SmartPixCreationStore::startedAt($order);
        $this->assertNotNull($startedAt);
        $this->assertGreaterThanOrEqual($before, $startedAt);
        $this->assertLessThanOrEqual(time(), $startedAt);
        $order->update_meta_data(SmartPixCreationStore::STARTED_AT_META_KEY, '1700000000');
        $this->assertFalse($store->begin($order, 'ATTEMPT'));
        $this->assertSame(1700000000, SmartPixCreationStore::startedAt($order));
    }

    /**
     * @dataProvider invalidStartedAtProvider
     * @param mixed $value
     */
    public function testAnUnreadableAdmissionTimeIsAbsent($value): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::STARTED_AT_META_KEY, true, 'edit')->andReturn($value);
        $this->assertNull(SmartPixCreationStore::startedAt($order));
    }

    public static function invalidStartedAtProvider(): array
    {
        return [[''], [null], [1700000000], ['0'], ['-1'], ['17e8'], ['1700000000.5'], [str_repeat('9', 20)]];
    }

    public function testAKnownFailureIsBoundToAdmissionAndNeverReleasesIt(): void
    {
        $rows = [];
        $store = new SmartPixCreationStore($this->database($rows));
        $order = $this->order();
        $this->assertTrue($store->begin($order, 'ATTEMPT'));
        $store->recordFailure($order, 'validation');
        $this->assertSame('validation', SmartPixCreationStore::failureCategory($order));
        $this->assertFalse($store->begin($order, 'ATTEMPT'));
        $order->update_meta_data(SmartPixCreationStore::META_KEY, str_repeat('b', 64));
        $this->assertNull(SmartPixCreationStore::failureCategory($order));
    }

    public function testAFailedFailureWriteDoesNotClaimAKnownOutcome(): void
    {
        $rows = [];
        $store = new SmartPixCreationStore($this->database($rows));
        $order = $this->order(true, false);
        $store->begin($order, 'ATTEMPT');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('creation_failure_persistence_failed');
        $store->recordFailure($order, 'validation');
    }

    /** @dataProvider invalidFailureProvider */
    public function testUnboundOrUnvalidatedFailuresDoNotChangeRecovery($failure): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::META_KEY, true, 'edit')->andReturn(str_repeat('a', 64));
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::FAILURE_META_KEY, true, 'edit')->andReturn($failure);
        $this->assertNull(SmartPixCreationStore::failureCategory($order));
    }

    public static function invalidFailureProvider(): array
    {
        return [[''], [null], [[]], [true], [str_repeat('a', 64) . ':unknown'],
            [str_repeat('b', 64) . ':validation'], ['validation']];
    }

    public function testAFailedMetadataWriteDoesNotFreeTheFinancialAdmission(): void
    {
        $rows = [];
        $store = new SmartPixCreationStore($this->database($rows));
        $order = $this->order(false);
        try {
            $store->begin($order, 'ATTEMPT');
            $this->fail('The marker must be verified before HTTP.');
        } catch (RuntimeException $failure) {
            $this->assertSame('creation_persistence_failed', $failure->getMessage());
        }
        $reloaded = $this->order();
        $this->assertFalse($store->begin($reloaded, 'ATTEMPT'));
        $this->assertTrue(SmartPixCreationStore::hasStarted($reloaded));
    }

    public function testDatabaseFailuresFailClosedBeforeAnyDispatch(): void
    {
        $database = Mockery::mock('wpdb');
        $database->options = 'shop_options';
        $database->last_error = '';
        $database->shouldReceive('suppress_errors')->with(true)->once()->andReturn(false);
        $database->shouldReceive('suppress_errors')->with(false)->once();
        $database->shouldReceive('prepare')->once()->andReturn('SAFE_PREPARED_INSERT');
        $database->shouldReceive('query')->once()->andReturn(false);
        $order = $this->order();
        $this->expectException(RuntimeException::class);
        (new SmartPixCreationStore($database))->begin($order, 'ATTEMPT');
    }

    /** @dataProvider invalidMarkerProvider */
    public function testInvalidOrMissingMarkersDoNotClaimARequestBegan($marker): void
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_meta')->with(SmartPixCreationStore::META_KEY, true, 'edit')->andReturn($marker);
        $this->assertFalse(SmartPixCreationStore::hasStarted($order));
    }

    public static function invalidMarkerProvider(): array
    {
        return [[''], [null], [[]], ['yes'], [str_repeat('g', 64)]];
    }

    public function testAChangedSiteOrAttemptCannotReuseTheStore(): void
    {
        $rows = [];
        $database = $this->database($rows);
        $store = new SmartPixCreationStore($database);
        $database->options = 'another_site_options';
        $this->expectException(RuntimeException::class);
        $store->begin($this->order(), 'ATTEMPT');
    }

    public function testAnUnsafeOptionsTableNameIsRefusedBeforeAnyQuery(): void
    {
        $database = Mockery::mock('wpdb');
        $database->options = 'shop_options` WHERE 1=1; --';
        $database->shouldNotReceive('prepare', 'query', 'get_var');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('creation_store_invalid');
        new SmartPixCreationStore($database);
    }

    /** @dataProvider invalidAdmissionContextProvider */
    public function testAnEmptyAttemptOrUnsavedOrderIsNeverAdmitted(string $attemptKey, int $orderId): void
    {
        $rows = [];
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn($orderId);
        $order->shouldNotReceive('update_meta_data', 'save_meta_data');
        $store = new SmartPixCreationStore($this->database($rows));
        try {
            $store->begin($order, $attemptKey);
            $this->fail('Expected the admission to be refused.');
        } catch (RuntimeException $failure) {
            $this->assertSame('creation_context_invalid', $failure->getMessage());
        }
        $this->assertSame([], $rows);
    }

    public static function invalidAdmissionContextProvider(): array
    {
        return ['empty attempt' => ['', 321], 'unsaved order' => ['ATTEMPT', 0]];
    }

    public function testAnUnknownFailureCategoryIsNotRecorded(): void
    {
        $rows = [];
        $store = new SmartPixCreationStore($this->database($rows));
        $order = $this->order();
        $store->begin($order, 'ATTEMPT');
        try {
            $store->recordFailure($order, 'rejected');
            $this->fail('Expected the unknown category to be refused.');
        } catch (RuntimeException $failure) {
            $this->assertSame('creation_failure_invalid', $failure->getMessage());
        }
        $this->assertNull(SmartPixCreationStore::failureCategory($order));
    }

    /** SQL is a boundary fake; this does not claim live datastore concurrency evidence. */
    private function database(array &$rows)
    {
        $database = Mockery::mock('wpdb');
        $database->options = 'shop_options';
        $database->last_error = '';
        $database->shouldReceive('suppress_errors')->andReturn(false);
        $database->shouldReceive('prepare')->andReturnUsing(function (string $sql, ...$args): string {
            $this->assertStringContainsString('`shop_options`', $sql);
            $this->assertStringContainsString('%s', $sql);
            return json_encode([$sql, $args]);
        });
        $database->shouldReceive('query')->andReturnUsing(function (string $prepared) use (&$rows): int {
            [$sql, $args] = json_decode($prepared, true);
            $this->assertStringStartsWith('INSERT IGNORE', $sql);
            $this->assertSame('no', $args[2]);
            if (isset($rows[$args[0]])) {
                return 0;
            }
            $rows[$args[0]] = $args[1];
            return 1;
        });
        $database->shouldReceive('get_var')->andReturnUsing(static function (string $prepared) use (&$rows) {
            [, $args] = json_decode($prepared, true);
            return $rows[$args[0]] ?? null;
        });
        return $database;
    }

    private function order(bool $persist = true, bool $persistFailure = true)
    {
        $data = ['_mp_idempotency_key' => 'ATTEMPT'];
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_meta')->andReturnUsing(static function (string $key) use (&$data) {
            return $data[$key] ?? '';
        });
        $order->shouldReceive('update_meta_data')->andReturnUsing(static function (string $key, string $value) use (&$data, $persist, $persistFailure): void {
            if ($persist && ($persistFailure || $key !== SmartPixCreationStore::FAILURE_META_KEY)) {
                $data[$key] = $value;
            }
        });
        $order->shouldReceive('save_meta_data');
        return $order;
    }
}
