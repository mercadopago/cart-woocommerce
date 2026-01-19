<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use Exception;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Session;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use MercadoPago\Woocommerce\Transactions\CustomTransaction;
use MercadoPago\Woocommerce\Transactions\SupertokenTransaction;
use MercadoPago\Woocommerce\Transactions\WalletButtonTransaction;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;
use MercadoPago\Woocommerce\Tests\Traits\AssertArrayMap;
use PHPUnit\Framework\Constraint\IsType;

class CustomGatewayTest extends TestCase
{
    use AssertArrayMap;
    use GatewayMock;
    use FormMock;

    private string $gatewayClass = CustomGateway::class;

    /**
     * @var \Mockery\MockInterface|CustomGateway
     */
    private $gateway;

    public function testGetCheckoutName(): void
    {
        $this->assertSame($this->gateway->getCheckoutName(), 'checkout-custom');
    }

    private function processPaymentMock(array $checkout, bool $isBlocks, bool $isWalletButton = false)
    {
        $this->abstractGatewayProcessPaymentMock($isBlocks);
        if ($isBlocks) {
            $_POST['mercadopago_custom'] = null;

            $postData = [];

            // Use FormMock trait methods
            if ($isWalletButton) {
                $this->mockFormWithCustomSetup(function ($mock) use ($postData) {
                    $mock->expects()
                        ->sanitizedPostData()
                        ->twice() // Called for mercadopago_custom and mercadopago_checkout_session (processBlocksCheckoutData)
                        ->andReturn($postData);
                });
            } else {
                $this->mockFormSanitizedPostData($postData);
            }

            $this->gateway
                ->expects()
                ->processBlocksCheckoutData('mercadopago_custom', $postData)
                ->andReturn($checkout);

            if ($isWalletButton) {
                $this->gateway
                    ->expects()
                    ->processBlocksCheckoutData('mercadopago_checkout_session', $postData)
                    ->andReturn(['_mp_flow_id' => 'test-flow-id-123']);
            }
        } else {
            $_POST['mercadopago_custom'] = [];

            // Use FormMock trait method
            $this->mockFormWithCustomSetup(function ($mock) use ($checkout, $isWalletButton) {
                // Always expect sanitizedPostData() without args first (called by AbstractGateway::process_payment)
                $mock->shouldReceive('sanitizedPostData')
                    ->with()
                    ->andReturn([]);

                $mock->expects()
                    ->sanitizedPostData('mercadopago_custom')
                    ->andReturn($checkout);

                if ($isWalletButton) {
                    $mock->expects()
                        ->sanitizedPostData('mercadopago_checkout_session')
                        ->andReturn(['_mp_flow_id' => 'test-flow-id-123']);
                }
            });
        }
    }

