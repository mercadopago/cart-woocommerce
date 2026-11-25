<?php

namespace MercadoPago\Woocommerce\Tests\Funnel;

use MercadoPago\Woocommerce\Api\Core\CoreApiClient;
use MercadoPago\Woocommerce\Api\Core\FunnelClient;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Funnel\CreationLock;
use MercadoPago\Woocommerce\Funnel\Funnel;
use MercadoPago\Woocommerce\Funnel\InstallationStore;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Gateways;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperty;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Mock;

class FunnelCreationTest extends TestCase
{
    use SetNotAccessibleProperty;

    private OptionsDatabase $database;
    private array $events = [];
    private array $logs = [];

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->database = new OptionsDatabase();
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        WP_Mock::userFunction('wp_cache_delete')->andReturn(true);
        WP_Mock::userFunction('get_option')->andReturnUsing(function ($name, $default = false) {
            $fixed = ['woocommerce_default_country' => 'BR:SP', 'admin_email' => 'seller@example.test'];
            return $fixed[$name] ?? $this->database->tables['wp_options'][$name]['option_value'] ?? $default;
        });
        WP_Mock::userFunction('site_url')->andReturn('https://store.example.test');
        WP_Mock::userFunction('update_option')->andReturnUsing(function ($name, $value) {
            // failMirrors breaks only the legacy ID/key mirror, not other plugin options.
            $mirror = in_array($name, ['_mp_installation_id', '_mp_installation_key'], true);
            if ($mirror && $this->database->failMirrors) {
                return false;
            }
            $this->database->put($name, (string) $value);
            return true;
        });
        $logger = Mockery::mock();
        $logger->shouldReceive('error')->andReturnUsing(function ($message) {
            $this->logs[] = $message;
        });
        $GLOBALS['mercadopago'] = (object) ['logs' => (object) ['file' => $logger]];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mercadopago']);
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testOverlappingCreatorsUseOnePostAndRunOnlyTheWinningCallbackAfterPersistence(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $first = $this->funnel($http);
        $second = $this->funnel($http);
        $secondAfter = false;
        $http->shouldReceive('request')->once()->andReturnUsing(function () use ($second, &$secondAfter) {
            $second->create(function () use (&$secondAfter) {
                $secondAfter = true;
            });
            return $this->registration();
        });
        $firstAfter = false;
        $first->create(function () use (&$firstAfter) {
            $this->assertSame(['id' => 'synthetic-id', 'key' => 'synthetic-key'], (new InstallationStore($this->database))->read());
            $firstAfter = true;
        });

        $this->assertTrue($firstAfter);
        $this->assertFalse($secondAfter);
        $this->assertSame([['funnel', 'success']], $this->events);
        $this->assertArrayNotHasKey(CreationLock::OPTION_NAME, $this->database->tables['wp_options']);
    }

    public function testFailedReleaseIsReportedAsItsOwnStepAndKeepsThePublishedIdentity(): void
    {
        $this->database->failRelease = true;
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->andReturn($this->registration());

        $this->funnel($http)->create();

        $this->assertSame([['funnel', 'error', 'Error creating onboarding installation (step: release)']], $this->events);
        $this->assertSame(['id' => 'synthetic-id', 'key' => 'synthetic-key'], (new InstallationStore($this->database))->read());
    }

    public function testIdentityPublishedBeforeTheSecondReadPreventsAnotherPostAndSuccessEvent(): void
    {
        $this->database->afterInsert = function () {
            $this->database->put(InstallationStore::OPTION_NAME, '{"id":"other-id","key":"other-key"}');
        };
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldNotReceive('request');
        $this->funnel($http)->create(function () {
            $this->fail('A skipped creation must not run its callback.');
        });

        $this->assertSame([], $this->events);
        $this->assertSame(['id' => 'other-id', 'key' => 'other-key'], (new InstallationStore($this->database))->read());
    }

    public function testLosingOwnershipBeforePostSkipsTheRemoteMutation(): void
    {
        $this->database->afterInsert = function () {
            $this->database->put(CreationLock::OPTION_NAME, (time() + 120) . ':another-owner');
        };
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldNotReceive('request');
        $this->funnel($http)->create();
        $this->assertSame([['funnel', 'error', 'Error creating onboarding installation (step: lease)']], $this->events);
    }

