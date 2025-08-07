<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use Exception;
use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Tests\Mocks\GatewayMock;
use MercadoPago\Woocommerce\Transactions\CustomTransaction;
use MercadoPago\Woocommerce\Transactions\SupertokenTransaction;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

class CustomGatewayTest extends TestCase
{
    use GatewayMock;

    private string $gatewayClass = CustomGateway::class;

    /**
     * @var \Mockery\MockInterface|CustomGateway
     */
    private $gateway;

    public function testGetCheckoutName(): void
    {
        $this->assertSame($this->gateway->getCheckoutName(), 'checkout-custom');
    }

    private function processPaymentMock(array $checkout, bool $isBlocks)
    {
        $this->abstractGatewayProcessPaymentMock($isBlocks);
        if ($isBlocks) {
            $_POST['mercadopago_custom'] = null;

            $postData = [];

            Mockery::mock('alias:' . Form::class)
                ->expects()
                ->sanitizedPostData()
                ->andReturn($postData);

            $this->gateway
                ->expects()
                ->processBlocksCheckoutData('mercadopago_custom', $postData)
                ->andReturn($checkout);
        } else {
            $_POST['mercadopago_custom'] = [];

            Mockery::mock('alias:' . Form::class)
                ->expects()
                ->sanitizedPostData('mercadopago_custom')
                ->andReturn($checkout);
        }
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testProcessPaymentWalletButton(bool $isBlocks): void
    {
        $this->processPaymentMock([
            'checkout_type' => 'wallet_button',
        ], $isBlocks);

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
                    return [
                        'result' => 'fail',
                        'messages' => 'error'
                    ];
                }
                $this->gateway
                    ->expects()
                    ->processReturnFail(
                        Mockery::type(RejectedPaymentException::class),
                        $response['status_detail'],
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

    public function testProcessPaymentFail()
    {
        WP_Mock::userFunction('wc_get_order');

        Mockery::mock('alias:' . Form::class)
            ->shouldReceive('sanitizedPostData')
            ->andThrow(Exception::class);

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
}
