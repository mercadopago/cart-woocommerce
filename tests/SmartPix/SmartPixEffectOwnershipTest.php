<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixEffectOwnership;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WC_Order;
use WP_Mock;

class SmartPixEffectOwnershipTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testACompletedApprovalCanAdmitOneSubsequentRefundButCannotBeReplayedAfterIt(): void
    {
        $raw = null;
        $failed = false;
        $ownership = new SmartPixEffectOwnership($this->database($raw, $failed));
        $key = 'SYNTHETIC_ATTEMPT';
        $approval = $ownership->acquire(321, $key, 'complete_payment');
        $this->assertTrue($ownership->start(321, $key, 'complete_payment', $approval['owner']));
        $this->assertSame('conflict', $ownership->acquire(321, $key, 'record_full_refund')['outcome']);
        $this->assertTrue($ownership->complete(321, $key, 'complete_payment', $approval['owner']));
        $refund = $ownership->acquire(321, $key, 'record_full_refund');
        $this->assertSame('acquired', $refund['outcome']);
        $this->assertSame('busy', $ownership->acquire(321, $key, 'record_full_refund')['outcome']);
        $this->assertTrue($ownership->start(321, $key, 'record_full_refund', $refund['owner']));
        $this->assertTrue($ownership->complete(321, $key, 'record_full_refund', $refund['owner']));
        $this->assertSame('completed', $ownership->acquire(321, $key, 'record_full_refund')['outcome']);
        $this->assertSame('conflict', $ownership->acquire(321, $key, 'complete_payment')['outcome']);
        $this->assertSame('conflict', $ownership->acquire(321, 'OTHER_ATTEMPT', 'record_full_refund')['outcome']);
    }

    public function testConcurrentRefundAdmissionCannotOverwriteTheWinningOwner(): void
    {
        $raw = null;
        $failed = false;
        $race = false;
        $database = $this->database($raw, $failed, false, static function ($sql, $args, &$stored) use (&$race): void {
            if ($race && strpos($sql, 'UPDATE ') === 0) {
                $winner = json_decode($stored, true);
                $winner['effect'] = 'record_full_refund';
                $winner['state'] = 'reserved';
                $winner['owner'] = str_repeat('b', 64);
                $stored = json_encode($winner);
                $race = false;
            }
        });
        $ownership = new SmartPixEffectOwnership($database);
        $approval = $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');
        $this->assertTrue($ownership->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $approval['owner']));
        $this->assertTrue($ownership->complete(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $approval['owner']));
        $race = true;
        $this->assertSame('busy', $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'record_full_refund')['outcome']);
        $this->assertSame(str_repeat('b', 64), json_decode($raw, true)['owner']);
    }


    public function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    public function testSuccessfulOwnerRecordsOneVerifiedCompletionAndRecognizesReplay(): void
    {
        // The SQL transport is mocked. This is not real-database or concurrency evidence.
        $database = Mockery::mock('wpdb');
        $database->options = 'shop_options';
        $database->last_error = '';
        $name = '_mp_smart_pix_effect_owner_321';
        $attempt = 'SYNTHETIC_ATTEMPT_001';
        $records = [];
        $stored = null;
        $insert = 'INSERT INTO `shop_options` (option_name, option_value, autoload) VALUES (%s, %s, %s)'
            . ' ON DUPLICATE KEY UPDATE option_name = option_name';
        $update = 'UPDATE `shop_options` SET option_value = %s, autoload = %s'
            . ' WHERE option_name = %s AND BINARY option_value = BINARY %s';

        $database->shouldReceive('suppress_errors')->with(true)->times(10)->andReturn(false);
        $database->shouldReceive('suppress_errors')->with(false)->times(10)->andReturn(true);
        $database->shouldReceive('prepare')->withArgs(function ($sql, ...$args) use ($insert, $name, $attempt, &$records) {
            if ($sql !== $insert) {
                return false;
            }
            $this->assertSame($name, $args[0]);
            $this->assertSame('no', $args[2]);
            $record = json_decode($args[1], true, 4, JSON_THROW_ON_ERROR);
            $this->assertSame(2, $record['version']);
            $this->assertIsInt($record['reserved_until']);
            $this->assertSame(hash('sha256', $attempt), $record['attempt_hash']);
            $this->assertSame('complete_payment', $record['effect']);
            $this->assertSame('reserved', $record['state']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $record['owner']);
            $this->assertStringNotContainsString($attempt, $args[1]);
            $records[] = $args[1];
            return true;
        })->twice()->andReturn('prepared_insert');
        $database->shouldReceive('query')->with('prepared_insert')->twice()
            ->andReturnUsing(function () use (&$records, &$stored) {
                if ($stored !== null) {
                    return 0;
                }
                $stored = $records[0];
                return 1;
            });
        $database->shouldReceive('prepare')
            ->with('SELECT option_value FROM `shop_options` WHERE option_name = %s', $name)
            ->times(6)->andReturn('prepared_read');
        $database->shouldReceive('get_var')->with('prepared_read')->times(6)
            ->andReturnUsing(function () use (&$stored) {
                return $stored;
            });
        $database->shouldReceive('prepare')->withArgs(function ($sql, ...$args) use ($update, $name, &$records, &$stored) {
            if ($sql !== $update) {
                return false;
            }
            $this->assertSame('no', $args[1]);
            $this->assertSame($name, $args[2]);
            $this->assertSame($stored, $args[3]);
            $before = json_decode($args[3], true, 4, JSON_THROW_ON_ERROR);
            $after = json_decode($args[0], true, 4, JSON_THROW_ON_ERROR);
            $this->assertSame($before['state'] === 'reserved' ? 'executing' : 'completed', $after['state']);
            $after['state'] = $before['state'];
            $this->assertSame($before, $after);
            $records[] = $args[0];
            return true;
        })->twice()->andReturn('prepared_update');
        $database->shouldReceive('query')->with('prepared_update')->twice()
            ->andReturnUsing(function () use (&$records, &$stored) {
                $stored = end($records);
                return 1;
            });
        foreach ([$name, 'notoptions', 'alloptions'] as $cacheKey) {
            WP_Mock::userFunction('wp_cache_delete', ['args' => [$cacheKey, 'options'], 'times' => 3, 'return' => true]);
        }

        $ownership = new SmartPixEffectOwnership($database);
        $claim = $ownership->acquire(321, $attempt, 'complete_payment');
        $this->assertSame('acquired', $claim['outcome']);
        $this->assertIsString($claim['owner']);
        $this->assertTrue($ownership->start(321, $attempt, 'complete_payment', $claim['owner']));
        $this->assertSame('executing', json_decode($stored, true, 4, JSON_THROW_ON_ERROR)['state']);

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('payment_complete')->withNoArgs()->once()->andReturn(true);
        $order->shouldReceive('is_paid')->once()->andReturn(true);
        $this->assertTrue($order->payment_complete());
        $this->assertTrue($order->is_paid());
        $this->assertTrue($ownership->complete(321, $attempt, 'complete_payment', $claim['owner']));
        $completed = $stored;

        $replay = $ownership->acquire(321, $attempt, 'complete_payment');
        if ($replay['outcome'] === 'acquired') {
            $order->payment_complete();
        }
        $this->assertSame(['outcome' => 'completed', 'owner' => null], $replay);
        $this->assertSame($completed, $stored);
        $this->assertSame('completed', json_decode($stored, true, 4, JSON_THROW_ON_ERROR)['state']);
    }

    /** @dataProvider duplicateCountProvider */
    public function testCompetingOwnerCannotReplaceTheReservation(bool $foundRows): void
    {
        $raw = null;
        $failed = false;
        $database = $this->database($raw, $failed, $foundRows);
        $first = new SmartPixEffectOwnership($database);
        $second = new SmartPixEffectOwnership($database);
        $claim = $first->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');
        $reserved = $raw;
        $this->assertSame(
            ['outcome' => 'busy', 'owner' => null],
            $second->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
        $this->assertSame($reserved, $raw);
        $this->assertFalse($second->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', str_repeat('b', 64)));
        $this->assertSame($reserved, $raw);
        $this->assertTrue($first->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertFalse($first->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertFalse($first->releaseReserved(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
    }

    public static function duplicateCountProvider(): array
    {
        return [[false], [true]];
    }

    public function testExpiredUnstartedReservationIsTakenOverByTheSameAttemptAndEffect(): void
    {
        $raw = null;
        $failed = false;
        $now = 1000;
        $clock = static function () use (&$now): int {
            return $now;
        };
        $database = $this->database($raw, $failed);
        $crashed = new SmartPixEffectOwnership($database, $clock);
        $recovering = new SmartPixEffectOwnership($database, $clock);
        $stale = $crashed->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');
        $this->assertSame('acquired', $stale['outcome']);

        $now += 300;
        $claim = $recovering->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');

        $this->assertSame('acquired', $claim['outcome']);
        $this->assertNotSame($stale['owner'], $claim['owner']);
        $record = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
        $this->assertSame(['reserved', 1600], [$record['state'], $record['reserved_until']]);
        $this->assertFalse($crashed->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $stale['owner']));
        $this->assertTrue($recovering->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
    }

    public function testStartedExecutionCanBecomeUncertainButCannotBeReleasedOrRepeated(): void
    {
        $raw = null;
        $failed = false;
        $ownership = new SmartPixEffectOwnership($this->database($raw, $failed));
        $claim = $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');
        $this->assertTrue($ownership->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertTrue($ownership->markUncertain(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertFalse($ownership->releaseReserved(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertFalse($ownership->complete(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertSame(
            ['outcome' => 'uncertain', 'owner' => null],
            $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
    }

    public function testOnlyAnUnstartedMatchingReservationCanBeReleased(): void
    {
        $raw = null;
        $failed = false;
        $ownership = new SmartPixEffectOwnership($this->database($raw, $failed));
        $claim = $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'mark_failed');
        $this->assertFalse($ownership->releaseReserved(321, 'OTHER_ATTEMPT', 'mark_failed', $claim['owner']));
        $this->assertFalse($ownership->releaseReserved(321, 'SYNTHETIC_ATTEMPT', 'mark_failed', str_repeat('b', 64)));
        $this->assertTrue($ownership->releaseReserved(321, 'SYNTHETIC_ATTEMPT', 'mark_failed', $claim['owner']));
        $this->assertNull($raw);
        $this->assertSame('acquired', $ownership->acquire(321, 'OTHER_ATTEMPT', 'mark_failed')['outcome']);
    }

    public function testDifferentAttemptOrEffectCannotBypassCompletedOrderSlot(): void
    {
        $raw = null;
        $failed = false;
        $ownership = new SmartPixEffectOwnership($this->database($raw, $failed));
        $claim = $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');
        $ownership->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']);
        $this->assertTrue($ownership->complete(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $completed = $raw;
        $this->assertSame('conflict', $ownership->acquire(321, 'OTHER_ATTEMPT', 'complete_payment')['outcome']);
        $this->assertSame('conflict', $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'mark_cancelled')['outcome']);
        $this->assertSame($completed, $raw);
    }

    public function testMalformedStorageAndDatabaseFailureNeverGrantOwnership(): void
    {
        $raw = '{"owner":"SYNTHETIC_PRIVATE_OWNER"}';
        $failed = false;
        $ownership = new SmartPixEffectOwnership($this->database($raw, $failed));
        $this->assertSame(
            ['outcome' => 'unavailable', 'owner' => null],
            $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
        $raw = null;
        $failed = true;
        $this->assertSame(
            ['outcome' => 'unavailable', 'owner' => null],
            $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
        $this->assertNull($raw);
    }

    public function testChangedBlogContextCannotWriteToThePreviouslyBoundTable(): void
    {
        $raw = null;
        $failed = false;
        $database = $this->database($raw, $failed);
        $ownership = new SmartPixEffectOwnership($database);
        $claim = $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');
        $reserved = $raw;
        $database->options = 'other_site_options';
        $this->assertSame('unavailable', $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')['outcome']);
        $this->assertFalse($ownership->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertFalse($ownership->releaseReserved(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertSame($reserved, $raw);
    }

    public function testInsertWhoseVerificationFailsDoesNotGrantOwnershipOrAllowReplacement(): void
    {
        $raw = null;
        $failed = false;
        $inject = true;
        $beforeRead = static function () use (&$failed, &$inject): void {
            if ($inject) {
                $failed = true;
            }
        };
        $ownership = new SmartPixEffectOwnership($this->database($raw, $failed, false, null, $beforeRead));
        $this->assertSame(
            ['outcome' => 'unavailable', 'owner' => null],
            $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
        $this->assertIsString($raw);
        $reserved = $raw;
        $inject = false;
        $failed = false;
        $this->assertSame(
            ['outcome' => 'busy', 'owner' => null],
            $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
        $this->assertSame($reserved, $raw);
    }

    /** @dataProvider unverifiedTransitionProvider */
    public function testPersistedTransitionWithoutFreshVerificationDoesNotGrantAdmission(
        string $transition,
        string $expectedState,
        string $replayOutcome
    ): void {
        $raw = null;
        $failed = false;
        $inject = false;
        $reads = 0;
        $beforeRead = static function () use (&$failed, &$inject, &$reads): void {
            if ($inject && ++$reads === 2) {
                $failed = true;
            }
        };
        $ownership = new SmartPixEffectOwnership($this->database($raw, $failed, false, null, $beforeRead));
        $claim = $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');
        if ($transition === 'complete') {
            $this->assertTrue($ownership->start(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        }
        $inject = true;
        $this->assertFalse($ownership->$transition(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertSame($expectedState, json_decode($raw, true, 4, JSON_THROW_ON_ERROR)['state']);
        $persisted = $raw;
        $inject = false;
        $failed = false;
        $this->assertFalse($ownership->releaseReserved(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertSame(
            ['outcome' => $replayOutcome, 'owner' => null],
            $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
        $this->assertSame($persisted, $raw);
    }

    public static function unverifiedTransitionProvider(): array
    {
        return [
            'execution already persisted' => ['start', 'executing', 'busy'],
            'completion already persisted' => ['complete', 'completed', 'completed'],
        ];
    }

    /** @dataProvider conditionalMutationProvider */
    public function testChangedRecordBetweenReadAndConditionalMutationIsPreserved(string $operation): void
    {
        $raw = null;
        $failed = false;
        $changed = null;
        $beforeQuery = static function ($sql, array $args, ?string &$stored) use ($operation, &$changed): void {
            $verb = $operation === 'start' ? 'UPDATE ' : 'DELETE ';
            if (strpos($sql, $verb) !== 0) {
                return;
            }
            $record = json_decode($stored, true, 4, JSON_THROW_ON_ERROR);
            $record['state'] = $operation === 'start' ? 'uncertain' : 'reserved';
            $record['owner'] = str_repeat('a', 64);
            $stored = json_encode($record, JSON_THROW_ON_ERROR);
            $changed = $stored;
        };
        $ownership = new SmartPixEffectOwnership($this->database($raw, $failed, false, $beforeQuery));
        $claim = $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment');
        $this->assertFalse($ownership->$operation(321, 'SYNTHETIC_ATTEMPT', 'complete_payment', $claim['owner']));
        $this->assertNotNull($changed);
        $this->assertSame($changed, $raw);
        $this->assertSame(
            ['outcome' => $operation === 'start' ? 'uncertain' : 'busy', 'owner' => null],
            $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
        $this->assertSame($changed, $raw);
    }

    public static function conditionalMutationProvider(): array
    {
        return [['start'], ['releaseReserved']];
    }

    /** @dataProvider databaseExceptionProvider */
    public function testDatabaseExceptionRestoresPreviousDiagnosticSettingAndDeniesOwnership(
        string $operation,
        bool $previous
    ): void {
        $database = Mockery::mock('wpdb');
        $database->options = 'shop_options';
        $database->last_error = '';
        $database->shouldReceive('prepare')->andReturn('prepared_query');
        $calls = $operation === 'query' ? 1 : 2;
        if ($previous) {
            $database->shouldReceive('suppress_errors')->with(true)->times(2 * $calls)->andReturn(true);
        } else {
            $database->shouldReceive('suppress_errors')->with(true)->times($calls)->andReturn(false);
            $database->shouldReceive('suppress_errors')->with(false)->times($calls)->andReturn(true);
        }
        $exception = new \RuntimeException('SYNTHETIC_PRIVATE_QUERY_AND_OWNER');
        if ($operation === 'query') {
            $database->shouldReceive('query')->once()->andThrow($exception);
            $database->shouldNotReceive('get_var');
        } else {
            $database->shouldReceive('query')->once()->andReturn(1);
            $database->shouldReceive('get_var')->once()->andThrow($exception);
        }
        WP_Mock::userFunction('wp_cache_delete', ['times' => 0]);
        WP_Mock::userFunction('get_option', ['times' => 0]);
        $ownership = new SmartPixEffectOwnership($database);
        $this->assertSame(
            ['outcome' => 'unavailable', 'owner' => null],
            $ownership->acquire(321, 'SYNTHETIC_ATTEMPT', 'complete_payment')
        );
    }

    public static function databaseExceptionProvider(): array
    {
        return [
            'query with diagnostics initially visible' => ['query', false],
            'query with diagnostics initially suppressed' => ['query', true],
            'read with diagnostics initially visible' => ['read', false],
            'read with diagnostics initially suppressed' => ['read', true],
        ];
    }

    /** Mocked SQL state, not a substitute for independent real-database workers. */
    private function database(
        ?string &$raw,
        bool &$failed,
        bool $foundRows = false,
        ?callable $beforeQuery = null,
        ?callable $beforeRead = null
    ) {
        $database = Mockery::mock('wpdb');
        $database->options = 'shop_options';
        $database->last_error = '';
        $database->shouldReceive('suppress_errors')->andReturn(false);
        $database->shouldReceive('prepare')->andReturnUsing(static function ($sql, ...$args): string {
            return json_encode([$sql, $args], JSON_THROW_ON_ERROR);
        });
        $database->shouldReceive('get_var')->andReturnUsing(function ($prepared) use (&$raw, &$failed, $database, $beforeRead) {
            [$sql] = json_decode($prepared, true, 8, JSON_THROW_ON_ERROR);
            $this->assertStringStartsWith('SELECT option_value FROM `shop_options`', $sql);
            if ($beforeRead !== null) {
                $beforeRead();
            }
            $database->last_error = $failed ? 'SYNTHETIC_DATABASE_FAILURE' : '';
            return $failed ? null : $raw;
        });
        $database->shouldReceive('query')->andReturnUsing(function ($prepared) use (&$raw, &$failed, $database, $foundRows, $beforeQuery) {
            [$sql, $args] = json_decode($prepared, true, 8, JSON_THROW_ON_ERROR);
            if ($beforeQuery !== null) {
                $beforeQuery($sql, $args, $raw);
            }
            $database->last_error = $failed ? 'SYNTHETIC_DATABASE_FAILURE' : '';
            if ($failed) {
                return false;
            }
            if (strpos($sql, 'INSERT ') === 0) {
                $this->assertStringEndsWith('ON DUPLICATE KEY UPDATE option_name = option_name', $sql);
                $this->assertSame('no', $args[2]);
                if ($raw !== null) {
                    return $foundRows ? 1 : 0;
                }
                $raw = $args[1];
                return 1;
            }
            $this->assertStringContainsString('AND BINARY option_value = BINARY %s', $sql);
            if (strpos($sql, 'UPDATE ') === 0) {
                if ($raw !== $args[3]) {
                    return 0;
                }
                $this->assertSame('no', $args[1]);
                $raw = $args[0];
            } elseif (strpos($sql, 'DELETE ') === 0) {
                if ($raw !== $args[1]) {
                    return 0;
                }
                $raw = null;
            } else {
                $this->fail('Unexpected SQL operation.');
            }
            return 1;
        });
        WP_Mock::userFunction('wp_cache_delete', ['return' => true]);
        WP_Mock::userFunction('get_option', ['times' => 0]);
        return $database;
    }
}
