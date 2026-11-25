<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use MercadoPago\Woocommerce\Exceptions\OrderStatusLockException;
use MercadoPago\Woocommerce\Order\PixOrderStatusLock;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests. The "database" below only models GET_LOCK semantics (one owner per name,
 * owner = connection); real exclusion between processes is proven by the integration run
 * (tests/integration/pix-completion), not by this double.
 */
class PixOrderStatusLockTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeNamedLockServer::reset();
        $this->forgetProcessLocalRegistry();
    }

    /** Models a fresh PHP process: the per-process re-entrancy registry starts empty. */
    private function forgetProcessLocalRegistry(): void
    {
        $property = new \ReflectionProperty(PixOrderStatusLock::class, 'held');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    public function testSecondConnectionCannotEnterWhileFirstHoldsTheLock(): void
    {
        $first  = new PixOrderStatusLock(new FakeNamedLockConnection(11));
        $second = new PixOrderStatusLock(new FakeNamedLockConnection(22));

        $lock = $first->acquire(74);

        try {
            $second->acquire(74);
            $this->fail('Second connection must not own the lock');
        } catch (OrderStatusLockException $e) {
            $this->assertSame('busy', $e->getReason());
        }

        $this->assertTrue($first->release($lock));
        $secondLock = $second->acquire(74);
        $this->assertSame(22, $secondLock['connection_id']);
        $second->release($secondLock);
    }

    public function testDifferentOrdersDoNotBlockEachOther(): void
    {
        $a = new PixOrderStatusLock(new FakeNamedLockConnection(11));
        $b = new PixOrderStatusLock(new FakeNamedLockConnection(22));

        $lockA = $a->acquire(1);
        $lockB = $b->acquire(2);

        $this->assertNotSame($lockA['name'], $lockB['name']);
        $a->release($lockA);
        $b->release($lockB);
    }

    public function testDifferentSitePrefixesDoNotCollide(): void
    {
        $a = new PixOrderStatusLock(new FakeNamedLockConnection(11, 'wp_'));
        $b = new PixOrderStatusLock(new FakeNamedLockConnection(22, 'site2_'));

        $lockA = $a->acquire(1);
        $lockB = $b->acquire(1);

        $this->assertNotSame($lockA['name'], $lockB['name']);
        $this->assertLessThan(64, strlen($lockA['name']));
        $a->release($lockA);
        $b->release($lockB);
    }

    public function testRefundLockUsesItsOwnNamespaceAndNeverBlocksThePixLock(): void
    {
        $pix    = new PixOrderStatusLock(new FakeNamedLockConnection(11));
        $refund = new PixOrderStatusLock(new FakeNamedLockConnection(22), PixOrderStatusLock::REFUND_NAME_PREFIX);

        $pixLock    = $pix->acquire(5);
        $refundLock = $refund->acquire(5, 3);

        $this->assertStringStartsWith('mp_pix_status_', $pixLock['name']);
        $this->assertStringStartsWith('mp_refund_acc_', $refundLock['name']);
        $this->assertLessThan(64, strlen($refundLock['name']));
        $this->assertFalse($pix->isHeld(6));
        $pix->release($pixLock);
        $refund->release($refundLock);
    }

    public function testSameProcessReentryDoesNotReacquire(): void
    {
        $connection = new FakeNamedLockConnection(11);
        $lock = new PixOrderStatusLock($connection);

        $held = $lock->acquire(5);
        $acquisitions = $connection->getLockCalls;

        try {
            (new PixOrderStatusLock($connection))->acquire(5);
            $this->fail('Re-entry must be refused');
        } catch (OrderStatusLockException $e) {
            $this->assertSame('busy', $e->getReason());
        }

        $this->assertSame($acquisitions, $connection->getLockCalls, 'No second GET_LOCK may be issued');
        $lock->release($held);
    }

    public function testIsHeldReflectsTheLocksOfThisProcessOnly(): void
    {
        $connection = new FakeNamedLockConnection(11);
        $lock = new PixOrderStatusLock($connection);

        $this->assertFalse($lock->isHeld(5));
        $held = $lock->acquire(5);
        $this->assertTrue($lock->isHeld(5));
        $this->assertFalse($lock->isHeld(6), 'other orders are not held');
        $lock->release($held);
        $this->assertFalse($lock->isHeld(5));
    }

    public function testNullFromDatabaseNeverAuthorizesTheEffect(): void
    {
        $connection = new FakeNamedLockConnection(11);
        $connection->failGetLock = true;

        try {
            (new PixOrderStatusLock($connection))->acquire(9);
            $this->fail('Unavailable lock must not allow entering the critical section');
        } catch (OrderStatusLockException $e) {
            $this->assertSame('unavailable', $e->getReason());
        }
    }

    public function testLostConnectionIsDetectedByAssertOwned(): void
    {
        $connection = new FakeNamedLockConnection(11);
        $lock = new PixOrderStatusLock($connection);
        $held = $lock->acquire(3);

        $lock->assertOwned($held);

        // Silent reconnection: the server dropped the old session and its locks.
        FakeNamedLockServer::$owners = [];

        try {
            $lock->assertOwned($held);
            $this->fail('Lost lock must be detected');
        } catch (OrderStatusLockException $e) {
            $this->assertSame('lost', $e->getReason());
        }
    }

    public function testLockIsReleasedWhenTheOwnerConnectionEnds(): void
    {
        $first = new PixOrderStatusLock(new FakeNamedLockConnection(11));
        $first->acquire(8);

        FakeNamedLockServer::dropConnection(11);
        $this->forgetProcessLocalRegistry();

        $second = new PixOrderStatusLock(new FakeNamedLockConnection(22));
        $held = $second->acquire(8);
        $this->assertSame(22, $held['connection_id']);
        $second->release($held);
    }

    public function testExceptionMessagesAreFixedAndGeneric(): void
    {
        foreach (['busy', 'unavailable', 'lost', 'anything-else'] as $reason) {
            $exception = new OrderStatusLockException($reason);
            $this->assertStringNotContainsString('SELECT', $exception->getMessage());
            $this->assertContains($exception->getReason(), ['busy', 'unavailable', 'lost']);
        }
    }
}

