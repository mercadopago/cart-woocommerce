<?php

namespace MercadoPago\Woocommerce\Tests\Blocks;

use MercadoPago\Woocommerce\Blocks\TicketBlock;
use MercadoPago\Woocommerce\Gateways\TicketGateway;

if (!defined('ABSPATH')) {
    exit;
}

class TicketBlockTest extends BlockTestCase
{
    protected string $blockClass = TicketBlock::class;
    protected string $gatewayClass = TicketGateway::class;
    protected string $siteId = 'MLB';

    public function testTicketRendersItsRealMethodAmountCurrencyAndBrazilAddressFields(): void
    {
        $params = $this->assertIdentityAndDataEnvelope('woo-mercado-pago-ticket');
        $this->assertSame('https://fixture.test/method.svg', $params['icon']);
        $this->assertStringContainsString('id="mp-checkout-ticket-root"', $params['content']);
        $this->assertStringContainsString('mp-checkout-ticket-single', $params['content']);
        $this->assertStringContainsString('value="bolbradesco"', $params['content']);
        $this->assertStringContainsString('id="ticket_amount" value="100"', $params['content']);
        $this->assertStringContainsString('id="ticket_currency_ratio" value="1.25"', $params['content']);
        $this->assertStringContainsString('name=\'mercadopago_ticket[address_zip_code]\'', $params['content']);
        $this->assertStringContainsString('documents=\'["CPF","CNPJ"]\'', $params['content']);
        $this->assertTemplateUsed('/public/checkouts/ticket-checkout.php');
    }

    public function testTicketScriptUsesItsOwnAsset(): void
    {
        $this->assertScriptContract('wc_mercadopago_ticket_blocks', 'ticket.block.js', 'ticket.block.asset.php');
    }

    public function testOfflineMethodEnablesTicket(): void
    {
        $this->assertTrue($this->block->is_active());
    }

    public function testLegacySerializedEmptyMethodListDisablesTicket(): void
    {
        // Seller supports the persisted JSON representation; a nonempty string avoids an API refresh.
        $this->options['_all_payment_methods_ticket'] = '[]';
        $this->assertFalse($this->block->is_active());
    }

    public function testMissingGatewayDoesNotRegisterScript(): void
    {
        $this->assertGatewayAbsenceDisablesBlock();
    }

    /** @dataProvider credentialProvider */
    public function testMissingCredentialDisablesTicket(string $option, bool $testMode): void
    {
        $this->assertMissingCredentialDisablesBlock($option, $testMode);
    }

    public function testDisabledOfflineMethodIsNotRenderedAsPaymentData(): void
    {
        $this->gateway->settings['bolbradesco'] = 'no';
        $params = $this->block->getScriptParams();
        $this->assertStringContainsString('id="mp-checkout-ticket-root"', $params['content']);
        $this->assertStringNotContainsString('name="mercadopago_ticket[payment_method_id]"', $params['content']);
        $this->assertStringNotContainsString('<input-table', $params['content']);
    }

    public function testMultipleOfflineMethodsRenderSelectionTable(): void
    {
        $this->options['_all_payment_methods_ticket'][] = [
            'id' => 'pec', 'name' => 'PEC', 'secure_thumbnail' => 'https://fixture.test/pec.svg',
        ];
        $params = $this->block->getScriptParams();
        $this->assertStringContainsString('mp-checkout-ticket-has-multiple', $params['content']);
        $this->assertStringContainsString('<input-table', $params['content']);
        $this->assertStringContainsString('&quot;value&quot;:&quot;bolbradesco&quot;', $params['content']);
        $this->assertStringContainsString('&quot;value&quot;:&quot;pec&quot;', $params['content']);
    }

    public function testUruguayRendersItsOwnDocumentContractWithoutBrazilAddressFields(): void
    {
        $this->setSite('MLU');
        $params = $this->block->getScriptParams();
        $this->assertStringContainsString('documents=\'["CI","OTRO"]\'', $params['content']);
        $this->assertStringContainsString('site-id="MLU"', $params['content']);
        $this->assertStringNotContainsString('mercadopago_ticket[address_zip_code]', $params['content']);
    }
}
