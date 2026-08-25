<?php

namespace MercadoPago\Woocommerce\Tests\Configs;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SellerTest extends TestCase
{
    use WoocommerceMock;

    private function buildSellerWithRequesterExpectation(
        string $expectedUri,
        array $expectedHeaders,
        bool $isTestMode,
        int $responseStatus = 200,
        array $responseData = [],
        string $productId = '',
        string $integratorId = ''
    ): Seller {
        Mockery::mock('alias:' . Device::class)
            ->shouldReceive('getDeviceProductId')
            ->andReturn($productId);

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn($responseStatus);
        $mockResponse->shouldReceive('getData')->andReturn($responseData);

        /** @var Requester&\Mockery\MockInterface $mockRequester */
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with($expectedUri, $expectedHeaders)
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('set')->andReturn(true);

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->andReturn(null);
        $mockCache->shouldReceive('setCache')->andReturn(null);

        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockStore->shouldReceive('isTestMode')->andReturn($isTestMode);
        $mockStore->shouldReceive('getIntegratorId')->andReturn($integratorId);

        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        return new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);
    }

    public function testUpdatePaymentMethodsUsesProdCoreEndpointWhenNotInTestMode(): void
    {
        $seller = $this->buildSellerWithRequesterExpectation(
            '/ppcore/prod/payment-methods/v1/payment-methods',
            ['x-platform-id: ' . MP_PLATFORM_ID, 'Authorization: test_public_key'],
            false
        );

        $seller->updatePaymentMethods('test_public_key');

        $this->addToAssertionCount(1);
    }

    public function testUpdatePaymentMethodsUsesBetaCoreEndpointWhenInTestMode(): void
    {
        $seller = $this->buildSellerWithRequesterExpectation(
            '/ppcore/beta/payment-methods/v1/payment-methods',
            ['x-platform-id: ' . MP_PLATFORM_ID, 'Authorization: test_public_key'],
            true
        );

        $seller->updatePaymentMethods('test_public_key');

        $this->addToAssertionCount(1);
    }

    public function testUpdatePaymentMethodsIncludesProductIdAndIntegratorIdWhenAvailable(): void
    {
        $seller = $this->buildSellerWithRequesterExpectation(
            '/ppcore/prod/payment-methods/v1/payment-methods',
            [
                'x-platform-id: ' . MP_PLATFORM_ID,
                'x-product-id: test_product_id',
                'x-integrator-id: test_integrator_id',
                'Authorization: test_public_key',
            ],
            false,
            200,
            [],
            'test_product_id',
            'test_integrator_id'
        );

        $seller->updatePaymentMethods('test_public_key');

        $this->addToAssertionCount(1);
    }

    public function testUpdatePaymentMethodsWithoutPublicKeyOmitsAuthorizationHeader(): void
    {
        Mockery::mock('alias:' . Device::class)
            ->shouldReceive('getDeviceProductId')
            ->andReturn('');

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn([]);

        /** @var Requester&\Mockery\MockInterface $mockRequester */
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with(
                '/ppcore/prod/payment-methods/v1/payment-methods',
                ['x-platform-id: ' . MP_PLATFORM_ID]
            )
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('set')->andReturn(true);
        $mockOptions->shouldReceive('get')->andReturn('');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->andReturn(null);
        $mockCache->shouldReceive('setCache')->andReturn(null);

        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockStore->shouldReceive('isTestMode')->andReturn(false);
        $mockStore->shouldReceive('getIntegratorId')->andReturn('');

        $mockLogsFile = Mockery::mock(File::class);
        $mockLogsFile->shouldReceive('warning')->once()->andReturn(null);

        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');
        $mockLogs->file = $mockLogsFile;

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $seller->updatePaymentMethods(null);

        $this->addToAssertionCount(1);
    }

    private function buildSellerForTicketTest(array $apiResponse, ?array &$capturedTicketMethods = null): Seller
    {
        Mockery::mock('alias:' . Device::class)
            ->shouldReceive('getDeviceProductId')
            ->andReturn('');

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn($apiResponse);

        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('set')
            ->andReturnUsing(function ($key, $value) use (&$capturedTicketMethods) {
                if ($key === '_all_payment_methods_ticket') {
                    $capturedTicketMethods = $value;
                }
                return true;
            });

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->andReturn(null);
        $mockCache->shouldReceive('setCache')->andReturn(null);

        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockStore->shouldReceive('isTestMode')->andReturn(false);
        $mockStore->shouldReceive('getIntegratorId')->andReturn('');

        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        return new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);
    }

    public function testSetupTicketPaymentMethodsExcludesConsumerCredits(): void
    {
        $apiResponse = [
            ['id' => 'bolbradesco',      'payment_type_id' => 'ticket',           'name' => 'Boleto',           'secure_thumbnail' => ''],
            ['id' => 'consumer_credits', 'payment_type_id' => 'digital_currency', 'name' => 'Linha de Crédito', 'secure_thumbnail' => ''],
        ];

        $capturedTicketMethods = null;
        $seller = $this->buildSellerForTicketTest($apiResponse, $capturedTicketMethods);
        $seller->updatePaymentMethods('test_public_key');

        $this->assertNotNull($capturedTicketMethods);
        $ids = array_column($capturedTicketMethods, 'id');
        $this->assertNotContains('consumer_credits', $ids);
    }

    public function testSetupTicketPaymentMethodsKeepsOfflinePaymentMethods(): void
    {
        $apiResponse = [
            ['id' => 'bolbradesco',      'payment_type_id' => 'ticket',           'name' => 'Boleto',           'secure_thumbnail' => ''],
            ['id' => 'paycash',          'payment_type_id' => 'ticket',           'name' => 'Paycash',          'secure_thumbnail' => ''],
            ['id' => 'consumer_credits', 'payment_type_id' => 'digital_currency', 'name' => 'Linha de Crédito', 'secure_thumbnail' => ''],
            ['id' => 'visa',             'payment_type_id' => 'credit_card',      'name' => 'Visa',             'secure_thumbnail' => ''],
        ];

        $capturedTicketMethods = null;
        $seller = $this->buildSellerForTicketTest($apiResponse, $capturedTicketMethods);
        $seller->updatePaymentMethods('test_public_key');

        $this->assertNotNull($capturedTicketMethods);
        $ids = array_column($capturedTicketMethods, 'id');
        $this->assertContains('bolbradesco', $ids);
        $this->assertContains('paycash', $ids);
        $this->assertNotContains('consumer_credits', $ids);
        $this->assertNotContains('visa', $ids);
    }

    public function testIsExpiredPublicKeyReturnsTrueWhenStatusIs401(): void
    {
        /** @var Requester&\Mockery\MockInterface $mockRequester */
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->once()->andReturn(401);

        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/plugins-credentials-wrapper/credentials?public_key=test_public_key', [])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $result = $seller->isExpiredPublicKey('test_public_key');

        $this->assertTrue($result);
    }

    public function testIsExpiredPublicKeyReturnsFalseWhenStatusIsNot401(): void
    {
        /** @var Requester&\Mockery\MockInterface $mockRequester */
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->once()->andReturn(200);

        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/plugins-credentials-wrapper/credentials?public_key=test_public_key', [])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $result = $seller->isExpiredPublicKey('test_public_key');

        $this->assertFalse($result);
    }

    public function testGetSiteIdReturnsSiteIdFromOptionsWhenSet(): void
    {
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldNotReceive('get');

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('mlb');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('MLB', $seller->getSiteId());
    }

    public function testGetSiteIdReturnsEmptyStringWhenSiteIdEmptyAndNoProdToken(): void
    {
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldNotReceive('get');

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('', $seller->getSiteId());
    }

    public function testGetSiteIdFetchesFromApiAndPersistsSiteIdWhenEmpty(): void
    {
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn(['site_id' => 'mlb', 'id' => 123]);

        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/users/me', ['Authorization: Bearer test-prod-token'])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('test-prod-token');
        $mockOptions->shouldReceive('set')
            ->once()
            ->with('_site_id_v1', 'MLB')
            ->andReturn(true);

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->once()->with('_site_id_recovery_failed')->andReturn(null);
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('MLB', $seller->getSiteId());
    }

    public function testGetSiteIdReturnsEmptyStringWhenApiReturnsNon200(): void
    {
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(500);
        $mockResponse->shouldReceive('getData')->andReturn([]);

        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/users/me', ['Authorization: Bearer test-prod-token'])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('test-prod-token');
        $mockOptions->shouldNotReceive('set');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->once()->with('_site_id_recovery_failed')->andReturn(null);
        $mockCache->shouldReceive('setCache')->once()->with('_site_id_recovery_failed', true, 21600)->andReturn(null);
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('', $seller->getSiteId());
    }

    public function testGetSiteIdLogsErrorAndReturnsEmptyWhenApiThrowsException(): void
    {
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/users/me', ['Authorization: Bearer test-prod-token'])
            ->andThrow(new \Exception('connection timeout'));

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('test-prod-token');
        $mockOptions->shouldNotReceive('set');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->once()->with('_site_id_recovery_failed')->andReturn(null);
        $mockCache->shouldReceive('setCache')->once()->with('_site_id_recovery_failed', true, 21600)->andReturn(null);
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');

        $mockLogsFile = Mockery::mock(File::class);
        $mockLogsFile->shouldReceive('error')->once()->andReturn(null);

        $mockLogs       = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');
        $mockLogs->file = $mockLogsFile;

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('', $seller->getSiteId());
    }

    public function testUpdatePaymentMethodsBySiteIdClearsPaymentMethodsAndSkipsApiWhenSiteIdEmpty(): void
    {
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldNotReceive('get');

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('');
        $mockOptions->shouldReceive('set')
            ->once()
            ->with('_site_id_payment_methods', [])
            ->andReturn(true);

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);
        $seller->updatePaymentMethodsBySiteId();

        $this->addToAssertionCount(1);
    }

    public function testUpdatePaymentMethodsBySiteIdSkipsGuardWhenSiteIdProvidedAsArgument(): void
    {
        Mockery::mock('alias:' . Device::class)
            ->shouldReceive('getDeviceProductId')
            ->andReturn('');

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn([]);

        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/sites/MLB/payment_methods', [])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')->andReturn([]);
        $mockOptions->shouldReceive('set')->andReturn(true);

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->andReturn(null);
        $mockCache->shouldReceive('setCache')->andReturn(null);

        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);
        $seller->updatePaymentMethodsBySiteId('MLB');

        $this->addToAssertionCount(1);
    }

    public function testGetSiteIdDoesNotPersistWhenApiReturnsMissingSiteId(): void
    {
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn(['id' => 123]);

        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/users/me', ['Authorization: Bearer test-prod-token'])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('test-prod-token');
        $mockOptions->shouldNotReceive('set');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->once()->with('_site_id_recovery_failed')->andReturn(null);
        $mockCache->shouldReceive('setCache')->once()->with('_site_id_recovery_failed', true, 21600)->andReturn(null);
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('', $seller->getSiteId());
    }

    public function testGetSiteIdDoesNotPersistUnexpectedSiteIdFromApi(): void
    {
        // An arbitrary upstream value must never reach setSiteId — otherwise it would flow into
        // the /sites/{siteId}/payment_methods route. Only known marketplaces are accepted.
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn(['site_id' => '../../admin', 'id' => 123]);

        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/users/me', ['Authorization: Bearer test-prod-token'])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('test-prod-token');
        $mockOptions->shouldNotReceive('set');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->once()->with('_site_id_recovery_failed')->andReturn(null);
        $mockCache->shouldReceive('setCache')->once()->with('_site_id_recovery_failed', true, 21600)->andReturn(null);
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('', $seller->getSiteId());
    }

    public function testGetSiteIdFetchesUsersMeOnlyOnceWhenReentered(): void
    {
        // Reproduces the reentrancy path: a metric emitted from within fetchUserData() re-enters
        // getSiteId(). The negative memo set before the network call must short-circuit the
        // reentrant call so /users/me is hit exactly once.
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn(['site_id' => 'mlb', 'id' => 123]);

        $seller          = null;
        $reentrantResult = 'not-called';

        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/users/me', ['Authorization: Bearer test-prod-token'])
            ->andReturnUsing(function () use (&$seller, &$reentrantResult, $mockResponse) {
                $reentrantResult = $seller->getSiteId();
                return $mockResponse;
            });

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('test-prod-token');
        $mockOptions->shouldReceive('set')
            ->once()
            ->with('_site_id_v1', 'MLB')
            ->andReturn(true);

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->once()->with('_site_id_recovery_failed')->andReturn(null);
        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('MLB', $seller->getSiteId());
        $this->assertSame('', $reentrantResult);
    }

    public function testGetSiteIdSkipsApiWhenNegativeCacheIsSet(): void
    {
        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldNotReceive('get');

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->with('_mp_access_token_prod', '')
            ->andReturn('test-prod-token');

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')
            ->once()
            ->with('_site_id_recovery_failed')
            ->andReturn(true);

        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $this->assertSame('', $seller->getSiteId());
    }

    public function testGetSiteIdMemoizesResultPerRequest(): void
    {
        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('getStatus')->andReturn(200);
        $mockResponse->shouldReceive('getData')->andReturn(['site_id' => 'mla', 'id' => 456]);

        $mockRequester = Mockery::mock('overload:MercadoPago\Woocommerce\Helpers\Requester');
        $mockRequester->shouldReceive('get')
            ->once()
            ->with('/users/me', ['Authorization: Bearer test-prod-token'])
            ->andReturn($mockResponse);

        $mockOptions = Mockery::mock('MercadoPago\Woocommerce\Hooks\Options');
        $mockOptions->shouldReceive('get')
            ->once()
            ->with('_site_id_v1', '')
            ->andReturn('');
        $mockOptions->shouldReceive('get')
            ->once()
            ->with('_mp_access_token_prod', '')
            ->andReturn('test-prod-token');
        $mockOptions->shouldReceive('set')
            ->once()
            ->with('_site_id_v1', 'MLA')
            ->andReturn(true);

        $mockCache = Mockery::mock('MercadoPago\Woocommerce\Helpers\Cache');
        $mockCache->shouldReceive('getCache')->once()->with('_site_id_recovery_failed')->andReturn(null);

        $mockStore = Mockery::mock('MercadoPago\Woocommerce\Configs\Store');
        $mockLogs  = Mockery::mock('MercadoPago\Woocommerce\Libraries\Logs\Logs');

        $seller = new Seller($mockCache, $mockOptions, $mockRequester, $mockStore, $mockLogs);

        $first  = $seller->getSiteId();
        $second = $seller->getSiteId();

        $this->assertSame('MLA', $first);
        $this->assertSame('MLA', $second);
    }
}
