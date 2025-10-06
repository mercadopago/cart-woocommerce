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

class CustomGatewayTest extends TestCase
{
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
                        ->getRejectedPaymentErrorMessage($response['status_detail'])
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
}
