<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Verifies that AutomaticPaymentsClient routes requests to the correct AP v2
 * base path depending on the isTestMode flag passed at construction time.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AutomaticPaymentsClientBaseUrlTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        \Mockery::close();
    }

    /* ───────────────────────── basePath resolution ───────────────────────── */

    public function testBasePathConstantOverridesResolution(): void
    {
        define(AutomaticPaymentsClient::BASE_PATH_CONSTANT, '/custom/base/path');

        $requester = \Mockery::mock(Requester::class);
        $requester->shouldReceive('post')
            ->once()
            ->withArgs(fn($path) => strpos($path, '/custom/base/path') === 0)
            ->andReturn($this->makeResponse(201));

        $logs       = \Mockery::mock(Logs::class)->shouldIgnoreMissing();
        $logs->file = \Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class)->shouldIgnoreMissing();

        $client = new AutomaticPaymentsClient(
            $requester,
            MercadoPagoMock::getWoocommerceMercadoPagoMock()->subscriptionsHelper,
            $logs,
            false
        );
        $client->mit('token', ['subscription' => ['id' => 'S1'], 'transaction' => ['external_reference' => 'R1']], 'k');
        $this->addToAssertionCount(1);
    }

    public function testBasePathConstantIgnoredWhenEmpty(): void
    {
        define(AutomaticPaymentsClient::BASE_PATH_CONSTANT, '');

        $requester = \Mockery::mock(Requester::class);
        $requester->shouldReceive('post')
            ->once()
            ->withArgs(fn($path) => strpos($path, '/homol') === false && strpos($path, '/plugins-platforms') === 0)
            ->andReturn($this->makeResponse(201));

        $logs       = \Mockery::mock(Logs::class)->shouldIgnoreMissing();
        $logs->file = \Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class)->shouldIgnoreMissing();

        $client = new AutomaticPaymentsClient(
            $requester,
            MercadoPagoMock::getWoocommerceMercadoPagoMock()->subscriptionsHelper,
            $logs,
            false
        );
        $client->mit('token', ['subscription' => ['id' => 'S1'], 'transaction' => ['external_reference' => 'R1']], 'k');
        $this->addToAssertionCount(1);
    }

    public function testProductionModeUsesPathWithoutHomolPrefix(): void
    {
        $requester = \Mockery::mock(Requester::class);
        $requester->shouldReceive('post')
            ->once()
            ->withArgs(function (string $path) {
                return strpos($path, '/homol') === false
                    && strpos($path, '/plugins-platforms/automatic-payments/v2') === 0;
            })
            ->andReturn($this->makeResponse(201));

        $logs       = \Mockery::mock(Logs::class)->shouldIgnoreMissing();
        $logs->file = \Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class)->shouldIgnoreMissing();

        $client = new AutomaticPaymentsClient(
            $requester,
            MercadoPagoMock::getWoocommerceMercadoPagoMock()->subscriptionsHelper,
            $logs,
            false
        );

        WP_Mock::userFunction('wp_get_attachment_url')->andReturn('');

        $client->mit('token', ['subscription' => ['id' => 'S1'], 'transaction' => ['external_reference' => 'R1']], 'idem-key');
        $this->addToAssertionCount(1);
    }

    public function testTestModeUsesPathWithHomolPrefix(): void
    {
        $requester = \Mockery::mock(Requester::class);
        $requester->shouldReceive('post')
            ->once()
            ->withArgs(function (string $path) {
                return strpos($path, '/homol/plugins-platforms/automatic-payments/v2') === 0;
            })
            ->andReturn($this->makeResponse(201));

        $logs       = \Mockery::mock(Logs::class)->shouldIgnoreMissing();
        $logs->file = \Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class)->shouldIgnoreMissing();

        $client = new AutomaticPaymentsClient(
            $requester,
            MercadoPagoMock::getWoocommerceMercadoPagoMock()->subscriptionsHelper,
            $logs,
            true
        );

        $client->mit('token', ['subscription' => ['id' => 'S1'], 'transaction' => ['external_reference' => 'R1']], 'idem-key');
        $this->addToAssertionCount(1);
    }

    private function makeResponse(int $status): \MercadoPago\PP\Sdk\HttpClient\Response
    {
        $response = \Mockery::mock(\MercadoPago\PP\Sdk\HttpClient\Response::class);
        $response->shouldReceive('getStatus')->andReturn($status);
        $response->shouldReceive('getData')->andReturn([]);
        return $response;
    }
}
