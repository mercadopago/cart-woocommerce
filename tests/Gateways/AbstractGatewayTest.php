<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Refund\RefundHandler;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Tests\Traits\AssertArrayMap;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use MercadoPago\Woocommerce\Tests\Mocks\ArrayMock;
use Mockery\MockInterface;
use Mockery\Exception\MockeryExceptionInterface;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\AbstractGateway;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Notification\NotificationFactory;
use MercadoPago\Woocommerce\Notification\NotificationHandler;
use Mockery;
use WP_Error;
use WP_Mock;

class AbstractGatewayTest extends TestCase
{
    use GatewayMock;
    use AssertArrayMap;
    use FormMock;

    private $sellerConfigMock;
    private $mercadopagoMock;
    private $adminTranslationsMock;
    private $gatewayClass = AbstractGateway::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|AbstractGateway
     */
    private $gateway;

    public function setUp(): void
    {
        $this->mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->sellerConfigMock = Mockery::mock(Seller::class);
        $this->adminTranslationsMock = Mockery::mock(AdminTranslations::class);
    }

    public function testProcessPayment()
    {
        $order = Mockery::mock('WC_Order');

        // Mock get_id() specifically for this test
        $order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        $orderTotal = 100;
        $order->total = $orderTotal;

        $order->shouldReceive('get_total')
            ->andReturn($orderTotal);

        $discountValue = 10;
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')
            ->once()
            ->with($this->gateway)
            ->andReturn($discountValue);

        $comissionValue = 1;
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithCommission')
            ->once()
            ->with($this->gateway)
            ->andReturn($comissionValue);

        $productionMode = 'yes';

        $this->gateway->mercadopago->storeConfig->shouldReceive('getProductionMode')
            ->once()
            ->andReturn($productionMode);

        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')
            ->once()
            ->with($order, $productionMode)
            ->andReturnSelf();

        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')
            ->once()
            ->with($order, '')
            ->andReturnSelf();

        $this->gateway->mercadopago = $this->gateway->mercadopago;

        $this->gateway->discount = $discountValue;

        $text = 'discount of';
        $this->gateway->mercadopago->storeTranslations->commonCheckout['discount_title'] = $text;

        $currencySymbol = '$';
        $this->gateway->mercadopago->helpers->currency->shouldReceive('getCurrencySymbol')
            ->once()
            ->andReturn($currencySymbol);

        $this->gateway->mercadopago->orderMetadata->shouldReceive('setDiscountData')
            ->once()
            ->with($order, 'discount of 9.09% = $ 10,00')
            ->andReturnSelf();

        $this->gateway->commission = $comissionValue;

        $text = 'fee of';
        $this->gateway->mercadopago->storeTranslations->commonCheckout['fee_title'] = $text;

        $currencySymbol = '$';
        $this->gateway->mercadopago->helpers->currency->shouldReceive('getCurrencySymbol')
            ->once()
            ->andReturn($currencySymbol);

        $this->gateway->mercadopago->orderMetadata->shouldReceive('setCommissionData')
            ->once()
            ->with($order, "fee of 0.99% = $ 1,00")
            ->andReturnSelf();

        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')
            ->andReturnNull();

        // Mock WordPress functions to avoid errors
        WP_Mock::userFunction('sanitize_post', [
            'return' => function ($data) {
                return $data;
            }
        ]);
        WP_Mock::userFunction('map_deep', [
            'return' => function ($data, $callback) {
                return is_array($data) ? array_map($callback, $data) : $callback($data);
            }
        ]);
        WP_Mock::userFunction('sanitize_text_field', [
            'return' => function ($data) {
                return $data;
            }
        ]);

        $this->gateway->expects()->proccessPaymentInternal($order)->andReturn($result = []);

        $this->assertSame($result, $this->gateway->process_payment(1));
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

    /**
     * @testWith ["no_permission"]
     *           ["supertoken_not_supported"]
     *           ["some_other_error", "unknown_error"]
     */
    public function testProcessRefundError(string $type, string $message = null)
    {
        WP_Mock::userFunction('wc_get_order')->once();

        Mockery::mock('overload:' . RefundHandler::class)
            ->shouldReceive('processRefund')
            ->once()
            ->with(100.00, '')
            ->andThrow(new \Exception($type));

        $this->gateway->mercadopago->adminTranslations->refund->except('some_other_error');

        Mockery::mock('overload:' . WP_Error::class)
            ->shouldReceive('__construct')
            ->once()
            ->with('refund_error', $this->gateway->mercadopago->adminTranslations->refund[$message ?? $type]);

        $this->assertInstanceOf(WP_Error::class, $this->gateway->process_refund(123, 100.00, ''));
    }

    /**
     * @testWith [true, true, false, false]
     *           [true, true, false, true]
     *           [false, false, false, false]
     */
    public function testFormFieldsHeaderSection(bool $homologValidate, bool $canCheckCredentials, bool $isCredentialsCached, bool $isCredentialsExpired)
    {
        $this->gateway->mercadopago->sellerConfig
            ->expects()
            ->getHomologValidate()
            ->andReturn($homologValidate);

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

        $this->assertArrayMap(
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

    public function testFormFieldsFooterSection()
    {
        $this->assertArrayMap(
            [
                'gateway_discount' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'input_type' => IsType::TYPE_STRING,
                    'description' => IsType::TYPE_STRING,
                    'checkbox_label' => IsType::TYPE_STRING,
                    'default' => IsType::TYPE_STRING,
                    'custom_attributes' => [
                        'step' => IsType::TYPE_STRING,
                        'min' => IsType::TYPE_STRING,
                        'max' => IsType::TYPE_STRING,
                    ],
                ],
                'commission' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'input_type' => IsType::TYPE_STRING,
                    'description' => IsType::TYPE_STRING,
                    'checkbox_label' => IsType::TYPE_STRING,
                    'default' => IsType::TYPE_STRING,
                    'custom_attributes' => [
                        'step' => IsType::TYPE_STRING,
                        'min' => IsType::TYPE_STRING,
                        'max' => IsType::TYPE_STRING,
                    ],
                ],
                'split_section' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                ],
                'support_link' => [
                    'type' => IsType::TYPE_STRING,
                    'bold_text' => IsType::TYPE_STRING,
                    'text_before_link' => IsType::TYPE_STRING,
                    'text_with_link' => IsType::TYPE_STRING,
                    'text_after_link' => IsType::TYPE_STRING,
                    'support_link' => IsType::TYPE_STRING,
                ],
            ],
            $this->gateway->formFieldsFooterSection()
        );
    }

