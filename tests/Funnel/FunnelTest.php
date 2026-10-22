<?php

namespace MercadoPago\Woocommerce\Tests\Funnel;

use MercadoPago\Woocommerce\Api\Core\FunnelClient;
use MercadoPago\Woocommerce\Api\Core\Models\FunnelRegistration;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Funnel\Funnel;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Gateways;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperty;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class FunnelTest extends TestCase
{
    use SetNotAccessibleProperty;

    private Funnel $funnel;

    /** @var MockInterface|Store */
    private $store;

    /** @var MockInterface|Seller */
    private $seller;

    private Country $country;

    /** @var MockInterface|Gateways */
    private $gateways;

    /** @var MockInterface|FunnelClient */
    private $client;

    /** @var MockInterface|Datadog */
    private $datadog;

    public function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->store = Mockery::mock(Store::class);
        $this->seller = Mockery::mock(Seller::class);
        // Real instance, not a mock: Funnel reaches Country statically, both through
        // this property (create()) and on the class itself (updateStepSellerContact()).
        // Mockery generates one shared mock class per real class name, so whether a
        // static call falls through to the real implementation depends on whatever the
        // *first* Country mock created anywhere in the PHPUnit process looked like —
        // order-dependent across the whole suite, not just this file. Using the real
        // class (with Seller mocked) sidesteps that and also matches how the code is
        // actually exercised in production.
        $this->country = new Country($this->seller);
        $this->gateways = Mockery::mock(Gateways::class);

        $this->client = Mockery::mock(FunnelClient::class);
        $this->funnel = new Funnel($this->client, $this->store, $this->seller, $this->country, $this->gateways);

        $this->datadog = Mockery::mock(Datadog::class);
        $this->datadog->shouldReceive('sendEvent')->byDefault();

        $this->setNotAccessibleProperty($this->funnel, 'datadog', $this->datadog);

        $GLOBALS['mercadopago'] = (object) [
            'logs' => (object) [
                'file' => Mockery::mock(),
            ],
        ];
        $GLOBALS['mercadopago']->logs->file->shouldReceive('error')->byDefault();
    }

    public function tearDown(): void
    {
        unset($GLOBALS['mercadopago']);
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * @dataProvider mpSiteIdCountryProvider
     */
    public function testGivenCountryAmongTheSevenMpSiteIdsWhenCreateRunsThenSiteIdIsSetToTheCorrespondingSite(
        string $country,
        string $expectedSiteId
    ): void {
        $this->allowFunnelCreation();
        $this->mockWoocommerceDefaultCountry($country);
        WP_Mock::userFunction('site_url')->once()->andReturn('https://store.example.com');

        $entity = $this->mockCreateSellerFunnelBaseEntity();

        $this->funnel->create();

        $this->assertSame($expectedSiteId, $entity->payload['site_id']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function mpSiteIdCountryProvider(): array
    {
        return [
            'Argentina' => ['AR', 'MLA'],
            'Brazil'    => ['BR', 'MLB'],
            'Chile'     => ['CL', 'MLC'],
            'Mexico'    => ['MX', 'MLM'],
            'Colombia'  => ['CO', 'MCO'],
            'Uruguay'   => ['UY', 'MLU'],
            'Peru'      => ['PE', 'MPE'],
        ];
    }

    public function testGivenCountryOutsideTheSevenMpSiteIdsWhenCreateRunsThenSiteIdIsNullWithoutException(): void
    {
        $this->allowFunnelCreation();
        $this->mockWoocommerceDefaultCountry('PT');
        WP_Mock::userFunction('site_url')->once()->andReturn('https://store.example.com');

        $entity = $this->mockCreateSellerFunnelBaseEntity();

        $this->funnel->create();

        $this->assertNull($entity->payload['site_id']);
    }

    /**
     * The two call sites had drifted: create() normalized the empty site id, this one did not.
     */
    public function testGivenCountryOutsideTheSevenMpSiteIdsWhenCredentialsStepRunsThenSiteIdIsNullNotEmptyString(): void
    {
        $this->markFunnelAsCreated();
        $this->mockWoocommerceDefaultCountry('PT');
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn('');
        $this->seller->shouldReceive('getCredentialsAccessTokenTest')->andReturn('');
        $this->seller->shouldReceive('getCustIdFromAT')->andReturn('');
        $this->store->shouldReceive('isProductionMode')->andReturn(false);

        $entity = $this->mockUpdateSellerFunnelBaseEntity();

        $this->funnel->updateStepCredentials();

        $this->assertNull($entity->payload['site_id']);
    }

    /**
     * An account on a site the plugin does not support writes its real site id through the
     * integration webhook, which persists it unvalidated. The funnel must not report that store
     * as Argentine — payment method availability is left alone on purpose, see traps.md.
     */
    public function testGivenAnUnsupportedPersistedSiteIdWhenCreateRunsThenTheStoreCountryIsSentInstead(): void
    {
        $this->store->shouldReceive('getInstallationId')->andReturn('');
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn('');
        $this->gateways->shouldReceive('getEnabledPaymentGateways')->andReturn([]);
        $this->seller->shouldReceive('getSiteId')->andReturn('MEC');
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_default_country', '')
            ->andReturn('BR:SP');
        WP_Mock::userFunction('site_url')->once()->andReturn('https://store.example.com');

        $entity = $this->mockCreateSellerFunnelBaseEntity();

        $this->funnel->create();

        $this->assertSame('MLB', $entity->payload['site_id']);
    }

    public function testGivenFunnelAlreadyCreatedWhenCreateIsCalledThenClientIsNeverRequested(): void
    {
        $this->markFunnelAsCreated();

        $this->client->shouldReceive('create')->never();

        $this->assertNull($this->funnel->create());
    }

    public function testGivenProductionCredentialsAlreadyPresentWhenCreateIsCalledThenClientIsNeverRequested(): void
    {
        $this->store->shouldReceive('getInstallationId')->andReturn('');
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn('APP_USR-access-token');

        $this->client->shouldReceive('create')->never();

        $this->assertNull($this->funnel->create());
    }

    public function testGivenPaymentGatewaysAlreadyEnabledWhenCreateIsCalledThenClientIsNeverRequested(): void
    {
        $this->store->shouldReceive('getInstallationId')->andReturn('');
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn('');
        $this->gateways->shouldReceive('getEnabledPaymentGateways')->andReturn(['woo-mercado-pago-pix']);

        $this->client->shouldReceive('create')->never();

        $this->assertNull($this->funnel->create());
    }

    public function testGivenCanCreateWhenCreateSucceedsThenInstallationIdAndKeyArePersistedAndAfterCallbackRuns(): void
    {
        $this->allowFunnelCreation();
        $this->mockWoocommerceDefaultCountry('BR');
        WP_Mock::userFunction('site_url')->once()->andReturn('https://store.example.com');

        $this->mockCreateSellerFunnelBaseEntity('installation-id', 'installation-key');

        $afterRan = false;
        $this->funnel->create(function () use (&$afterRan) {
            $afterRan = true;
        });

        $this->assertTrue($afterRan, 'the after callback must run once the funnel is created');
    }

    public function testGivenStoreConfiguredForBrazilWhenSellerContactStepRunsThenEmailAndIsoCountryReachThePayload(): void
    {
        $this->markFunnelAsCreated();
        $this->mockStoreEmail('seller@example.com');
        // "BR:SP" is what WooCommerce stores for a state-qualified country.
        $this->mockWoocommerceDefaultCountry('BR:SP');

        $entity = $this->mockUpdateSellerFunnelBaseEntity();

        $this->funnel->updateStepSellerContact();

        $payload = $entity->payload;
        $this->assertSame('seller@example.com', $payload['email']);
        $this->assertSame('BR', $payload['country']);
    }

    public function testGivenStoreWithoutDefaultCountryWhenSellerContactStepRunsThenFlowCompletesWithEmptyCountry(): void
    {
        $this->markFunnelAsCreated();
        $this->mockStoreEmail('seller@example.com');
        $this->mockWoocommerceDefaultCountry('');

        $entity = $this->mockUpdateSellerFunnelBaseEntity();

        $this->datadog->shouldReceive('sendEvent')->once()->with('funnel', 'success');

        $this->funnel->updateStepSellerContact();

        $this->assertSame('', $entity->payload['country']);
    }

    public function testGivenStoreOutsideTheMercadoPagoSitesWhenSellerContactStepRunsThenIsoCountryStillReachesThePayload(): void
    {
        $this->markFunnelAsCreated();
        $this->mockStoreEmail('seller@example.com');
        $this->mockWoocommerceDefaultCountry('PT');

        $entity = $this->mockUpdateSellerFunnelBaseEntity();

        $this->funnel->updateStepSellerContact();

        // Unlike site_id on create(), country is not allowlisted — reaching sellers
        // outside the seven site ids is the whole point of this step.
        $this->assertSame('PT', $entity->payload['country']);
    }

    public function testGivenUpdateFailsEchoingTheRequestBodyWhenSellerContactStepRunsThenEmailIsAbsentFromLogAndDatadog(): void
    {
        $email = 'leak-check@example.com';

        $this->markFunnelAsCreated();
        $this->mockStoreEmail($email);
        $this->mockWoocommerceDefaultCountry('BR');

        // The API client can echo the request body verbatim (e.g. on a JSON encode failure),
        // which is the path that would carry the email into the log and Datadog.
        $this->mockUpdateSellerFunnelBaseEntity();
        $this->client->shouldReceive('update')
            ->once()
            ->andThrow(new \Exception('JSON Error [5] - Data: {"email":"' . $email . '"}'));

        $loggedMessage = null;
        $GLOBALS['mercadopago']->logs->file->shouldReceive('error')
            ->once()
            ->andReturnUsing(function ($message) use (&$loggedMessage) {
                $loggedMessage = $message;
            });

        $datadogMessage = null;
        $this->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('funnel', 'error', Mockery::on(function ($message) use (&$datadogMessage) {
                $datadogMessage = $message;
                return true;
            }));

        $this->funnel->updateStepSellerContact();

        $this->assertStringNotContainsString($email, (string) $loggedMessage);
        $this->assertStringNotContainsString($email, (string) $datadogMessage);
    }

    public function testGivenSellerContactStepRunsThenTheEmailIsNeverWrittenBackToTheStore(): void
    {
        $this->markFunnelAsCreated();
        $this->mockStoreEmail('persistence-check@example.com');
        $this->mockWoocommerceDefaultCountry('BR');

        // The email is read from the option at send time and never written back:
        // persisting it would put PII in wp_options, readable by any admin and
        // carried along in database dumps (CWE-312). This fences the likely
        // regression — caching the address in an option to avoid re-reading it —
        // and not every possible write: $store is a mock here, so a setter on it
        // would go unnoticed.
        WP_Mock::userFunction('update_option')->never();

        $entity = $this->mockUpdateSellerFunnelBaseEntity();

        $this->funnel->updateStepSellerContact();

        // Asserting the payload keeps the never() expectation honest: without it an
        // early return would satisfy "update_option was never called" while proving
        // nothing, since the step would not have run at all.
        $this->assertSame('persistence-check@example.com', $entity->payload['email']);
    }

    /**
     * Mocks the guard conditions so canCreate() returns true: not created yet, no
     * production credential and no payment gateway enabled.
     */
    private function allowFunnelCreation(): void
    {
        $this->store->shouldReceive('getInstallationId')->andReturn('');
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn('');
        $this->gateways->shouldReceive('getEnabledPaymentGateways')->andReturn([]);
    }

    /**
     * Mocks the opposite of allowFunnelCreation(): the funnel record already exists,
     * which is the precondition every update step checks through created().
     */
    private function markFunnelAsCreated(): void
    {
        $this->store->shouldReceive('getInstallationId')->andReturn('installation-id');
        $this->store->shouldReceive('getInstallationKey')->andReturn('installation-key');
    }

    /**
     * Drives Country::getWoocommerceDefaultCountry() — reached both directly by
     * updateStepSellerContact() and through getPluginDefaultCountry() by create(),
     * whose fallback requires no site_id recovered from the seller (which matches
     * canCreate(), already requiring an empty production access token).
     */
    private function mockWoocommerceDefaultCountry(string $country): void
    {
        $this->seller->shouldReceive('getSiteId')->andReturn('');
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('woocommerce_default_country', '')
            ->andReturn($country);
    }

    private function mockStoreEmail(string $email): void
    {
        WP_Mock::userFunction('get_option')
            ->once()
            ->with('admin_email', '')
            ->andReturn($email);
    }

    /**
     * Captures the create payload and returns a valid registration model.
     */
    private function mockCreateSellerFunnelBaseEntity(string $id = 'installation-id', string $cppToken = 'installation-key')
    {
        $capture = (object) ['payload' => []];
        $this->client->shouldReceive('create')->once()->andReturnUsing(
            function (array $payload) use ($capture, $id, $cppToken) {
                $capture->payload = $payload;
                return new FunnelRegistration(['id' => $id], $cppToken);
            }
        );
        $this->store->shouldReceive('setInstallationId')->once()->with($id);
        $this->store->shouldReceive('setInstallationKey')->once()->with($cppToken);

        return $capture;
    }

    /**
     * Captures the update payload sent to the resource client.
     */
    private function mockUpdateSellerFunnelBaseEntity()
    {
        $capture = (object) ['payload' => []];
        $this->client->shouldReceive('update')
            ->once()
            ->with(Mockery::type('array'), 'installation-key')
            ->andReturnUsing(function (array $payload) use ($capture) {
                $capture->payload = $payload;
            })
            ->byDefault();

        return $capture;
    }
}
