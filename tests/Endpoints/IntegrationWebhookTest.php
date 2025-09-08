<?php

namespace MercadoPago\Woocommerce\Tests\Endpoints;

use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Endpoints\IntegrationWebhook;
use Mockery;
use WP_Mock;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Hooks\Plugin;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\PP\Sdk\HttpClient\Response;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IntegrationWebhookTest extends TestCase
{
    use WoocommerceMock;

    public function setUp(): void
    {
        WP_Mock::userFunction('admin_url', [
            'return' => 'url'
        ]);
    }

    public function testWebhookHandlerWithoutIntegrationId(): void
    {
        $sellerConfigMock = Mockery::mock(Seller::class);
        $storeConfigMock = Mockery::mock(Store::class);
        $requesterMock = Mockery::mock(Requester::class);
        $endpointsMock = Mockery::mock(Endpoints::class);
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = Mockery::mock(File::class);
        $pluginHookMock = Mockery::mock(Plugin::class);

        $_GET = [];
        $endpointsMock->shouldReceive('registerApiEndpoint')->once()->withArgs(['WC_WooMercadoPago_Integration_Webhook', Mockery::type('callable')]);
        $logsMock->file->shouldReceive('error')->once()->withArgs(["Missing integration_id in Integration Webhook request", IntegrationWebhook::class]);

        $integrationWebhook = new IntegrationWebhook(
            $sellerConfigMock,
            $storeConfigMock,
            $requesterMock,
            $endpointsMock,
            $logsMock,
            $pluginHookMock
        );

        $this->assertFalse($integrationWebhook->webhookHandler());
    }

    public function testWebhookHandlerSuccess(): void
    {
        $sellerConfigMock = Mockery::mock(Seller::class);
        $storeConfigMock = Mockery::mock(Store::class);
        $requesterMock = Mockery::mock(Requester::class);
        $endpointsMock = Mockery::mock(Endpoints::class);
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = Mockery::mock(File::class);
        $pluginHookMock = Mockery::mock(Plugin::class);

        Mockery::mock('overload:' . Form::class)
            ->shouldReceive('sanitizedGetData')
            ->once()
            ->withArgs(['integration_id'])
            ->andReturn('integration_id');

        $_GET = ['integration_id' => 'integration_id'];

        $endpointsMock
            ->shouldReceive('registerApiEndpoint')
            ->once()
            ->withArgs(['WC_WooMercadoPago_Integration_Webhook', Mockery::type('callable')]);

        $storeConfigMock
            ->shouldReceive('getCodeVerifier')
            ->once()
            ->andReturn('code_verifier');

        $sellerConfigMock
            ->shouldReceive('getDeviceFingerprint')
            ->once()
            ->andReturn('device_fingerprint');

        $logsMock->file
            ->shouldReceive('info')
            ->once()
            ->withArgs(['Received webhook with integration_id: integration_id', IntegrationWebhook::class]);

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(200);
        $responseMock->shouldReceive('getData')->andReturn([
            'credentials' => [
                'production' => [
                    'access_token' => 'prod_access_token',
                    'public_key' => 'prod_public_key',
                ],
                'test' => [
                    'access_token' => 'test_access_token',
                    'public_key' => 'test_public_key',
                ],
            ],
        ]);

        $requesterMock
            ->shouldReceive('get')
            ->once()
            ->withArgs([
                '/ppcore/prod/configurations-api/onboarding/v1/integration/integration_id?code_verifier=code_verifier',
                ['X-Device-Fingerprint' => 'device_fingerprint']
            ])
            ->andReturn($responseMock);

        $logsMock->file
            ->shouldReceive('info')
            ->once()
            ->withArgs(['API request successful for integration_id: integration_id, got credentials.', IntegrationWebhook::class]);

        $logsMock->file
            ->shouldNotReceive('error');

        $sellerConfigMock
            ->shouldReceive('validateCredentials')
            ->once()
            ->withArgs(['prod_access_token', 'prod_public_key'])
            ->andReturn(['status' => 200, 'data' => ['homologated' => true, 'client_id' => 'prod_client_id']]);

        $sellerConfigMock
            ->shouldReceive('validateCredentials')
            ->once()
            ->withArgs(['test_access_token', 'test_public_key'])
            ->andReturn(['status' => 200, 'data' => ['homologated' => true, 'client_id' => 'test_client_id', 'is_test' => true]]);

        $sellerConfigMock
            ->shouldReceive('getSellerInfo')
            ->once()
            ->withArgs(['prod_access_token'])
            ->andReturn(['status' => 200, 'data' => ['site_id' => 'prod_site_id', 'tags' => ['test_user']]]);

        $storeConfigMock
            ->shouldReceive('setCheckoutCountry')
            ->once()
            ->withArgs(['prod_site_id']);

        $sellerConfigMock
            ->shouldReceive('setSiteId')
            ->once()
            ->withArgs(['prod_site_id']);

        $sellerConfigMock
            ->shouldReceive('setTestUser')
            ->once()
            ->withArgs([true]);

        $sellerConfigMock
            ->shouldReceive('setHomologValidate')
            ->once()
            ->withArgs([true]);

        $sellerConfigMock
            ->shouldReceive('setClientId')
            ->once()
            ->withArgs(['prod_client_id']);

        $sellerConfigMock
            ->shouldReceive('setCredentialsAccessTokenProd')
            ->once()
            ->withArgs(['prod_access_token']);

        $sellerConfigMock
            ->shouldReceive('setCredentialsAccessTokenTest')
            ->once()
            ->withArgs(['test_access_token']);

        $sellerConfigMock
            ->shouldReceive('setCredentialsPublicKeyProd')
            ->once()
            ->withArgs(['prod_public_key']);

        $sellerConfigMock
            ->shouldReceive('setCredentialsPublicKeyTest')
            ->once()
            ->withArgs(['test_public_key']);

        $pluginHookMock
            ->shouldReceive('executeUpdateCredentialAction')
            ->once();

        $logsMock->file
            ->shouldReceive('info')
            ->once()
            ->withArgs(['Stored validated credentials successfully for integration_id: integration_id', IntegrationWebhook::class]);

        $integrationWebhook = Mockery::mock(IntegrationWebhook::class, [
            $sellerConfigMock,
            $storeConfigMock,
            $requesterMock,
            $endpointsMock,
            $logsMock,
            $pluginHookMock
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $integrationWebhook->shouldReceive('getRedirectUrlBasedOnCredentialsState')
            ->once()
            ->andReturn('admin.php?page=mercadopago-settings');

        $integrationWebhook->shouldReceive('sendUserTo')->andReturn(true);

        $this->assertTrue($integrationWebhook->webhookHandler());
    }

    public function testWebhookHandlerWithErrorInCoreCall(): void
    {
        $sellerConfigMock = Mockery::mock(Seller::class);
        $storeConfigMock = Mockery::mock(Store::class);
        $requesterMock = Mockery::mock(Requester::class);
        $endpointsMock = Mockery::mock(Endpoints::class);
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = Mockery::mock(File::class);
        $pluginHookMock = Mockery::mock(Plugin::class);

        Mockery::mock('overload:' . Form::class)
            ->shouldReceive('sanitizedGetData')
            ->once()
            ->withArgs(['integration_id'])
            ->andReturn('integration_id');

        $_GET = ['integration_id' => 'integration_id'];

        $endpointsMock
            ->shouldReceive('registerApiEndpoint')
            ->once()
            ->withArgs(['WC_WooMercadoPago_Integration_Webhook', Mockery::type('callable')]);

        $storeConfigMock
            ->shouldReceive('getCodeVerifier')
            ->once()
            ->andReturn('code_verifier');

        $sellerConfigMock
            ->shouldReceive('getDeviceFingerprint')
            ->once()
            ->andReturn('device_fingerprint');

        $logsMock->file
            ->shouldReceive('info')
            ->once()
            ->withArgs(['Received webhook with integration_id: integration_id', IntegrationWebhook::class]);

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getData')
            ->once()
            ->andReturn([
                'original_message' => 'Simulated error message'
            ]);
        $responseMock->shouldReceive('getStatus')->andReturn(500);

        $requesterMock
            ->shouldReceive('get')
            ->once()
            ->withArgs([
                '/ppcore/prod/configurations-api/onboarding/v1/integration/integration_id?code_verifier=code_verifier',
                ['X-Device-Fingerprint' => 'device_fingerprint']
            ])
            ->andReturn($responseMock);

        $logsMock->file
            ->shouldReceive('error')
            ->once()
            ->withArgs([
                "API request failed for integration_id: integration_id. Device: device_fingerprint Status code: 500 Original Message: Simulated error message",
                IntegrationWebhook::class
            ]);

        $integrationWebhook = new IntegrationWebhook(
            $sellerConfigMock,
            $storeConfigMock,
            $requesterMock,
            $endpointsMock,
            $logsMock,
            $pluginHookMock
        );

        $this->assertFalse($integrationWebhook->webhookHandler());
    }

    public function testWebhookHandlerExceptionError(): void
    {
        $sellerConfigMock = Mockery::mock(Seller::class);
        $storeConfigMock = Mockery::mock(Store::class);
        $requesterMock = Mockery::mock(Requester::class);
        $endpointsMock = Mockery::mock(Endpoints::class);
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = Mockery::mock(File::class);
        $pluginHookMock = Mockery::mock(Plugin::class);

        Mockery::mock('overload:' . Form::class)
            ->shouldReceive('sanitizedGetData')
            ->once()
            ->withArgs(['integration_id'])
            ->andReturn('integration_id');

        $_GET = ['integration_id' => 'integration_id'];

        $endpointsMock
            ->shouldReceive('registerApiEndpoint')
            ->once()
            ->withArgs(['WC_WooMercadoPago_Integration_Webhook', Mockery::type('callable')]);

        $storeConfigMock
            ->shouldReceive('getCodeVerifier')
            ->once()
            ->andReturn('code_verifier');

        $sellerConfigMock
            ->shouldReceive('getDeviceFingerprint')
            ->once();

        $logsMock->file
            ->shouldReceive('info')
            ->once()
            ->withArgs(['Received webhook with integration_id: integration_id', IntegrationWebhook::class]);

        $requesterMock
            ->shouldReceive('get')
            ->once()
            ->withArgs([
                '/ppcore/prod/configurations-api/onboarding/v1/integration/integration_id?code_verifier=code_verifier',
                ['X-Device-Fingerprint' => '']
            ])
            ->andThrow(new \Exception('Error'));

        $logsMock->file
            ->shouldReceive('error')
            ->once()
            ->withArgs(["Error in credentials update workflow: Error", IntegrationWebhook::class]);

        $integrationWebhook = new IntegrationWebhook(
            $sellerConfigMock,
            $storeConfigMock,
            $requesterMock,
            $endpointsMock,
            $logsMock,
            $pluginHookMock
        );

        $this->assertFalse($integrationWebhook->webhookHandler());
    }

    public function testGetRedirectUrlBasedOnCredentialsStateWithValidCredentials(): void
    {
        $sellerConfigMock = Mockery::mock(Seller::class);
        $storeConfigMock = Mockery::mock(Store::class);
        $requesterMock = Mockery::mock(Requester::class);
        $endpointsMock = Mockery::mock(Endpoints::class);
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = Mockery::mock(File::class);
        $pluginHookMock = Mockery::mock(Plugin::class);

        $endpointsMock
            ->shouldReceive('registerApiEndpoint')
            ->once()
            ->withArgs([IntegrationWebhook::WEBHOOK_ENDPOINT, Mockery::type('callable')]);

        $sellerConfigMock
            ->shouldReceive('getCredentialsPublicKeyProd')
            ->once()
            ->andReturn('prod_public_key');

        $sellerConfigMock
            ->shouldReceive('isExpiredPublicKey')
            ->once()
            ->withArgs(['prod_public_key'])
            ->andReturn(false);

        $integrationWebhook = new IntegrationWebhook(
            $sellerConfigMock,
            $storeConfigMock,
            $requesterMock,
            $endpointsMock,
            $logsMock,
            $pluginHookMock
        );

        $this->assertEquals('admin.php?page=mercadopago-settings', $integrationWebhook->getRedirectUrlBasedOnCredentialsState());
    }

    public function testGetRedirectUrlBasedOnCredentialsStateWithExpiredCredentials(): void
    {
        $sellerConfigMock = Mockery::mock(Seller::class);
        $storeConfigMock = Mockery::mock(Store::class);
        $requesterMock = Mockery::mock(Requester::class);
        $endpointsMock = Mockery::mock(Endpoints::class);
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = Mockery::mock(File::class);
        $pluginHookMock = Mockery::mock(Plugin::class);

        $endpointsMock
            ->shouldReceive('registerApiEndpoint')
            ->once()
            ->withArgs([IntegrationWebhook::WEBHOOK_ENDPOINT, Mockery::type('callable')]);

        $sellerConfigMock
            ->shouldReceive('getCredentialsPublicKeyProd')
            ->once()
            ->andReturn('prod_public_key');

        $sellerConfigMock
            ->shouldReceive('isExpiredPublicKey')
            ->once()
            ->withArgs(['prod_public_key'])
            ->andReturn(true);

        $integrationWebhook = new IntegrationWebhook(
            $sellerConfigMock,
            $storeConfigMock,
            $requesterMock,
            $endpointsMock,
            $logsMock,
            $pluginHookMock
        );

        $this->assertEquals('admin.php?page=mercadopago-settings&link_updated=true', $integrationWebhook->getRedirectUrlBasedOnCredentialsState());
    }
}
