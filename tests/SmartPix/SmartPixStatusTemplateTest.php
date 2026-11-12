<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use DOMDocument;
use DOMXPath;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixBuyerError;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class SmartPixStatusTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        WP_Mock::setUp();
        foreach (['esc_attr', 'esc_html', 'esc_url'] as $function) {
            WP_Mock::userFunction($function)->andReturnUsing(static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'));
        }
        WP_Mock::userFunction('wp_print_inline_script_tag')->andReturnUsing(static function (string $code, array $attributes): void {
            echo '<script id="' . htmlspecialchars($attributes['id'], ENT_QUOTES, 'UTF-8') . '">' . $code . '</script>';
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        WP_Mock::tearDown();
    }

    /** @dataProvider initialStateProvider */
    public function testTheInitialPageShowsOnlyItsCurrentStateAndRejection(string $state, ?string $error, string $variant): void
    {
        $xpath = $this->xpath($this->render($state, $error));
        $cards = $xpath->query('//*[@data-mp-smart-pix-state and not(@hidden)]');
        $this->assertSame(1, $cards->length);
        $this->assertSame($state, $cards->item(0)->getAttribute('data-mp-smart-pix-state'));
        if ($state === 'rejected') {
            $visible = $xpath->query('//*[@data-mp-smart-pix-error and not(@hidden)]');
            $this->assertSame(1, $visible->length);
            $this->assertSame($variant, $visible->item(0)->getAttribute('data-mp-smart-pix-error'));
            $link = $xpath->query('//*[@data-mp-smart-pix-state="rejected"]/a')->item(0);
            $this->assertSame('https://store.test/shop/', $link->getAttribute('href'));
            $this->assertSame('Fazer nova compra', $link->textContent);
        }
        if ($state === 'cancelled') {
            $icon = $xpath->query('//*[@data-mp-smart-pix-state="cancelled"]//*[contains(@class,"__heading")]//img');
            $this->assertSame(1, $icon->length);
            $this->assertSame('/assets/images/icons/smart-pix-rejected.svg', $icon->item(0)->getAttribute('src'));
            $this->assertSame(0, $xpath->query('//*[@data-mp-smart-pix-state="cancelled"]//a | //*[@data-mp-smart-pix-state="cancelled"]//button')->length);
        }
        $this->assertSame(0, $xpath->query('//a[contains(@href,"order-pay")]')->length);
        $this->assertSame(0, $xpath->query('//*[@role="progressbar" or @aria-valuenow]')->length);
        $this->assertSame('polite', $xpath->query('//*[@role="status"]')->item(0)->getAttribute('aria-live'));
    }

    public static function initialStateProvider(): array
    {
        return [
            ['pending', null, ''], ['approved', null, ''], ['cancelled', null, ''],
            ['rejected', SmartPixBuyerError::REJECTED, SmartPixBuyerError::REJECTED],
            ['rejected', SmartPixBuyerError::INSUFFICIENT_BALANCE, SmartPixBuyerError::INSUFFICIENT_BALANCE],
            ['rejected', SmartPixBuyerError::LIMIT_EXCEEDED, SmartPixBuyerError::LIMIT_EXCEEDED],
            ['rejected', null, SmartPixBuyerError::REJECTED],
            ['rejected', 'private_provider_reason', SmartPixBuyerError::REJECTED],
        ];
    }

    public function testTranslatedTextIsEscapedAndDecorativeIconsHaveLocalSources(): void
    {
        $html = $this->render('approved', null, ['approved_title' => '<script>alert("x")</script>']);
        $xpath = $this->xpath($html);
        $this->assertSame(0, $xpath->query('//script')->length);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $icons = $xpath->query('//img');
        foreach ($icons as $icon) {
            $this->assertStringStartsWith('/assets/images/icons/smart-pix-', $icon->getAttribute('src'));
            $this->assertSame('', $icon->getAttribute('alt'));
            $this->assertSame('true', $icon->parentNode->getAttribute('aria-hidden'));
            $this->assertFileExists(dirname(__DIR__, 2) . $icon->getAttribute('src'));
        }
        $this->assertStringNotContainsString('figma.com', $html);
    }

    public function testTheFirstPaintPresenterIsStaticAndCannotIncludeTranslatedMarkup(): void
    {
        $html = $this->render('pending', null, ['processing_title' => '<script>alert("injected")</script>']);
        $xpath = $this->xpath($html);
        $scripts = $xpath->query('//script');
        $this->assertSame(1, $scripts->length);
        $this->assertSame('mp-smart-pix-initial-progress', $scripts->item(0)->getAttribute('id'));
        $this->assertStringNotContainsString('injected', $scripts->item(0)->textContent);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($document);
    }

    /** @dataProvider initialAgeProvider */
    public function testReloadRendersTheCurrentPresentationBeforeJavascript(int $age, int $stage, bool $waiting, bool $stopped): void
    {
        $xpath = $this->xpath($this->render('pending', null, ['initial_elapsed_ms' => $age]));
        $this->assertSame((string) $stage, $xpath->query('//*[@data-stage]')->item(0)->getAttribute('data-stage'));
        $this->assertSame($waiting, $xpath->query('//*[@data-mp-smart-pix-loading]')->item(0)->hasAttribute('hidden'));
        $this->assertSame(!$waiting, $xpath->query('//*[@data-mp-smart-pix-waiting]')->item(0)->hasAttribute('hidden'));
        $this->assertSame(!$stopped, $xpath->query('//*[@data-mp-smart-pix-timeout]')->item(0)->hasAttribute('hidden'));
        $this->assertSame('width: ' . min(90, $age * 90 / 30000) . '%', $xpath->query('//*[contains(@class,"__progress-fill")]')->item(0)->getAttribute('style'));
        $this->assertSame(1, $xpath->query('//script[@id="mp-smart-pix-initial-progress"]')->length);
        if ($stage === 4 && !$waiting) {
            $this->assertSame('Seu pagamento segue em processamento', $xpath->query('//*[@data-mp-smart-pix-step="4"]')->item(0)->textContent);
        }
        $this->assertSame(0, $xpath->query('//*[@data-mp-smart-pix-waiting]//a | //*[@data-mp-smart-pix-waiting]//button')->length);
    }

    public static function initialAgeProvider(): array
    {
        return [[0, 0, false, false], [10000, 1, false, false], [20000, 2, false, false],
            [30000, 3, false, false], [45000, 4, false, false], [120000, 4, true, false],
            [600000, 4, true, false], [900000, 4, true, true]];
    }

    private function render(string $state, ?string $error, array $overrides = []): string
    {
        extract(array_merge([
            'initial_state' => $state, 'initial_error' => $error,
            'processing_title' => 'Estamos processando seu pagamento',
            'processing_description' => 'Isso pode demorar alguns segundos',
            'finalizing_description' => 'Finalizando seu pagamento',
            'delayed_description' => 'Ainda estamos processando seu pagamento',
            'waiting_description' => 'Seu pagamento segue em processamento',
            'long_wait_description' => 'A confirmação pode levar até 15 minutos. Acompanhe o status nos pedidos da loja.',
            'info_icon' => '/assets/images/icons/smart-pix-info.svg',
            'timeout_description' => 'Consulte o status mais tarde.',
            'approved_title' => 'Seu pagamento foi aprovado', 'approved_description' => 'Já atualizamos o status do seu pedido.',
            'rejected_title' => 'Não conseguimos processar seu pagamento', 'rejected_description' => 'Faça uma nova compra com outro meio de pagamento.',
            'insufficient_title' => 'Saldo insuficiente', 'limit_title' => 'Limite do Pix atingido',
            'alternative_description' => 'Faça uma nova compra com outra conta ou outro meio de pagamento.',
            'new_purchase_url' => 'https://store.test/shop/', 'new_purchase_label' => 'Fazer nova compra',
            'approved_icon' => '/assets/images/icons/smart-pix-approved.svg', 'rejected_icon' => '/assets/images/icons/smart-pix-rejected.svg',
            'cancelled_title' => 'Seu pagamento foi cancelado',
            'cancelled_description' => 'O pedido foi cancelado. Consulte seus pedidos no site ou entre em contato com a loja.',
        ], $overrides), EXTR_SKIP);
        ob_start();
        include dirname(__DIR__, 2) . '/templates/public/order/smart-pix-status.php';
        return (string) ob_get_clean();
    }
}
