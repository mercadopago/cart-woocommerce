<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use MercadoPago\Woocommerce\Hooks\Template;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('MP_PLUGIN_FILE')) {
    define('MP_PLUGIN_FILE', dirname(__DIR__, 2) . '/woocommerce-mercadopago.php');
}

class TemplateTest extends TestCase
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

    public function testRenderPassesThroughRealHelperToWooCommerceWithArguments(): void
    {
        $arguments = null;
        WP_Mock::userFunction('wc_get_template', [
            'return' => function (...$args) use (&$arguments) {
                $arguments = $args;
            },
        ]);

        (new Template())->getWoocommerceTemplate('admin/fixture-view', ['state' => 'ready']);

        $this->assertSame(['/admin/fixture-view.php', ['state' => 'ready'], '', dirname(MP_PLUGIN_FILE) . '/templates'], $arguments);
    }

    public function testRenderDefaultsToEmptyVariablesAndDoesNotAppendAnotherExtension(): void
    {
        $arguments = null;
        WP_Mock::userFunction('wc_get_template', [
            'return' => function (...$args) use (&$arguments) {
                $arguments = $args;
            },
        ]);

        (new Template())->getWoocommerceTemplate('admin/fixture-view.php');

        $this->assertSame(['/admin/fixture-view.php', [], '', dirname(MP_PLUGIN_FILE) . '/templates'], $arguments);
    }

    public function testHtmlPassesThroughRealHelperAndPreservesWooCommerceOutput(): void
    {
        $arguments = null;
        WP_Mock::userFunction('wc_get_template_html', [
            'return' => function (...$args) use (&$arguments) {
                $arguments = $args;
                return '<section>ready</section>';
            },
        ]);

        $this->assertSame('<section>ready</section>', (new Template())->getWoocommerceTemplateHtml('admin/fixture-view', ['state' => 'ready']));
        $this->assertSame(['/admin/fixture-view.php', ['state' => 'ready'], '', dirname(MP_PLUGIN_FILE) . '/templates'], $arguments);
    }

    public function testHtmlDefaultsToEmptyVariablesAndPreservesEmptyOutput(): void
    {
        $arguments = null;
        WP_Mock::userFunction('wc_get_template_html', [
            'return' => function (...$args) use (&$arguments) {
                $arguments = $args;
                return '';
            },
        ]);

        $this->assertSame('', (new Template())->getWoocommerceTemplateHtml('admin/fixture-view.php'));
        $this->assertSame(['/admin/fixture-view.php', [], '', dirname(MP_PLUGIN_FILE) . '/templates'], $arguments);
    }
}
