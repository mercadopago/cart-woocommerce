<?php

namespace MercadoPago\Woocommerce\Tests\Funnel;

use MercadoPago\Woocommerce\Api\Core\Models\FunnelRegistration;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Funnel\CreationLock;
use MercadoPago\Woocommerce\Funnel\InstallationStore;
use MercadoPago\Woocommerce\Hooks\Options;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class InstallationStoreTest extends TestCase
{
    private OptionsDatabase $database;
    private InstallationStore $identity;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->database = new OptionsDatabase();
        $this->identity = new InstallationStore($this->database);
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_cache_delete')->with(Mockery::type('string'), 'options')->andReturn(true);
        WP_Mock::userFunction('update_option')->andReturnUsing(function ($name, $value, $autoload) {
            $this->assertFalse($autoload);
            $this->assertTrue($this->database->suppressed);
            if ($this->database->failMirrors) {
                return false;
            }
            $this->database->put($name, $value);
            return true;
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testSavingPublishesOneCompleteIdentityAndCompatibleLegacyOptions(): void
    {
        $owner = (new CreationLock($this->database))->acquire();
        $this->identity->save(new FunnelRegistration(['id' => 'synthetic-id'], 'synthetic-key'), $owner);

        $this->assertSame(['id' => 'synthetic-id', 'key' => 'synthetic-key'], $this->identity->read());
        $rows = $this->database->tables['wp_options'];
        $this->assertSame('no', $rows[InstallationStore::OPTION_NAME]['autoload']);
        $this->assertSame('synthetic-id', $rows['_mp_installation_id']['option_value']);
        $this->assertSame('synthetic-key', $rows['_mp_installation_key']['option_value']);
        $this->assertFalse($this->database->suppressed);
    }

    public function testCanonicalIdentityWinsOverMismatchedLegacyWithoutMixingFields(): void
    {
        $this->database->put(InstallationStore::OPTION_NAME, '{"id":"canonical-id","key":"canonical-key"}');
        $this->database->put('_mp_installation_id', 'legacy-id');
        $this->database->put('_mp_installation_key', 'legacy-key');

        $store = new Store(new Options(), $this->identity);
        $this->assertSame('canonical-id', $store->getInstallationId());
        $this->assertSame('canonical-key', $store->getInstallationKey());
        $this->assertSame(['id' => 'canonical-id', 'key' => 'canonical-key'], $store->getInstallationCredentials());
    }

    public function testLegacyIdentityIsReadWithoutMigrationOrWrites(): void
    {
        $this->database->put('_mp_installation_id', 'legacy-id');
        $this->database->put('_mp_installation_key', 'legacy-key');
        $store = new Store(new Options(), $this->identity);
        $pair = $store->getInstallationCredentials();
        $store->synchronizeInstallationCredentials($pair);

        $this->assertSame(['id' => 'legacy-id', 'key' => 'legacy-key'], $pair);
        $this->assertCount(2, $this->database->tables['wp_options']);
    }

    /** @dataProvider invalidIdentityProvider */
    public function testCorruptCanonicalIdentityNeverFallsBackToAnUnrelatedLegacyPair(string $value): void
    {
        $this->database->put(InstallationStore::OPTION_NAME, $value);
        $this->database->put('_mp_installation_id', 'legacy-id');
        $this->database->put('_mp_installation_key', 'legacy-key');
        $this->expectExceptionMessage('Invalid onboarding installation identity.');
        $this->identity->read();
    }

    public static function invalidIdentityProvider(): array
    {
        return [['invalid-json'], ['null'], ['{"id":"canonical-id"}'], ['{"id":1,"key":"key"}'], ['{"id":"id","key":""}']];
    }

    public function testLostLeaseCannotPublishAnIdentity(): void
    {
        $lock = new CreationLock($this->database);
        $owner = $lock->acquire();
        $lock->release($owner);
        $lock->acquire();
        $this->expectExceptionMessage('Unable to persist onboarding installation identity.');
        $this->identity->save(new FunnelRegistration(['id' => 'synthetic-id'], 'synthetic-key'), $owner);
    }

    public function testExpiredLeaseCannotPublishAnIdentity(): void
    {
        $this->database->put(CreationLock::OPTION_NAME, '1:expired-owner');
        $this->expectExceptionMessage('Unable to persist onboarding installation identity.');
        $this->identity->save(new FunnelRegistration(['id' => 'synthetic-id'], 'synthetic-key'), '1:expired-owner');
    }

    public function testDatabaseClockAheadOfPhpDoesNotDropTheConfirmedResponse(): void
    {
        $owner = (new CreationLock($this->database))->acquire();
        $this->database->databaseNow = time() + CreationLock::LEASE_SECONDS + 300;

        $this->identity->save(new FunnelRegistration(['id' => 'synthetic-id'], 'synthetic-key'), $owner);

        $this->assertSame(['id' => 'synthetic-id', 'key' => 'synthetic-key'], $this->identity->read());
    }

    public function testConfirmedIdentityCannotBeOverwrittenByAnotherResponse(): void
    {
        $owner = (new CreationLock($this->database))->acquire();
        $this->identity->save(new FunnelRegistration(['id' => 'first-id'], 'first-key'), $owner);
        try {
            $this->identity->save(new FunnelRegistration(['id' => 'second-id'], 'second-key'), $owner);
            $this->fail('A confirmed identity must be immutable.');
        } catch (\RuntimeException $error) {
            $this->assertSame(['id' => 'first-id', 'key' => 'first-key'], $this->identity->read());
        }
    }

    public function testFailedLegacyMirrorCanBeRepairedWithoutCreatingAnotherIdentity(): void
    {
        $this->database->failMirrors = true;
        $owner = (new CreationLock($this->database))->acquire();
        $store = new Store(new Options(), $this->identity);
        try {
            $store->setInstallationCredentials(new FunnelRegistration(['id' => 'synthetic-id'], 'synthetic-key'), $owner);
            $this->fail('Unconfirmed mirror must be reported.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Unable to synchronize onboarding installation identity.', $error->getMessage());
        }
        $this->assertFalse($this->database->suppressed);
        $this->assertSame(['id' => 'synthetic-id', 'key' => 'synthetic-key'], $store->getInstallationCredentials());
        $this->database->failMirrors = false;
        $store->synchronizeInstallationCredentials($store->getInstallationCredentials());
        $this->assertSame('synthetic-key', $this->database->tables['wp_options']['_mp_installation_key']['option_value']);
    }

    public function testSynchronizationRejectsADifferentIdentity(): void
    {
        $this->database->put(InstallationStore::OPTION_NAME, '{"id":"canonical-id","key":"canonical-key"}');
        $this->expectExceptionMessage('Unable to synchronize onboarding installation identity.');
        $this->identity->synchronizeLegacy(['id' => 'different-id', 'key' => 'different-key']);
    }

    public function testDatabaseReadFailureIsDistinctFromAnAbsentIdentity(): void
    {
        $this->database->failReads = true;
        $this->expectExceptionMessage('Unable to read onboarding installation identity.');
        $this->identity->read();
    }

    public function testEmptyStoreReturnsOnlyAnEmptyPair(): void
    {
        $this->assertSame(['id' => '', 'key' => ''], $this->identity->read());
    }
}