    /** @dataProvider creationFailureProvider */
    public function testUnconfirmedCreationDoesNotRunCallbackOrExposeTheUpstreamSecret(string $failure): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->andReturnUsing(function () use ($failure) {
            if ($failure === 'transport') {
                throw new \RuntimeException('private-cpp-token@example.test');
            }
            if ($failure === 'error') {
                throw new \Error('private-cpp-token@example.test');
            }
            if ($failure === 'database') {
                $this->database->failWrites = true;
            }
            if ($failure === 'ownership') {
                $this->database->put(CreationLock::OPTION_NAME, (time() + 120) . ':replacement-owner');
            }
            return $this->registration();
        });
        $this->funnel($http)->create(function () {
            $this->fail('An unconfirmed creation must not run its callback.');
        });

        $step = in_array($failure, ['transport', 'error'], true) ? 'core' : 'persist';
        $this->assertSame([['funnel', 'error', "Error creating onboarding installation (step: {$step})"]], $this->events);
        $this->assertCount(1, $this->logs);
        $this->assertStringNotContainsString('private-cpp-token', $this->logs[0]);
        $this->assertSame(['id' => '', 'key' => ''], (new InstallationStore($this->database))->read());
    }

    public static function creationFailureProvider(): array
    {
        return [['transport'], ['error'], ['database'], ['ownership']];
    }

    public function testReactivationRepairsFailedMirrorAndReusesTheSameCanonicalPairWithoutPost(): void
    {
        $this->database->failMirrors = true;
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->with('POST', Mockery::type('string'), Mockery::type('array'), Mockery::type('string'))
            ->andReturn($this->registration());
        $funnel = $this->funnel($http);
        $funnel->create(function () {
            $this->fail('Mirror failure must not complete activation.');
        });
        $this->assertTrue($funnel->created());
        $this->database->failMirrors = false;
        $http->shouldReceive('request')->once()->with(
            'PUT',
            Mockery::type('string'),
            ['x-cpp-token' => 'synthetic-key', 'Content-Type' => 'application/json'],
            '{"is_disabled":false,"id":"synthetic-id"}'
        )->andReturn(new HttpResponse(204));
        $after = false;
        $funnel->updateStepActivate(function () use (&$after) {
            $after = true;
        });

        $this->assertTrue($after);
        $this->assertSame('synthetic-key', $this->database->tables['wp_options']['_mp_installation_key']['option_value']);
        $this->assertSame([['funnel', 'error', 'Error creating onboarding installation (step: persist)'], ['funnel', 'success']], $this->events);
    }

    public function testActivationRecoveredAfterAFailedMirrorSendsTheFirstContactOnceWithTheSamePair(): void
    {
        $requests = [];
        $http = $this->recordingHttp($requests);
        $plugin = $this->activationPlugin($http);
        $this->database->put('_mp_execute_activate', '1');

        $this->database->failMirrors = true;
        $plugin->activatePlugin();
        $this->assertSame('1', $this->database->tables['wp_options']['_mp_execute_activate']['option_value']);

        $this->database->failMirrors = false;
        $plugin->activatePlugin();
        // A later, normal reactivation of the completed installation must not resend the contact.
        $plugin->activatePlugin();

        $this->assertSame([
            ['POST', null],
            ['PUT', ['is_disabled' => false, 'id' => 'synthetic-id']],
            ['PUT', ['email' => 'seller@example.test', 'country' => 'BR', 'id' => 'synthetic-id']],
            ['PUT', ['is_disabled' => false, 'id' => 'synthetic-id']],
        ], $requests);
        $options = $this->database->tables['wp_options'];
        $this->assertSame('0', $options['_mp_execute_activate']['option_value']);
        $this->assertSame('0', $options['_mp_funnel_contact_pending']['option_value']);
        $this->assertSame('synthetic-key', $options['_mp_installation_key']['option_value']);
    }

    public function testFailedFirstContactStaysOwedUntilAnActivationConfirmsIt(): void
    {
        $requests = [];
        $failContact = true;
        $http = $this->recordingHttp($requests, function (array $body) use (&$failContact) {
            if ($failContact && isset($body['email'])) {
                throw new \RuntimeException('synthetic contact failure');
            }
        });
        $plugin = $this->activationPlugin($http);
        $this->database->put('_mp_execute_activate', '1');

        $plugin->activatePlugin();
        $this->assertSame('1', $this->database->tables['wp_options']['_mp_funnel_contact_pending']['option_value']);

        $failContact = false;
        $this->database->put('_mp_execute_activate', '1');
        $plugin->activatePlugin();

        $this->assertSame(['POST', 'PUT', 'PUT', 'PUT'], array_column($requests, 0));
        $this->assertSame('0', $this->database->tables['wp_options']['_mp_funnel_contact_pending']['option_value']);
    }

    public function testDatabaseReadErrorNeverBecomesPermissionToCreate(): void
    {
        $this->database->failReads = true;
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldNotReceive('request');
        $funnel = $this->funnel($http);
        $this->assertFalse($funnel->created());
        $funnel->create();
        $this->assertSame([['funnel', 'error', 'Error creating onboarding installation (step: eligibility)']], $this->events);
    }

    public function testUnavailableDiagnosticsCannotPropagateAnOnboardingFailureToThePage(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->andThrow(new \Error('private-upstream-diagnostic'));
        $funnel = $this->funnel($http);
        $logger = Mockery::mock();
        $logger->shouldReceive('error')->once()->andThrow(new \Error('private-logger-diagnostic'));
        $GLOBALS['mercadopago']->logs->file = $logger;
        $datadog = Mockery::mock(Datadog::class);
        $datadog->shouldReceive('sendEvent')->once()->with('funnel', 'error', 'Error creating onboarding installation (step: core)')
            ->andThrow(new \Error('private-metrics-diagnostic'));
        $this->setNotAccessibleProperty($funnel, 'datadog', $datadog);

        $funnel->create();

        $this->assertSame(['id' => '', 'key' => ''], (new InstallationStore($this->database))->read());
        $this->assertArrayNotHasKey(CreationLock::OPTION_NAME, $this->database->tables['wp_options']);
    }

    /** Records [method, decoded body] per Core call; POST bodies are not asserted here. */
    private function recordingHttp(array &$requests, ?\Closure $beforePut = null): HttpClientInterface
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->andReturnUsing(function ($method, $url, $headers, $body) use (&$requests, $beforePut) {
            if ($method === 'POST') {
                $requests[] = ['POST', null];
                return $this->registration();
            }
            $decoded = json_decode($body, true);
            $requests[] = ['PUT', $decoded];
            $this->assertSame('synthetic-key', $headers['x-cpp-token']);
            if ($beforePut) {
                $beforePut($decoded);
            }
            return new HttpResponse(204);
        });
        return $http;
    }

    /** The real activation entry point wired to the real Funnel and Store over the doubles. */
    private function activationPlugin(HttpClientInterface $http): WoocommerceMercadoPago
    {
        $store = new Store(new Options(), new InstallationStore($this->database));
        $plugin = (new ReflectionClass(WoocommerceMercadoPago::class))->newInstanceWithoutConstructor();
        $plugin->storeConfig = $store;
        $plugin->funnel = $this->funnel($http, $store);
        return $plugin;
    }

    private function registration(): HttpResponse
    {
        return new HttpResponse(201, ['x-cpp-token' => 'synthetic-key'], '', ['id' => 'synthetic-id']);
    }

    private function funnel(HttpClientInterface $http, ?Store $store = null): Funnel
    {
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getSiteId')->andReturn('');
        $seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn('');
        $gateways = Mockery::mock(Gateways::class);
        $gateways->shouldReceive('getEnabledPaymentGateways')->andReturn([]);
        $funnel = new Funnel(
            new FunnelClient(new CoreApiClient($http)),
            $store ?? new Store(new Options(), new InstallationStore($this->database)),
            $seller,
            new Country($seller),
            $gateways,
            new CreationLock($this->database)
        );
        $datadog = Mockery::mock(Datadog::class);
        $datadog->shouldReceive('sendEvent')->andReturnUsing(function (...$event) {
            $this->events[] = $event;
        });
        $this->setNotAccessibleProperty($funnel, 'datadog', $datadog);
        return $funnel;
    }
}
