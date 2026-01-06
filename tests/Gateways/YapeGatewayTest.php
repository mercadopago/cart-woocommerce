<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use MercadoPago\Woocommerce\Transactions\YapeTransaction;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\YapeGateway;

class YapeGatewayTest extends TestCase
{
    use GatewayMock;
    use FormMock;

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
            $this->mockFormSanitizedPostData([]);
            $this->gateway
                ->expects()
                ->processBlocksCheckoutData('mercadopago_yape', [])
                ->andReturn([]);
        } else {
            $_POST['mercadopago_yape'] = 1;
            $this->mockFormSanitizedPostData([], 'mercadopago_yape');
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
     * @runInSeparateProcess
     * @preserveGlobalState disabled
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
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalRejected(bool $isBlocks): void
    {
        $response = [
            'status' => 'rejected'
        ];

        $this->yapeProcessPaymentInternalMock($isBlocks, $response);

        $rejectedMessage = 'buyer_yape_default';
        $translatedMessage = '<strong>Yape declined your payment</strong>';

        $this->gateway
            ->expects()
            ->handleWithRejectPayment($response)
            ->andThrow(new RejectedPaymentException($rejectedMessage));

        // Mock processReturnFail dependencies
        $this->gateway->mercadopago->helpers->errorMessages
            ->expects()
            ->findErrorMessage($rejectedMessage)
            ->andReturn($translatedMessage);

        $this->gateway->datadog
            ->expects()
            ->sendEvent('woo_checkout_error', $translatedMessage, $rejectedMessage);

        $this->gateway->mercadopago->helpers->notices
            ->expects()
            ->storeNotice($translatedMessage, 'error');

        // Exception is now caught and handled, returns fail array
        $result = $this->gateway->proccessPaymentInternal($this->order);

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($translatedMessage, $result['message']);
    }

    /**
     * @testWith [true]
     *           [false]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalStatusNotMapped(bool $isBlocks): void
    {
        $this->yapeProcessPaymentInternalMock($isBlocks, [
            'status' => random()->word()
        ]);

        $originalMessage = 'buyer_yape_default';
        $translatedMessage = '<strong>Yape declined your payment</strong>';

        // Mock processReturnFail dependencies
        $this->gateway->mercadopago->helpers->errorMessages
            ->expects()
            ->findErrorMessage($originalMessage)
            ->andReturn($translatedMessage);

        $this->gateway->datadog
            ->expects()
            ->sendEvent('woo_checkout_error', $translatedMessage, $originalMessage);

        $this->gateway->mercadopago->helpers->notices
            ->expects()
            ->storeNotice($translatedMessage, 'error');

        // Exception is now caught and handled, returns fail array
        $result = $this->gateway->proccessPaymentInternal($this->order);

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($translatedMessage, $result['message']);
    }
}
