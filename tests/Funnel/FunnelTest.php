<?php

namespace MercadoPago\Woocommerce\Tests\Funnel;

use MercadoPago\PP\Sdk\Common\Constants;
use MercadoPago\PP\Sdk\Entity\Identification\CreateSellerFunnelBase;
use MercadoPago\PP\Sdk\Entity\Identification\ResponseCreateSellerFunnelBase;
use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Funnel\Funnel;
use MercadoPago\Woocommerce\Funnel\UpdateSellerFunnelBase;
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

    /** @var MockInterface|Sdk */
    private $sdk;

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

        $this->funnel = new Funnel($this->store, $this->seller, $this->country, $this->gateways);

        // Sdk and Datadog are built internally by the Funnel constructor (new Sdk(),
        // Datadog::getInstance()), so they are swapped afterward via reflection.
        $this->sdk = Mockery::mock(Sdk::class);
        $this->datadog = Mockery::mock(Datadog::class);
        $this->datadog->shouldReceive('sendEvent')->byDefault();

        $this->setNotAccessibleProperty($this->funnel, 'sdk', $this->sdk);
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

        $this->assertSame($expectedSiteId, $entity->site_id);
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

        $this->assertNull($entity->site_id);
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

        $this->assertNull($entity->toArray()['site_id']);
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

        $this->assertSame('MLB', $entity->site_id);
    }

    public function testGivenFunnelAlreadyCreatedWhenCreateIsCalledThenSdkIsNeverRequested(): void
    {
        $this->markFunnelAsCreated();

        $this->sdk->shouldReceive('getCreateSellerFunnelBaseInstance')->never();

        $this->assertNull($this->funnel->create());
    }

    public function testGivenProductionCredentialsAlreadyPresentWhenCreateIsCalledThenSdkIsNeverRequested(): void
    {
        $this->store->shouldReceive('getInstallationId')->andReturn('');
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn('APP_USR-access-token');

        $this->sdk->shouldReceive('getCreateSellerFunnelBaseInstance')->never();

        $this->assertNull($this->funnel->create());
    }

    public function testGivenPaymentGatewaysAlreadyEnabledWhenCreateIsCalledThenSdkIsNeverRequested(): void
    {
        $this->store->shouldReceive('getInstallationId')->andReturn('');
        $this->seller->shouldReceive('getCredentialsAccessTokenProd')->andReturn('');
        $this->gateways->shouldReceive('getEnabledPaymentGateways')->andReturn(['woo-mercado-pago-pix']);

        $this->sdk->shouldReceive('getCreateSellerFunnelBaseInstance')->never();

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

        $payload = $entity->toArray();
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

        $this->assertSame('', $entity->toArray()['country']);
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
        $this->assertSame('PT', $entity->toArray()['country']);
    }

    public function testGivenUpdateFailsEchoingTheRequestBodyWhenSellerContactStepRunsThenEmailIsAbsentFromLogAndDatadog(): void
    {
        $email = 'leak-check@example.com';

        $this->markFunnelAsCreated();
        $this->mockStoreEmail($email);
        $this->mockWoocommerceDefaultCountry('BR');

        // The SDK can echo the request body verbatim (e.g. on a JSON encode failure),
        // which is the path that would carry the email into the log and Datadog.
        $entity = $this->mockUpdateSellerFunnelBaseEntity();
        $entity->shouldReceive('update')
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
        $this->assertSame('persistence-check@example.com', $entity->toArray()['email']);
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
     * Builds a partial mock of the real SDK entity (real __set/__get from
     * AbstractEntity keep running, only save() is stubbed), wires it as the instance
     * returned by the mocked Sdk, and expects the resulting id/cpp_token to be
     * persisted on the store — the same outcome production code relies on.
     *
     * @return MockInterface|CreateSellerFunnelBase
     */
    private function mockCreateSellerFunnelBaseEntity(string $id = 'installation-id', string $cppToken = 'installation-key')
    {
        $entity = Mockery::mock(CreateSellerFunnelBase::class)->makePartial();
        $entity->shouldReceive('save')->once()->andReturn(new ResponseCreateSellerFunnelBase($id, $cppToken));

        $this->sdk->shouldReceive('getCreateSellerFunnelBaseInstance')->once()->andReturn($entity);
        $this->store->shouldReceive('setInstallationId')->once()->with($id);
        $this->store->shouldReceive('setInstallationKey')->once()->with($cppToken);

        return $entity;
    }

    /**
     * Partial mock of the plugin's own UpdateSellerFunnelBase subclass, so assertions
     * read the payload through the real toArray() instead of a property set on a bare
     * double. That is what actually proves email/country survive the entity: an
     * undeclared property is discarded in silence by AbstractEntity::__set(), so a
     * full mock would pass even if the field never reached the request body.
     *
     * @return MockInterface|UpdateSellerFunnelBase
     */
    private function mockUpdateSellerFunnelBaseEntity()
    {
        // Partial mock naming only update(), plus the constructor argument: every
        // other method has to run for real, because AbstractEntity's constructor
        // initializes $excluded_properties and toArray() reads it.
        $entity = Mockery::mock(UpdateSellerFunnelBase::class . '[update]', [null]);
        $entity->shouldReceive('update')->once()->byDefault();

        $this->sdk->shouldReceive('getEntityInstance')
            ->once()
            ->with(UpdateSellerFunnelBase::class, Constants::BASEURL_MP)
            ->andReturn($entity);

        return $entity;
    }
}