class FakeNamedLockServer
{
    /** @var array<string, int> lock name => owner connection id */
    public static array $owners = [];

    public static function reset(): void
    {
        self::$owners = [];
    }

    public static function dropConnection(int $connectionId): void
    {
        self::$owners = array_filter(self::$owners, fn ($owner) => $owner !== $connectionId);
    }
}

class FakeNamedLockConnection
{
    public string $prefix;

    public int $getLockCalls = 0;

    public bool $failGetLock = false;

    private int $connectionId;

    public function __construct(int $connectionId, string $prefix = 'wp_')
    {
        $this->connectionId = $connectionId;
        $this->prefix       = $prefix;
    }

    public function prepare(string $sql, ...$args): string
    {
        foreach ($args as $arg) {
            $sql = preg_replace('/%s/', "'" . addslashes((string) $arg) . "'", $sql, 1);
        }

        return $sql;
    }

    public function get_var(string $query)
    {
        if (preg_match("/GET_LOCK\('([^']+)'/", $query, $m)) {
            $this->getLockCalls++;
            if ($this->failGetLock) {
                return null;
            }
            if (isset(FakeNamedLockServer::$owners[$m[1]]) && FakeNamedLockServer::$owners[$m[1]] !== $this->connectionId) {
                return '0';
            }
            FakeNamedLockServer::$owners[$m[1]] = $this->connectionId;
            return '1';
        }
        if (strpos($query, 'CONNECTION_ID()') !== false) {
            return (string) $this->connectionId;
        }
        if (preg_match("/IS_USED_LOCK\('([^']+)'/", $query, $m)) {
            return isset(FakeNamedLockServer::$owners[$m[1]]) ? (string) FakeNamedLockServer::$owners[$m[1]] : null;
        }
        if (preg_match("/RELEASE_LOCK\('([^']+)'/", $query, $m)) {
            if ((FakeNamedLockServer::$owners[$m[1]] ?? null) === $this->connectionId) {
                unset(FakeNamedLockServer::$owners[$m[1]]);
                return '1';
            }
            return null;
        }

        return null;
    }
}