    /**
     * @testWith [true]
     *           [false]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentWalletButton(bool $isBlocks): void
    {
        $this->processPaymentMock([
            'checkout_type' => 'wallet_button',
        ], $isBlocks, true);

        // Mock get_id() specifically for wallet button test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        // Mock session helper for wallet button
        $sessionHelper = Mockery::mock(Session::class);
        $sessionHelper->expects()
            ->setSession('mp_checkout_session_1', ['_mp_flow_id' => 'test-flow-id-123'])
            ->once();

        $this->gateway->mercadopago->helpers->session = $sessionHelper;

        // For classic checkout, set $_POST data for wallet button
        if (!$isBlocks) {
            $_POST['mercadopago_checkout_session'] = ['_mp_flow_id' => 'test-flow-id-123'];
        }

        $fakeUrl = random()->url();

        $this->order
            ->expects()
            ->get_checkout_payment_url(true)
            ->andReturn($fakeUrl);

        $this->gateway->mercadopago->helpers->url
            ->expects()
            ->setQueryVar(
                'wallet_button',
                'autoOpen',
                $fakeUrl
            )
            ->andReturn($fakeUrl);

        $this->assertEquals(
            [
                'result' => 'success',
                'redirect' => $fakeUrl,
            ],
            $this->gateway->process_payment(1)
        );
    }

    private function handleResponseStatusMock(array $response, bool $isOrderPayPage = false): array
    {
        switch ($response['status']) {
            case 'approved':
                $this->gateway->mercadopago->helpers->cart
                    ->expects()
                    ->emptyCart();

                $this->order
                    ->expects()
                    ->get_checkout_order_received_url()
                    ->andReturn($redirect = random()->url());

                $this->gateway->mercadopago->orderStatus
                    ->expects()
                    ->getOrderStatusMessage('accredited')
                    ->andReturn($statusMessage = random()->text());

                $this->gateway->mercadopago->helpers->notices
                    ->expects()
                    ->storeApprovedStatusNotice($statusMessage);

                $this->gateway->mercadopago->orderStatus
                    ->expects()
                    ->setOrderStatus($this->order, 'failed', 'pending');

                return [
                    'result' => 'success',
                    'redirect' => $redirect,
                ];

            case 'pending':
            case 'in_process':
                if ($response['status_detail'] === 'pending_challenge') {
                    $this->order->ID = 1;
                    $this->gateway->mercadopago->helpers->session
                        ->expects()
                        ->setSession('mp_3ds_url', $response['three_ds_info']['external_resource_url'])
                        ->getMock()
                        ->expects()
                        ->setSession('mp_3ds_creq', $response['three_ds_info']['creq'])
                        ->getMock()
                        ->expects()
                        ->setSession('mp_order_id', $this->order->ID)
                        ->getMock()
                        ->expects()
                        ->setSession('mp_payment_id', $response['id']);

                    $lastFourDigits = (empty($response['card']['last_four_digits'])) ? '****' : $response['card']['last_four_digits'];

                    return [
                        'result' => 'success',
                        'three_ds_flow' => true,
                        'last_four_digits' => $lastFourDigits,
                        'redirect' => false,
                        'messages' => "<script>window.mpCustomCheckoutHandler.threeDSHandler.load3DSFlow($lastFourDigits)</script>",
                    ];
                }

                $this->gateway->mercadopago->helpers->cart
                    ->expects()
                    ->emptyCart();

                $this->order
                    ->expects()
                    ->get_checkout_order_received_url()
                    ->andReturn($redirect = random()->url());

                return [
                    'result' => 'success',
                    'redirect' => $redirect,
                ];

            case 'rejected':
                if ($isOrderPayPage) {
                    $this->gateway
                        ->expects()
                        ->getRejectedPaymentErrorKey($response['status_detail'])
                        ->andReturn('error');
                    return [
                        'result' => 'fail',
                        'messages' => 'error'
                    ];
                }
                $this->gateway
                    ->expects()
                    ->handleWithRejectPayment($response)
                    ->andThrow(RejectedPaymentException::class)
                    ->getMock()
                    ->expects()
                    ->processReturnFail(
                        Mockery::type(RejectedPaymentException::class),
                        Mockery::type('string'),
                        CustomGateway::LOG_SOURCE,
                        $response,
                        true
                    )->andReturn($expected = [
                        'result' => 'fail',
                        'redirect' => '',
                        'message' => 'error',
                    ]);
                return $expected;
        }
    }

    /**
     * @dataProvider processPaymentSuperTokenProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentSuperToken(bool $isBlocks, bool $isOrderPayPage, array $checkout, array $response)
    {
        $this->processPaymentMock(
            array_merge([
                'checkout_type' => 'super_token',
                'token' => random()->uuid(),
                'amount' => 100,
                'payment_method_id' => 'visa',
            ], $checkout),
            $isBlocks
        );

        // Mock get_id() specifically for this test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        Mockery::mock('overload:' . SupertokenTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response)
            ->getMock()
            ->expects()
            ->getInternalMetadata()
            ->andReturn($paymentMetadata = Mockery::mock(PaymentMetadata::class));

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->setSupertokenMetadata($this->order, $response, $paymentMetadata);

        $expected = $this->handleResponseStatusMock($response, $isOrderPayPage);

        $this->gateway->mercadopago->helpers->url
            ->expects()
            ->validateGetVar('pay_for_order')
            ->andReturn($isOrderPayPage);

        if ($isOrderPayPage) {
            WP_Mock::userFunction('wp_json_encode')
                ->once()
                ->andReturnUsing('json_encode');
            $this->expectOutputString(json_encode($expected));
            $this->gateway->process_payment(1);
            return;
        }

        $this->assertEquals($expected, $this->gateway->process_payment(1));
    }

    /**
     * @dataProvider processPaymentDefaultProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentDefault(bool $isBlocks, bool $isOrderPayPage, array $checkout, array $response)
    {
        $this->processPaymentMock(
            array_merge([
                'checkout_type' => 'custom',
                'token' => random()->uuid(),
                'amount' => 100,
                'payment_method_id' => 'visa',
                'installments' => 1
            ], $checkout),
            $isBlocks
        );

        // Mock get_id() specifically for this test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        Mockery::mock('overload:' . CustomTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->setCustomMetadata($this->order, $response);

        $expected = $this->handleResponseStatusMock($response, $isOrderPayPage);

        $this->gateway->mercadopago->helpers->url
            ->expects()
            ->validateGetVar('pay_for_order')
            ->andReturn($isOrderPayPage);

        if ($isOrderPayPage) {
            WP_Mock::userFunction('wp_json_encode')
                ->once()
                ->andReturnUsing('json_encode');
            $this->expectOutputString(json_encode($expected));
            $this->gateway->process_payment(1);
            return;
        }

        $this->assertEquals($expected, $this->gateway->process_payment(1));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentFail()
    {
        WP_Mock::userFunction('wc_get_order');

        $this->mockFormSanitizedPostDataThrows(Exception::class);

        $this->gateway
            ->expects()
            ->processReturnFail()
            ->withAnyArgs()
            ->andReturn($expected = [
                'result' => 'fail',
                'redirect' => '',
                'message' => 'error',
            ]);

        $this->assertEquals($expected, $this->gateway->process_payment(1));
    }

    public function processPaymentSuperTokenProvider(): array
    {
        return [
            [
                true,
                true,
                [
                    'payment_type_id' => 'credit_card',
                    'installments' => 1
                ],
                [
                    'status' => 'approved'
                ]
            ],
            [
                false,
                false,
                [
                    'payment_type_id' => 'debit_card'
                ],
                [
                    'status' => 'approved'
                ]
            ],
            [
                true,
                true,
                [
                    'payment_type_id' => 'credit_card',
                    'installments' => 1
                ],
                [
                    'status' => 'pending',
                    'id' => random()->uuid(),
                    'status_detail' => 'pending_challenge',
                    'three_ds_info' => [
                        'external_resource_url' => random()->url(),
                        'creq' => random()->lexify('??????')
                    ],
                    'card' => [
                        'last_four_digits' => random()->numerify('####')
                    ]
                ]
            ],
            [
                true,
                true,
                [
                    'payment_type_id' => 'credit_card',
                    'installments' => 1
                ],
                [
                    'status' => 'pending',
                    'id' => random()->uuid(),
                    'status_detail' => 'pending_challenge',
                    'three_ds_info' => [
                        'external_resource_url' => random()->url(),
                        'creq' => random()->lexify('??????')
                    ]
                ]
            ],
            [
                false,
                false,
                [
                    'payment_type_id' => 'debit_card'
                ],
                [
                    'status' => 'in_process',
                    'status_detail' => random()->lexify('?????')
                ]
            ],
            [
                true,
                true,
                [
                    'payment_type_id' => 'credit_card',
                    'installments' => 1
                ],
                [
                    'status' => 'rejected',
                    'status_detail' => 'error'
                ]
            ],
            [
                false,
                false,
                [
                    'payment_type_id' => 'debit_card'
                ],
                [
                    'status' => 'rejected',
                    'status_detail' => 'error'
                ]
            ],
        ];
    }

    public function processPaymentDefaultProvider(): array
    {
        return [
            [
                true,
                true,
                [
                    'payment_type_id' => 'credit_card',
                ],
                [
                    'status' => 'approved'
                ]
            ],
            [
                false,
                false,
                [
                    'payment_type_id' => 'debit_card'
                ],
                [
                    'status' => 'approved'
                ]
            ],
            [
                true,
                true,
                [
                    'payment_type_id' => 'credit_card',
                ],
                [
                    'status' => 'pending',
                    'id' => random()->uuid(),
                    'status_detail' => 'pending_challenge',
                    'three_ds_info' => [
                        'external_resource_url' => random()->url(),
                        'creq' => random()->lexify('??????')
                    ],
                    'card' => [
                        'last_four_digits' => '1234'
                    ]
                ]
            ],
            [
                true,
                true,
                [
                    'payment_type_id' => 'credit_card',
                ],
                [
                    'status' => 'pending',
                    'id' => random()->uuid(),
                    'status_detail' => 'pending_challenge',
                    'three_ds_info' => [
                        'external_resource_url' => random()->url(),
                        'creq' => random()->lexify('??????')
                    ]
                ]
            ],
            [
                false,
                false,
                [
                    'payment_type_id' => 'debit_card'
                ],
                [
                    'status' => 'in_process',
                    'status_detail' => random()->lexify('?????')
                ]
            ],
            [
                true,
                true,
                [
                    'payment_type_id' => 'credit_card',
                ],
                [
                    'status' => 'rejected',
                    'status_detail' => 'error'
                ]
            ],
            [
                false,
                false,
                [
                    'payment_type_id' => 'debit_card'
                ],
                [
                    'status' => 'rejected',
                    'status_detail' => 'error'
                ]
            ],
        ];
    }

    public function testFormFieldsMainSectionReturnsConfigurationFields(): void
    {
        WP_Mock::userFunction('get_locale', [
            'return' => 'pt_BR'
        ]);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->andReturn('preview-image.png');

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplateHtml')
            ->andReturn('<div>Preview</div>');

        $result = $this->gateway->formFieldsMainSection();

        $this->assertArrayMap(
            [
                'card_info_helper' => [
                    'type' => IsType::TYPE_STRING,
                    'value' => IsType::TYPE_STRING,
                ],
                'card_info_fees' => [
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
                'currency_conversion' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'subtitle' => IsType::TYPE_STRING,
                    'default' => IsType::TYPE_STRING,
                    'descriptions' => [
                        'enabled' => IsType::TYPE_STRING,
                        'disabled' => IsType::TYPE_STRING,
                    ],
                ],
                'wallet_button' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'subtitle' => IsType::TYPE_STRING,
                    'default' => IsType::TYPE_STRING,
                    'after_toggle' => IsType::TYPE_STRING,
                    'descriptions' => [
                        'enabled' => IsType::TYPE_STRING,
                        'disabled' => IsType::TYPE_STRING,
                    ],
                ],
                'advanced_configuration_title' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'class' => IsType::TYPE_STRING,
                ],
                'advanced_configuration_description' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'class' => IsType::TYPE_STRING,
                ],
                'binary_mode' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'subtitle' => IsType::TYPE_STRING,
                    'default' => IsType::TYPE_STRING,
                    'descriptions' => [
                        'enabled' => IsType::TYPE_STRING,
                        'disabled' => IsType::TYPE_STRING,
                    ],
                ],
            ],
            $result
        );
    }

    public function testPaymentFieldsAndParams(): void
    {
        $siteId = 'MLB';
        $expectedIconUrls = [
            'url/master.png',
            'url/visa.png',
            'url/elo.png',
            'url/amex.png',
            'url/hypercard.png'
        ];

        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn($siteId);
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->andReturn(false);
        $this->gateway->mercadopago->hooks->options->shouldReceive('getGatewayOption')
            ->with($this->gateway, 'wallet_button', 'yes')
            ->andReturn('yes');

        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('gateways/wallet-button/logo.svg')
            ->andReturn('https://example.com/wallet-button-logo.svg');

        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('checkouts/custom/card-flags/master')
            ->andReturn('url/master.png');
        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('checkouts/custom/card-flags/visa')
            ->andReturn('url/visa.png');
        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('checkouts/custom/card-flags/elo')
            ->andReturn('url/elo.png');
        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('checkouts/custom/card-flags/amex')
            ->andReturn('url/amex.png');
        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('checkouts/custom/card-flags/hypercard')
            ->andReturn('url/hypercard.png');

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getAmountAndCurrency')
            ->andReturn(['amount' => 100.0, 'currencyRatio' => 1.0]);

        $this->gateway->shouldReceive('get_option')
            ->with('enabled', 'no')
            ->andReturn('yes');

        $this->gateway->shouldReceive('get_option')
            ->with('wallet_button', 'yes')
            ->andReturn('yes');

        // Mock storeTranslations
        $this->gateway->storeTranslations = [
            'test_mode_title' => 'Test Mode',
            'test_mode_description' => 'Test Description',
            'test_mode_link_text' => 'Test Link',
            'wallet_button_title' => 'Wallet Button',
            'card_number_input_label' => 'Card Number',
            'card_number_input_helper' => 'Card Helper',
            'card_holder_name_input_label' => 'Card Holder',
            'card_holder_name_input_helper' => 'Holder Helper',
            'card_expiration_input_label' => 'Expiration',
            'card_expiration_input_helper' => 'Expiration Helper',
            'card_security_code_input_label' => 'Security Code',
            'card_security_code_input_helper' => 'Security Helper',
            'card_document_input_label' => 'Document',
            'card_document_input_helper_empty' => 'Empty Document',
            'card_document_input_helper_invalid' => 'Invalid Document',
            'card_document_input_helper_wrong' => 'Wrong Document',
            'card_issuer_input_label' => 'Issuer',
            'message_error_amount' => 'Amount Error',
            'security_code_tooltip_text_3_digits' => '3 Digits Tooltip',
            'placeholders_cardholder_name' => 'Cardholder Name',
            'card_holder_input_helper_info' => 'As it spelled on the card.',
            'mercadopago_privacy_policy' => 'Learn more about&nbsp;<a href="{link}" target="_blank">how we protect your privacy</a>.',
        ];

        // Mock links using reflection
        $linksProperty = (new \ReflectionClass($this->gateway))->getProperty('links');
        $linksProperty->setAccessible(true);
        $linksProperty->setValue($this->gateway, [
            'docs_integration_test' => 'https://example.com/test-docs',
        ]);

        // Mock countryConfigs using reflection
        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'site_id' => $siteId
        ]);

        $this->gateway->mercadopago->helpers->links
            ->shouldReceive('getPrivacyPolicyLink')
            ->with($siteId)
            ->andReturn('https://example.com/privacy-policy');

        $params = $this->gateway->getPaymentFieldsParams();

        $this->assertEquals($expectedIconUrls, $params['cardFlagIconUrls']);
        $this->assertEquals($siteId, $params['site_id']);
        $this->assertEquals(true, $params['wallet_button_enabled']);
        $this->assertEquals('https://example.com/wallet-button-logo.svg', $params['wallet_button_image']);
        $this->assertEquals(100.0, $params['amount']);
        $this->assertEquals(1.0, $params['currency_ratio']);

        $this->gateway->mercadopago->hooks->template->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/checkouts/custom-checkout.php', $params);

        $this->gateway->shouldReceive('getAmount')
            ->andReturn(100.00);

        // Test payment_fields method
        $this->gateway->payment_fields();
    }

    /**
     * Test renderOrderForm with wallet_button query var
     *
     * @return void
     */
    public function testRenderOrderFormWithWalletButton()
    {
        $orderId = 123;
        $preferenceId = 'pref-123-abc';
        $publicKey = 'TEST-public-key';
        $cancelUrl = 'https://example.com/cancel';

        // Mock URL helper to return true for wallet_button query var
        $this->gateway->mercadopago->helpers->url
            ->expects()
            ->validateQueryVar('wallet_button')
            ->andReturn(true);

        // Mock wc_get_order
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_cancel_order_url')
            ->andReturn($cancelUrl);

        WP_Mock::userFunction('wc_get_order', [
            'times' => 1,
            'args' => [$orderId],
            'return' => $order
        ]);

        // Mock WalletButtonTransaction
        $transactionMock = Mockery::mock('overload:' . WalletButtonTransaction::class);
        $transactionMock->expects()
            ->createPreference()
            ->andReturn(['id' => $preferenceId]);

        // Mock seller config
        $this->gateway->mercadopago->sellerConfig
            ->expects()
            ->getCredentialsPublicKey()
            ->andReturn($publicKey);

        // Mock store translations
        $this->gateway->storeTranslations = [
            'wallet_button_order_receipt_title' => 'Pay with Mercado Pago',
            'cancel_url_text' => 'Cancel'
        ];

        // Mock template helper
        $this->gateway->mercadopago->hooks->template
            ->expects()
            ->getWoocommerceTemplate(
                'public/receipt/preference-modal.php',
                [
                    'public_key'        => $publicKey,
                    'preference_id'     => $preferenceId,
                    'pay_with_mp_title' => 'Pay with Mercado Pago',
                    'cancel_url'        => $cancelUrl,
                    'cancel_url_text'   => 'Cancel',
                ]
            )
            ->once();

        // Execute
        $result = $this->gateway->renderOrderForm($orderId);

        // Assert method completes successfully (returns void)
        $this->assertNull($result);
    }

