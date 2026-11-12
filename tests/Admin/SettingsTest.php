<?php

namespace MercadoPago\Woocommerce\Tests\Admin;

use MercadoPago\Woocommerce\Admin\Settings;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Scripts;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Mock;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
    }

    protected function tearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    /** @dataProvider noticeContexts */
    public function testNoticeLoaderKeepsItsExistingAdminScreens(bool $isAdmin, string $path, string $page, bool $expected): void
    {
        $admin = Mockery::mock(Admin::class);
        $admin->shouldReceive('isAdmin')->andReturn($isAdmin);
        $url = Mockery::mock(Url::class);
        $url->shouldReceive('validateUrl')->andReturnUsing(static function (string $candidate) use ($path): bool {
            return $candidate === $path;
        });
        $url->shouldReceive('validatePage')->andReturnUsing(static function (string $candidate) use ($page): bool {
            return $candidate === $page;
        });
        $class = new ReflectionClass(Settings::class);
        $settings = $class->newInstanceWithoutConstructor();
        foreach (['admin' => $admin, 'url' => $url] as $name => $value) {
            $property = $class->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($settings, $value);
        }

        $this->assertSame($expected, $settings->canLoadScriptsNoticesAdmin());
    }

    public function noticeContexts(): array
    {
        return [
            'dashboard' => [true, 'index', '', true],
            'plugins' => [true, 'plugins', '', true],
            'wc home' => [true, 'admin', 'wc-admin', true],
            'wc settings' => [true, 'admin', 'wc-settings', true],
            'mp settings' => [true, 'admin', 'mercadopago-settings', true],
            'unrelated admin' => [true, 'edit', '', false],
            'frontend' => [false, 'index', '', false],
        ];
    }

    /** @dataProvider noticeContainerContexts */
    public function testNoticeEnqueueKeepsTheRemoteLoaderAndSelectsItsContainerContext(bool $networkAdmin, bool $userAdmin, bool $useSharedRegion): void
    {
        WP_Mock::userFunction('is_admin')->andReturn(true);
        WP_Mock::userFunction('is_network_admin')->andReturn($networkAdmin);
        WP_Mock::userFunction('is_user_admin')->andReturn($userAdmin);
        $admin = new Admin();
        $url = Mockery::mock(Url::class);
        $url->shouldReceive('validateUrl')->andReturnUsing(static function (string $candidate): bool {
            return $candidate === 'plugins';
        });
        $url->shouldReceive('validatePage')->andReturn(false);
        $url->shouldReceive('validateSection')->andReturn(false);
        $scripts = Mockery::mock(Scripts::class);
        $selectedRegion = null;
        $scripts->shouldReceive('registerNoticesAdminScript')->once()->andReturnUsing(static function (bool $selected) use (&$selectedRegion): void {
            $selectedRegion = $selected;
        });
        $scripts->shouldNotReceive('registerNativeNoticesAdminScript');
        $class = new ReflectionClass(Settings::class);
        $settings = $class->newInstanceWithoutConstructor();
        foreach (['admin' => $admin, 'url' => $url, 'scripts' => $scripts] as $name => $value) {
            $property = $class->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($settings, $value);
        }

        $settings->loadScriptsAndStyles();

        $this->assertSame($useSharedRegion, $selectedRegion);
    }

    public function noticeContainerContexts(): array
    {
        return [
            'site admin' => [false, false, true],
            'network admin' => [true, false, false],
            'user admin' => [false, true, false],
        ];
    }
}
