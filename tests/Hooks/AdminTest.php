<?php

namespace MercadoPago\Woocommerce\Hooks;

// Capture the WordPress boundary, matching GatewayTest's existing filter recorder.
// The callback remains the real closure created by Admin.
if (!function_exists('MercadoPago\\Woocommerce\\Hooks\\add_filter')) {
    function add_filter(string $tag, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['__captured_filters'][$tag][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $acceptedArgs,
        ];
        return true;
    }
}

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Hooks\Admin;
use MercadoPago\Woocommerce\Hooks\Template;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

class AdminTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
        $GLOBALS['__captured_filters'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__captured_filters']);
        WP_Mock::tearDown();
        Mockery::close();
    }

    public function testPluginActionLinksPrependRenderedLinksAndPreserveExistingLinks(): void
    {
        $admin = new Admin();
        $admin->registerPluginActionLinks('woocommerce-mercadopago/woocommerce-mercadopago.php', [
            ['href' => '/settings', 'target' => '_self', 'text' => 'Settings'],
            ['href' => 'https://example.test/docs', 'target' => '_blank', 'text' => 'Docs'],
        ]);

        $registration = $GLOBALS['__captured_filters']['plugin_action_links_woocommerce-mercadopago/woocommerce-mercadopago.php'][0];
        $this->assertSame(10, $registration['priority']);

        $result = ($registration['callback'])(['<a href="/deactivate">Deactivate</a>', 'Existing support']);

        $this->assertSame([
            '<a href="/settings" target="_self">Settings</a>',
            '<a href="https://example.test/docs" target="_blank">Docs</a>',
            '<a href="/deactivate">Deactivate</a>',
            'Existing support',
        ], $result);
    }

    public function testEmptyPluginActionLinksLeaveExistingLinksIntact(): void
    {
        (new Admin())->registerPluginActionLinks('fixture/plugin.php', []);
        $callback = $GLOBALS['__captured_filters']['plugin_action_links_fixture/plugin.php'][0]['callback'];

        $this->assertSame(['existing'], $callback(['existing']));
        $this->assertSame([], $callback([]));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPluginActionLinksAcceptTheExistingLinksArgument(): void
    {
        // The isolated process loads this local recorder regardless of the parent's file order.
        (new Admin())->registerPluginActionLinks('fixture/plugin.php', [
            ['href' => '/settings', 'target' => '_self', 'text' => 'Settings'],
        ]);
        $registration = $GLOBALS['__captured_filters']['plugin_action_links_fixture/plugin.php'][0];

        $this->assertSame(1, $registration['accepted_args'], 'WordPress must pass the existing links to the real filter closure.');
        $this->assertSame([
            '<a href="/settings" target="_self">Settings</a>',
            'Existing support',
        ], ($registration['callback'])(['Existing support']));
    }

    public function testMenuRegistrationPreservesCallbackAndPriority(): void
    {
        $callback = [new Template(), 'getWoocommerceTemplate'];
        $registered = false;
        WP_Mock::onActionAdded('admin_menu')->with($callback, 20, 1)
            ->perform(function () use (&$registered) {
                $registered = true;
            });

        (new Admin())->registerOnMenu(20, $callback);

        $this->assertTrue($registered, 'The real Admin hook must register the supplied callback at priority 20.');
    }

    public function testSubmenuRegistrationPreservesAllWordPressArguments(): void
    {
        $callback = [new Template(), 'getWoocommerceTemplate'];
        $arguments = null;
        WP_Mock::userFunction('add_submenu_page', [
            'return' => function (...$args) use (&$arguments) {
                $arguments = $args;
            },
        ]);

        (new Admin())->registerSubmenuPage('woocommerce', 'Mercado Pago settings', 'Mercado Pago', 'manage_woocommerce', 'mercadopago-settings', $callback);

        $this->assertSame(['woocommerce', 'Mercado Pago settings', 'Mercado Pago', 'manage_woocommerce', 'mercadopago-settings', $callback], $arguments);
    }

    /** @dataProvider adminContextProvider */
    public function testAdminContextUsesWordPressVerdict(bool $isAdmin): void
    {
        WP_Mock::userFunction('is_admin', ['times' => 1, 'return' => $isAdmin]);

        $this->assertSame($isAdmin, (new Admin())->isAdmin());
    }

    public static function adminContextProvider(): array
    {
        return ['admin page' => [true], 'storefront' => [false]];
    }
}
