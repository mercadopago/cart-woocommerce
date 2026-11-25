<?php

namespace MercadoPago\Woocommerce\Tests\Funnel;

use MercadoPago\Woocommerce\Funnel\CreationLock;
use PHPUnit\Framework\TestCase;

class CreationLockTest extends TestCase
{
    public function testOnlyOneOwnerCanProceedAndReleaseMakesTheLeaseAvailable(): void
    {
        $database = new OptionsDatabase();
        $first = new CreationLock($database);
        $second = new CreationLock($database);
        $owner = $first->acquire();

        $this->assertTrue($first->owns($owner));
        $this->assertNull($second->acquire());
        $this->assertFalse($second->owns('another-owner'));
        $this->assertSame('no', $database->tables['wp_options'][CreationLock::OPTION_NAME]['autoload']);
        $first->release($owner);
        $this->assertNotNull($second->acquire());
        $this->assertFalse($database->suppressed);
    }

    public function testExpiredOwnerCannotDeleteOrUseTheReplacementLease(): void
    {
        $database = new OptionsDatabase();
        $database->put(CreationLock::OPTION_NAME, '1:interrupted-owner');
        $lock = new CreationLock($database);
        $replacement = $lock->acquire();

        $this->assertNotNull($replacement);
        $lock->release('1:interrupted-owner');
        $this->assertTrue($lock->owns($replacement));
        $this->assertFalse($lock->owns('1:interrupted-owner'));
    }

    public function testDatabaseClockDriftNeitherShortensNorExtendsTheLease(): void
    {
        $database = new OptionsDatabase();
        $lock = new CreationLock($database);
        $owner = $lock->acquire();

        $database->databaseNow = time() + CreationLock::LEASE_SECONDS + 300;
        $this->assertTrue($lock->owns($owner));

        $database->put(CreationLock::OPTION_NAME, '1:interrupted-owner');
        $database->databaseNow = 0;
        $this->assertFalse($lock->owns('1:interrupted-owner'));
    }

    public function testSiteOptionsTablesDoNotShareALease(): void
    {
        $database = new OptionsDatabase();
        $lock = new CreationLock($database);
        $this->assertNotNull($lock->acquire());
        $database->options = 'wp_2_options';
        $this->assertNotNull($lock->acquire());
        $this->assertCount(2, $database->tables);
    }

    public function testDatabaseFailureDoesNotGrantALeaseAndRestoresErrorSuppression(): void
    {
        $database = new OptionsDatabase();
        $database->failWrites = true;
        $lock = new CreationLock($database);
        try {
            $lock->acquire();
            $this->fail('An unavailable database must not grant ownership.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Unable to acquire onboarding creation lease.', $error->getMessage());
        }
        $this->assertFalse($database->suppressed);
    }

    public function testFailedExpiredLeaseRecoveryIsReported(): void
    {
        $database = new OptionsDatabase();
        $database->put(CreationLock::OPTION_NAME, '1:interrupted-owner');
        $database->failWrites = true;
        $this->expectExceptionMessage('Unable to recover onboarding creation lease.');
        (new CreationLock($database))->acquire();
    }

    public function testFailedReleaseIsReportedAndLeavesTheBoundedLeaseInPlace(): void
    {
        $database = new OptionsDatabase();
        $lock = new CreationLock($database);
        $owner = $lock->acquire();
        $database->failRelease = true;
        $this->expectExceptionMessage('Unable to release onboarding creation lease.');
        $lock->release($owner);
    }
}
