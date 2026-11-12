<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricPage;
use PHPUnit\Framework\TestCase;

class SmartPixMetricPageTest extends TestCase
{
    private const STORE = 'https://store.example.test/wordpress';

    /** @dataProvider pages */
    public function testPageContextRemovesPrivateDataAndRequiresTheStoreOrigin($page, ?string $expected): void
    {
        $actual = SmartPixMetricPage::sanitize($page, self::STORE);
        $this->assertSame($expected, $actual);
        if ($actual !== null) {
            $this->assertSame($actual, SmartPixMetricPage::sanitize($actual, self::STORE));
            $this->assertLessThanOrEqual(180, strlen($actual));
        }
    }

    public static function pages(): array
    {
        $origin = 'https://store.example.test';
        return [
            'checkout' => [$origin . '/checkout/?email=SYNTHETIC&nonce=SYNTHETIC#token', $origin . '/checkout/'],
            'custom checkout' => [$origin . '/finalizar-compra/', $origin . '/finalizar-compra/'],
            'thank you' => [$origin . '/checkout/order-received/42/?key=wc_order_SYNTHETIC', $origin . '/checkout/order-received/'],
            'order pay' => [$origin . '/checkout/order-pay/42?key=wc_order_SYNTHETIC', $origin . '/checkout/order-pay/'],
            'admin refund' => [$origin . '/wp-admin/admin.php?page=wc-orders&action=edit&id=42&_wpnonce=SYNTHETIC', $origin . '/wp-admin/admin.php'],
            'custom endpoint ID' => [$origin . '/obrigado/42/', $origin . '/obrigado/{id}/'],
            'encoded ID' => [$origin . '/checkout/order-received/%34%32/', $origin . '/checkout/order-received/'],
            'UUID' => [$origin . '/orders/9cbc804c-b4e9-4074-8b84-1f9c38be3b1b/', $origin . '/orders/{id}/'],
            'order key path' => [$origin . '/checkout/wc_order_SYNTHETIC/', $origin . '/checkout/{id}/'],
            'default port' => ['https://STORE.example.test:443/checkout/', $origin . '/checkout/'],
            'root' => [$origin, $origin . '/'],
            'off-site' => ['https://external.example/checkout/', null],
            'host suffix' => [$origin . '.external.example/checkout/', null],
            'port mismatch' => [$origin . ':444/checkout/', null],
            'HTTP mismatch' => ['http://store.example.test/checkout/', null],
            'credentials' => ['https://user:password@store.example.test/checkout/', null],
            'non-http' => ['javascript:alert(1)', null],
            'relative' => ['/checkout/', null],
            'empty' => ['', null],
            'missing' => [null, null],
            'non-string' => [[], null],
            'control' => [$origin . "/checkout/\r\nSYNTHETIC", null],
            'backslash' => [$origin . '/checkout\\SYNTHETIC', null],
            'encoded contact' => [$origin . '/buyer/synthetic%40example.test/', null],
            'encoded control' => [$origin . '/checkout/%0a/', null],
            'encoded slash' => [$origin . '/checkout%2fother/', null],
            'double encoding' => [$origin . '/checkout/%2540/', null],
            'dot traversal' => [$origin . '/checkout/../other/', null],
            'double slash' => [$origin . '//checkout/', null],
            'invalid placeholder' => [$origin . '/{SYNTHETIC}/', null],
            'oversized' => [$origin . '/' . str_repeat('a/', 100), null],
        ];
    }

    /** @dataProvider requests */
    public function testRequestContextDistinguishesThePageFromAjaxAndBackgroundEndpoints(array $server, ?string $expected): void
    {
        $this->assertSame($expected, SmartPixMetricPage::fromRequest($server, self::STORE));
    }

