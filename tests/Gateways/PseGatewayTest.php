<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use MercadoPago\Woocommerce\Transactions\PseTransaction;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\PseGateway;
use Mockery;
use WP_Mock;

class PseGatewayTest extends TestCase
{
    use GatewayMock;
    use FormMock;

    private string $gatewayClass = PseGateway::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var \Mockery\MockInterface|PseGateway
     */
    private $gateway;

    private function processPaymentMock(bool $isBlocks, array $checkout): void
    {
        $this->processPaymentInternalMock($isBlocks);

        $checkout = array_merge([
            'site_id' => Country::SITE_ID_MCO,
            'doc_number' => '123456789',
            'doc_type' => 'otro',
            'person_type' => random()->randomElement(['individual', 'association']),
            'bank' => 'bank',
        ], $checkout);

        if ($isBlocks) {
            $this->mockFormSanitizedPostData([]);
            $this->gateway
                ->expects()
                ->processBlocksCheckoutData('mercadopago_pse', [])
                ->andReturn($checkout);
            $_POST['mercadopago_pse'] = null;
        } else {
            $this->mockFormSanitizedPostData($checkout, 'mercadopago_pse');
            $_POST['mercadopago_pse'] = 1;
        }
    }

    public function testGetCheckoutName(): void
    {
        $this->assertEquals('checkout-pse', $this->gateway->getCheckoutName());
    }

    /**
     * @testWith [true, "individual", "pending_waiting_payment", "no"]
     *           [false, "association", "pending_waiting_transfer", "yes"]
      * @runInSeparateProcess
      * @preserveGlobalState disabled
     */
    public function testProcessPaymentSuccess(bool $isBlocks, string $personType, string $statusDetail, string $stockReduceMode): void
    {
        $this->processPaymentMock($isBlocks, [
            'person_type' => $personType,
        ]);

        Mockery::mock('overload:' . PseTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response = [
                'id' => random()->uuid(),
                'status' => 'pending',
                'status_detail' => $statusDetail,
                'transaction_details' => [
                    'external_resource_url' => random()->url()
                ]
            ]);

        $this->gateway->mercadopago->woocommerce->cart->expects()->empty_cart();

        $this->gateway->mercadopago->hooks->options
            ->expects()
            ->getGatewayOption($this->gateway, 'stock_reduce_mode', 'no')
            ->andReturn($stockReduceMode);

        $this->gateway->mercadopago->hooks->order
            ->expects()
            ->addOrderNote($this->order, $this->gateway->storeTranslations['customer_not_paid']);

        if ($stockReduceMode === 'yes') {
            $this->order->expects()->get_id();
            WP_Mock::userFunction('wc_reduce_stock_levels')->with(null);
        }

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->updatePaymentsOrderMetadata($this->order, ['id' => $response['id']]);

        $this->assertEquals(
            [
                'result' => 'success',
                'redirect' => $response['transaction_details']['external_resource_url'],
            ],
            $this->gateway->proccessPaymentInternal($this->order)
        );
    }

    /**
     * @testWith [true, {"site_id": ""}]
     *           [false, {"doc_number": ""}]
     *           [false, {"doc_type": ""}]
     *           [false, {"person_type": ""}]
     *           [false, {"bank": ""}]
     *           [true, {"site_id": "MLB"}]
     *           [true, {"person_type": "error"}]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProccessPaymentInvalidCheckout(bool $isBlocks, array $checkout)
    {
        $this->processPaymentMock($isBlocks, $checkout);

        $this->gateway
            ->expects()
            ->processReturnFail(
                Mockery::type(InvalidCheckoutDataException::class),
                $this->gateway->mercadopago->storeTranslations->commonMessages['cho_form_error'] = random()->word(),
                PseGateway::LOG_SOURCE,
                Mockery::type('array'),
                true
            )
            ->andReturn($expected = [
                'result' => 'fail',
                'redirect' => '',
                'message' => 'error',
            ]);

        $this->assertEquals(
            $expected,
            $this->gateway->proccessPaymentInternal($this->order)
        );
    }

    /**
     * @testWith [true, "individual"]
     *           [false, "association"]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProccessPaymentRejected(bool $isBlocks, string $personType)
    {
        $this->processPaymentMock($isBlocks, [
            'person_type' => $personType,
        ]);

        Mockery::mock('overload:' . PseTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response = [
                'id' => random()->uuid(),
                'status' => 'rejected',
            ]);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->updatePaymentsOrderMetadata($this->order, ['id' => $response['id']]);

        $this->gateway
            ->expects()
            ->handleWithRejectPayment($response)
            ->andThrow(RejectedPaymentException::class);

        $this->expectException(RejectedPaymentException::class);

        $this->gateway->proccessPaymentInternal($this->order);
    }

    /**
     * @testWith [true, "individual", {"id": "PAY_123", "status": "error", "status_detail": "pending_waiting_payment"}]
     *           [false, "association", {"id": "PAY_123", "status": "pending", "status_detail": "error"}]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProccessPaymentInvalidStatus(bool $isBlocks, string $personType, array $response)
    {
        $this->processPaymentMock($isBlocks, [
            'person_type' => $personType,
        ]);

        Mockery::mock('overload:' . PseTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->updatePaymentsOrderMetadata($this->order, ['id' => $response['id']]);

        $this->gateway
            ->expects()
            ->processReturnFail(
                Mockery::type(ResponseStatusException::class),
                $this->gateway->mercadopago->storeTranslations->commonMessages['cho_form_error'] = random()->word(),
                PseGateway::LOG_SOURCE,
                $response
            )
            ->andReturn($expected = [
                'result' => 'fail',
                'redirect' => '',
                'message' => 'error',
            ]);

        $this->assertEquals(
            $expected,
            $this->gateway->proccessPaymentInternal($this->order)
        );
    }

    /**
     * @testWith [true, "individual"]
     *           [false, "association"]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProccessPaymentUnableProccess(bool $isBlocks, string $personType)
    {
        $this->processPaymentMock($isBlocks, [
            'person_type' => $personType,
        ]);

        Mockery::mock('overload:' . PseTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn([]);

        $this->expectException(InvalidCheckoutDataException::class);

        $this->gateway->proccessPaymentInternal($this->order);
    }
}
