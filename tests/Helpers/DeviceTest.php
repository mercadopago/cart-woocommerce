<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\Device;
use WP_Mock;

class DeviceTest extends TestCase
{
    public function setUp(): void
    {
        WP_Mock::setUp();

        WP_Mock::userFunction('sanitize_text_field', ['return_arg' => 0]);
        WP_Mock::userFunction('wp_unslash', ['return_arg' => 0]);
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    public function testGetDeviceTypeReturnsIosForIphone(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15';

        $this->assertEquals('ios', Device::getDeviceType());
    }

    public function testGetDeviceTypeReturnsIosForIpad(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15';

        $this->assertEquals('ios', Device::getDeviceType());
    }

    public function testGetDeviceTypeReturnsIosForIpadDesktopMode(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

        $this->assertEquals('ios', Device::getDeviceType());
    }

    public function testGetDeviceTypeReturnsAndroid(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 14; SM-G991B) AppleWebKit/537.36 Chrome/120.0';

        $this->assertEquals('android', Device::getDeviceType());
    }

    public function testGetDeviceTypeReturnsDesktopWhenNotMobile(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0';

        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);

        $this->assertEquals('desktop', Device::getDeviceType());
    }

    public function testGetDeviceTypeReturnsDesktopForRealMacSafari(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

        WP_Mock::userFunction('wp_is_mobile', ['return' => false]);

        $this->assertEquals('desktop', Device::getDeviceType());
    }

    public function testGetDeviceTypeReturnsOtherForNonIosAndroidMobile(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Mobile; rv:48.0) Gecko/48.0 Firefox/48.0 KAIOS/2.5';

        WP_Mock::userFunction('wp_is_mobile', ['return' => true]);

        $this->assertEquals('other', Device::getDeviceType());
    }

    public function testGetDeviceTypeReturnsUnknownWhenHeaderMissing(): void
    {
        unset($_SERVER['HTTP_USER_AGENT']);

        $this->assertEquals('unknown', Device::getDeviceType());
    }

    public function testGetDeviceTypeReturnsUnknownWhenHeaderEmpty(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = '';

        $this->assertEquals('unknown', Device::getDeviceType());
    }
}