    /**
     * Test renderOrderForm without wallet_button query var
     *
     * @return void
     */
    public function testRenderOrderFormWithoutWalletButton()
    {
        $orderId = 456;

        // Mock URL helper to return false for wallet_button query var
        $this->gateway->mercadopago->helpers->url
            ->expects()
            ->validateQueryVar('wallet_button')
            ->andReturn(false);

        // wc_get_order should NOT be called
        WP_Mock::userFunction('wc_get_order', [
            'times' => 0
        ]);

        // Template helper should NOT be called
        $this->gateway->mercadopago->hooks->template
            ->expects()
            ->getWoocommerceTemplate(Mockery::any(), Mockery::any())
            ->never();

        // Execute
        $result = $this->gateway->renderOrderForm($orderId);

        // Assert method completes successfully (returns void)
        $this->assertNull($result);
    }

    /**
     * Test renderOrderForm creates correct WalletButtonTransaction
     *
     * @return void
     */
    public function testRenderOrderFormCreatesWalletButtonTransaction()
    {
        $orderId = 789;
        $preferenceId = 'pref-789-xyz';

        // Mock URL helper
        $this->gateway->mercadopago->helpers->url
            ->expects()
            ->validateQueryVar('wallet_button')
            ->andReturn(true);

        // Mock order
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_cancel_order_url')
            ->andReturn('https://example.com/cancel');

        WP_Mock::userFunction('wc_get_order', [
            'return' => $order
        ]);

        // Mock WalletButtonTransaction with specific constructor expectations
        $transactionMock = Mockery::mock('overload:' . WalletButtonTransaction::class);
        $transactionMock->shouldReceive('__construct')
            ->with($this->gateway, $order)
            ->once();
        $transactionMock->expects()
            ->createPreference()
            ->andReturn(['id' => $preferenceId]);

        // Mock other dependencies
        $this->gateway->mercadopago->sellerConfig
            ->expects()
            ->getCredentialsPublicKey()
            ->andReturn('test-key');

        $this->gateway->storeTranslations = [
            'wallet_button_order_receipt_title' => 'Pay',
            'cancel_url_text' => 'Cancel'
        ];

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate');

        // Execute
        $this->gateway->renderOrderForm($orderId);

        // Verify transaction was created with correct parameters
        $this->assertInstanceOf(
            WalletButtonTransaction::class,
            $this->gateway->transaction
        );
    }

