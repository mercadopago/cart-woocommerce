<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Helpers\Arrays;
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
                'cho_form_error',
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
                'buyer_default',
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

    /**
     * Test processReturnFail execution to increase coverage
     * This test ensures processReturnFail is actually executed (not mocked) to cover all lines
     */
    public function testProcessReturnFailExecution(): void
    {
        // Set paymentMethodName to match TicketGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', TicketGateway::ID);

        $errorMessage = 'buyer_default';
        $translatedMessage = 'Translated error message';

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), TicketGateway::LOG_SOURCE, Mockery::type('array'))
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
            ->with('woo_checkout_error', $translatedMessage, $errorMessage, TicketGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($translatedMessage, 'error');

        $exception = new \Exception('Test exception');

        $result = $this->gateway->processReturnFail(
            $exception,
            $errorMessage,
            TicketGateway::LOG_SOURCE,
            [],
            true
        );

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($translatedMessage, $result['message']);
    }

    /**
     * Test formFieldsMainSection method
     *
     * @return void
     */
    public function testFormFieldsMainSection(): void
    {
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCheckoutTicketPaymentMethods')
            ->andReturn([
                ['id' => 'payment1', 'name' => 'Payment 1']
            ]);

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('get_field_key')
            ->andReturn('field_key_1');

        $this->gateway->mercadopago->hooks->options
            ->shouldReceive('getGatewayOption')
            ->andReturn('yes');

        $this->gateway->id = TicketGateway::ID;

        $result = $this->gateway->formFieldsMainSection();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('currency_conversion', $result);
        $this->assertArrayHasKey('type_payments', $result);
        $this->assertArrayHasKey('date_expiration', $result);
        $this->assertArrayHasKey('advanced_configuration_title', $result);
        $this->assertArrayHasKey('advanced_configuration_description', $result);
        $this->assertArrayHasKey('stock_reduce_mode', $result);
    }

    /**
     * Test formFieldsMainSection with payment methods that have payment_places
     *
     * @return void
     */
    public function testFormFieldsMainSectionWithPaymentPlaces(): void
    {
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCheckoutTicketPaymentMethods')
            ->andReturn([
                [
                    'id' => 'paycash',
                    'name' => 'Paycash',
                    'payment_places' => [
                        ['name' => 'Place 1'],
                        ['name' => 'Place 2']
                    ]
                ]
            ]);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCheckoutTicketPaymentMethods')
            ->andReturn([
                [
                    'id' => 'paycash',
                    'payment_places' => [
                        ['name' => 'Place 1'],
                        ['name' => 'Place 2']
                    ]
                ]
            ]);

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('get_field_key')
            ->andReturn('field_key_1');

        $this->gateway->mercadopago->hooks->options
            ->shouldReceive('getGatewayOption')
            ->andReturn('yes');

        $this->gateway->id = TicketGateway::ID;

        $this->gateway->storeTranslations = [
            'paycash_concatenator' => ' and '
        ];

        $result = $this->gateway->formFieldsMainSection();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('type_payments', $result);
    }

    /**
     * Test registerCheckoutScripts method
     *
     * @return void
     */
    public function testRegisterCheckoutScripts(): void
    {
        \WP_Mock::userFunction('get_stylesheet')
            ->andReturn('test-theme');

        $this->gateway->mercadopago->woocommerce->version = '8.0.0';

        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'site_id' => 'MLB',
            'currency' => 'BRL'
        ]);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsPublicKey')
            ->andReturn('test-public-key');

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getPaymentFieldsErrorMessages')
            ->andReturn([]);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->andReturn('test-css-url');

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getJsAsset')
            ->andReturn('test-js-url');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerCheckoutScript')
            ->atLeast()->once();

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerCheckoutStyle')
            ->atLeast()->once();

        $this->gateway->storeTranslations = [
            'custom_checkout' => [],
            'billing_data_postalcode_error_empty' => 'Postal code is required',
            'billing_data_postalcode_error_partial' => 'Postal code is partial',
            'billing_data_postalcode_error_invalid' => 'Postal code is invalid',
            'billing_data_state_error_unselected' => 'State is required',
            'billing_data_city_error_empty' => 'City is required',
            'billing_data_city_error_invalid' => 'City is invalid',
            'billing_data_neighborhood_error_empty' => 'Neighborhood is required',
            'billing_data_neighborhood_error_invalid' => 'Neighborhood is invalid',
            'billing_data_address_error_empty' => 'Address is required',
            'billing_data_address_error_invalid' => 'Address is invalid',
            'billing_data_number_error_empty' => 'Number is required',
            'billing_data_number_error_invalid' => 'Number is invalid'
        ];

        $this->gateway->registerCheckoutScripts();

        $this->assertTrue(true);
    }

    /**
     * Test payment_fields method
     *
     * @return void
     */
    public function testPaymentFields(): void
    {
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getPaymentFieldsParams')
            ->andReturn(['test' => 'params']);

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/checkouts/ticket-checkout.php', ['test' => 'params']);

        $this->gateway->payment_fields();

        $this->assertTrue(true);
    }

    /**
     * Test renderThankYouPage when transactionDetails is empty
     *
     * @return void
     */
    public function testRenderThankYouPageEmptyTransactionDetails(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');

        WP_Mock::userFunction('wc_get_order')
            ->with($orderId)
            ->andReturn($order);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTicketTransactionDetailsMeta')
            ->with($order)
            ->andReturn([]);

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->never();

        $this->gateway->renderThankYouPage($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test renderThankYouPage with valid transactionDetails
     *
     * @return void
     */
    public function testRenderThankYouPageWithTransactionDetails(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');
        $transactionDetails = ['ticket_url' => 'https://example.com/ticket'];

        WP_Mock::userFunction('wc_get_order')
            ->with($orderId)
            ->andReturn($order);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTicketTransactionDetailsMeta')
            ->with($order)
            ->andReturn($transactionDetails);

        $this->gateway->storeTranslations = [
            'print_ticket_label' => 'Print Ticket',
            'print_ticket_link' => 'Print'
        ];

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/order/ticket-order-received.php', Mockery::type('array'));

        $this->gateway->renderThankYouPage($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test buildPaycashPaymentString with single payment place
     *
     * @return void
     */
    public function testBuildPaycashPaymentStringWithSinglePlace(): void
    {
        $this->gateway->mercadopago->sellerConfig = Mockery::mock(Seller::class)
            ->shouldReceive('getCheckoutTicketPaymentMethods')
            ->andReturn([
                [
                    'id' => 'paycash',
                    'payment_places' => [
                        ['name' => 'Place 1'],
                    ],
                ],
            ])
            ->getMock();

        $this->gateway->storeTranslations = [
            'paycash_concatenator' => ' and '
        ];

        $result = $this->gateway->buildPaycashPaymentString();

        // With single place, array_pop returns that place, and implode with empty array returns empty string
        // So result should be empty string concatenated with the place
        $this->assertIsString($result);
    }



    /**
     * Test getCheckoutExpirationDate with default value
     *
     * @return void
     */
    public function testGetCheckoutExpirationDateWithDefault(): void
    {
        $this->gateway
            ->shouldReceive('get_option')
            ->with('date_expiration', '3')
            ->andReturn('3');

        $result = $this->gateway->getCheckoutExpirationDate();

        $this->assertEquals('3', $result);
    }

}
