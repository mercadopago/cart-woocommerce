<?php

namespace MercadoPago\Woocommerce\Tests\IO;

use MercadoPago\Woocommerce\Entities\Files\Log as LogFile;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\IO\Downloader;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DownloaderTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Builds a Downloader without invoking the constructor (which scans the real
     * wc-logs directory), injecting a permissive user and an explicit whitelist.
     *
     * @param string[] $knownFileNames
     */
    private function buildDownloader(array $knownFileNames): Downloader
    {
        $currentUser = Mockery::mock(CurrentUser::class);
        $currentUser->shouldReceive('validateUserNeededPermissions')->andReturnNull();

        $reflection = new \ReflectionClass(Downloader::class);
        $downloader = $reflection->newInstanceWithoutConstructor();

        $currentUserProperty = $reflection->getProperty('currentUser');
        $currentUserProperty->setAccessible(true);
        $currentUserProperty->setValue($downloader, $currentUser);

        $downloader->pluginLogs = array_map(function (string $name) {
            $logFile = new LogFile();
            $logFile->fileFullName = $name;
            return $logFile;
        }, $knownFileNames);

        return $downloader;
    }

    private function validates(Downloader $downloader, string $filename): bool
    {
        $method = new \ReflectionMethod(Downloader::class, 'validatesDownloadSecurity');
        $method->setAccessible(true);
        return $method->invoke($downloader, $filename);
    }

    public function testAllowsKnownLogFile(): void
    {
        $downloader = $this->buildDownloader(['mercadopago--2026-07-01.log']);

        $this->assertTrue($this->validates($downloader, 'mercadopago--2026-07-01.log'));
    }

    public function testRejectsUnknownLogFileNotInWhitelist(): void
    {
        $downloader = $this->buildDownloader(['mercadopago--2026-07-01.log']);

        $this->assertFalse($this->validates($downloader, 'mercadopago-evil.log'));
    }

    /**
     * Regression for the IDOR/path-traversal finding: a crafted filename with
     * path separators that ends in .log and contains "mercadopago" used to pass
     * the old array_intersect check. The basename guard must now reject it.
     */
    public function testRejectsPathTraversalWithLogExtension(): void
    {
        $downloader = $this->buildDownloader(['mercadopago--2026-07-01.log']);

        $this->assertFalse($this->validates($downloader, '../../../uploads/mercadopago-secret.log'));
    }

    public function testRejectsPathTraversalToArbitraryFile(): void
    {
        $downloader = $this->buildDownloader(['mercadopago--2026-07-01.log']);

        $this->assertFalse($this->validates($downloader, '../../../wp-config.php'));
    }
}
