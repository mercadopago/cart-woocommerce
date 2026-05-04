<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use Exception;
use MercadoPago\PP\Sdk\HttpClient\HttpClientInterface;
use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RequesterTest extends TestCase
{
    private $httpClient;
    private $datadogMock;
    private Requester $requester;

    protected function setUp(): void
    {
        WP_Mock::setUp();

        if (!defined('MP_VERSION')) {
            define('MP_VERSION', '8.2.0');
        }

        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }

        WP_Mock::userFunction('site_url', [
            'return' => 'https://test-store.com',
        ]);

        WP_Mock::userFunction('wp_json_encode', [
            'return' => function ($data) {
                return json_encode($data);
            },
        ]);

        WP_Mock::userFunction('wp_remote_post', [
            'return' => [],
        ]);

        $GLOBALS['woocommerce'] = (object) ['version' => '9.0.0'];

        $this->httpClient = Mockery::mock(HttpClientInterface::class);
        $this->datadogMock = Mockery::mock(Datadog::class);

        $this->requester = new Requester($this->httpClient);
        $this->injectDatadogMock($this->datadogMock);
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
        unset($GLOBALS['woocommerce']);
        unset($GLOBALS['mercadopago']);
    }

    private function injectDatadogMock($datadogMock): void
    {
        $reflection = new \ReflectionClass($this->requester);
        $property = $reflection->getProperty('datadog');
        $property->setAccessible(true);
        $property->setValue($this->requester, $datadogMock);
    }

    private function createResponse(int $status, $data = null): Response
    {
        $response = new Response();
        $response->setStatus($status);
        if ($data !== null) {
            $response->setData($data);
        }
        return $response;
    }

    // --- Success cases: no metric sent ---

    public function testGetSuccessDoesNotSendMetric(): void
    {
        $response = $this->createResponse(200);
        $this->httpClient->shouldReceive('get')->once()->andReturn($response);
        $this->datadogMock->shouldNotReceive('sendEvent');

        $result = $this->requester->get('/v1/payments', []);

        $this->assertEquals(200, $result->getStatus());
    }

    public function testPostSuccessDoesNotSendMetric(): void
    {
        $response = $this->createResponse(201);
        $this->httpClient->shouldReceive('post')->once()->andReturn($response);
        $this->datadogMock->shouldNotReceive('sendEvent');

        $result = $this->requester->post('/v1/payments', [], ['amount' => 100]);

        $this->assertEquals(201, $result->getStatus());
    }

    public function testPutSuccessDoesNotSendMetric(): void
    {
        $response = $this->createResponse(200);
        $this->httpClient->shouldReceive('put')->once()->andReturn($response);
        $this->datadogMock->shouldNotReceive('sendEvent');

        $result = $this->requester->put('/v1/payments/123', [], ['status' => 'cancelled']);

        $this->assertEquals(200, $result->getStatus());
    }

    // --- HTTP error cases: metric sent, response returned ---

    public function testPostWithHttp400SendsMetric(): void
    {
        $response = $this->createResponse(400, ['message' => 'Bad Request']);
        $this->httpClient->shouldReceive('post')->once()->andReturn($response);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '400', 'Bad Request', null, [
                'team'      => 'big',
                'api_route' => '/v1/payments',
            ]);

        $result = $this->requester->post('/v1/payments', [], []);

        $this->assertEquals(400, $result->getStatus());
    }

    public function testPostWithHttp400ObjectPayloadSendsMetricWithMessage(): void
    {
        $response = $this->createResponse(422, (object) ['message' => 'Unprocessable Entity']);
        $this->httpClient->shouldReceive('post')->once()->andReturn($response);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '422', 'Unprocessable Entity', null, [
                'team'      => 'big',
                'api_route' => '/v1/payments',
            ]);

        $result = $this->requester->post('/v1/payments', [], []);

        $this->assertEquals(422, $result->getStatus());
    }

    public function testGetWithHttp500SendsMetric(): void
    {
        $response = $this->createResponse(500);
        $this->httpClient->shouldReceive('get')->once()->andReturn($response);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '500', 'HTTP 500', null, [
                'team'      => 'big',
                'api_route' => '/v1/payments/123',
            ]);

        $result = $this->requester->get('/v1/payments/123', []);

        $this->assertEquals(500, $result->getStatus());
    }

    public function testPutWithHttp404SendsMetric(): void
    {
        $response = $this->createResponse(404, ['message' => 'Not Found']);
        $this->httpClient->shouldReceive('put')->once()->andReturn($response);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '404', 'Not Found', null, [
                'team'      => 'big',
                'api_route' => '/v1/refunds/99',
            ]);

        $result = $this->requester->put('/v1/refunds/99', [], []);

        $this->assertEquals(404, $result->getStatus());
    }

    // --- Exception cases: metric sent with status 0, exception re-thrown ---

    public function testPostExceptionSendsMetricAndRethrows(): void
    {
        $this->httpClient->shouldReceive('post')->once()->andThrow(new Exception('Connection timeout'));

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '0', 'Connection timeout', null, [
                'team'      => 'big',
                'api_route' => '/v1/payments',
            ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Connection timeout');

        $this->requester->post('/v1/payments', [], []);
    }

    public function testGetExceptionSendsMetricAndRethrows(): void
    {
        $this->httpClient->shouldReceive('get')->once()->andThrow(new Exception('DNS resolution failed'));

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '0', 'DNS resolution failed', null, [
                'team'      => 'big',
                'api_route' => '/v1/payments',
            ]);

        $this->expectException(Exception::class);

        $this->requester->get('/v1/payments', []);
    }

    // --- With global $mercadopago: includes site_id, environment, cust_id ---

    public function testErrorMetricIncludesSellerDetailsWhenGlobalAvailable(): void
    {
        $sellerConfig = Mockery::mock();
        $sellerConfig->shouldReceive('getSiteId')->once()->andReturn('MLB');
        $sellerConfig->shouldReceive('getCustIdFromAT')->once()->andReturn('123456789');

        $storeConfig = Mockery::mock();
        $storeConfig->shouldReceive('isTestMode')->once()->andReturn(false);

        $GLOBALS['mercadopago'] = (object) [
            'sellerConfig' => $sellerConfig,
            'storeConfig'  => $storeConfig,
        ];

        $response = $this->createResponse(500);
        $this->httpClient->shouldReceive('get')->once()->andReturn($response);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '500', 'HTTP 500', null, [
                'team'        => 'big',
                'api_route'   => '/v1/payments',
                'site_id'     => 'MLB',
                'environment' => 'prod',
                'cust_id'     => '123456789',
            ]);

        $result = $this->requester->get('/v1/payments', []);

        $this->assertEquals(500, $result->getStatus());
    }

    public function testErrorMetricShowsHomolWhenTestMode(): void
    {
        $sellerConfig = Mockery::mock();
        $sellerConfig->shouldReceive('getSiteId')->once()->andReturn('MLA');
        $sellerConfig->shouldReceive('getCustIdFromAT')->once()->andReturn('987654321');

        $storeConfig = Mockery::mock();
        $storeConfig->shouldReceive('isTestMode')->once()->andReturn(true);

        $GLOBALS['mercadopago'] = (object) [
            'sellerConfig' => $sellerConfig,
            'storeConfig'  => $storeConfig,
        ];

        $response = $this->createResponse(401);
        $this->httpClient->shouldReceive('post')->once()->andReturn($response);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '401', 'HTTP 401', null, [
                'team'        => 'big',
                'api_route'   => '/v1/payments',
                'site_id'     => 'MLA',
                'environment' => 'homol',
                'cust_id'     => '987654321',
            ]);

        $result = $this->requester->post('/v1/payments', [], []);

        $this->assertEquals(401, $result->getStatus());
    }

    // --- Edge case: error response without message field ---

    public function testErrorResponseWithoutMessageFieldFallsBackToHttpStatus(): void
    {
        $response = $this->createResponse(502, ['error' => 'something']);
        $this->httpClient->shouldReceive('get')->once()->andReturn($response);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_api_error', '502', 'HTTP 502', null, [
                'team'      => 'big',
                'api_route' => '/v1/test',
            ]);

        $result = $this->requester->get('/v1/test', []);

        $this->assertEquals(502, $result->getStatus());
    }
}
