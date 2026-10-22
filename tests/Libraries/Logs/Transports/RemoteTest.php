<?php

namespace MercadoPago\Woocommerce\Tests\Libraries\Logs\Transports;

use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class RemoteTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $httpClient;

    private array $serverBefore;

    private array $globalsBefore;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        if (!defined('MP_PLATFORM_NAME')) {
            define('MP_PLATFORM_NAME', 'woocommerce');
        }
        $this->serverBefore = $_SERVER;
        $this->globalsBefore = [];
        foreach (['woocommerce', 'mercadopago'] as $name) {
            if (array_key_exists($name, $GLOBALS)) {
                $this->globalsBefore[$name] = $GLOBALS[$name];
            }
        }
        unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'], $GLOBALS['mercadopago']);
        $GLOBALS['woocommerce'] = (object) ['version' => '9.5.0'];
        $this->httpClient = Mockery::mock(HttpClientInterface::class);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBefore;
        foreach (['woocommerce', 'mercadopago'] as $name) {
            if (array_key_exists($name, $this->globalsBefore)) {
                $GLOBALS[$name] = $this->globalsBefore[$name];
            } else {
                unset($GLOBALS[$name]);
            }
        }
        try {
            WP_Mock::tearDown();
        } finally {
            $this->httpClient = null;
        }
    }

    /**
     * @dataProvider severityMethods
     */
    public function testSendsEachSeverityWithTheCompletePayload(
        string $method,
        string $name,
        string $message
    ): void {
        $transport = $this->createTransport('yes', true);
        $this->expectJsonEncoding();
        $capturedPayload = $this->captureRequest();
        WP_Mock::userFunction('wp_remote_post', ['times' => 0]);

        $transport->{$method}('Diagnostic event', 'diagnostics', ['attempt' => 2]);

        $this->assertSame($this->expectedPayload($name, $message), $capturedPayload->value);
    }

    public static function severityMethods(): array
    {
        return [
            'error' => ['error', 'MP_WOO_PE_LOG_ERROR', '[ERROR] Diagnostic event - Context: {"attempt":2}'],
            'warning' => ['warning', 'MP_WOO_PE_LOG_WARNING', '[WARNING] Diagnostic event - Context: {"attempt":2}'],
            'notice' => ['notice', 'MP_WOO_PE_LOG_NOTICE', '[NOTICE] Diagnostic event - Context: {"attempt":2}'],
            'info' => ['info', 'MP_WOO_PE_LOG_INFO', '[INFO] Diagnostic event - Context: {"attempt":2}'],
            'debug' => ['debug', 'MP_WOO_PE_LOG_DEBUG', '[DEBUG] Diagnostic event - Context: {"attempt":2}'],
        ];
    }

    public function testSendsErrorsWhenBothDebugSettingsAreDisabled(): void
    {
        $transport = $this->createTransport('no', false);
        $this->expectJsonEncoding();
        $capturedPayload = $this->captureRequest();
        WP_Mock::userFunction('wp_remote_post', ['times' => 0]);

        $transport->error('Diagnostic event', 'diagnostics');

        $this->assertSame(
            $this->expectedPayload('MP_WOO_PE_LOG_ERROR', '[ERROR] Diagnostic event - Context: []'),
            $capturedPayload->value
        );
    }

    /**
     * @dataProvider suppressedMethods
     */
    public function testSuppressesLevelsUnlessTheirDebugGatesAreEnabled(
        string $method,
        string $debugMode,
        bool $wpDebug
    ): void {
        $transport = $this->createTransport($debugMode, $wpDebug);
        $requests = [];
        $this->httpClient->shouldReceive('request')->andReturnUsing(
            function ($method, $path, array $headers, ?string $body = null) use (&$requests) {
                $requests[] = [$method, $path, $headers, $body];
                return new HttpResponse(200);
            }
        );
        $this->expectJsonEncoding(0);
        WP_Mock::userFunction('wp_remote_post', ['times' => 0]);

        $transport->{$method}('Filtered event', 'diagnostics', ['attempt' => 2]);

        $this->assertSame([], $requests);
    }

    public static function suppressedMethods(): array
    {
        return [
            'warning without store debug' => ['warning', 'no', true],
            'notice without store debug' => ['notice', 'no', true],
            'info without store debug' => ['info', 'no', true],
            'debug without store debug' => ['debug', 'no', true],
            'debug without WordPress debug' => ['debug', 'yes', false],
            'debug without either setting' => ['debug', 'no', false],
            'store debug requires exact yes' => ['info', 'YES', true],
        ];
    }

    /**
     * @dataProvider platformUris
     */
    public function testBuildsPlatformUriFromOnlyThePresentSanitizedComponents(
        ?string $host,
        ?string $requestUri,
        string $expectedUri
    ): void {
        if ($host !== null) {
            $_SERVER['HTTP_HOST'] = $host;
            WP_Mock::userFunction('wp_unslash', [
                'times' => 1,
                'args' => [$host],
                'return' => '<b>store.example.test</b>',
            ]);
            WP_Mock::userFunction('sanitize_text_field', [
                'times' => 1,
                'args' => ['<b>store.example.test</b>'],
                'return' => 'store.example.test',
            ]);
        }
        if ($requestUri !== null) {
            $_SERVER['REQUEST_URI'] = $requestUri;
            WP_Mock::userFunction('wp_unslash', [
                'times' => 1,
                'args' => [$requestUri],
                'return' => '/checkout/<i>review</i>',
            ]);
            WP_Mock::userFunction('sanitize_text_field', [
                'times' => 1,
                'args' => ['/checkout/<i>review</i>'],
                'return' => '/checkout/review',
            ]);
        }
        $transport = $this->createTransport('yes', false);
        $this->expectJsonEncoding();
        $capturedPayload = $this->captureRequest();

        $transport->notice('Diagnostic event', 'diagnostics');

        $this->assertSame($this->expectedPayload(
            'MP_WOO_PE_LOG_NOTICE',
            '[NOTICE] Diagnostic event - Context: []',
            $expectedUri
        ), $capturedPayload->value);
    }

    public static function platformUris(): array
    {
        return [
            'host and URI' => ['raw-host', 'raw-uri', 'store.example.test/checkout/review'],
            'host only' => ['raw-host', null, 'store.example.test'],
            'URI only' => [null, 'raw-uri', '/checkout/review'],
            'neither component' => [null, null, ''],
        ];
    }

    public function testAbsorbsTheHttpFailureAfterAttemptingTheCorrectPayload(): void
    {
        $transport = $this->createTransport('no', false);
        $expectedPayload = $this->expectedPayload(
            'MP_WOO_PE_LOG_ERROR',
            '[ERROR] Diagnostic event - Context: {"attempt":2}'
        );
        $this->expectJsonEncoding(2);
        $capturedPayload = null;
        $this->httpClient->shouldReceive('request')->once()
            ->with('POST', '/v1/plugins/melidata/errors', ['Content-Type: application/json'], Mockery::type('string'))
            ->andReturnUsing(function ($method, $path, array $headers, string $body) use (&$capturedPayload) {
                $capturedPayload = json_decode($body, true);
                throw new RuntimeException('Synthetic HTTP failure');
            });
        WP_Mock::userFunction('site_url', [
            'times' => 1,
            'return' => 'https://store.example.test',
        ]);
        $capturedMetric = null;
        WP_Mock::userFunction('wp_remote_post', [
            'times' => 1,
            'args' => [
                'https://api.mercadopago.com/ppcore/prod/monitor/v1/event/datadog/big/mp_api_error',
                WP_Mock\Functions::type('array'),
            ],
        ])->andReturnUsing(function ($url, array $args) use (&$capturedMetric) {
            $capturedMetric = json_decode($args['body'], true);
            return [];
        });

        $transport->error('Diagnostic event', 'diagnostics', ['attempt' => 2]);

        $this->assertSame($expectedPayload, $capturedPayload);
        $this->assertSame('0', $capturedMetric['value']);
        $this->assertSame('Synthetic HTTP failure', $capturedMetric['message']);
        $this->assertSame([
            'team' => 'big',
            'api_route' => '/v1/plugins/melidata/errors',
        ], $capturedMetric['details']);
    }

    private function createTransport(string $debugMode, bool $wpDebug): Remote
    {
        define('WP_DEBUG', $wpDebug);
        WP_Mock::userFunction('get_option', [
            'times' => 1,
            'args' => ['_mp_debug_mode', 'no'],
            'return' => $debugMode,
        ]);

        return new Remote(new Store(new Options()), new Requester($this->httpClient));
    }

    private function expectJsonEncoding(int $times = 1): void
    {
        WP_Mock::userFunction('wp_json_encode', [
            'times' => $times,
            'return' => function (array $data) {
                return json_encode($data);
            },
        ]);
    }

    private function captureRequest(): \stdClass
    {
        $capturedPayload = (object) ['value' => null];
        $this->httpClient->shouldReceive('request')->once()
            ->with('POST', '/v1/plugins/melidata/errors', ['Content-Type: application/json'], Mockery::type('string'))
            ->andReturnUsing(function ($method, $path, array $headers, string $body) use ($capturedPayload) {
                $capturedPayload->value = json_decode($body, true);
                return new HttpResponse(200);
            });

        return $capturedPayload;
    }

    private function expectedPayload(string $name, string $message, string $uri = ''): array
    {
        return [
            'name' => $name,
            'message' => $message,
            'target' => 'diagnostics',
            'plugin' => ['version' => MP_VERSION],
            'platform' => [
                'name' => 'woocommerce',
                'uri' => $uri,
                'version' => '9.5.0',
                'location' => '/backend',
            ],
        ];
    }
}
