<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PixGatewayTest extends TestCase
{
    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetCheckoutName()
    {
        $gateway = Mockery::mock(PixGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $result = $gateway->getCheckoutName();
        $this->assertEquals('checkout-pix', $result);
    }

   public function testPaymentScripts()
   {
        $id = 'woo-mercado-pago-pix';

        $pixGatewayMock = Mockery::mock(PixGateway::class)->makePartial();
        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $pixGatewayMock->mercadopago = $mercadopagoMock;

        $this->assertTrue(method_exists($pixGatewayMock, 'payment_scripts'));

        $pixGatewayMock->shouldReceive('canAdminLoadScriptsAndStyles')
            ->once()
            ->with($id)
            ->andReturn(false);
        $pixGatewayMock->shouldReceive('canCheckoutLoadScriptsAndStyles')
            ->once()
            ->andReturn(true);
        $pixGatewayMock->shouldReceive('registerCheckoutScripts')
            ->once()
            ->andReturn(null);

        // Use reflection to call the payment_scripts method
        $reflection = new \ReflectionClass($pixGatewayMock);
        $method = $reflection->getMethod('payment_scripts');
        $method->setAccessible(true);

        // Call the method using reflection
        $method->invoke($pixGatewayMock, $id);
   }

    public function testPaymentScriptsDoesNotRegisterCheckoutScriptsWhenNotAllowed()
    {
        $id = 'woo-mercado-pago-pix';

        $pixGatewayMock = Mockery::mock(PixGateway::class)->makePartial();
        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $pixGatewayMock->mercadopago = $mercadopagoMock;
        $this->assertTrue(method_exists($pixGatewayMock, 'payment_scripts'));

        $pixGatewayMock->shouldReceive('canAdminLoadScriptsAndStyles')
            ->once()
            ->with($id)
            ->andReturn(false);
        $pixGatewayMock->shouldReceive('canCheckoutLoadScriptsAndStyles')
            ->once()
            ->andReturn(false);
        $pixGatewayMock->shouldReceive('registerCheckoutScripts')
            ->never();

        $reflection = new \ReflectionClass($pixGatewayMock);
        $method = $reflection->getMethod('payment_scripts');
        $method->setAccessible(true);
        $method->invoke($pixGatewayMock, $id);
    }


    public function testInitFormFieldsWithValidConditionsPopulatesFormFields() {

        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $pixGatewayMock = Mockery::mock(PixGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $pixGatewayMock->mercadopago = $mercadopagoMock;

        // Set up the required adminTranslations keys for pixGatewaySettings
        $pixGatewaySettings = [
            'header_title' => 'Transparent Checkout | Pix',
            'header_description' => 'With the Transparent Checkout, you can sell inside your store environment, without redirection and all the safety from Mercado Pago.',
            'card_settings_title' => 'Mercado Pago plugin general settings',
            'card_settings_subtitle' => 'Set the deadlines and fees, test your store or access the Plugin manual.',
            'card_settings_button_text' => 'Go to Settings',
            'enabled_title' => 'Enable the checkout',
            'enabled_subtitle' => 'By disabling it, you will disable all Pix payments from Mercado Pago Transparent Checkout.',
            'enabled_descriptions_enabled' => 'The transparent checkout for Pix payment is enabled.',
            'enabled_descriptions_disabled' => 'The transparent checkout for Pix payment is disabled.',
            'title_title' => 'Title in the store Checkout',
            'title_description' => 'Title that the customer sees during the checkout.',
            'title_default' => 'Pix',
            'title_desc_tip' => 'Title that the customer sees during the checkout.',
            'expiration_date_title' => 'Pix expiration time',
            'expiration_date_description' => 'Time in which the Pix payment will expire.',
            'expiration_date_options_15_minutes' => '15 minutes',
            'expiration_date_options_30_minutes' => '30 minutes',
            'expiration_date_options_60_minutes' => '60 minutes',
            'expiration_date_options_12_hours' => '12 hours',
            'expiration_date_options_24_hours' => '24 hours',
            'expiration_date_options_2_days' => '2 days',
            'expiration_date_options_3_days' => '3 days',
            'expiration_date_options_4_days' => '4 days',
            'expiration_date_options_5_days' => '5 days',
            'expiration_date_options_6_days' => '6 days',
            'expiration_date_options_7_days' => '7 days',
            'currency_conversion_title' => 'Currency conversion',
            'currency_conversion_subtitle' => 'Enable currency conversion for this payment method.',
            'currency_conversion_descriptions_enabled' => 'Currency conversion is enabled.',
            'currency_conversion_descriptions_disabled' => 'Currency conversion is disabled.',
            'card_info_title' => 'Pix payment method',
            'card_info_subtitle' => 'Learn more about Pix payments.',
            'card_info_button_text' => 'Learn more',
            'advanced_configuration_title' => 'Advanced configuration',
            'advanced_configuration_subtitle' => 'Configure additional options for this payment method.',
            'support_link_bold_text' => 'Need help?',
            'support_link_text_before_link' => 'Check our',
            'support_link_text_with_link' => 'documentation',
            'support_link_text_after_link' => 'or contact our support team.',
            'steps_title' => 'How to activate Pix',
            'steps_step_one_text' => 'Step 1: Access your Mercado Pago account',
            'steps_step_two_text' => 'Step 2: Configure your Pix keys',
            'steps_step_three_text' => 'Step 3: Activate Pix in your store',
            'steps_observation_one' => 'Observation 1',
            'steps_observation_two' => 'Observation 2',
            'steps_button_about_pix' => 'Learn more about Pix',
            'steps_observation_three' => 'Observation 3',
            'steps_link_title_one' => 'Mercado Pago Pix',
        ];

        $pixGatewayMock->adminTranslations = $pixGatewaySettings;

        // Set up the links property that is required by AbstractGateway using reflection
        $reflection = new \ReflectionClass($pixGatewayMock);
        $linksProperty = $reflection->getProperty('links');
        $linksProperty->setAccessible(true);
        $linksProperty->setValue($pixGatewayMock, [
            'admin_settings_page' => 'http://localhost.com/settings',
            'mercadopago_pix' => 'https://www.mercadopago.com.br/pix',
            'mercadopago_support' => 'https://www.mercadopago.com.br/support',
            'docs_support_faq' => 'https://developers.mercadopago.com.br/docs',
        ]);

        $pixGatewayMock->shouldReceive('getHomologValidateNoticeOrHidden')
            ->once()
            ->andReturn([
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => 'Test Pix',
                    'subtitle'    => 'Testing pix gateway configuration',
                    'button_text' => 'Press and pay',
                    'button_url'  => 'https://www.mercadopago.com.br',
                    'icon'        => 'mp-icon-badge-warning',
                    'color_card'  => 'mp-alert-color-alert',
                    'size_card'   => 'mp-card-body-size-homolog',
                    'target'      => '_blank'
                ]
            ]);

        $pixGatewayMock->shouldReceive('getCredentialExpiredNotice')
            ->once()
            ->andReturn([
                'type'  => 'mp_card_info',
                'value' => ''
            ]);

        $pixGatewayMock->shouldReceive('getCommissionField')
            ->once()
            ->andReturn([
                'commission_title' => [
                    'title' => 'Comissão do Gateway',
                    'type' => 'title',
                    'description' => 'Configure a comissão para este método de pagamento.'
                ]
            ]);

        $pixGatewayMock->shouldReceive('getDiscountField')
            ->once()
            ->andReturn([
                'discount_title' => [
                    'title' => 'Desconto do Gateway',
                    'type' => 'title',
                    'description' => 'Configure o desconto para este método de pagamento.'
                ]
            ]);

        $storeConfigMock = Mockery::mock(\MercadoPago\Woocommerce\Configs\Store::class);
        $storeConfigMock->shouldReceive('getCheckoutCountry')
            ->andReturn('BR');
        $pixGatewayMock->mercadopago->storeConfig = $storeConfigMock;
        $sellerConfigMock = Mockery::mock(\MercadoPago\Woocommerce\Configs\Seller::class);
        $sellerConfigMock->shouldReceive('getCredentialsPublicKey')
            ->andReturn('APP_USR-0000000000-0000-0000-0000-000000000000');
        $sellerConfigMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('APP_USR-0000000000-0000-0000-0000-000000000000');
        $sellerConfigMock->shouldReceive('getCheckoutPixPaymentMethods')
            ->andReturn(['pix']);
        $pixGatewayMock->mercadopago->sellerConfig = $sellerConfigMock;

        // Use reflection to call the init_form_fields method
        $reflection = new \ReflectionClass($pixGatewayMock);
        $method = $reflection->getMethod('init_form_fields');
        $method->setAccessible(true);
        $method->invoke($pixGatewayMock);

        $this->assertArrayHasKey('enabled', $pixGatewayMock->form_fields);
        $this->assertArrayHasKey('discount_title', $pixGatewayMock->form_fields['gateway_discount']);
        $this->assertArrayHasKey('title', $pixGatewayMock->form_fields['header']);
        $this->assertArrayHasKey('15 minutes', $pixGatewayMock->form_fields['expiration_date']['options']);
    }
}
