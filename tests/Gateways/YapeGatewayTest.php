<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Transactions\YapeTransaction;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\YapeGateway;

class YapeGatewayTest extends TestCase
{
    use GatewayMock;

    private string $gatewayClass = YapeGateway::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|YapeGateway
     */
    private $gateway;

    public function testGetCheckoutName()
    {
        $this->assertEquals('checkout-yape', $this->gateway->getCheckoutName());
    }

    private function yapeProcessPaymentInternalMock(bool $isBlocks, array $response): void
    {
        $this->processPaymentInternalMock($isBlocks);

        if ($isBlocks) {
            $_POST['mercadopago_yape'] = null;
            Mockery::mock('alias:' . Form::class)
                ->expects()
                ->sanitizedPostData()
                ->andReturn([]);
            $this->gateway
                ->expects()
                ->processBlocksCheckoutData('mercadopago_yape', [])
                ->andReturn([]);
        } else {
            $_POST['mercadopago_yape'] = 1;
            Mockery::mock('alias:' . Form::class)
                ->expects()
                ->sanitizedPostData('mercadopago_yape')
                ->andReturn([]);
        }

        Mockery::mock('overload:' . YapeTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->setCustomMetadata($this->order, $response);
    }

    /**
     * @testWith [true, "approved"]
     *           [false, "pending"]
     *           [false, "in_process"]
     */
    public function testProcessPaymentInternalSuccess(bool $isBlocks, string $status): void
    {
        $this->yapeProcessPaymentInternalMock($isBlocks, compact('status'));

        $this->gateway->mercadopago->helpers->cart
            ->expects()
            ->emptyCart();

        $this->order
            ->expects()
            ->get_checkout_order_received_url()
            ->andReturn($redirect = random()->url());

        if ($status == 'approved') {
            $this->gateway->mercadopago->orderStatus->expects()->getOrderStatusMessage('accredited');
            $this->gateway->mercadopago->helpers->notices->expects()->storeApprovedStatusNotice(null);
            $this->gateway->mercadopago->orderStatus->expects()->setOrderStatus($this->order, 'failed', 'pending');
        }

        $this->assertEquals(
            [
                'result' => 'success',
                'redirect' => $redirect,
            ],
            $this->gateway->proccessPaymentInternal($this->order)
        );
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testProcessPaymentInternalRejected(bool $isBlocks): void
    {
        $response = [
            'status' => 'rejected'
        ];

        $this->yapeProcessPaymentInternalMock($isBlocks, $response);

        $this->gateway
            ->expects()
            ->handleWithRejectPayment($response)
            ->andThrow(RejectedPaymentException::class);

        $this->expectException(RejectedPaymentException::class);

        $this->gateway->proccessPaymentInternal($this->order);
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testProcessPaymentInternalStatusNotMapped(bool $isBlocks): void
    {
        $this->yapeProcessPaymentInternalMock($isBlocks, [
            'status' => random()->word()
        ]);

        $this->expectException(ResponseStatusException::class);

        $this->gateway->proccessPaymentInternal($this->order);
    }
}
