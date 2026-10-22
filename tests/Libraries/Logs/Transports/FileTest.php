<?php

namespace MercadoPago\Woocommerce\Tests\Libraries\Logs\Transports;

use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Hooks\Options;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FileTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $logger;

    protected function setUp(): void
    {
        WP_Mock::setUp();
        $this->logger = Mockery::mock('WC_Logger');
        WP_Mock::userFunction('wc_get_logger', [
            'times' => 1,
            'return' => $this->logger,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            WP_Mock::tearDown();
        } finally {
            $this->logger = null;
        }
    }

    /**
     * @dataProvider severityMethods
     */
    public function testPersistsEachSeverityWithItsSourceAndUnicodeContext(string $method): void
    {
        $transport = $this->createTransport('yes', true);
        $context = ['status' => 'ação', 'attempt' => 2];
        $expectedMessage = "Diagnostic event - Context: {\n    \"status\": \"ação\",\n    \"attempt\": 2\n}";
        $this->logger->shouldReceive($method)->once()
            ->with($expectedMessage, ['source' => 'diagnostics'])
            ->andReturnNull();

        $transport->{$method}('Diagnostic event', 'diagnostics', $context);
    }

    public static function severityMethods(): array
    {
        return [
            'error' => ['error'],
            'warning' => ['warning'],
            'notice' => ['notice'],
            'info' => ['info'],
            'debug' => ['debug'],
        ];
    }

    public function testPersistsErrorsWhenBothDebugSettingsAreDisabled(): void
    {
        $transport = $this->createTransport('no', false);
        $this->logger->shouldReceive('error')->once()
            ->with('Delivery failed - Context: []', ['source' => 'diagnostics'])
            ->andReturnNull();

        $transport->error('Delivery failed', 'diagnostics');
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
        foreach (['error', 'warning', 'notice', 'info', 'debug'] as $level) {
            $this->logger->shouldNotReceive($level);
        }

        $transport->{$method}('Filtered event', 'diagnostics', ['attempt' => 2]);
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

    public function testSerializesScalarContextWithoutChangingTheSource(): void
    {
        $transport = $this->createTransport('yes', false);
        $this->logger->shouldReceive('notice')->once()
            ->with('Worker state - Context: "offline"', ['source' => 'background-worker'])
            ->andReturnNull();

        $transport->notice('Worker state', 'background-worker', 'offline');
    }

    public function testPropagatesTheLoggerPersistenceException(): void
    {
        $transport = $this->createTransport('no', false);
        $failure = new RuntimeException('Synthetic persistence failure');
        $this->logger->shouldReceive('error')->once()
            ->with('Delivery failed - Context: []', ['source' => 'diagnostics'])
            ->andThrow($failure);
        $this->expectExceptionObject($failure);

        $transport->error('Delivery failed', 'diagnostics');
    }

    private function createTransport(string $debugMode, bool $wpDebug): File
    {
        define('WP_DEBUG', $wpDebug);
        WP_Mock::userFunction('get_option', [
            'times' => 1,
            'args' => ['_mp_debug_mode', 'no'],
            'return' => $debugMode,
        ]);

        return new File(new Store(new Options()));
    }
}
