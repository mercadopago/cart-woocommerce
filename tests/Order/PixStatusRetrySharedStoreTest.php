<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Order\PixStatusRetry;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * The retry coordination (guard + signal transients) across two requests that share one store but keep their own
 * per-request caches, with and without a persistent object cache. The store and caches follow WordPress semantics:
 * without a persistent cache a transient is an option cached per request (value or `notoptions` absence); with one
 * it lives in the shared `transient` group and drop-ins such as Redis Object Cache keep a per-request copy that only
 * a forced read bypasses.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PixStatusRetrySharedStoreTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const GUARD  = 'mp_pix_retry_28';
    private const SIGNAL = 'mp_pix_retry_signal_28';

    /** @var array<string, mixed> shared store (database or persistent cache backend) */
    private array $store = [];

    /** @var array<string, array{options: array, notoptions: array, transient: array}> per-request caches */
    private array $caches = [];

    private string $request = 'A';

    private bool $externalCache = false;

    private bool $refuseSignalWrites = false;

    /** @var array<int, int> actions created by as_enqueue_async_action */
    private array $actions = [];

    private int $nextActionId = 42;

    /** @var callable|null runs once, right before the next signal write reaches the store */
    private $beforeSignalWrite = null;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->caches = ['A' => $this->emptyCache(), 'B' => $this->emptyCache()];
        $this->stubWordPress();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    /**
     * @dataProvider signalWriteCases
     */
    public function testEnqueueReusesTheLiveChainOnlyWhenTheStoreKeepsTheSignal(
        bool $externalCache,
        bool $signalAlreadyStored,
        bool $refuseSignalWrites,
        int $expectedActionId,
        array $expectedActions,
        $expectedGuard,
        $expectedSignal
    ): void {
        $this->externalCache = $externalCache;
        $this->store[self::GUARD] = 41;
        if ($signalAlreadyStored) {
            $this->store[self::SIGNAL] = 1;
        }
        $this->refuseSignalWrites = $refuseSignalWrites;

        $this->request = 'B';
        $actionId      = $this->retry()->enqueue(28, 'sync', '28');

        $this->assertSame($expectedActionId, $actionId);
        $this->assertSame($expectedActions, $this->actions);
        $this->assertSame($expectedGuard, $this->store[self::GUARD] ?? false);
        $this->assertSame($expectedSignal, $this->store[self::SIGNAL] ?? false);
    }

    public function signalWriteCases(): array
    {
        return [
            'signal stored while the chain is live reuses the action (options)'          => [false, false, false, 41, [], 41, 1],
            'signal stored while the chain is live reuses the action (persistent cache)' => [true, false, false, 41, [], 41, 1],
            'same signal already stored still reuses the action (options)'              => [false, true, false, 41, [], 41, 1],
            'same signal already stored still reuses the action (persistent cache)'     => [true, true, false, 41, [], 41, 1],
            'refused signal write opens a new chain (options)'                          => [false, false, true, 42, [42], 42, false],
            'refused signal write opens a new chain (persistent cache)'                 => [true, false, true, 42, [42], 42, false],
        ];
    }

    /**
     * @dataProvider cacheModes
     */
    public function testALocalCopyOfAnEndedGuardIsNotReusedAfterTheSignal(bool $externalCache): void
    {
        // Request B looked up the guard before; worker A then ends its chain right before B stores the signal.
        $this->externalCache = $externalCache;
        $this->store[self::GUARD] = 41;
        if ($externalCache) {
            $this->caches['B']['transient'][self::GUARD] = 41;
        } else {
            $this->caches['B']['options']['_transient_' . self::GUARD] = 41;
        }
        $worker                  = $this->retry();
        $this->beforeSignalWrite = function () use ($worker) {
            $this->inRequest('A', fn () => $this->invoke($worker, 'endChain', 28, 'polling'));
        };

        $this->request = 'B';
        $actionId      = $this->retry()->enqueue(28, 'sync', '28');

        $this->assertSame(42, $actionId, 'B must not hand back the action of the chain that already ended');
        $this->assertSame([42], $this->actions, 'a successor chain must exist');
        $this->assertSame(42, $this->store[self::GUARD] ?? false);
        $this->assertFalse($this->store[self::SIGNAL] ?? false);
    }

    public function cacheModes(): array
    {
        return [
            'options cached by the request' => [false],
            'persistent cache local copy'   => [true],
        ];
    }

    public function testTheEndOfTheChainSeesASignalStoredAfterItsFirstReadWithoutAPersistentCache(): void
    {
        // Worker A cached the absence of the signal when it started; request B signals while A still holds the chain.
        $this->store[self::GUARD] = 41;
        $worker                   = $this->retry();
        $this->inRequest('A', fn () => $this->invoke($worker, 'consumeSignal', 28));

        $reused = $this->inRequest('B', fn () => $this->retry()->enqueue(28, 'sync', '28'));
        $this->inRequest('A', fn () => $this->invoke($worker, 'endChain', 28, 'polling'));

        $this->assertSame(41, $reused);
        $this->assertSame([42], $this->actions, 'the ending worker must hand the signal to a successor');
        $this->assertSame(42, $this->store[self::GUARD] ?? false);
        $this->assertFalse($this->store[self::SIGNAL] ?? false);
    }

    private function inRequest(string $request, callable $work)
    {
        $previous      = $this->request;
        $this->request = $request;
        try {
            return $work();
        } finally {
            $this->request = $previous;
        }
    }

    private function invoke(PixStatusRetry $retry, string $method, ...$args)
    {
        $reflection = new \ReflectionMethod(PixStatusRetry::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($retry, ...$args);
    }

    private function retry(): PixStatusRetry
    {
        $logs       = Mockery::mock(Logs::class);
        $file       = Mockery::mock(File::class);
        $file->shouldIgnoreMissing();
        $logs->file = $file;

        return new PixStatusRetry(
            Mockery::mock(OrderStatus::class),
            Mockery::mock(OrderMetadata::class),
            Mockery::mock(Requester::class),
            $logs,
            Mockery::mock(Datadog::class)->shouldIgnoreMissing()
        );
    }

    private function emptyCache(): array
    {
        return ['options' => [], 'notoptions' => [], 'transient' => []];
    }

    private function stubWordPress(): void
    {
        WP_Mock::userFunction('wp_using_ext_object_cache')->andReturnUsing(fn () => $this->externalCache);
        WP_Mock::userFunction('get_transient')->andReturnUsing(fn (string $key) => $this->getTransient($key));
        WP_Mock::userFunction('set_transient')->andReturnUsing(fn (string $key, $value) => $this->setTransient($key, $value));
        WP_Mock::userFunction('delete_transient')->andReturnUsing(fn (string $key) => $this->deleteTransient($key));
        WP_Mock::userFunction('wp_cache_get')->andReturnUsing(
            fn (string $key, string $group = '', bool $force = false) => $this->cacheGet($key, $group, $force)
        );
        WP_Mock::userFunction('wp_cache_delete')->andReturnUsing(fn (string $key, string $group = '') => $this->cacheDelete($key, $group));
        WP_Mock::userFunction('as_enqueue_async_action')->andReturnUsing(function () {
            $id              = $this->nextActionId++;
            $this->actions[] = $id;

            return $id;
        });
    }

    private function getTransient(string $key)
    {
        if ($this->externalCache) {
            return $this->cacheGet($key, 'transient', false);
        }

        // get_option(): the request's cached value, then its cached absence, then the database.
        $option = '_transient_' . $key;
        $cache  = &$this->caches[$this->request];
        if (array_key_exists($option, $cache['options'])) {
            return $cache['options'][$option];
        }
        if (isset($cache['notoptions'][$option])) {
            return false;
        }
        if (!array_key_exists($key, $this->store)) {
            $cache['notoptions'][$option] = true;

            return false;
        }

        return $cache['options'][$option] = $this->store[$key];
    }

    private function setTransient(string $key, $value): bool
    {
        if ($key === self::SIGNAL && $this->beforeSignalWrite !== null) {
            $interleave              = $this->beforeSignalWrite;
            $this->beforeSignalWrite = null;
            $interleave();
        }

        if ($key === self::SIGNAL && $this->refuseSignalWrites) {
            return false;
        }

        $unchanged         = ($this->store[$key] ?? null) === $value;
        $this->store[$key] = $value;
        $this->rememberLocally($key, $value);

        // Like update_option(), an unchanged value reports false although the store holds it.
        return !$unchanged;
    }

    private function deleteTransient(string $key): bool
    {
        $existed = array_key_exists($key, $this->store);
        unset($this->store[$key]);
        unset($this->caches[$this->request]['transient'][$key], $this->caches[$this->request]['options']['_transient_' . $key]);

        return $existed;
    }

    private function cacheGet(string $key, string $group, bool $force)
    {
        $cache = &$this->caches[$this->request];
        if ($group === 'transient') {
            // Persistent cache: the per-request copy answers unless the read is forced to the backend.
            if (!$force && array_key_exists($key, $cache['transient'])) {
                return $cache['transient'][$key];
            }
            if (!array_key_exists($key, $this->store)) {
                return false;
            }

            return $cache['transient'][$key] = $this->store[$key];
        }

        return $cache['options'][$key] ?? false;
    }

    private function cacheDelete(string $key, string $group): bool
    {
        $cache = &$this->caches[$this->request];
        if ($group === 'options' && $key === 'notoptions') {
            $cache['notoptions'] = [];

            return true;
        }
        if ($group === 'options') {
            unset($cache['options'][$key]);

            return true;
        }

        // Deleting from a persistent cache would delete the shared record itself.
        unset($cache[$group][$key], $this->store[$key]);

        return true;
    }

    private function rememberLocally(string $key, $value): void
    {
        $cache = &$this->caches[$this->request];
        if ($this->externalCache) {
            $cache['transient'][$key] = $value;

            return;
        }

        $cache['options']['_transient_' . $key] = $value;
        unset($cache['notoptions']['_transient_' . $key]);
    }
}
