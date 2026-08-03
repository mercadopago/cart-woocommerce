<?php

namespace MercadoPago\Woocommerce\Tests\Helpers;

use MercadoPago\Woocommerce\Helpers\Template;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

if (!defined('MP_PLUGIN_FILE')) {
    define('MP_PLUGIN_FILE', dirname(__DIR__, 2) . '/woocommerce-mercadopago.php');
}

class TemplateTest extends TestCase
{
    /**
     * @before
     */
    public function templateSetUp(): void
    {
        WP_Mock::setUp();
    }

    /**
     * @after
     */
    public function templateTearDown(): void
    {
        WP_Mock::tearDown();
        Mockery::close();
    }

    // PSW-4264: third arg must be '' not null — Divi 5.9.0 typed hook callback triggers a TypeError on PHP 8.x.
    public function testRenderPassesEmptyStringAsThirdArgument(): void
    {
        $capturedTemplatePath = 'NOT_CALLED';

        WP_Mock::userFunction('wc_get_template', [
            'times'  => 1,
            'return' => function ($templateName, $args, $templatePath, $defaultPath) use (&$capturedTemplatePath) {
                $capturedTemplatePath = $templatePath;
            },
        ]);

        Template::render('test-template', ['key' => 'value']);

        $this->assertSame('', $capturedTemplatePath, 'Third argument ($template_path) must be an empty string, never null.');
    }

    // PSW-4264: third arg must be '' not null (same TypeError guard); also verifies the returned HTML passes through.
    public function testHtmlPassesEmptyStringAsThirdArgument(): void
    {
        $expectedHtml         = '<div class="template-output">Test Content</div>';
        $capturedTemplatePath = 'NOT_CALLED';

        WP_Mock::userFunction('wc_get_template_html', [
            'times'  => 1,
            'return' => function ($templateName, $args, $templatePath, $defaultPath) use (&$capturedTemplatePath, $expectedHtml) {
                $capturedTemplatePath = $templatePath;
                return $expectedHtml;
            },
        ]);

        $result = Template::html('test-template', ['key' => 'value']);

        $this->assertSame('', $capturedTemplatePath, 'Third argument ($template_path) must be an empty string, never null.');
        $this->assertSame($expectedHtml, $result);
    }
}
