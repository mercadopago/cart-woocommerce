<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use MercadoPago\Woocommerce\Transactions\TicketTransaction;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\TicketGateway;
use Mockery;
use WP_Mock;

class TicketGatewayTest extends TestCase
{
    use GatewayMock;
    use FormMock;

    private string $gatewayClass = TicketGateway::class;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var MockInterface|TicketGateway
     */
    private $gateway;

    public function testGetMLBStates()
    {
        $this->assertEquals([
            'AC' => 'Acre',
            'AL' => 'Alagoas',
            'AP' => 'Amapá',
            'AM' => 'Amazonas',
            'BA' => 'Bahia',
            'CE' => 'Ceará',
            'DF' => 'Distrito Federal',
            'ES' => 'Espirito Santo',
            'GO' => 'Goiás',
            'MA' => 'Maranhão',
            'MS' => 'Mato Grosso do Sul',
            'MT' => 'Mato Grosso',
            'MG' => 'Minas Gerais',
            'PA' => 'Pará',
            'PB' => 'Paraíba',
            'PR' => 'Paraná',
            'PE' => 'Pernambuco',
            'PI' => 'Piauí',
            'RJ' => 'Rio de Janeiro',
            'RN' => 'Rio Grande do Norte',
            'RS' => 'Rio Grande do Sul',
            'RO' => 'Rondônia',
            'RR' => 'Roraima',
            'SC' => 'Santa Catarina',
            'SP' => 'São Paulo',
            'SE' => 'Sergipe',
            'TO' => 'Tocantins',
        ], $this->gateway->getMLBStates());
    }

    public function testGetPaymentFieldsErrorMessages()
    {
        $this->assertEquals([
            'postalcode_error_empty' => $this->gateway->storeTranslations['billing_data_postalcode_error_empty'],
            'postalcode_error_partial' => $this->gateway->storeTranslations['billing_data_postalcode_error_partial'],
            'postalcode_error_invalid' => $this->gateway->storeTranslations['billing_data_postalcode_error_invalid'],
            'state_error_unselected' => $this->gateway->storeTranslations['billing_data_state_error_unselected'],
            'city_error_empty' => $this->gateway->storeTranslations['billing_data_city_error_empty'],
            'city_error_invalid' => $this->gateway->storeTranslations['billing_data_city_error_invalid'],
            'neighborhood_error_empty' => $this->gateway->storeTranslations['billing_data_neighborhood_error_empty'],
            'neighborhood_error_invalid' => $this->gateway->storeTranslations['billing_data_neighborhood_error_invalid'],
            'address_error_empty' => $this->gateway->storeTranslations['billing_data_address_error_empty'],
            'address_error_invalid' => $this->gateway->storeTranslations['billing_data_address_error_invalid'],
            'number_error_empty' => $this->gateway->storeTranslations['billing_data_number_error_empty'],
            'number_error_invalid' => $this->gateway->storeTranslations['billing_data_number_error_invalid'],
        ], $this->gateway->getPaymentFieldsErrorMessages());
    }

    public function testIsAvailableReturnsTrue()
    {
        global $mercadopago;

        $mercadopago = Mockery::mock();
        $mercadopago->sellerConfig = Mockery::mock();

        $mercadopago->sellerConfig->shouldReceive('getCheckoutTicketPaymentMethods')->andReturn(['method1', 'method2']);
        $this->assertTrue(TicketGateway::isAvailable());
    }

    public function testIsAvailableReturnsFalse()
    {
        global $mercadopago;

        $mercadopago = Mockery::mock();
        $mercadopago->sellerConfig = Mockery::mock();

        $mercadopago->sellerConfig->shouldReceive('getCheckoutTicketPaymentMethods')->andReturn([]);
        $this->assertFalse(TicketGateway::isAvailable());
    }

    public function testBuildPaycashPaymentString()
    {
        $this->gateway->mercadopago->sellerConfig = Mockery::mock(Seller::class)
            ->shouldReceive('getCheckoutTicketPaymentMethods')
            ->andReturn([
                [
                    'id' => 'paycash',
                    'payment_places' => [
                        ['name' => 'Place 1'],
                        ['name' => 'Place 2'],
                        ['name' => 'Place 3'],
                    ],
                ],
                [
                    'id' => random()->word(),
                    'payment_places' => [
                        ['name' => 'Place A'],
                        ['name' => 'Place B'],
                        ['name' => 'Place C'],
                    ],
                ],
            ])
            ->getMock();

        $this->assertEquals(
            "Place 1, Place 2{$this->gateway->storeTranslations['paycash_concatenator']}Place 3",
            $this->gateway->buildPaycashPaymentString()
        );
    }

    private function processPaymentMock(bool $isBlocks, array $checkout): void
    {
        $this->processPaymentInternalMock($isBlocks);

        $checkout = array_merge([
            'amount' => random()->numberBetween(1),
            'payment_method_id' => random()->creditCardType(),
        ], $checkout);

        if ($isBlocks) {
            $_POST['mercadopago_ticket'] = null;
            $this->mockFormSanitizedPostData([]);
            $this->gateway
                ->expects()
                ->processBlocksCheckoutData('mercadopago_ticket', [])
                ->andReturn($checkout);
        } else {
            $_POST['mercadopago_ticket'] = 1;
            $this->mockFormSanitizedPostData($checkout, 'mercadopago_ticket');
        }
    }

