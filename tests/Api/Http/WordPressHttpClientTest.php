<?php

namespace MercadoPago\Woocommerce\Tests\Api\Http;

use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\Api\Http\WordPressHttpClient;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class WordPressHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    public function testRequestUsesWordPressHttpApiWithSecureDefaults(): void
    {
        $rawResponse = ['body' => '{"id":123}'];

        WP_Mock::userFunction('wp_remote_request')
            ->once()
            ->with('https://api.mercadopago.com/v1/payments/123', [
                'method'             => 'GET',
                'headers'            => ['Authorization' => 'Bearer token'],
                'timeout'            => 15,
                'redirection'        => 0,
                'reject_unsafe_urls' => true,
                'user-agent'         => 'platform:v1-whitelabel,type:mp_sdk',
            ])
            ->andReturn($rawResponse);
        WP_Mock::userFunction('is_wp_error')->once()->with($rawResponse)->andReturn(false);
        WP_Mock::userFunction('wp_remote_retrieve_body')->once()->with($rawResponse)->andReturn('{"id":123}');
        WP_Mock::userFunction('wp_remote_retrieve_response_code')->once()->with($rawResponse)->andReturn(200);
        WP_Mock::userFunction('wp_remote_retrieve_headers')->once()->with($rawResponse)->andReturn(['x-request-id' => 'request']);

        $response = (new WordPressHttpClient('https://api.mercadopago.com', 15))->request(
            'get',
            '/v1/payments/123',
            ['Authorization: Bearer token']
        );

        $this->assertSame(200, $response->getStatus());
        $this->assertSame(['id' => 123], $response->getData());
        $this->assertSame('{"id":123}', $response->getBody());
        $this->assertSame(['x-request-id' => 'request'], $response->getHeaders());
    }

    public function testRequestSendsJsonBodyWithoutWordPressReencodingIt(): void
    {
        $rawResponse = ['body' => ''];

        WP_Mock::userFunction('wp_remote_request')
            ->once()
            ->with('https://api.mercadopago.com/v1/asgard/payments', [
                'method'             => 'POST',
                'headers'            => ['Content-Type' => 'application/json'],
                'timeout'            => 30,
                'redirection'        => 0,
                'reject_unsafe_urls' => true,
                'user-agent'         => 'platform:v1-whitelabel,type:mp_sdk',
                'body'               => '{"amount":10}',
                'data_format'        => 'body',
            ])
            ->andReturn($rawResponse);
        WP_Mock::userFunction('is_wp_error')->once()->andReturn(false);
        WP_Mock::userFunction('wp_remote_retrieve_body')->once()->andReturn('');
        WP_Mock::userFunction('wp_remote_retrieve_response_code')->once()->andReturn(204);
        WP_Mock::userFunction('wp_remote_retrieve_headers')->once()->andReturn([]);

        $response = (new WordPressHttpClient('https://api.mercadopago.com'))->request(
            'POST',
            '/v1/asgard/payments',
            ['Content-Type' => 'application/json'],
            '{"amount":10}'
        );

        $this->assertSame(204, $response->getStatus());
        $this->assertNull($response->getData());
    }

    /**
     * @dataProvider invalidPathProvider
     */
    public function testRequestRejectsUnsafePaths(string $path): void
    {
        WP_Mock::userFunction('wp_remote_request')->never();

        $this->expectException(\InvalidArgumentException::class);

        (new WordPressHttpClient('https://api.mercadopago.com'))->request('GET', $path);
    }

    public static function invalidPathProvider(): array
    {
        return [
            'absolute URL' => ['https://attacker.example/path'],
            'protocol relative URL' => ['//attacker.example/path'],
            'plain relative path' => ['v1/payments'],
            'path traversal' => ['/v1/../users/me'],
            'encoded path traversal' => ['/v1/%2e%2e/users/me'],
            'double encoded path traversal' => ['/v1/%252e%252e/users/me'],
            'fragment' => ['/v1/payments#fragment'],
            'backslash' => ['/v1\\payments'],
            'encoded control character' => ['/v1/payments%0d%0aheader'],
        ];
    }

    public function testRequestRejectsNonScalarHeaderValues(): void
    {
        WP_Mock::userFunction('wp_remote_request')->never();

        $this->expectException(\InvalidArgumentException::class);

        (new WordPressHttpClient('https://api.mercadopago.com'))->request(
            'GET',
            '/v1/payments',
            ['Authorization' => ['Bearer token']]
        );
    }

    public function testRequestMapsWpErrorWithoutExposingItsMessage(): void
    {
        $wpError = Mockery::mock();
        $wpError->shouldReceive('get_error_code')->once()->andReturn('http_request_failed');

        WP_Mock::userFunction('wp_remote_request')->once()->andReturn($wpError);
        WP_Mock::userFunction('is_wp_error')->once()->with($wpError)->andReturn(true);

        try {
            (new WordPressHttpClient('https://api.mercadopago.com'))->request('GET', '/users/me');
            $this->fail('Expected transport exception.');
        } catch (TransportException $exception) {
            $this->assertSame('http_request_failed', $exception->getTransportCode());
            $this->assertStringNotContainsString('TLS details', $exception->getMessage());
            $this->assertStringNotContainsString('token', $exception->getMessage());
        }
    }
}
