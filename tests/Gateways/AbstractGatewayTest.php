<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Tests\Mocks\GatewayMock;
use MercadoPago\Woocommerce\Tests\Traits\AssertArraySchema;
use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperties;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Helpers;
use MercadoPago\Woocommerce\Exceptions\RefundException;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AbstractGatewayTest extends TestCase
{
    use GatewayMock;
    use SetNotAccessibleProperties;
    use AssertArraySchema;

    private $sellerConfigMock;
    private $mercadopagoMock;
    private $adminTranslationsMock;
    /**
     * @var \Mockery\MockInterface|AbstractGateway
     */
    private $gateway;

    public function setUp(): void
    {
        $this->woocommerceSetUp();

        $this->mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->sellerConfigMock = Mockery::mock(Seller::class);
        $this->adminTranslationsMock = Mockery::mock(AdminTranslations::class);
        $this->gateway = Mockery::mock(AbstractGateway::class)->makePartial();
    }

    public function testProcessPayment()
    {
        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();

        $gateway = Mockery::mock(AbstractGateway::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $order = Mockery::mock('WC_Order');
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        $orderTotal = 100;
        $order->total = $orderTotal;

        $order->shouldReceive('get_total')
            ->andReturn($orderTotal);

        $cartHelper = Mockery::mock(Helpers::class);

        $discountValue = 10;
        $mercadopagoMock->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')
            ->once()
            ->with($gateway)
            ->andReturn($discountValue);

        $comissionValue = 1;
        $mercadopagoMock->helpers->cart->shouldReceive('calculateSubtotalWithCommission')
            ->once()
            ->with($gateway)
            ->andReturn($comissionValue);

        $productionMode = 'yes';

        $mercadopagoMock->storeConfig->shouldReceive('getProductionMode')
            ->once()
            ->andReturn($productionMode);

        $mercadopagoMock->orderMetadata->shouldReceive('setIsProductionModeData')
            ->once()
            ->with($order, $productionMode)
            ->andReturnSelf();

        $mercadopagoMock->orderMetadata->shouldReceive('setUsedGatewayData')
            ->once()
            ->with($order, '')
            ->andReturnSelf();

        $gateway->mercadopago = $mercadopagoMock;

        $gateway->discount = $discountValue;

        $text = 'discount of';
        $mercadopagoMock->storeTranslations->commonCheckout['discount_title'] = $text;

        $currencySymbol = '$';
        $mercadopagoMock->helpers->currency->shouldReceive('getCurrencySymbol')
            ->once()
            ->andReturn($currencySymbol);

        $mercadopagoMock->orderMetadata->shouldReceive('setDiscountData')
        ->once()
        ->with($order, 'discount of 9.09% = $ 10,00')
        ->andReturnSelf();

        $gateway->commission = $comissionValue;

        $text = 'fee of';
        $mercadopagoMock->storeTranslations->commonCheckout['fee_title'] = $text;

        $currencySymbol = '$';
        $mercadopagoMock->helpers->currency->shouldReceive('getCurrencySymbol')
            ->once()
            ->andReturn($currencySymbol);

        $mercadopagoMock->orderMetadata->shouldReceive('setCommissionData')
        ->once()
        ->with($order, "fee of 0.99% = $ 1,00")
        ->andReturnSelf();

        $result = $gateway->process_payment(1);
        $this->assertEquals($result, []);
        $this->assertIsArray($result);
    }

    public function testValidCredentialsReturnEmptyNotice()
    {

        $this->mercadopagoMock->sellerConfig = $this->sellerConfigMock;
        $this->mercadopagoMock->adminTranslations = $this->adminTranslationsMock;

        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
        ->once()
        ->andReturn(false);

        $this->gateway->id = 'test_gateway';
        $this->gateway->mercadopago = $this->mercadopagoMock;

        $result = $this->gateway->getCredentialExpiredNotice();
        $this->assertEquals(['type' => 'title', 'value' => ''], $result);
    }

    public function testReturnsNoticeForExpiredCredentialsNoCache()
    {
        $this->mercadopagoMock->sellerConfig = $this->sellerConfigMock;
        $this->mercadopagoMock->adminTranslations = $this->adminTranslationsMock;

        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validatePage')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validateSection')
        ->once()
        ->andReturn(true);

        WP_Mock::userFunction('get_transient')
        ->once()
        ->andReturn(false);

        $this->sellerConfigMock->shouldReceive('getCredentialsPublicKeyProd')
        ->once()
        ->andReturn('test_public_key');

        $this->sellerConfigMock->shouldReceive('isExpiredPublicKey')
            ->once()
            ->with('test_public_key')
            ->andReturn(true);

        WP_Mock::userFunction('set_transient')
            ->once()
            ->andReturn(true);

        $this->adminTranslationsMock->credentialsSettings = [
            'title_invalid_credentials' => 'Invalid Credentials',
            'subtitle_invalid_credentials' => 'Please update your credentials.',
            'button_invalid_credentials' => 'Update Credentials'
        ];

        $linksMock = [
            'admin_settings_page' => 'http://localhost.com/settings'
        ];

        $this->gateway->mercadopago = $this->mercadopagoMock;

        $reflection = new \ReflectionClass($this->gateway);
        $property = $reflection->getProperty('links');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $linksMock);

        $this->gateway->id = 'test_gateway';
        $result = $this->gateway->getCredentialExpiredNotice();

        $expected = [
            'type'  => 'mp_card_info',
            'value' => [
                'title'       => 'Invalid Credentials',
                'subtitle'    => 'Please update your credentials.',
                'button_text' => 'Update Credentials',
                'button_url'  => 'http://localhost.com/settings',
                'icon'        => 'mp-icon-badge-warning',
                'color_card'  => 'mp-alert-color-error',
                'size_card'   => 'mp-card-body-size',
                'target'      => '_blank',
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testReturnsNoticeForExpiredCredentialsWithCache()
    {
        $this->mercadopagoMock->sellerConfig = $this->sellerConfigMock;
        $this->mercadopagoMock->adminTranslations = $this->adminTranslationsMock;

        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validatePage')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validateSection')
        ->once()
        ->andReturn(true);

        $expected = [
            'type'  => 'mp_card_info',
            'value' => [
                'title'       => 'Invalid Credentials',
                'subtitle'    => 'Please update your credentials.',
                'button_text' => 'Update Credentials',
                'button_url'  => 'http://localhost.com/settings',
                'icon'        => 'mp-icon-badge-warning',
                'color_card'  => 'mp-alert-color-error',
                'size_card'   => 'mp-card-body-size',
                'target'      => '_blank',
            ]
        ];

        WP_Mock::userFunction('get_transient')
        ->once()
        ->andReturn($expected);

        $this->gateway->mercadopago = $this->mercadopagoMock;

        $this->gateway->id = 'test_gateway';
        $result = $this->gateway->getCredentialExpiredNotice();
        $this->assertEquals($expected, $result);
    }

    public function testGetCredentialExpiredNoticeReturnsEmptyNoticeWhenNotAdminOrInvalidPageOrSection()
    {
        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
            ->once()
            ->andReturn(false);

        $this->mercadopagoMock->helpers->url->shouldReceive('validatePage')
            ->never();

        $this->mercadopagoMock->helpers->url->shouldReceive('validateSection')
            ->never();

        $this->gateway->mercadopago = $this->mercadopagoMock;

        $result = $this->gateway->getCredentialExpiredNotice();

        $this->assertEquals(['type' => 'title', 'value' => ''], $result);
    }

    public function testGetCredentialExpiredNoticeReturnsCachedResult()
    {
        WP_Mock::userFunction('get_transient')
            ->once()
            ->with('mp_credentials_expired_result')
            ->andReturn(['type' => 'cached', 'value' => 'cached_value']);

        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
            ->once()
            ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validatePage')
            ->once()
            ->andReturn(true);

        $this->gateway->id = 'test_gateway';

        $this->mercadopagoMock->helpers->url->shouldReceive('validateSection')
            ->once()
            ->with($this->gateway->id)
            ->andReturn(true);

        $this->gateway->mercadopago = $this->mercadopagoMock;

        $result = $this->gateway->getCredentialExpiredNotice();

        $this->assertEquals(['type' => 'cached', 'value' => 'cached_value'], $result);
    }

    public function testGetCredentialExpiredNoticeWithEmptyCachedResultAndValidCredentials()
    {
        $this->gateway->mercadopago = $this->mercadopagoMock;
        $this->mercadopagoMock->sellerConfig = $this->sellerConfigMock;

        $this->mercadopagoMock->hooks->admin->shouldReceive('isAdmin')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validatePage')
        ->once()
        ->andReturn(true);

        $this->mercadopagoMock->helpers->url->shouldReceive('validateSection')
        ->once()
        ->andReturn(true);

        $this->sellerConfigMock->shouldReceive('getCredentialsPublicKeyProd')
        ->once()
        ->andReturn('test_public_key');

        $this->sellerConfigMock->shouldReceive('isExpiredPublicKey')
        ->once()
        ->with('test_public_key')
        ->andReturn(false);

        WP_Mock::userFunction('get_transient')
        ->once()
        ->with('mp_credentials_expired_result')
        ->andReturn([]);

        WP_Mock::userFunction('set_transient')
        ->once()
        ->andReturn(true);

        $expected = ['type' => 'title', 'value' => ''];

        $this->gateway->id = 'test_gateway';
        $result = $this->gateway->getCredentialExpiredNotice();

        $this->assertEquals($expected, $result);
    }

    public function testGetConnectionUrl()
    {
        $linksMock = [
            'admin_settings_page' => 'http://localhost.com/wp-admin/admin.php?page=mercadopago-settings'
        ];

        $this->gateway->mercadopago = $this->mercadopagoMock;

        $reflection = new \ReflectionClass($this->gateway);
        $property = $reflection->getProperty('links');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $linksMock);

        $result = $this->gateway->get_connection_url();

        $this->assertEquals('http://localhost.com/wp-admin/admin.php?page=mercadopago-settings', $result);
    }

    public function testGetSettingsUrl()
    {
        WP_Mock::userFunction('admin_url')
            ->once()
            ->with('admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-basic')
            ->andReturn('http://localhost.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-basic');

        $this->gateway->id = 'woo-mercado-pago-basic';

        $result = $this->gateway->get_settings_url();

        $this->assertEquals('http://localhost.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-basic', $result);
    }

    public function testGetConnectionUrlWithDifferentUrls()
    {
        $linksMock = [
            'admin_settings_page' => 'https://localhost.com/wp-admin/custom-page-mercadopago'
        ];

        $this->gateway->mercadopago = $this->mercadopagoMock;

        $reflection = new \ReflectionClass($this->gateway);
        $property = $reflection->getProperty('links');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $linksMock);

        $result = $this->gateway->get_connection_url();

        $this->assertEquals('https://localhost.com/wp-admin/custom-page-mercadopago', $result);
        $this->assertIsString($result);
    }

    public function testGetConnectionUrlWithEmptyLinks()
    {
        $linksMock = [
            'admin_settings_page' => ''
        ];

        $reflection = new \ReflectionClass($this->gateway);
        $property = $reflection->getProperty('links');
        $property->setAccessible(true);
        $property->setValue($this->gateway, $linksMock);

        $result = $this->gateway->get_connection_url();

        $this->assertEquals('', $result);
        $this->assertIsString($result);
    }

    public function testGetSettingsUrlWithUppercaseId()
    {
        WP_Mock::userFunction('admin_url')
            ->once()
            ->with('admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-custom')
            ->andReturn('http://localhost.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-custom');

        $this->gateway->id = 'WOO-MERCADO-PAGO-CUSTOM';

        $result = $this->gateway->get_settings_url();

        $this->assertEquals('http://localhost.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-custom', $result);
    }

    public function testGetSettingsUrlWithMixedCaseId()
    {
        WP_Mock::userFunction('admin_url')
            ->once()
            ->with('admin.php?page=wc-settings&tab=checkout&section=test_gateway_123')
            ->andReturn('http://localhost.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=test_gateway_123');

        $this->gateway->id = 'Test_Gateway_123';

        $result = $this->gateway->get_settings_url();

        $this->assertEquals('http://localhost.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=test_gateway_123', $result);
    }

    public function testProcessRefundWithNoPermissionException()
    {
        $order = Mockery::mock('WC_Order');
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(123)
            ->andReturn($order);

        $mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();

        $refundHandlerMock = Mockery::mock('overload:MercadoPago\Woocommerce\Refund\RefundHandler');
        $refundHandlerMock->shouldReceive('processRefund')
            ->once()
            ->with(100.00, '')
            ->andThrow(new \Exception(RefundException::TYPE_NO_PERMISSION));

        $gateway = Mockery::mock(AbstractGateway::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $gateway->mercadopago = $mercadopago;

        $result = $gateway->process_refund(123, 100.00, '');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('refund_error', $result->get_error_code());
        $this->assertEquals('You do not have permission to process a refund. Please check your access to the site and try again.', $result->get_error_message());
    }

    public function testProcessRefundWithNotSupportedException()
    {
        $order = Mockery::mock('WC_Order');
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(789)
            ->andReturn($order);

        $mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();

        $refundHandlerMock = Mockery::mock('overload:MercadoPago\Woocommerce\Refund\RefundHandler');
        $refundHandlerMock->shouldReceive('processRefund')
            ->once()
            ->with(75.00, '')
            ->andThrow(new \Exception(RefundException::TYPE_SUPERTOKEN_NOT_SUPPORTED));

        $gateway = Mockery::mock(AbstractGateway::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $gateway->mercadopago = $mercadopago;

        $result = $gateway->process_refund(789, 75.00, '');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('refund_error', $result->get_error_code());
        $this->assertEquals('This payment was made using Fast Pay with Mercado Pago and does not yet support refunds through the WooCommerce order page. Please process the refund directly from your Mercado Pago payment details page.', $result->get_error_message());
    }

    public function testProcessRefundWithUnknownException()
    {
        $order = Mockery::mock('WC_Order');
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(456)
            ->andReturn($order);

        $mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();

        $refundHandlerMock = Mockery::mock('overload:MercadoPago\Woocommerce\Refund\RefundHandler');
        $refundHandlerMock->shouldReceive('processRefund')
            ->once()
            ->with(50.00, '')
            ->andThrow(new \Exception('some_other_error'));

        $gateway = Mockery::mock(AbstractGateway::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $gateway->mercadopago = $mercadopago;

        $result = $gateway->process_refund(456, 50.00, '');

        $this->assertInstanceOf('WP_Error', $result);
        $this->assertEquals('refund_error', $result->get_error_code());
        $this->assertEquals('Something went wrong. Please contact the Mercado Pago support team and we will help you resolve it.', $result->get_error_message());
    }

    /**
     * @testWith [true, true, false, false]
     *           [true, true, false, true]
     *           [false, false, false, false]
     */
    public function testFormFieldsHeaderSection(bool $homologValidate, bool $canCheckCredentials, bool $isCredentialsCached, bool $isCredentialsExpired)
    {
        $this->gateway->mercadopago = $this->mercadopagoMock;
        $this->gateway->mercadopago->sellerConfig
            ->expects()
            ->getHomologValidate()
            ->andReturn($homologValidate);

        $this->gateway->adminTranslations = MercadoPagoMock::mockTranslations([
            'header_title',
            'header_description',
            'card_settings_title',
            'card_settings_subtitle',
            'card_settings_button_text',
            'enabled_title',
            'enabled_subtitle',
            'enabled_descriptions_enabled',
            'enabled_descriptions_disabled',
            'title_title',
            'title_description',
            'title_default',
            'title_desc_tip',
        ]);

        $this->mockGatewayLinks([
            'admin_settings_page'
        ]);

        $this->gateway->mercadopago->adminTranslations->credentialsSettings = MercadoPagoMock::mockTranslations([
            'card_homolog_title',
            'card_homolog_subtitle',
            'card_homolog_button_text',
            'title_invalid_credentials',
            'subtitle_invalid_credentials',
            'button_invalid_credentials',
        ]);

        $credentialNotice = [
            'type' => IsType::TYPE_STRING,
            'value' => IsType::TYPE_STRING,
        ];

        if ($canCheckCredentials) {
            $this->gateway->id = 'abstract';
            $this->gateway->mercadopago->hooks->admin
                ->expects()
                ->isAdmin()
                ->andReturn(true);

            $this->gateway->mercadopago->helpers->url
                ->expects()
                ->validatePage('wc-settings')
                ->andReturn(true)
                ->getMock()
                ->expects()
                ->validateSection($this->gateway->id)
                ->andReturn(true);

            $getTransientMock = WP_Mock::userFunction('get_transient')
                ->with('mp_credentials_expired_result');

            if ($isCredentialsCached) {
                $getTransientMock->andReturn($credentialNotice);
            } else {
                $getTransientMock->andReturn([]);

                $this->gateway->mercadopago->sellerConfig
                    ->expects()
                    ->getCredentialsPublicKeyProd()
                    ->andReturn($publicKey = random()->uuid());

                $this->gateway->mercadopago->sellerConfig
                    ->expects()
                    ->isExpiredPublicKey($publicKey)
                    ->andReturn($isCredentialsExpired);

                if ($isCredentialsExpired) {
                    $credentialNotice = [
                        'type' => IsType::TYPE_STRING,
                        'value' => [
                            'title' => IsType::TYPE_STRING,
                            'subtitle' => IsType::TYPE_STRING,
                            'button_text' => IsType::TYPE_STRING,
                            'button_url' => IsType::TYPE_STRING,
                            'icon' => IsType::TYPE_STRING,
                            'color_card' => IsType::TYPE_STRING,
                            'size_card' => IsType::TYPE_STRING,
                            'target' => IsType::TYPE_STRING,
                        ]
                    ];
                }

                WP_Mock::userFunction('set_transient')
                    ->with('mp_credentials_expired_result', Mockery::type('array'), 3600);
            }
        } else {
            $this->gateway->mercadopago->hooks->admin
                ->expects()
                ->isAdmin()
                ->andReturn(false);

            $credentialNotice = [
                'type' => IsType::TYPE_STRING,
                'value' => IsType::TYPE_STRING,
            ];
        }

        $this->assertArraySchema(
            [
                'header' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'description' => IsType::TYPE_STRING,
                ],
                'card_homolog_validate' => $homologValidate
                    ? [
                        'type' => IsType::TYPE_STRING,
                        'value' => IsType::TYPE_STRING,
                    ]
                    : [
                        'type' => IsType::TYPE_STRING,
                        'value' => [
                            'title' => IsType::TYPE_STRING,
                            'subtitle' => IsType::TYPE_STRING,
                            'button_text' => IsType::TYPE_STRING,
                            'button_url' => IsType::TYPE_STRING,
                            'icon' => IsType::TYPE_STRING,
                            'color_card' => IsType::TYPE_STRING,
                            'size_card' => IsType::TYPE_STRING,
                            'target' => IsType::TYPE_STRING,
                        ]
                    ],
                'card_invalid_credentials' => $credentialNotice,
                'card_settings' => [
                    'type' => IsType::TYPE_STRING,
                    'value' => [
                        'title' => IsType::TYPE_STRING,
                        'subtitle' => IsType::TYPE_STRING,
                        'button_text' => IsType::TYPE_STRING,
                        'button_url' => IsType::TYPE_STRING,
                        'icon' => IsType::TYPE_STRING,
                        'color_card' => IsType::TYPE_STRING,
                        'size_card' => IsType::TYPE_STRING,
                        'target' => IsType::TYPE_STRING,
                    ],
                ],
                'enabled' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'subtitle' => IsType::TYPE_STRING,
                    'default' => IsType::TYPE_STRING,
                    'descriptions' => [
                        'enabled' => IsType::TYPE_STRING,
                        'disabled' => IsType::TYPE_STRING,
                    ],
                ],
                'title' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'description' => IsType::TYPE_STRING,
                    'default' => IsType::TYPE_STRING,
                    'desc_tip' => IsType::TYPE_STRING,
                    'class' => IsType::TYPE_STRING,
                ],
            ],
            $this->gateway->formFieldsHeaderSection()
        );
    }
}