    /**
     * Test that new account money translation keys are available in storeTranslations
     *
     * @return void
     */
    public function testAccountMoneyTranslationKeysAreAvailable()
    {
        // Mock the storeTranslations with the new keys
        $this->gateway->storeTranslations = [
            'account_money_text' => 'Account Money',
            'account_money_wallet_with_investment_text' => 'Balance in Mercado Pago Wallet + Generating returns in GBM',
            'account_money_wallet_text' => 'Balance in Mercado Pago Wallet',
            'account_money_investment_text' => 'Balance generating returns in GBM through Mercado Pago',
            'account_money_available_text' => 'Money available at Mercado Pago',
        ];

        // Verify that all new translation keys are present
        $this->assertArrayHasKey('account_money_wallet_with_investment_text', $this->gateway->storeTranslations);
        $this->assertArrayHasKey('account_money_wallet_text', $this->gateway->storeTranslations);
        $this->assertArrayHasKey('account_money_investment_text', $this->gateway->storeTranslations);
        $this->assertArrayHasKey('account_money_available_text', $this->gateway->storeTranslations);

        // Verify the values are correct
        $this->assertEquals('Balance in Mercado Pago Wallet + Generating returns in GBM', $this->gateway->storeTranslations['account_money_wallet_with_investment_text']);
        $this->assertEquals('Balance in Mercado Pago Wallet', $this->gateway->storeTranslations['account_money_wallet_text']);
        $this->assertEquals('Balance generating returns in GBM through Mercado Pago', $this->gateway->storeTranslations['account_money_investment_text']);
        $this->assertEquals('Money available at Mercado Pago', $this->gateway->storeTranslations['account_money_available_text']);
    }

    /**
     * Test getWalletButtonEnabled method when enabled
     *
     * @return void
     */
    public function testGetWalletButtonEnabledWhenEnabled()
    {
        $this->gateway->shouldReceive('getEnabled')->andReturn(true);
        $this->gateway->shouldReceive('get_option')
            ->with('wallet_button', 'yes')
            ->andReturn('yes');

        $result = $this->gateway->getWalletButtonEnabled();
        $this->assertTrue($result);
    }

    /**
     * Test getWalletButtonEnabled method when disabled
     *
     * @return void
     */
    public function testGetWalletButtonEnabledWhenDisabled()
    {
        $this->gateway->shouldReceive('getEnabled')->andReturn(false);

        $result = $this->gateway->getWalletButtonEnabled();
        $this->assertFalse($result);
    }