    /**
     * Data provider for processReturnFail test cases
     *
     * @return array[]
     */
    public function processReturnFailProvider(): array
    {
        return [
            'with_known_error_and_notice' => [
                'error_message' => 'Invalid users involved',
                'expected_message' => 'Translated error message',
                'with_notice' => true
            ],
            'with_unknown_error_and_notice' => [
                'error_message' => 'Some unknown error',
                'expected_message' => 'Default error message',
                'with_notice' => true
            ],
            'without_notice' => [
                'error_message' => 'Any error message',
                'expected_message' => 'Any translated message',
                'with_notice' => false
            ]
        ];
    }

    /**
     * Test processReturnFail method behavior
     * Tests that the method correctly calls the errorMessages helper and returns the expected result
     *
     * @dataProvider processReturnFailProvider
     * @param string $error_message The error message to test
     * @param string $expected_message The expected message in the result
     * @param bool $with_notice Whether to show notice or not
     */
    public function testProcessReturnFail(string $error_message, string $expected_message, bool $with_notice)
    {
        $this->gateway->mercadopago = $this->mercadopagoMock;

        // Mock the errorMessages helper to return the expected message
        $this->mercadopagoMock->helpers->errorMessages
            ->shouldReceive('findErrorMessage')
            ->with($error_message)
            ->once()
            ->andReturn($expected_message);

        // Mock notices only if with_notice is true
        if ($with_notice) {
            $this->mercadopagoMock->helpers->notices
                ->shouldReceive('storeNotice')
                ->with($expected_message, 'error')
                ->once();
        } else {
            $this->mercadopagoMock->helpers->notices
                ->shouldReceive('storeNotice')
                ->never();
        }

        $exception = Mockery::mock(\Exception::class);
        $exception->shouldReceive('getMessage')->andReturn($error_message);

        $result = $this->gateway->processReturnFail(
            $exception,
            $error_message,
            'test_source',
            [],
            $with_notice
        );

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($expected_message, $result['message']);
    }