    public static function requests(): array
    {
        $origin = 'https://store.example.test';
        $thankyou = $origin . '/checkout/order-received/';
        return [
            'direct page wins over previous Referer' => [['REQUEST_URI' => '/checkout/order-received/42/?key=wc_order_SYNTHETIC', 'HTTP_REFERER' => $origin . '/checkout/'], $thankyou],
            'Classic checkout Ajax' => [['REQUEST_URI' => '/?wc-ajax=checkout', 'HTTP_REFERER' => $origin . '/checkout/?nonce=SYNTHETIC'], $origin . '/checkout/'],
            'thank you polling' => [['REQUEST_URI' => '/?wc-ajax=mp_smart_pix_order_status', 'HTTP_REFERER' => $thankyou . '42/?key=wc_order_SYNTHETIC'], $thankyou],
            'client polling failure' => [['REQUEST_URI' => '/wp-admin/admin-ajax.php', 'HTTP_REFERER' => $thankyou . '42/?key=wc_order_SYNTHETIC'], $thankyou],
            'Blocks checkout' => [['REQUEST_URI' => '/wp-json/wc/store/v1/checkout', 'HTTP_REFERER' => $origin . '/checkout-blocks/'], $origin . '/checkout-blocks/'],
            'plain REST permalinks' => [['REQUEST_URI' => '/?rest_route=/wc/store/v1/checkout', 'HTTP_REFERER' => $origin . '/checkout-blocks/'], $origin . '/checkout-blocks/'],
            'admin mutation' => [['REQUEST_URI' => '/wp-admin/admin-ajax.php', 'HTTP_REFERER' => $origin . '/wp-admin/post.php?post=42&_wpnonce=SYNTHETIC'], $origin . '/wp-admin/post.php'],
            'no Referer' => [['REQUEST_URI' => '/?wc-ajax=checkout'], null],
            'external Referer' => [['REQUEST_URI' => '/?wc-ajax=checkout', 'HTTP_REFERER' => 'https://external.example/checkout/'], null],
            'cron' => [['REQUEST_URI' => '/wp-cron.php?doing_wp_cron=SYNTHETIC', 'HTTP_REFERER' => $origin . '/checkout/'], null],
            'notification query' => [['REQUEST_URI' => '/?wc-api=WC_WooMercadoPago_CoreNotification', 'HTTP_REFERER' => $origin . '/checkout/'], null],
            'notification path' => [['REQUEST_URI' => '/wc-api/WC_WooMercadoPago_CoreNotification/', 'HTTP_REFERER' => $origin . '/checkout/'], null],
            'no request' => [['HTTP_REFERER' => $origin . '/checkout/'], null],
            'no context' => [[], null],
            'untrusted host ignored' => [['REQUEST_URI' => '/checkout/', 'HTTP_HOST' => 'external.example'], $origin . '/checkout/'],
            'absolute request' => [['REQUEST_URI' => 'https://external.example/checkout/'], null],
            'scheme relative request' => [['REQUEST_URI' => '//external.example/checkout/'], null],
        ];
    }

    public function testCustomRestPrefixUsesTheWordPressRequestContext(): void
    {
        $server = ['REQUEST_URI' => '/api/wc/store/v1/checkout',
            'HTTP_REFERER' => 'https://store.example.test/checkout-blocks/'];
        $this->assertSame('https://store.example.test/checkout-blocks/', SmartPixMetricPage::fromRequest($server, self::STORE, true));
        unset($server['HTTP_REFERER']);
        $this->assertNull(SmartPixMetricPage::fromRequest($server, self::STORE, true));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider backgroundContexts
     */
    public function testBackgroundWorkHasNoPageEvenWithAnApparentBrowserReferer(string $constant): void
    {
        define($constant, true);
        $_SERVER = ['REQUEST_URI' => '/checkout/', 'HTTP_REFERER' => 'https://store.example.test/checkout/'];
        $this->assertNull(SmartPixMetricPage::current(self::STORE));
    }

    public static function backgroundContexts(): array
    {
        return [['DOING_CRON'], ['WP_CLI'], ['WC_API_REQUEST']];
    }
}