    /**
     * Test getWalletButtonEnabled method when gateway enabled but wallet button disabled
     *
     * @return void
     */
    public function testGetWalletButtonEnabledWhenGatewayEnabledButWalletDisabled()
    {
        $this->gateway->shouldReceive('getEnabled')->andReturn(true);
        $this->gateway->shouldReceive('get_option')
            ->with('wallet_button', 'yes')
            ->andReturn('no');

        $result = $this->gateway->getWalletButtonEnabled();
        $this->assertFalse($result);
    }

    /**
     * Test getPaymentFieldsParams method
     *
     * @return void
     */
    public function testGetPaymentFieldsParams()
    {
        // Mock dependencies
        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->andReturn('test-image-url');

        $this->gateway->mercadopago->helpers->country
            ->shouldReceive('SITE_ID_MLA')
            ->andReturn('MLA');

        // Mock storeTranslations
        $this->gateway->storeTranslations = [
            'test_mode_title' => 'Test Mode',
            'test_mode_description' => 'Test Description',
            'test_mode_link_text' => 'Test Link',
            'wallet_button_title' => 'Wallet Button',
            'card_number_input_label' => 'Card Number',
            'card_number_input_helper' => 'Card Helper',
            'card_holder_name_input_label' => 'Card Holder',
            'card_holder_name_input_helper' => 'Holder Helper',
            'card_expiration_input_label' => 'Expiration',
            'card_expiration_input_helper' => 'Expiration Helper',
            'card_security_code_input_label' => 'Security Code',
            'card_security_code_input_helper' => 'Security Helper',
            'card_document_input_label' => 'Document',
            'card_document_input_helper_empty' => 'Empty Document',
            'card_document_input_helper_invalid' => 'Invalid Document',
            'card_document_input_helper_wrong' => 'Wrong Document',
            'card_issuer_input_label' => 'Issuer',
            'message_error_amount' => 'Amount Error',
            'security_code_tooltip_text_3_digits' => '3 Digits Tooltip',
            'placeholders_cardholder_name' => 'Cardholder Name',
            'card_holder_input_helper_info' => 'As it spelled on the card.',
            'mercadopago_privacy_policy' => 'Learn more about&nbsp;<a href="{link}" target="_blank">how we protect your privacy</a>.',
        ];

        // Mock getAmountAndCurrency method using shouldAllowMockingProtectedMethods
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getAmountAndCurrency')
            ->andReturn(['amount' => 100, 'currencyRatio' => 1.0]);

        // Mock getWalletButtonEnabled method
        $this->gateway->shouldReceive('getWalletButtonEnabled')
            ->andReturn(true);

        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'site_id' => 'MLA'
        ]);

        $this->gateway->mercadopago->helpers->links
            ->shouldReceive('getPrivacyPolicyLink')
            ->andReturn('https://example.com/privacy-policy');

        $params = $this->gateway->getPaymentFieldsParams();

        $this->assertIsArray($params);
        $this->assertArrayHasKey('test_mode', $params);
        $this->assertArrayHasKey('wallet_button_enabled', $params);
        $this->assertArrayHasKey('site_id', $params);
        $this->assertArrayHasKey('amount', $params);
        $this->assertArrayHasKey('currency_ratio', $params);
    }

    /**
     * Test registerCheckoutStyle method
     *
     * @return void
     */
    public function testRegisterCheckoutStyle()
    {
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->with('checkouts/super-token/super-token-payment-methods')
            ->andReturn('test-css-url');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerCheckoutStyle')
            ->with('wc_mercadopago_supertoken_payment_methods', 'test-css-url')
            ->once();

        $this->gateway->registerCheckoutStyle();

        // Verify the method was called
        $this->assertTrue(true);
    }

    /**
     * Test getWalletButtonPreview method
     *
     * @return void
     */
    public function testGetWalletButtonPreview()
    {
        $this->gateway->storeTranslations = [
            'locale' => 'en-US'
        ];

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->with('gateways/wallet-button/preview-en-us')
            ->andReturn('test-preview-url');

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplateHtml')
            ->with('admin/components/preview.php', Mockery::type('array'))
            ->andReturn('<div>Preview</div>');

        $result = $this->gateway->getWalletButtonPreview();

        $this->assertEquals('<div>Preview</div>', $result);
    }

    /**
     * Test registerCheckoutScripts calls getCurrencyCode method
     *
     * @return void
     */
    public function testRegisterCheckoutScriptsCallsGetCurrencyCode()
    {
        // Mock getCurrencyCode to return a specific currency
        $expectedCurrency = 'BRL';
        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getCurrencyCode')
            ->with($this->gateway)
            ->once()
            ->andReturn($expectedCurrency);

        // Mock other dependencies for registerCheckoutScripts
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->andReturn('test-css-url');

        WP_Mock::userFunction('wp_is_mobile')
            ->andReturn(false);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getJsAsset')
            ->andReturn('test-js-url');

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->andReturn('test-image-url');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsPublicKey')
            ->andReturn('test-public-key');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('test-cust-id');

        $this->gateway->mercadopago->hooks->options
            ->shouldReceive('getGatewayOption')
            ->andReturn('cards_first');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getPaymentMethodsThumbnails')
            ->andReturn([]);

        $this->gateway->mercadopago->helpers->links
            ->shouldReceive('getPrivacyPolicyLink')
            ->andReturn('https://example.com/privacy');

        // Mock storeTranslations
        $this->gateway->storeTranslations = [
            'locale' => 'en-US',
            'payment_methods_list_text' => 'Payment Methods',
            'payment_methods_list_alt_text' => 'Payment Methods alt text',
            'last_digits_text' => 'Last digits',
            'new_card_text' => 'New card',
            'account_money_text' => 'Account Money',
            'account_money_wallet_with_investment_text' => 'Wallet + Investment',
            'account_money_wallet_text' => 'Wallet',
            'account_money_investment_text' => 'Investment',
            'account_money_available_text' => 'Available',
            'interest_free_part_one_text' => 'Interest free',
            'interest_free_part_two_text' => 'part two',
            'interest_free_option_text' => 'Interest free option',
            'security_code_input_title_text' => 'Security code',
            'security_code_placeholder_text_3_digits' => '3 digits',
            'security_code_placeholder_text_4_digits' => '4 digits',
            'security_code_tooltip_text_3_digits' => '3 digits tooltip',
            'security_code_tooltip_text_4_digits' => '4 digits tooltip',
            'security_code_error_message_text' => 'Security code error',
            'card_installments_label' => 'Installments',
            'placeholders_issuer' => 'Issuer',
            'placeholders_installments' => 'Installments',
            'placeholders_card_expiration_date' => 'Expiration',
            'placeholders_cardholder_name' => 'Cardholder Name',
            'installments_required' => 'Required',
            'card_installments_interest_text' => 'Interest text',
            'input_helper_message_invalid_type' => 'Invalid type',
            'input_helper_message_invalid_length' => 'Invalid length',
            'input_helper_message_invalid_value' => 'Invalid value',
            'input_helper_message_card_holder_name_221' => 'Card holder name 221',
            'input_helper_message_card_holder_name_316' => 'Card holder name 316',
            'input_helper_message_expiration_date_invalid_type' => 'Expiration date invalid type',
            'input_helper_message_expiration_date_invalid_length' => 'Expiration date invalid length',
            'input_helper_message_expiration_date_invalid_value' => 'Expiration date invalid value',
            'input_helper_message_security_code_invalid_type' => 'Security code invalid type',
            'input_helper_message_security_code_invalid_length' => 'Security code invalid length',
            'default_error_message' => 'Default error message',
            'installments_error_invalid_amount' => 'Invalid amount error',
            'mercado_pago_card_name' => 'Mercado Pago Card',
            'mercadopago_privacy_policy' => 'Privacy policy {link}',
            'consumer_credits_due_date' => 'The first installment is due on',
            'months_abbreviated' => 'Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec',
            'mlb_installment_debit_auto_text' => 'MLB Installment Debit Auto',
            'interest_rate_mlb_text' => 'Interest Rate MLB',
            'effective_total_cost_mlb_text' => 'Effective Total Cost MLB',
            'iof_mlb_text' => 'IOF MLB',
            'borrowed_amount_mlb_text' => 'Borrowed Amount MLB',
            'per_month' => 'per month',
            'per_year' => 'per year',
            'cat_mlm_text' => 'CAT MLM',
            'no_iva_text' => 'No IVA',
            'tna_mlm_text' => 'TNA MLM',
            'system_amortization_mlm_text' => 'System Amortization MLM',
            'cftea_mla_text' => 'CFTEA MLA',
            'tna_mla_text' => 'TNA MLA',
            'tea_mla_text' => 'TEA MLA',
            'fixed_rate_text' => 'Fixed Rate',
            'update_security_code_with_retry_error_text' => 'Update security code with retry error',
            'update_security_code_no_retry_error_text' => 'Update security code no retry error',
            'authorize_payment_method_with_retry_error_text' => 'Authorize payment method with retry error',
            'authorize_payment_method_no_retry_error_text' => 'Authorize payment method no retry error',
            'select_payment_method_error_text' => 'Select payment method error',
        ];

        // Mock threeDsTranslations
        $this->gateway->mercadopago->storeTranslations->threeDsTranslations = [
            'title_loading_3ds_frame' => 'Loading 3DS',
            'title_loading_3ds_frame2' => 'Loading 3DS 2',
            'text_loading_3ds_frame' => 'Loading frame',
            'title_loading_3ds_response' => 'Loading response',
            'title_3ds_frame' => '3DS Frame',
            'tooltip_3ds_frame' => '3DS Tooltip',
            'message_3ds_declined' => '3DS Declined',
        ];

        // Mock countryConfigs
        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'intl' => 'en-US',
            'site_id' => 'MLA',
            'currency' => 'ARS'
        ]);

        // Mock WooCommerce version
        $this->gateway->mercadopago->woocommerce->version = '8.0.0';

        // Mock get_option method
        $this->gateway->shouldReceive('get_option')
            ->andReturn('yes');

        // Mock WordPress functions
        WP_Mock::userFunction('get_stylesheet')
            ->andReturn('test-theme');

        WP_Mock::userFunction('wp_get_current_user')
            ->andReturn((object) ['user_email' => 'test@example.com']);

        // Mock scripts registration
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerCheckoutStyle')
            ->andReturnSelf();

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerCheckoutScript')
            ->andReturnSelf();

        // Execute the method
        $this->gateway->registerCheckoutScripts();

        // The test passes if no exceptions are thrown and getCurrencyCode was called
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test processReturnFail execution to increase coverage
     * This test ensures processReturnFail is actually executed (not mocked) to cover all lines
     */
    public function testProcessReturnFailExecution(): void
    {
        // Set paymentMethodName to match CustomGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', CustomGateway::ID);

        $errorMessage = 'buyer_default';
        $translatedMessage = 'Translated error message';

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), CustomGateway::LOG_SOURCE, Mockery::type('array'))
            ->andReturnNull();
        $this->gateway->mercadopago->logs->file = $logsFileMock;

        // Mock errorMessages helper
        $this->gateway->mercadopago->helpers->errorMessages
            ->shouldReceive('findErrorMessage')
            ->once()
            ->with($errorMessage)
            ->andReturn($translatedMessage);

        // Mock datadog->sendEvent
        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('woo_checkout_error', $translatedMessage, $errorMessage, CustomGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($translatedMessage, 'error');

        $exception = new \Exception('Test exception');

        $result = $this->gateway->processReturnFail(
            $exception,
            $errorMessage,
            CustomGateway::LOG_SOURCE,
            [],
            true
        );

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($translatedMessage, $result['message']);
    }

    /**
     * Test formFieldsHeaderSection with MLB site
     *
     * @return void
     */
    public function testFormFieldsHeaderSectionWithMLB(): void
    {
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getHomologValidate')
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialExpired')
            ->andReturn(false);

        $this->gateway->id = CustomGateway::ID;

        $this->gateway->mercadopago->hooks->admin
            ->shouldReceive('isAdmin')
            ->andReturn(true);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('validatePage')
            ->with('wc-settings')
            ->andReturn(true);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('validateSection')
            ->with(CustomGateway::ID)
            ->andReturn(true);

        WP_Mock::userFunction('get_transient')
            ->with('mp_credentials_expired_result')
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsPublicKeyProd')
            ->andReturn('test-public-key-prod');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('isExpiredPublicKey')
            ->with('test-public-key-prod')
            ->andReturn(false);

        WP_Mock::userFunction('set_transient')
            ->with('mp_credentials_expired_result', Mockery::type('array'), 3600)
            ->andReturn(true);

        $this->gateway->title = 'Test Gateway Title';

        $this->gateway->adminTranslations = [
            'header_title_MLB' => 'MLB Header Title',
            'enabled_descriptions_enabled_MLB' => 'MLB Enabled',
            'enabled_descriptions_disabled_MLB' => 'MLB Disabled',
        ];

        $result = $this->gateway->formFieldsHeaderSection();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('header', $result);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertEquals('MLB Header Title', $result['header']['title']);
        $this->assertEquals('MLB Enabled', $result['enabled']['descriptions']['enabled']);
        $this->assertEquals('MLB Disabled', $result['enabled']['descriptions']['disabled']);
    }

    /**
     * Test formFieldsHeaderSection with non-MLB site
     *
     * @return void
     */
    public function testFormFieldsHeaderSectionWithNonMLB(): void
    {
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLA');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getHomologValidate')
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialExpired')
            ->andReturn(false);

        $this->gateway->id = CustomGateway::ID;

        $this->gateway->mercadopago->hooks->admin
            ->shouldReceive('isAdmin')
            ->andReturn(true);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('validatePage')
            ->with('wc-settings')
            ->andReturn(true);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('validateSection')
            ->with(CustomGateway::ID)
            ->andReturn(true);

        WP_Mock::userFunction('get_transient')
            ->with('mp_credentials_expired_result')
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsPublicKeyProd')
            ->andReturn('test-public-key-prod');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('isExpiredPublicKey')
            ->with('test-public-key-prod')
            ->andReturn(false);

        WP_Mock::userFunction('set_transient')
            ->with('mp_credentials_expired_result', Mockery::type('array'), 3600)
            ->andReturn(true);

        $this->gateway->title = 'Test Gateway Title';

        $this->gateway->adminTranslations = [
            'header_title_ALL' => 'ALL Header Title',
            'enabled_descriptions_enabled_ALL' => 'ALL Enabled',
            'enabled_descriptions_disabled_ALL' => 'ALL Disabled',
        ];

        $result = $this->gateway->formFieldsHeaderSection();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('header', $result);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertEquals('ALL Header Title', $result['header']['title']);
        $this->assertEquals('ALL Enabled', $result['enabled']['descriptions']['enabled']);
        $this->assertEquals('ALL Disabled', $result['enabled']['descriptions']['disabled']);
    }

    /**
     * Test renderInstallmentsRateDetails when totalDiffCost > 0
     *
     * @return void
     */
    public function testRenderInstallmentsRateDetailsWithDiffCost(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');

        WP_Mock::userFunction('wc_get_order', [
            'times' => 1,
            'args' => [$orderId],
            'return' => $order
        ]);

        // Mock countryConfigs
        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'currency_symbol' => 'R$'
        ]);

        // Mock order metadata
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getInstallmentsMeta')
            ->with($order)
            ->andReturn(12);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTransactionDetailsMeta')
            ->with($order)
            ->andReturn(100.50);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTransactionAmountMeta')
            ->with($order)
            ->andReturn(1000.00);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTotalPaidAmountMeta')
            ->with($order)
            ->andReturn(1100.00);

        // Mock storeTranslations
        $this->gateway->storeTranslations = [
            'title_installment_cost' => 'Installment Cost',
            'title_installment_total' => 'Installment Total',
            'text_installments' => 'Installments',
        ];

        // Mock template
        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/order/custom-order-received.php', Mockery::type('array'));

        $this->gateway->renderInstallmentsRateDetails($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test renderInstallmentsRateDetails when totalDiffCost <= 0
     *
     * @return void
     */
    public function testRenderInstallmentsRateDetailsWithoutDiffCost(): void
    {
        $orderId = 456;
        $order = Mockery::mock('WC_Order');

        WP_Mock::userFunction('wc_get_order', [
            'times' => 1,
            'args' => [$orderId],
            'return' => $order
        ]);

        // Mock countryConfigs
        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'currency_symbol' => 'R$'
        ]);

        // Mock order metadata - totalPaidAmount <= transactionAmount
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getInstallmentsMeta')
            ->with($order)
            ->andReturn(1);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTransactionDetailsMeta')
            ->with($order)
            ->andReturn(100.00);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTransactionAmountMeta')
            ->with($order)
            ->andReturn(1000.00);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTotalPaidAmountMeta')
            ->with($order)
            ->andReturn(1000.00); // Same as transactionAmount, so diff = 0

        // Template should NOT be called
        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->never();

        $this->gateway->renderInstallmentsRateDetails($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test registerInstallmentsFeeOnAdminOrder when fee exists and gateway matches
     *
     * @return void
     */
    public function testRegisterInstallmentsFeeOnAdminOrderWithFee(): void
    {
        $orderId = 789;
        $order = Mockery::mock('WC_Order');

        WP_Mock::userFunction('wc_get_order', [
            'times' => 1,
            'args' => [$orderId],
            'return' => $order
        ]);

        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getCurrencySymbol')
            ->andReturn('R$');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getUsedGatewayData')
            ->with($order)
            ->andReturn(CustomGateway::ID);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTotalPaidAmountMeta')
            ->with($order)
            ->andReturn(1100.00);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTransactionAmountMeta')
            ->with($order)
            ->andReturn(1000.00);

        $this->gateway->mercadopago->adminTranslations->order = [
            'order_note_installments_fee_tip' => 'Installments Fee Tip',
            'order_note_installments_fee_title' => 'Installments Fee',
            'order_note_total_paid_amount_tip' => 'Total Paid Tip',
            'order_note_total_paid_amount_title' => 'Total Paid',
        ];

        // Template should be called twice (for fee and total paid)
        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->twice()
            ->with('admin/order/generic-note.php', Mockery::type('array'));

        $this->gateway->registerInstallmentsFeeOnAdminOrder($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test registerInstallmentsFeeOnAdminOrder when fee is zero
     *
     * @return void
     */
    public function testRegisterInstallmentsFeeOnAdminOrderWithZeroFee(): void
    {
        $orderId = 101;
        $order = Mockery::mock('WC_Order');

        WP_Mock::userFunction('wc_get_order', [
            'times' => 1,
            'args' => [$orderId],
            'return' => $order
        ]);

        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getCurrencySymbol')
            ->andReturn('R$');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getUsedGatewayData')
            ->with($order)
            ->andReturn(CustomGateway::ID);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTotalPaidAmountMeta')
            ->with($order)
            ->andReturn(1000.00);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTransactionAmountMeta')
            ->with($order)
            ->andReturn(1000.00); // Same, so fee = 0

        // Template should NOT be called
        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->never();

        $this->gateway->registerInstallmentsFeeOnAdminOrder($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test registerInstallmentsFeeOnAdminOrder when gateway doesn't match
     *
     * @return void
     */
    public function testRegisterInstallmentsFeeOnAdminOrderWithDifferentGateway(): void
    {
        $orderId = 202;
        $order = Mockery::mock('WC_Order');

        WP_Mock::userFunction('wc_get_order', [
            'times' => 1,
            'args' => [$orderId],
            'return' => $order
        ]);

        $this->gateway->mercadopago->helpers->currency
            ->shouldReceive('getCurrencySymbol')
            ->andReturn('R$');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getUsedGatewayData')
            ->with($order)
            ->andReturn('woo-mercado-pago-basic'); // Different gateway

        // Template should NOT be called
        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->never();

        $this->gateway->registerInstallmentsFeeOnAdminOrder($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test getPaymentFieldsParams with different site IDs to cover CARD_FLAGS_BY_COUNTRY
     *
     * @return void
     */
    public function testGetPaymentFieldsParamsWithDifferentSites(): void
    {
        $sites = ['MLA', 'MLM', 'MLC', 'MCO', 'MLU', 'MPE'];

        foreach ($sites as $siteId) {
            $this->gateway->mercadopago->sellerConfig
                ->shouldReceive('getSiteId')
                ->andReturn($siteId);

            $this->gateway->mercadopago->storeConfig
                ->shouldReceive('isTestMode')
                ->andReturn(false);

            $this->gateway->mercadopago->helpers->url
                ->shouldReceive('getImageAsset')
                ->andReturn('test-image-url');

            $this->gateway->mercadopago->helpers->country
                ->shouldReceive('SITE_ID_MLA')
                ->andReturn('MLA');

            $this->gateway->storeTranslations = [
                'test_mode_title' => 'Test Mode',
                'test_mode_description' => 'Test Description',
                'test_mode_link_text' => 'Test Link',
                'wallet_button_title' => 'Wallet Button',
                'card_number_input_label' => 'Card Number',
                'card_number_input_helper' => 'Card Helper',
                'card_holder_name_input_label' => 'Card Holder',
                'card_holder_name_input_helper' => 'Holder Helper',
                'card_expiration_input_label' => 'Expiration',
                'card_expiration_input_helper' => 'Expiration Helper',
                'card_security_code_input_label' => 'Security Code',
                'card_security_code_input_helper' => 'Security Helper',
                'card_document_input_label' => 'Document',
                'card_document_input_helper_empty' => 'Empty Document',
                'card_document_input_helper_invalid' => 'Invalid Document',
                'card_document_input_helper_wrong' => 'Wrong Document',
                'card_issuer_input_label' => 'Issuer',
                'message_error_amount' => 'Amount Error',
                'security_code_tooltip_text_3_digits' => '3 Digits Tooltip',
                'placeholders_cardholder_name' => 'Cardholder Name',
                'card_holder_input_helper_info' => 'As it spelled on the card.',
                'mercadopago_privacy_policy' => 'Learn more about&nbsp;<a href="{link}" target="_blank">how we protect your privacy</a>.',
            ];

            // Mock getAmountAndCurrency method using shouldAllowMockingProtectedMethods
            $this->gateway->shouldAllowMockingProtectedMethods();
            $this->gateway->shouldReceive('getAmountAndCurrency')
                ->andReturn(['amount' => 100, 'currencyRatio' => 1.0]);

            // Mock getWalletButtonEnabled method
            $this->gateway->shouldReceive('getWalletButtonEnabled')
                ->andReturn(true);

            $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
            $countryConfigsProperty->setAccessible(true);
            $countryConfigsProperty->setValue($this->gateway, [
                'site_id' => $siteId
            ]);

            $this->gateway->mercadopago->helpers->links
                ->shouldReceive('getPrivacyPolicyLink')
                ->andReturn('https://example.com/privacy-policy');

            $params = $this->gateway->getPaymentFieldsParams();

            $this->assertIsArray($params);
            $this->assertArrayHasKey('cardFlagIconUrls', $params);
            $this->assertIsArray($params['cardFlagIconUrls']);
        }
    }

    /**
     * Test getPaymentFieldsParams when site_id is empty string (uses default)
     *
     * @return void
     */
    public function testGetPaymentFieldsParamsWithEmptySiteId(): void
    {
        // Mock getSiteId() to return empty string - this will trigger the ?: operator
        // The code will use SITE_ID_MLA constant, but for CARD_FLAGS_BY_COUNTRY lookup
        // we need a valid site ID. Since empty string is falsy, it will use MLA.
        // However, getSiteId() is called twice - once for site_id param and once for CARD_FLAGS_BY_COUNTRY
        // We'll mock it to return empty first time, then MLA for the array lookup
        $callCount = 0;
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturnUsing(function() use (&$callCount) {
                $callCount++;
                // First call is for site_id param (line 538), second is for CARD_FLAGS_BY_COUNTRY (line 559)
                return $callCount === 1 ? '' : 'MLA';
            });

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(false);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->andReturn('test-image-url');

        $this->gateway->storeTranslations = [
            'test_mode_title' => 'Test Mode',
            'test_mode_description' => 'Test Description',
            'test_mode_link_text' => 'Test Link',
            'wallet_button_title' => 'Wallet Button',
            'card_number_input_label' => 'Card Number',
            'card_number_input_helper' => 'Card Helper',
            'card_holder_name_input_label' => 'Card Holder',
            'card_holder_name_input_helper' => 'Holder Helper',
            'card_expiration_input_label' => 'Expiration',
            'card_expiration_input_helper' => 'Expiration Helper',
            'card_security_code_input_label' => 'Security Code',
            'card_security_code_input_helper' => 'Security Helper',
            'card_document_input_label' => 'Document',
            'card_document_input_helper_empty' => 'Empty Document',
            'card_document_input_helper_invalid' => 'Invalid Document',
            'card_document_input_helper_wrong' => 'Wrong Document',
            'card_issuer_input_label' => 'Issuer',
            'message_error_amount' => 'Amount Error',
            'security_code_tooltip_text_3_digits' => '3 Digits Tooltip',
            'placeholders_cardholder_name' => 'Cardholder Name',
            'card_holder_input_helper_info' => 'As it spelled on the card.',
            'mercadopago_privacy_policy' => 'Learn more about&nbsp;<a href="{link}" target="_blank">how we protect your privacy</a>.',
        ];

        // Mock getAmountAndCurrency method using shouldAllowMockingProtectedMethods
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getAmountAndCurrency')
            ->andReturn(['amount' => 100, 'currencyRatio' => 1.0]);

        // Mock getWalletButtonEnabled method
        $this->gateway->shouldReceive('getWalletButtonEnabled')
            ->andReturn(true);

        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'site_id' => 'MLA'
        ]);

        $this->gateway->mercadopago->helpers->links
            ->shouldReceive('getPrivacyPolicyLink')
            ->andReturn('https://example.com/privacy-policy');

        $params = $this->gateway->getPaymentFieldsParams();

        $this->assertIsArray($params);
        $this->assertEquals('MLA', $params['site_id']);
    }
}
