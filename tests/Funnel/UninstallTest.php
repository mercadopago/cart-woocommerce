<?php

namespace MercadoPago\Woocommerce\Tests\Funnel;

use MercadoPago\Woocommerce\Api\Core\CoreApiClient;
use MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException;
use MercadoPago\Woocommerce\Api\Core\FunnelClient;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Funnel\Uninstall;
use MercadoPago\Woocommerce\Hooks\Options;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class UninstallTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testGivenAnExistingInstallationWhenUninstalledThenOnlyIdAndDeletionFlagAreSent(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->expects()->request(
            'PUT',
            '/v1/eplatforms/core/identification/seller-configuration/update-integration',
            ['x-cpp-token' => 'synthetic-cpp-token', 'Content-Type' => 'application/json'],
            '{"id":"synthetic-installation","is_deleted":true}'
        )->andReturn(new HttpResponse(204));

        $this->assertTrue($this->createUninstall($http)->send());
    }

    /** @dataProvider missingIdentityProvider */
    public function testGivenIncompleteIdentityWhenUninstalledThenNoRequestIsSent(string $id, string $token): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldNotReceive('request');

        $this->assertFalse($this->createUninstall($http, $id, $token)->send());
    }

    public static function missingIdentityProvider(): array
    {
        return [
            'missing id' => ['', 'synthetic-cpp-token'],
            'missing token' => ['synthetic-installation', ''],
            'missing both' => ['', ''],
        ];
    }

    public function testGivenAnApiFailureWhenSendingThenItReachesTheUninstallBoundary(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->andReturn(new HttpResponse(500));

        $this->expectException(ApiException::class);

        $this->createUninstall($http)->send();
    }

    public function testGivenATransportFailureWhenSendingThenItReachesTheUninstallBoundary(): void
    {
        $http = Mockery::mock(HttpClientInterface::class);
        $http->shouldReceive('request')->once()->andThrow(new TransportException('http_request_failed'));

        $this->expectException(TransportException::class);

        $this->createUninstall($http)->send();
    }

    private function createUninstall(
        HttpClientInterface $http,
        string $id = 'synthetic-installation',
        string $token = 'synthetic-cpp-token'
    ): Uninstall {
        WP_Mock::userFunction('get_option')->once()->with('_mp_installation_id', '')->andReturn($id);
        WP_Mock::userFunction('get_option')->once()->with('_mp_installation_key', '')->andReturn($token);

        return new Uninstall(new Store(new Options()), new FunnelClient(new CoreApiClient($http)));
    }
}
