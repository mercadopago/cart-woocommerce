<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixStatusLock;
use PHPUnit\Framework\TestCase;

class SmartPixStatusLockTest extends TestCase
{
    public function testGivenARequestHoldsTheOrderLeaseWhenAnotherAcquiresThenOnlyTheOwnerProceedsUntilRelease(): void
    {
        $database = new SmartPixStatusLockDatabase();
        $first = new SmartPixStatusLock($database);
        $second = new SmartPixStatusLock($database);

        $lease = $first->acquire(321);

        $this->assertNotNull($lease);
        $this->assertSame('mp_smart_pix_status_lock_321', $lease['name']);
        $this->assertSame('no', $database->rows[$lease['name']]['autoload']);
        $this->assertNull($second->acquire(321));
        $this->assertNotNull($second->acquire(322));
        $this->assertTrue($first->release($lease));
        $this->assertNotNull($second->acquire(321));
    }

    public function testGivenAnExpiredLeaseWhenAcquiringThenItIsTakenOverAndTheStaleOwnerCannotReleaseIt(): void
    {
        $database = new SmartPixStatusLockDatabase();
        $stale = new SmartPixStatusLock($database);
        $fresh = new SmartPixStatusLock($database);

        $expired = $stale->acquire(321);
        $this->assertNotNull($expired);
        $expired['value'] = '1:crashed-owner';
        $database->rows[$expired['name']]['option_value'] = $expired['value'];

        $replacement = $fresh->acquire(321);

        $this->assertNotNull($replacement);
        $this->assertFalse($stale->release($expired));
        $this->assertNull($stale->acquire(321));
        $this->assertTrue($fresh->release($replacement));
    }

    public function testGivenOverlappingPollsWhenTheInsertHitsTheExistingLeaseThenWpdbErrorsStaySuppressed(): void
    {
        $database = new SmartPixStatusLockDatabase();
        $lease = (new SmartPixStatusLock($database))->acquire(321);

        $this->assertNotNull($lease);
        $this->assertNull((new SmartPixStatusLock($database))->acquire(321));
        $this->assertSame([], $database->insertsWithVisibleErrors);
        $this->assertFalse($database->suppressErrors, 'The previous wpdb setting must be restored');
    }

    public function testGivenTheDatabaseFailsWhenAcquiringThenNoLeaseIsGranted(): void
    {
        $database = new SmartPixStatusLockDatabase();
        $database->failing = true;

        $this->assertNull((new SmartPixStatusLock($database))->acquire(321));
    }

    public function testGivenTheTerminalObservationPrefixWhenAcquiringThenItNeverContendsWithTheStatusQueryLease(): void
    {
        $database = new SmartPixStatusLockDatabase();
        $query = new SmartPixStatusLock($database);
        $terminal = new SmartPixStatusLock($database, SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX);

        $queryLease = $query->acquire(321);
        $terminalLease = $terminal->acquire(321);

        $this->assertNotNull($queryLease);
        $this->assertNotNull($terminalLease);
        $this->assertSame('mp_smart_pix_status_lock_321', $queryLease['name']);
        $this->assertSame('mp_smart_pix_terminal_lock_321', $terminalLease['name']);
        $this->assertNull((new SmartPixStatusLock($database, SmartPixStatusLock::TERMINAL_OBSERVATION_NAME_PREFIX))->acquire(321));
        $this->assertTrue($terminal->release($terminalLease));
        $this->assertSame(['mp_smart_pix_status_lock_321'], array_keys($database->rows));
    }
}
