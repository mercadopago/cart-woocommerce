<?php

namespace MercadoPago\Woocommerce\Tests\HealthMonitor;

use Mockery;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\HealthMonitor\FileIntegrityChecker;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use MercadoPago\Woocommerce\Libraries\Singleton\Singleton;
use WP_Mock;

if (!defined('MP_PLUGIN_FILE')) {
    define('MP_PLUGIN_FILE', __FILE__);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

class FileIntegrityCheckerTest extends TestCase
{
    /** Controls the hash_file() namespace override defined at the bottom of this file. */
    public static bool $throwHashException = false;

    private string $tempDir;

    private FileIntegrityChecker $checker;

    private \Mockery\MockInterface $datadogMock;

    public function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->tempDir = sys_get_temp_dir() . '/mp-integrity-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->setupMercadoPagoGlobal();
        $this->datadogMock = $this->mockDatadog();
        $this->datadogMock->shouldReceive('sendEvent')->byDefault();
        $this->checker = new FileIntegrityChecker();
    }

    public function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        $this->clearDatadogMock();
        $this->cleanTempDir($this->tempDir);
        unset($GLOBALS['mercadopago']);
        WP_Mock::tearDown();
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // run() — pure logic, no Datadog, no transients
    // -------------------------------------------------------------------------

    /** TC-FI-01 */
    public function testRunReturnOkWhenAllFilesAreIntact(): void
    {
        $file = $this->createTempFile('hello world');
        $hash = hash_file('sha256', $file);
        $relative = basename($file);

        $this->createManifest([$relative => $hash]);
        $this->mockPluginDirPath();

        $result = $this->checker->run();

        $this->assertSame('ok', $result['status']);
        $this->assertEmpty($result['files']);
    }

    /** TC-FI-02 */
    public function testRunReturnsTamperedWithFileNameWhenOneFileIsModified(): void
    {
        $file = $this->createTempFile('original content');
        $relative = basename($file);

        $this->createManifest([$relative => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']);
        $this->mockPluginDirPath();

        $result = $this->checker->run();

        $this->assertSame('tampered', $result['status']);
        $this->assertContains($relative, $result['files']);
    }

    /** TC-FI-03 */
    public function testRunReportsAllTamperedFilesAndOmitsIntactOne(): void
    {
        $fileA = $this->createTempFile('content A');
        $fileB = $this->createTempFile('content B');
        $fileC = $this->createTempFile('content C');

        $hashA = hash_file('sha256', $fileA);

        $this->createManifest([
            basename($fileA) => $hashA,
            basename($fileB) => 'wrong-hash-for-b',
            basename($fileC) => 'wrong-hash-for-c',
        ]);
        $this->mockPluginDirPath();

        $result = $this->checker->run();

        $this->assertSame('tampered', $result['status']);
        $this->assertCount(2, $result['files']);
        $this->assertNotContains(basename($fileA), $result['files']);
        $this->assertContains(basename($fileB), $result['files']);
        $this->assertContains(basename($fileC), $result['files']);
    }

    /** TC-FI-04 — arquivo ausente no disco é detectado como tampered */
    public function testRunDetectsMissingFileAsTampered(): void
    {
        $this->createManifest(['does-not-exist.js' => 'some-hash']);
        $this->mockPluginDirPath();

        $result = $this->checker->run();

        $this->assertSame('tampered', $result['status']);
        $this->assertContains('does-not-exist.js', $result['files']);
    }

    /** TC-FI-05 — manifesto ausente */
    public function testRunReturnsOkWhenManifestIsMissing(): void
    {
        // tempDir has no integrity-manifest.json
        $this->mockPluginDirPath();

        $result = $this->checker->run();

        $this->assertSame('ok', $result['status']);
        $this->assertEmpty($result['files']);
    }

    /** TC-FI-06 — manifesto com JSON inválido */
    public function testRunReturnsOkWhenManifestContainsInvalidJson(): void
    {
        file_put_contents($this->tempDir . '/integrity-manifest.json', '{ invalid json !!');
        $this->mockPluginDirPath();

        $result = $this->checker->run();

        $this->assertSame('ok', $result['status']);
        $this->assertEmpty($result['files']);
    }

    /** Entrada de diretório no manifesto é detectada como tampered */
    public function testRunDetectsDirectoryEntryAsTampered(): void
    {
        // is_file() returns false for directories, so the entry is flagged as tampered
        mkdir($this->tempDir . '/its-a-dir', 0755);
        $this->createManifest(['its-a-dir' => 'any-hash']);
        $this->mockPluginDirPath();

        $result = $this->checker->run();

        $this->assertSame('tampered', $result['status']);
        $this->assertContains('its-a-dir', $result['files']);
    }

    /** TC-FI-07 — exceção interna não propaga; checkout não quebra */
    public function testRunReturnsOkAndDoesNotThrowOnInternalException(): void
    {
        // hash_file() in the HealthMonitor namespace (defined at bottom of this file)
        // throws when $throwHashException is true, simulating an unexpected runtime failure.
        $file = $this->createTempFile('some content');
        $this->createManifest([basename($file) => 'any-hash']);
        $this->mockPluginDirPath();

        self::$throwHashException = true;

        try {
            $result = $this->checker->run();
        } finally {
            self::$throwHashException = false;
        }

        $this->assertSame('ok', $result['status']);
        $this->assertEmpty($result['files']);
    }

    // -------------------------------------------------------------------------
    // runWithRateLimit() — transients + Datadog
    // -------------------------------------------------------------------------

    /** TC-FI-08 — segunda chamada dentro da hora é ignorada */
    public function testRunWithRateLimitSkipsWhenTransientIsAlreadySet(): void
    {
        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_integrity_checked'],
            'return' => 'ok',
            'times'  => 1,
        ]);

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->checker->runWithRateLimit();
    }

    /** TC-FI-09 — métrica enviada com payload correto na primeira detecção */
    public function testRunWithRateLimitSendsMetricWhenFileIsTampered(): void
    {
        $file = $this->createTempFile('some content');
        $relative = basename($file);
        $this->createManifest([$relative => 'wrong-hash']);
        $this->mockPluginDirPath();

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_integrity_checked'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_integrity_checked', 'tampered', HOUR_IN_SECONDS],
            'times' => 1,
        ]);
        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_integrity_metric_sent'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_integrity_metric_sent', true, HOUR_IN_SECONDS],
            'times' => 1,
        ]);

        $this->datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with(
                'mp_file_integrity_failed',
                'true',
                $relative,
                null,
                [
                    'site_id'     => 'MLB',
                    'environment' => 'homol',
                    'cust_id'     => 'cust-123',
                    'team'        => 'big',
                ]
            );

        $this->checker->runWithRateLimit();
    }

    /** TC-FI-10 — métrica NÃO enviada na segunda hora (idempotência) */
    public function testRunWithRateLimitDoesNotSendMetricWhenAlreadySentThisHour(): void
    {
        $file = $this->createTempFile('some content');
        $relative = basename($file);
        $this->createManifest([$relative => 'wrong-hash']);
        $this->mockPluginDirPath();

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_integrity_checked'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_integrity_checked', 'tampered', HOUR_IN_SECONDS],
            'times' => 1,
        ]);
        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_integrity_metric_sent'],
            'return' => true,  // already sent
            'times'  => 1,
        ]);

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->checker->runWithRateLimit();
    }

    /** TC-FI-11 — sem métrica quando arquivo está íntegro */
    public function testRunWithRateLimitDoesNotSendMetricWhenAllFilesAreIntact(): void
    {
        $file = $this->createTempFile('intact content');
        $hash = hash_file('sha256', $file);
        $this->createManifest([basename($file) => $hash]);
        $this->mockPluginDirPath();

        WP_Mock::userFunction('get_transient', [
            'args'   => ['mp_health_integrity_checked'],
            'return' => false,
            'times'  => 1,
        ]);
        WP_Mock::userFunction('set_transient', [
            'args'  => ['mp_health_integrity_checked', 'ok', HOUR_IN_SECONDS],
            'times' => 1,
        ]);

        $this->datadogMock->shouldNotReceive('sendEvent');

        $this->checker->runWithRateLimit();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function setupMercadoPagoGlobal(): void
    {
        $sellerConfig = Mockery::mock(\MercadoPago\Woocommerce\Configs\Seller::class);
        $sellerConfig->shouldReceive('getSiteId')->andReturn('MLB')->byDefault();
        $sellerConfig->shouldReceive('getCustIdFromAT')->andReturn('cust-123')->byDefault();

        $storeConfig = Mockery::mock(\MercadoPago\Woocommerce\Configs\Store::class);
        $storeConfig->shouldReceive('isTestMode')->andReturn(true)->byDefault();

        $mock = Mockery::mock(\MercadoPago\Woocommerce\WoocommerceMercadoPago::class);
        $mock->sellerConfig = $sellerConfig;
        $mock->storeConfig  = $storeConfig;

        $GLOBALS['mercadopago'] = $mock;
    }

    private function createTempFile(string $content): string
    {
        $path = $this->tempDir . '/' . uniqid('file-') . '.js';
        file_put_contents($path, $content);
        return $path;
    }

    private function createManifest(array $entries): void
    {
        file_put_contents(
            $this->tempDir . '/integrity-manifest.json',
            json_encode($entries)
        );
    }

    private function mockPluginDirPath(): void
    {
        WP_Mock::userFunction('plugin_dir_path', [
            'return' => $this->tempDir . '/',
        ]);
    }

    private function mockDatadog(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(Datadog::class);

        $reflection = new \ReflectionProperty(Singleton::class, 'instances');
        $reflection->setAccessible(true);
        $current = $reflection->getValue(null);
        $current[Datadog::class] = $mock;
        $reflection->setValue(null, $current);

        return $mock;
    }

    private function clearDatadogMock(): void
    {
        $reflection = new \ReflectionProperty(Singleton::class, 'instances');
        $reflection->setAccessible(true);
        $current = $reflection->getValue(null);
        unset($current[Datadog::class]);
        $reflection->setValue(null, $current);
    }

    private function cleanTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->cleanTempDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}

// -----------------------------------------------------------------------------
// Namespace function override — PHP prefers a namespace-local function over the
// global one. Defining hash_file() here makes FileIntegrityChecker::run() call
// this stub instead of \hash_file(), allowing TC-FI-07 to simulate an exception
// without modifying production code.
// -----------------------------------------------------------------------------
namespace MercadoPago\Woocommerce\HealthMonitor;

function hash_file(string $algo, string $path)
{
    if (\MercadoPago\Woocommerce\Tests\HealthMonitor\FileIntegrityCheckerTest::$throwHashException) {
        throw new \RuntimeException('Simulated hash_file failure for TC-FI-07');
    }
    return \hash_file($algo, $path);
}