    /**
     * Test that MockeryExceptionInterface is re-thrown and not handled
     *
     * This test ensures that Mockery exceptions (used for test assertions)
     * are properly re-thrown instead of being caught and handled as business errors.
     * This is critical for test infrastructure - it allows PHPUnit to properly
     * report test failures when mock expectations are not met.
     *
     * GIVEN a Mockery exception is passed to processReturnFail
     * WHEN the method checks the exception type
     * THEN it should re-throw the exception without any processing
     * AND not log or handle it as a business error
     */
    public function testProcessReturnFailShouldRethrowMockeryExceptions()
    {
        // Given - Create a concrete exception that implements MockeryExceptionInterface
        // We use an anonymous class to simulate a Mockery exception
        $mockeryException = new class('Mockery expectation failed') extends \Exception implements MockeryExceptionInterface {
        };

        // Expect the exception to be re-thrown without any processing
        $this->expectException(MockeryExceptionInterface::class);
        $this->expectExceptionMessage('Mockery expectation failed');

        // When - processReturnFail is called with a Mockery exception
        // Then - The exception should be re-thrown immediately (line 632-633)
        $this->gateway->processReturnFail(
            $mockeryException,
            'test message',
            'test_source',
            [],
            false,
            false
        );

        // Note: Code after this point won't execute because the exception is thrown
        // The test passes if the MockeryExceptionInterface is thrown as expected
    }

    /**
     * Test that loadWooCommerceScripts calls registerWooCommerceScripts on the Scripts class
     */
    public function testLoadWooCommerceScriptsCallsRegisterMpBehaviorTrackingScript(): void
    {
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerMpBehaviorTrackingScript')
            ->once();

        $result = $this->gateway->loadMpWooCommerceScripts();

        $this->assertNull($result);
    }

    /**
     * Test webhook method with array data
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @return void
     */
    public function testWebhookWithArrayData()
    {
        $data = ['source_news' => 'webhooks', 'notification_id' => '123456789'];

        $this->mockFormSanitizedGetData($data);

        $notificationHandlerMock = Mockery::mock(NotificationHandler::class);
        $notificationHandlerMock->shouldReceive('handleReceivedNotification')
            ->once()
            ->with($data);

        $notificationFactoryMock = Mockery::mock('overload:' . NotificationFactory::class);
        $notificationFactoryMock->shouldReceive('createNotificationHandler')
            ->once()
            ->with(Mockery::type(AbstractGateway::class), $data)
            ->andReturn($notificationHandlerMock);

        $this->gateway->mercadopago = $this->mercadopagoMock;

        // Execute webhook and verify it doesn't throw exceptions
        $result = $this->gateway->webhook();

        // Assert that webhook method completes successfully (returns void)
        $this->assertNull($result);
    }

    /**
     * Test webhook method with non-array data
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @return void
     */
    public function testWebhookWithNonArrayData()
    {
        $data = '';
        $expectedArrayData = [$data];

        $this->mockFormSanitizedGetData($data);

        $notificationHandlerMock = Mockery::mock(NotificationHandler::class);
        $notificationHandlerMock->shouldReceive('handleReceivedNotification')
            ->once()
            ->with($expectedArrayData);

        $notificationFactoryMock = Mockery::mock('overload:' . NotificationFactory::class);
        $notificationFactoryMock->shouldReceive('createNotificationHandler')
            ->once()
            ->with(Mockery::type(AbstractGateway::class), $expectedArrayData)
            ->andReturn($notificationHandlerMock);

        $this->gateway->mercadopago = $this->mercadopagoMock;

        // Execute webhook and verify it doesn't throw exceptions
        $result = $this->gateway->webhook();

        // Assert that webhook method completes successfully (returns void)
        $this->assertNull($result);
    }