    /**
     * @testWith [true, {"site_id": "MLB", "doc_number": "1234567909"}, {"status_detail": "pending_waiting_payment", "payment_type_id": "bank_transfer"}, "no"]
     *           [false, {"site_id": "MLU", "doc_number": "1234567909", "doc_type": "otro"}, {"status_detail": "pending_waiting_transfer", "payment_type_id": "fake"}, "yes"]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalSuccess(bool $isBlocks, array $checkout, array $response, string $stockReduceMode): void
    {
        $this->processPaymentMock($isBlocks, $checkout);

        if ($response['payment_type_id'] !== 'bank_transfer') {
            $response['transaction_details'] = [
                'external_resource_url' => random()->url()
            ];

            $this->gateway->mercadopago->hooks->order
                ->expects()
                ->addOrderNote(
                    $this->order,
                    allOf(
                        containsString($this->gateway->storeTranslations['congrats_title']),
                        containsString($response['transaction_details']['external_resource_url']),
                        containsString($this->gateway->storeTranslations['congrats_subtitle'])
                    ),
                    1
                );
        }

        Mockery::mock('overload:' . TicketTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn(
                $response = array_merge([
                    'id' => random()->uuid(),
                    'status' => 'pending'
                ], $response)
            );

        $this->order->expects()->get_id();

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->updatePaymentsOrderMetadata($this->order, ['id' => $response['id']]);

        $this->gateway
            ->expects()
            ->handleWithRejectPayment($response);

        $this->gateway->mercadopago->helpers->cart
            ->expects()
            ->emptyCart();

        $this->gateway->mercadopago->hooks->options
            ->expects()
            ->getGatewayOption($this->gateway, 'stock_reduce_mode', 'no')
            ->andReturn($stockReduceMode);

        if ($stockReduceMode === 'yes') {
            WP_Mock::userFunction('wc_reduce_stock_levels')->with(null);
        }

        $this->gateway->mercadopago->hooks->order
            ->expects()
            ->setTicketMetadata($this->order, $response)
            ->getMock()
            ->expects()
            ->addOrderNote($this->order, $this->gateway->storeTranslations['customer_not_paid']);

        $this->order
            ->expects()
            ->get_checkout_order_received_url()
            ->andReturn($redirect = random()->url());

        $this->assertEquals(
            [
                'result' => 'success',
                'redirect' => $redirect,
            ],
            $this->gateway->proccessPaymentInternal($this->order)
        );
    }

    /**
     * @testWith [true, {"amount": null}, false]
     *           [false, {"payment_method_id": null}, false]
     *           [true, {"site_id": "MLB", "doc_number": "1234567909"}, true]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalInvalidCheckoutDataException(bool $isBlocks, array $checkout, bool $mockTransaction): void
    {
        $this->processPaymentMock($isBlocks, $checkout);

        if ($mockTransaction) {
            Mockery::mock('overload:' . TicketTransaction::class)
                ->expects()
                ->createPayment()
                ->andReturn([]);
        }

        $this->expectException(InvalidCheckoutDataException::class);

        $this->gateway->proccessPaymentInternal($this->order);
    }

    /**
     * @testWith [true, {"site_id": "MLB"}]
     *           [false, {"site_id": "MLU"}]
     *           [true, {"site_id": "MLU", "doc_number": "1234567909"}]
     *           [false, {"site_id": "MLU", "doc_type": "otro"}]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalInvalidCheckout(bool $isBlocks, array $checkout): void
    {
        $this->processPaymentMock($isBlocks, $checkout);

        $this->gateway->mercadopago->storeTranslations->commonMessages['cho_form_error'] = random()->word();

        $this->gateway
            ->expects()
            ->processReturnFail(
                Mockery::type(\Exception::class),
                $this->gateway->mercadopago->storeTranslations->commonMessages['cho_form_error'],
                TicketGateway::LOG_SOURCE
            )->andReturn($return = [
                'result' => 'fail',
                'redirect' => '',
                'message' => 'error',
            ]);

        $this->assertEquals($return, $this->gateway->proccessPaymentInternal($this->order));
    }

    /**
     * @testWith [true]
     *           [false]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalRejected(bool $isBlocks): void
    {
        $this->processPaymentMock($isBlocks, [
            "site_id" => "MLB",
            "doc_number" => "1234567909"
        ]);

        Mockery::mock('overload:' . TicketTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response = [
                'id' => random()->uuid(),
                'status' => 'rejected',
            ]);

        $this->order->expects()->get_id();

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
     * @testWith [true, {"id": "PAY_123", "status": "fake"}]
     *           [false, {"id": "PAY_123", "status": "pending", "status_detail": "fake"}]
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalInvalidStatus(bool $isBlocks, array $response): void
    {
        $this->processPaymentMock($isBlocks, [
            "site_id" => "MLB",
            "doc_number" => "1234567909"
        ]);

        Mockery::mock('overload:' . TicketTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn($response);

        $this->order->expects()->get_id();

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->updatePaymentsOrderMetadata($this->order, ['id' => $response['id']]);

        $this->gateway->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'] = random()->word();

        $this->gateway
            ->expects()
            ->processReturnFail(
                Mockery::type(ResponseStatusException::class),
                $this->gateway->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'],
                TicketGateway::LOG_SOURCE,
                $response
            )->andReturn($return = [
                'result' => 'fail',
                'redirect' => '',
                'message' => 'error',
            ]);

        $this->assertEquals($return, $this->gateway->proccessPaymentInternal($this->order));
    }

    public function testGetCheckoutExpirationDate()
    {
        $this->gateway
            ->expects('get_option')
            ->andReturn($expected = random()->randomNumber(1));

        $this->assertEquals($expected, $this->gateway->getCheckoutExpirationDate());
    }
}
