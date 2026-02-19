<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Helpers\Strings;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class UrlTest extends TestCase
{
    private Url $url;
    private Strings $stringsMock;

    public function setUp(): void
    {
        WP_Mock::setUp();
        
        // Define MP_PLUGIN_FILE if not already defined
        if (!defined('MP_PLUGIN_FILE')) {
            define('MP_PLUGIN_FILE', '/path/to/plugin/mercadopago.php');
        }
        
        $this->stringsMock = $this->createMock(Strings::class);
        $this->url = new Url($this->stringsMock);
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        
        // Clean up superglobals
        $_GET = [];
        $_SERVER = [];
    }

    // ========== Static Method Tests ==========

    public function testIsValid()
    {
        $this->assertFalse(Url::isValid(''));
        $this->assertFalse(Url::isValid('l'));
        $this->assertFalse(Url::isValid('l.com'));
        $this->assertTrue(Url::isValid('http://localhost'));
        $this->assertTrue(Url::isValid('https://localhost.com'));
        $this->assertTrue(Url::isValid('https://www.mercadopago.com.br'));
        $this->assertTrue(Url::isValid('ftp://example.com'));
        $this->assertFalse(Url::isValid('not a url'));
    }

    // ========== Plugin File URL Tests ==========

    public function testGetPluginFileUrl()
    {
        WP_Mock::userFunction('plugins_url', [
            'times' => 1,
            'args' => ['assets/css/style.css', MP_PLUGIN_FILE],
            'return' => 'https://example.com/wp-content/plugins/mercadopago/assets/css/style.css'
        ]);

        $result = $this->url->getPluginFileUrl('assets/css/style.css');
        $this->assertEquals('https://example.com/wp-content/plugins/mercadopago/assets/css/style.css', $result);
    }

    // ========== SDK URL Tests ==========

    public function testGetMercadoPagoSdkUrlProd()
    {
        $result = $this->url->getMercadoPagoSdkUrl();
        $this->assertEquals('https://sdk.mercadopago.com/js/v2', $result);
    }

    /**
     * Note: Tests for getMercadoPagoSdkUrl with different environments (beta, gama, invalid)
     * are not included because MP_SDK_ENV is a constant that cannot be redefined during test execution.
     * These scenarios should be tested through integration tests or by setting the constant
     * before running the test suite.
     */

    // ========== Asset URL Tests ==========

    public function testGetCssAsset()
    {
        WP_Mock::userFunction('plugins_url', [
            'times' => 1,
            'args' => ['assets/css/checkout.min.css', MP_PLUGIN_FILE],
            'return' => 'https://example.com/wp-content/plugins/mercadopago/assets/css/checkout.min.css'
        ]);

        $result = $this->url->getCssAsset('checkout');
        $this->assertEquals('https://example.com/wp-content/plugins/mercadopago/assets/css/checkout.min.css', $result);
    }

    public function testGetJsAsset()
    {
        WP_Mock::userFunction('plugins_url', [
            'times' => 1,
            'args' => ['assets/js/payment.min.js', MP_PLUGIN_FILE],
            'return' => 'https://example.com/wp-content/plugins/mercadopago/assets/js/payment.min.js'
        ]);

        $result = $this->url->getJsAsset('payment');
        $this->assertEquals('https://example.com/wp-content/plugins/mercadopago/assets/js/payment.min.js', $result);
    }

    public function testGetImageAsset()
    {
        WP_Mock::userFunction('plugins_url', [
            'times' => 1,
            'args' => ['assets/images/logo.png', MP_PLUGIN_FILE],
            'return' => 'https://example.com/wp-content/plugins/mercadopago/assets/images/logo.png'
        ]);

        WP_Mock::userFunction('wp_get_environment_type', [
            'return' => 'production'
        ]);

        $result = $this->url->getImageAsset('logo');
        $this->assertStringContainsString('https://example.com/wp-content/plugins/mercadopago/assets/images/logo.png', $result);
        $this->assertStringContainsString('?ver=', $result);
    }

    public function testGetImageAssetWithExtension()
    {
        WP_Mock::userFunction('plugins_url', [
            'times' => 1,
            'args' => ['assets/images/logo.png', MP_PLUGIN_FILE],
            'return' => 'https://example.com/wp-content/plugins/mercadopago/assets/images/logo.png'
        ]);

        WP_Mock::userFunction('wp_get_environment_type', [
            'return' => 'production'
        ]);

        $result = $this->url->getImageAsset('logo.png');
        $this->assertStringContainsString('https://example.com/wp-content/plugins/mercadopago/assets/images/logo.png', $result);
    }

    // ========== Current URL/Page Tests ==========

    /**
     * Note: Tests for getCurrentPage, getCurrentSection, and getCurrentTab are not included
     * because they depend on filter_input_array() and other internal PHP functions that cannot
     * be mocked with WP_Mock. These methods also depend on Form::sanitizedGetData() which uses
     * WordPress functions like map_deep() and sanitize_text_field().
     * 
     * These scenarios should be tested through integration tests with a full WordPress environment.
     */

    public function testGetCurrentUrl()
    {
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=mercadopago';
        
        WP_Mock::userFunction('sanitize_text_field', [
            'times' => 1,
            'return' => '/wp-admin/admin.php?page=mercadopago'
        ]);

        WP_Mock::userFunction('wp_unslash', [
            'times' => 1,
            'return' => '/wp-admin/admin.php?page=mercadopago'
        ]);

        $result = $this->url->getCurrentUrl();
        $this->assertEquals('/wp-admin/admin.php?page=mercadopago', $result);
    }

    public function testGetCurrentUrlWithoutRequestUri()
    {
        unset($_SERVER['REQUEST_URI']);
        
        $result = $this->url->getCurrentUrl();
        $this->assertEquals('', $result);
    }

    public function testGetBaseUrl()
    {
        WP_Mock::userFunction('home_url', [
            'times' => 1,
            'return' => 'https://example.com'
        ]);

        $result = $this->url->getBaseUrl();
        $this->assertEquals('https://example.com', $result);
    }

    public function testGetServerAddress()
    {
        $_SERVER['SERVER_ADDR'] = '192.168.1.1';
        
        WP_Mock::userFunction('sanitize_text_field', [
            'times' => 1,
            'return' => '192.168.1.1'
        ]);

        WP_Mock::userFunction('wp_unslash', [
            'times' => 1,
            'return' => '192.168.1.1'
        ]);

        $result = $this->url->getServerAddress();
        $this->assertEquals('192.168.1.1', $result);
    }

    public function testGetServerAddressWithoutServerAddr()
    {
        unset($_SERVER['SERVER_ADDR']);
        
        $result = $this->url->getServerAddress();
        $this->assertEquals('127.0.0.1', $result);
    }

    // ========== Query Variable Tests ==========

    public function testSetQueryVar()
    {
        WP_Mock::userFunction('add_query_arg', [
            'times' => 1,
            'args' => ['action', 'edit', 'https://example.com/page'],
            'return' => 'https://example.com/page?action=edit'
        ]);

        $result = $this->url->setQueryVar('action', 'edit', 'https://example.com/page');
        $this->assertEquals('https://example.com/page?action=edit', $result);
    }

    public function testGetQueryVar()
    {
        WP_Mock::userFunction('get_query_var', [
            'times' => 1,
            'args' => ['order_id', ''],
            'return' => '12345'
        ]);

        $result = $this->url->getQueryVar('order_id');
        $this->assertEquals('12345', $result);
    }

    public function testGetQueryVarWithDefault()
    {
        WP_Mock::userFunction('get_query_var', [
            'times' => 1,
            'args' => ['missing_var', 'default_value'],
            'return' => 'default_value'
        ]);

        $result = $this->url->getQueryVar('missing_var', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    // ========== Validation Tests ==========

    public function testValidatePageExactMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('mercadopago-settings', 'mercadopago-settings', false)
            ->willReturn(true);

        $result = $this->url->validatePage('mercadopago-settings', 'mercadopago-settings', false);
        $this->assertTrue($result);
    }

    public function testValidatePagePartialMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('mercadopago', 'mercadopago-settings', true)
            ->willReturn(true);

        $result = $this->url->validatePage('mercadopago', 'mercadopago-settings', true);
        $this->assertTrue($result);
    }

    public function testValidatePageNoMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('woocommerce', 'mercadopago-settings', false)
            ->willReturn(false);

        $result = $this->url->validatePage('woocommerce', 'mercadopago-settings', false);
        $this->assertFalse($result);
    }

    public function testValidateSectionExactMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('basic_checkout', 'basic_checkout', false)
            ->willReturn(true);

        $result = $this->url->validateSection('basic_checkout', 'basic_checkout', false);
        $this->assertTrue($result);
    }

    public function testValidateSectionPartialMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('basic', 'basic_checkout', true)
            ->willReturn(true);

        $result = $this->url->validateSection('basic', 'basic_checkout');
        $this->assertTrue($result);
    }

    public function testValidateSectionNoMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('advanced', 'basic_checkout', true)
            ->willReturn(false);

        $result = $this->url->validateSection('advanced', 'basic_checkout');
        $this->assertFalse($result);
    }

    public function testValidateUrlExactMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('/admin/settings', '/admin/settings', false)
            ->willReturn(true);

        $result = $this->url->validateUrl('/admin/settings', '/admin/settings', false);
        $this->assertTrue($result);
    }

    public function testValidateUrlPartialMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('/admin', '/admin/settings', true)
            ->willReturn(true);

        $result = $this->url->validateUrl('/admin', '/admin/settings');
        $this->assertTrue($result);
    }

    public function testValidateUrlNoMatch()
    {
        $this->stringsMock->expects($this->once())
            ->method('compareStrings')
            ->with('/checkout', '/admin/settings', true)
            ->willReturn(false);

        $result = $this->url->validateUrl('/checkout', '/admin/settings');
        $this->assertFalse($result);
    }

    public function testValidateQueryVarExists()
    {
        WP_Mock::userFunction('get_query_var', [
            'times' => 1,
            'args' => ['order_id', ''],
            'return' => '12345'
        ]);

        $result = $this->url->validateQueryVar('order_id');
        $this->assertTrue($result);
    }

    public function testValidateQueryVarNotExists()
    {
        WP_Mock::userFunction('get_query_var', [
            'times' => 1,
            'args' => ['missing_var', ''],
            'return' => ''
        ]);

        $result = $this->url->validateQueryVar('missing_var');
        $this->assertFalse($result);
    }

    public function testValidateGetVarExists()
    {
        $_GET['action'] = 'edit';
        
        $result = $this->url->validateGetVar('action');
        $this->assertTrue($result);
    }

    public function testValidateGetVarNotExists()
    {
        $result = $this->url->validateGetVar('missing_var');
        $this->assertFalse($result);
    }

    // ========== Asset Version Tests ==========

    public function testAssetVersionProduction()
    {
        WP_Mock::userFunction('wp_get_environment_type', [
            'times' => 1,
            'return' => 'production'
        ]);

        $result = $this->url->assetVersion();
        $this->assertEquals(MP_VERSION, $result);
    }

    public function testAssetVersionDevelopment()
    {
        WP_Mock::userFunction('wp_get_environment_type', [
            'times' => 1,
            'return' => 'development'
        ]);

        $result = $this->url->assetVersion();
        $this->assertStringContainsString(MP_VERSION, $result);
        $this->assertStringContainsString('.', $result);
        // Verify it has a timestamp appended
        $parts = explode('.', $result);
        $this->assertGreaterThan(1, count($parts));
    }

    public function testAssetVersionLocal()
    {
        WP_Mock::userFunction('wp_get_environment_type', [
            'times' => 1,
            'return' => 'local'
        ]);

        $result = $this->url->assetVersion();
        $this->assertStringContainsString(MP_VERSION, $result);
        $this->assertStringContainsString('.', $result);
    }

    public function testAssetVersionStaging()
    {
        WP_Mock::userFunction('wp_get_environment_type', [
            'times' => 1,
            'return' => 'staging'
        ]);

        $result = $this->url->assetVersion();
        $this->assertEquals(MP_VERSION, $result);
    }
}