    /**
     * Test setCheckoutSessionDataOnSessionHelperByOrderId with classic checkout
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @return void
     */
    public function testSetCheckoutSessionDataOnSessionHelperByOrderIdClassicCheckout()
    {
        $orderId = '123';
        $checkoutSessionData = ['_mp_flow_id' => 'test-flow-id-456'];

        // Mock classic checkout POST data
        $_POST['mercadopago_checkout_session'] = $checkoutSessionData;

        // Mock Form::sanitizedPostData
        $this->mockFormSanitizedPostData($checkoutSessionData, 'mercadopago_checkout_session');

        // Mock session helper
        $this->gateway->mercadopago->helpers->session
            ->expects()
            ->setSession('mp_checkout_session_' . $orderId, $checkoutSessionData)
            ->once();

        $result = $this->gateway->setCheckoutSessionDataOnSessionHelperByOrderId($orderId);

        // Assert method completes successfully (returns void)
        $this->assertNull($result);

        // Cleanup
        unset($_POST['mercadopago_checkout_session']);
    }

    /**
     * Test setCheckoutSessionDataOnSessionHelperByOrderId with blocks checkout
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @return void
     */
    public function testSetCheckoutSessionDataOnSessionHelperByOrderIdBlocksCheckout()
    {
        $orderId = '456';
        $checkoutSessionData = ['_mp_flow_id' => 'test-flow-id-789'];
        $postData = [];

        // Mock blocks checkout (no mercadopago_checkout_session in $_POST)
        unset($_POST['mercadopago_checkout_session']);

        // Mock Form::sanitizedPostData without arguments
        $this->mockFormSanitizedPostData($postData);

        // Mock processBlocksCheckoutData
        $this->gateway
            ->expects()
            ->processBlocksCheckoutData('mercadopago_checkout_session', $postData)
            ->andReturn($checkoutSessionData);

        // Mock session helper
        $this->gateway->mercadopago->helpers->session
            ->expects()
            ->setSession('mp_checkout_session_' . $orderId, $checkoutSessionData)
            ->once();

        $result = $this->gateway->setCheckoutSessionDataOnSessionHelperByOrderId($orderId);

        // Assert method completes successfully (returns void)
        $this->assertNull($result);
    }

    /**
     * Test setCheckoutSessionDataOnSessionHelperByOrderId with empty data
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @return void
     */
    public function testSetCheckoutSessionDataOnSessionHelperByOrderIdWithEmptyData()
    {
        $orderId = '789';
        $emptyData = [];

        // Mock classic checkout with empty data
        $_POST['mercadopago_checkout_session'] = $emptyData;

        // Mock Form::sanitizedPostData
        $this->mockFormSanitizedPostData($emptyData, 'mercadopago_checkout_session');

        // Session helper should NOT be called when data is empty
        $this->gateway->mercadopago->helpers->session
            ->expects()
            ->setSession(Mockery::any(), Mockery::any())
            ->never();

        $result = $this->gateway->setCheckoutSessionDataOnSessionHelperByOrderId($orderId);

        // Assert method completes successfully (returns void)
        $this->assertNull($result);

        // Cleanup
        unset($_POST['mercadopago_checkout_session']);
    }

    /**
     * @testWith [true, "yes"]
     *           [false, "no"]
     */
    public function testGetEnabled(bool $expected, string $option)
    {
        $this->gateway
            ->expects()
            ->get_option('enabled', 'no')
            ->andReturn($option);

        $this->assertEquals(
            $expected,
            $this->gateway->getEnabled()
        );
    }

    /**
     * Test process payment behavior when currency conversion is enabled
     *
     * GIVEN a gateway with currency conversion enabled
     * WHEN process_payment is called
     * THEN it should calculate currency ratio and store it in order metadata
     */
    public function testProcessPaymentShouldSetCurrencyRatioWhenCurrencyConversionIsEnabled()
    {
        // Given - Setup basic order mock
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_total')->andReturn(100.0);

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        // Enable currency conversion in gateway settings
        $this->gateway->settings['currency_conversion'] = 'yes';

        // Mock basic dependencies
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')->andReturn(0);
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithCommission')->andReturn(0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getProductionMode')->andReturn('yes');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->andReturnSelf();

        // Mock currency ratio calculation
        $expectedRatio = 3.85;
        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getRatio')
            ->once()
            ->with($this->gateway)
            ->andReturn($expectedRatio);

        // When - Currency ratio metadata should be set
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCurrencyRatioData')
            ->once()
            ->with($order, $expectedRatio)
            ->andReturnSelf();

