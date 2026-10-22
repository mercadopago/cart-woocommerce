<?php

namespace MercadoPago\Woocommerce\Tests\Api\Core;

use MercadoPago\Woocommerce\Api\Core\CoreApiClient;
use MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class CoreApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
        WP_Mock::userFunction('wp_json_encode')->andReturnUsing(function ($data) {
            return json_encode($data);
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    public function testPostEncodesJsonAndAddsContentType(): void
    {
        $response = new HttpResponse(201, [], '{"id":123}', ['id' => 123]);
        $httpClient = Mockery::mock(HttpClientInterface::class);
        $httpClient->expects()
            ->request(
                'POST',
                '/v1/asgard/payments',
                ['Authorization' => 'Bearer token', 'Content-Type' => 'application/json'],
                '{"amount":10}'
            )
            ->andReturn($response);

        $result = (new CoreApiClient($httpClient))->post(
            '/v1/asgard/payments',
            ['Authorization' => 'Bearer token'],
            ['amount' => 10]
        );

        $this->assertSame($response, $result);
    }

    public function testNonSuccessfulResponseMapsApiErrorFields(): void
    {
        $response = new HttpResponse(422, [], '', [
            'message' => 'Invalid payment',
            'error' => 'invalid_payment',
            'status' => 400,
            'original_message' => 'processor detail',
        ]);
        $httpClient = Mockery::mock(HttpClientInterface::class);
        $httpClient->expects()->request('GET', '/v1/resource', [], null)->andReturn($response);

        try {
            (new CoreApiClient($httpClient))->get('/v1/resource');
            $this->fail('Expected Core API exception.');
        } catch (ApiException $exception) {
            $this->assertSame('Invalid payment', $exception->getMessage());
            $this->assertSame('invalid_payment', $exception->getErrorCode());
            $this->assertSame(400, $exception->getApiStatus());
            $this->assertSame('processor detail', $exception->getOriginalMessage());
            $this->assertSame(422, $exception->getHttpStatus());
            $this->assertSame(422, $exception->getCode());
        }
    }
}
