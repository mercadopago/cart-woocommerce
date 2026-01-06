<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Tests\Traits\AssertArrayMap;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use MercadoPago\Woocommerce\Transactions\PixTransaction;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use Mockery;

class PixGatewayTest extends TestCase
{
    use GatewayMock;
    use AssertArrayMap;
    use FormMock;

    private string $gatewayClass = PixGateway::class;

    /**
     * @var \Mockery\MockInterface|PixGateway
     */
    private $gateway;

    public function testGetCheckoutName(): void
    {
        $this->assertEquals('checkout-pix', $this->gateway->getCheckoutName());
    }

    public function processPaymentMock(?bool $isBlocks = null): void
    {
        $isBlocks ??= random()->boolean();

        $this->abstractGatewayProcessPaymentMock($isBlocks);

        $this->mockFormSanitizedPostData([]);

        $_POST['wc-woo-mercado-pago-pix-new-payment-method'] = $isBlocks ? 1 : null;
    }

    /**
     * @testWith [true, "pending_waiting_payment"]
     *           [false, "pending_waiting_transfer"]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentSuccess(bool $isBlocks, string $statusDetail): void
    {
        $this->processPaymentMock($isBlocks);

        // Mock get_id() specifically for this test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        $this->order
            ->expects()
            ->get_billing_email()
            ->andReturn(random()->email());

        Mockery::mock('overload:' . PixTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response = [
                'id' => random()->uuid(),
                'status' => 'pending',
                'status_detail' => $statusDetail
            ]);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->updatePaymentsOrderMetadata($this->order, ['id' => $response['id']]);

        $this->gateway
            ->expects()
            ->handleWithRejectPayment($response);

        $this->gateway->mercadopago->helpers->cart
            ->expects()
            ->emptyCart();

        $this->gateway->mercadopago->hooks->order
            ->expects()
            ->setPixMetadata($this->gateway, $this->order, $response)
            ->getMock()
            ->expects()
            ->addOrderNote($this->order, $this->gateway->storeTranslations['customer_not_paid'])
            ->getMock()
            ->expects()
            ->addOrderNote(
                $this->order,
                both(containsString($this->gateway->storeTranslations['congrats_title']))
                    ->andAlso(containsString($this->gateway->storeTranslations['congrats_subtitle'])),
                1
            );

        $this->order
            ->expects()
            ->get_checkout_order_received_url()
            ->andReturn($redirect = random()->url());

        $this->assertEquals(
            [
                'result' => 'success',
                'redirect' => $redirect,
            ],
            $this->gateway->process_payment(1)
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInvalidEmail(): void
    {
        $this->processPaymentMock();

        // Mock get_id() specifically for this test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        $this->order
            ->expects()
            ->get_billing_email()
            ->andReturn('invalid');

        $translatedMessage = '<strong>The email you entered is not valid</strong>';

        $this->gateway
            ->expects()
            ->processReturnFail(
                Mockery::type(\MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException::class),
                'invalid_email',
                PixGateway::LOG_SOURCE,
                Mockery::type('array'),
                true
            )->andReturn($expected = [
                'result' => 'fail',
                'redirect' => '',
                'message' => $translatedMessage,
            ]);

        $this->assertEquals($expected, $this->gateway->process_payment(1));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentFail(): void
    {
        $this->processPaymentMock();

        // Mock get_id() specifically for this test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        $this->order
            ->expects()
            ->get_billing_email()
            ->andReturn(random()->email());

        Mockery::mock('overload:' . PixTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn([]);

        $this->gateway
            ->expects()
            ->processReturnFail(
                Mockery::type(ResponseStatusException::class),
                Mockery::type('string'),
                PixGateway::LOG_SOURCE,
                Mockery::type('array'),
                true
            )->andReturn($expected = [
                'result' => 'fail',
                'redirect' => '',
                'message' => 'mock',
            ]);

        $this->assertEquals($expected, $this->gateway->process_payment(1));
    }

    public function testSellerWithPixFields(): void
    {
        $this->assertArrayMap(
            [
                'expiration_date' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'description' => IsType::TYPE_STRING,
                    'default' => IsType::TYPE_STRING,
                    'options' => [
                        '15 minutes' => IsType::TYPE_STRING,
                        '30 minutes' => IsType::TYPE_STRING,
                        '60 minutes' => IsType::TYPE_STRING,
                        '12 hours' => IsType::TYPE_STRING,
                        '24 hours' => IsType::TYPE_STRING,
                        '2 days' => IsType::TYPE_STRING,
                        '3 days' => IsType::TYPE_STRING,
                        '4 days' => IsType::TYPE_STRING,
                        '5 days' => IsType::TYPE_STRING,
                        '6 days' => IsType::TYPE_STRING,
                        '7 days' => IsType::TYPE_STRING,
                    ]
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
                'card_info_helper' => [
                    'type' => IsType::TYPE_STRING,
                    'value' => IsType::TYPE_STRING,
                ],
                'card_info' => [
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
            ],
            $this->gateway->formFieldsMainSection()
        );
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testSellerWithoutPixFields(bool $isPixSection): void
    {
        $this->gateway->expects()->sellerHavePix()->andReturn(false);

        $this->gateway->id = PixGateway::ID;

        $this->gateway->mercadopago->helpers->url
            ->expects()
            ->getCurrentSection()
            ->andReturn($isPixSection ? PixGateway::ID : '');

        if ($isPixSection) {
            $this->gateway->mercadopago->helpers->notices
                ->expects()
                ->adminNoticeMissPix();
        }

        $this->gateway->mercadopago->hooks->template
            ->expects()
            ->getWoocommerceTemplateHtml(Mockery::type('string'), Mockery::type('array'));

        $this->assertArrayMap(
            [
                'header' => [
                    'type' => IsType::TYPE_STRING,
                    'title' => IsType::TYPE_STRING,
                    'description' => IsType::TYPE_STRING,
                ],
                'steps_content' => [
                    'title' => IsType::TYPE_STRING,
                    'type' => IsType::TYPE_STRING,
                    'class' => IsType::TYPE_STRING,
                ],
            ],
            $this->gateway->formFields()
        );
    }
}