        // Initialize gateway properties
        $this->gateway->discount = 0;
        $this->gateway->commission = 0;

        // Mock WordPress functions
        WP_Mock::userFunction('sanitize_post', ['return' => function ($data) {
            return $data;
        }]);
        WP_Mock::userFunction('map_deep', ['return' => function ($data, $callback) {
            return is_array($data) ? array_map($callback, $data) : $callback($data);
        }]);
        WP_Mock::userFunction('sanitize_text_field', ['return' => function ($data) {
            return $data;
        }]);

        // Mock the internal payment processing
        $expectedResult = ['result' => 'success', 'redirect' => 'test-url'];
        $this->gateway->expects()->proccessPaymentInternal($order)->andReturn($expectedResult);

        // When - Execute process_payment
        $result = $this->gateway->process_payment(1);

        // Then - Verify the expected result is returned
        $this->assertSame($expectedResult, $result);
    }

    /**
     * Test process payment behavior when currency conversion is disabled
     *
     * GIVEN a gateway with currency conversion disabled
     * WHEN process_payment is called
     * THEN it should NOT calculate currency ratio or store it in order metadata
     */
    public function testProcessPaymentShouldNotSetCurrencyRatioWhenCurrencyConversionIsDisabled()
    {
        // Given - Setup basic order mock
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_total')->andReturn(100.0);

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        // Currency conversion is disabled by default
        $this->gateway->settings['currency_conversion'] = 'no';

        // Mock basic dependencies
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')->andReturn(0);
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithCommission')->andReturn(0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getProductionMode')->andReturn('yes');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->andReturnSelf();

        // When - Currency helper methods should NOT be called
        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getRatio')
            ->never();

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCurrencyRatioData')
            ->never();

        // Initialize gateway properties
        $this->gateway->discount = 0;
        $this->gateway->commission = 0;

        // Mock WordPress functions
        WP_Mock::userFunction('sanitize_post', ['return' => function ($data) {
            return $data;
        }]);
        WP_Mock::userFunction('map_deep', ['return' => function ($data, $callback) {
            return is_array($data) ? array_map($callback, $data) : $callback($data);
        }]);
        WP_Mock::userFunction('sanitize_text_field', ['return' => function ($data) {
            return $data;
        }]);

        // Mock the internal payment processing
        $expectedResult = ['result' => 'success', 'redirect' => 'test-url'];
        $this->gateway->expects()->proccessPaymentInternal($order)->andReturn($expectedResult);

        // When - Execute process_payment
        $result = $this->gateway->process_payment(1);

        // Then - Verify the expected result is returned
        $this->assertSame($expectedResult, $result);
    }


    /**
     * Test process payment behavior when currency conversion returns invalid ratio
     *
     * GIVEN a gateway with currency conversion enabled
     * AND currency helper returns zero or negative ratio
     * WHEN process_payment is called
     * THEN it should fail the payment and return error result
     *
     * @testWith [0]
     *           [-1]
     *           [0.0]
     *           [-0.5]
     */
    public function testProcessPaymentShouldFailWhenCurrencyRatioIsInvalid($invalidRatio)
    {
        // Given - Setup basic order mock
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_total')->andReturn(100.0);

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        // Enable currency conversion in gateway settings
        $this->gateway->settings['currency_conversion'] = 'yes';

        // Mock basic dependencies
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')->andReturn(0);
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithCommission')->andReturn(0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getProductionMode')->andReturn('yes');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->andReturnSelf();

        // When - Currency ratio calculation returns invalid value
        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getRatio')
            ->once()
            ->with($this->gateway)
            ->andReturn($invalidRatio);

        // Mock notice storage for error handling
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with(Mockery::type('string'), 'error');

        // When - Execute process_payment
        $result = $this->gateway->process_payment(1);

        // Then - Payment should fail due to invalid currency ratio
        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Test process payment behavior when currency conversion throws exception
     *
     * GIVEN a gateway with currency conversion enabled
     * AND currency helper throws an exception during getRatio
     * WHEN process_payment is called
     * THEN it should fail the payment and return error result
     */
    public function testProcessPaymentShouldFailWhenCurrencyConversionThrowsException()
    {
        // Given - Setup basic order mock
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_total')->andReturn(100.0);

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        // Enable currency conversion in gateway settings
        $this->gateway->settings['currency_conversion'] = 'yes';

        // Mock basic dependencies
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')->andReturn(0);
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithCommission')->andReturn(0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getProductionMode')->andReturn('yes');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->andReturnSelf();

        // When - Currency ratio calculation throws an exception
        $exceptionMessage = 'Currency conversion API unavailable';
        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getRatio')
            ->once()
            ->with($this->gateway)
            ->andThrow(new \Exception($exceptionMessage));

        // Mock notice storage for error handling
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with(Mockery::type('string'), 'error');

        // When - Execute process_payment
        $result = $this->gateway->process_payment(1);

        // Then - Payment should fail due to currency conversion exception
        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Test process payment behavior when RejectedPaymentException is thrown
     *
     * GIVEN a gateway processing a payment
     * AND proccessPaymentInternal throws a RejectedPaymentException
     * WHEN process_payment is called
     * THEN it should fail the payment with the rejection message
     * AND the error should be marked as handled (no translation, no datadog)
     */
    public function testProcessPaymentShouldFailWhenRejectedPaymentExceptionIsThrown()
    {
        // Given - Setup basic order mock
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_total')->andReturn(100.0);

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        // Mock basic dependencies
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')->andReturn(0);
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithCommission')->andReturn(0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getProductionMode')->andReturn('yes');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->andReturnSelf();

        // Initialize discount and commission properties
        $this->gateway->discount = 0;
        $this->gateway->commission = 0;

        // Mock session helper - called before exception
        $this->gateway->shouldReceive('setCheckoutSessionDataOnSessionHelperByOrderId')
            ->once()
            ->with($order->get_id());

        // When - proccessPaymentInternal throws RejectedPaymentException
        $rejectionMessage = 'Invalid users involved';
        $this->gateway
            ->shouldReceive('proccessPaymentInternal')
            ->once()
            ->with($order)
            ->andThrow(new \MercadoPago\Woocommerce\Exceptions\RejectedPaymentException($rejectionMessage));

        // Mock error messages helper - should NOT be called because handled=true
        $this->gateway->mercadopago->helpers->errorMessages
            ->shouldReceive('findErrorMessage')
            ->never();

        // Mock datadog - should NOT be called because handled=true
        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->never();

        // Mock notice storage for error handling
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($rejectionMessage, 'error');

        // When - Execute process_payment
        $result = $this->gateway->process_payment(1);

        // Then - Payment should fail with the rejection message
        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($rejectionMessage, $result['message']);
    }

    /**
     * Test process payment behavior when RejectedPaymentException is thrown with custom message
     *
     * GIVEN a gateway processing a payment
     * AND proccessPaymentInternal throws a RejectedPaymentException with specific message
     * WHEN process_payment is called
     * THEN it should fail the payment with the exact rejection message
     * AND not translate or modify the message
     */
    public function testProcessPaymentShouldPreserveRejectedPaymentExceptionMessage()
    {
        // Given - Setup basic order mock
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_total')->andReturn(100.0);

        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        // Mock basic dependencies
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithDiscount')->andReturn(0);
        $this->gateway->mercadopago->helpers->cart->shouldReceive('calculateSubtotalWithCommission')->andReturn(0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getProductionMode')->andReturn('yes');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->andReturnSelf();

        // Initialize discount and commission properties
        $this->gateway->discount = 0;
        $this->gateway->commission = 0;

        // Mock session helper - called before exception
        $this->gateway->shouldReceive('setCheckoutSessionDataOnSessionHelperByOrderId')
            ->once()
            ->with($order->get_id());

        // When - proccessPaymentInternal throws RejectedPaymentException with specific message
        $specificMessage = 'Card payment declined - insufficient funds';
        $this->gateway
            ->shouldReceive('proccessPaymentInternal')
            ->once()
            ->with($order)
            ->andThrow(new \MercadoPago\Woocommerce\Exceptions\RejectedPaymentException($specificMessage));

        // Mock notice storage
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($specificMessage, 'error');

        // When - Execute process_payment
        $result = $this->gateway->process_payment(1);

        // Then - Payment should fail with the exact message (not translated)
        $this->assertEquals('fail', $result['result']);
        $this->assertEquals($specificMessage, $result['message']);
    }
}
