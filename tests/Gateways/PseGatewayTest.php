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
                'cho_form_error',
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
                'cho_form_error',
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

    /**
     * Test processReturnFail execution to increase coverage
     * This test ensures processReturnFail is actually executed (not mocked) to cover all lines
     */
    public function testProcessReturnFailExecution(): void
    {
        // Set paymentMethodName to match PseGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', PseGateway::ID);

        $errorMessage = 'cho_form_error';
        $translatedMessage = 'Translated error message';

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), PseGateway::LOG_SOURCE, Mockery::type('array'))
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
            ->with('woo_checkout_error', $translatedMessage, $errorMessage, PseGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($translatedMessage, 'error');

        $exception = new \Exception('Test exception');

        $result = $this->gateway->processReturnFail(
            $exception,
            $errorMessage,
            PseGateway::LOG_SOURCE,
            [],
            true
        );

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($translatedMessage, $result['message']);
    }

    /**
     * Test processPaymentInternal when checkout is invalid - executes processReturnFail directly
     * This increases coverage by executing the actual processReturnFail code path
     * Note: We test this by providing invalid checkout data that will fail validation
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalInvalidCheckoutExecutesProcessReturnFail(): void
    {
        // Set paymentMethodName to match PseGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', PseGateway::ID);

        // Provide invalid checkout data to make validation fail
        // isCheckoutValid requires: site_id='MCO', doc_number, doc_type, person_type, bank
        // We override defaults with invalid values to make validation fail
        $this->processPaymentMock(false, [
            'site_id' => '', // Empty - will fail validation
            'doc_number' => '', // Empty - will fail validation
            'doc_type' => '', // Empty - will fail validation
            'person_type' => 'individual',
            'bank' => '', // Empty - will fail validation
        ]);

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), PseGateway::LOG_SOURCE, Mockery::type('array'))
            ->andReturnNull();
        $this->gateway->mercadopago->logs->file = $logsFileMock;

        // Mock errorMessages helper
        $this->gateway->mercadopago->helpers->errorMessages
            ->shouldReceive('findErrorMessage')
            ->once()
            ->with('cho_form_error')
            ->andReturn('Form error message');

        // Mock datadog->sendEvent
        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('woo_checkout_error', 'Form error message', 'cho_form_error', PseGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with('Form error message', 'error');

        $result = $this->gateway->proccessPaymentInternal($this->order);

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals('Form error message', $result['message']);
    }

    /**
     * Test formFieldsMainSection method
     *
     * @return void
     */
    public function testFormFieldsMainSection(): void
    {
        $result = $this->gateway->formFieldsMainSection();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('currency_conversion', $result);
        $this->assertArrayHasKey('advanced_configuration_title', $result);
        $this->assertArrayHasKey('advanced_configuration_description', $result);
        $this->assertArrayHasKey('stock_reduce_mode', $result);
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
            'intl' => 'es-CO',
            'site_id' => 'MCO',
            'currency' => 'COP'
        ]);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsPublicKey')
            ->andReturn('test-public-key');

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->andReturn('test-css-url');

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getJsAsset')
            ->andReturn('test-js-url');

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getPaymentFieldsErrorMessages')
            ->andReturn([]);

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerCheckoutStyle')
            ->atLeast()->once();

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerCheckoutScript')
            ->atLeast()->once(); // parent::registerCheckoutScripts() + 3 scripts específicos do PSE

        $this->gateway->storeTranslations = [
            'financial_placeholder' => 'Select financial institution'
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
            ->with('public/checkouts/pse-checkout.php', ['test' => 'params']);

        $this->gateway->payment_fields();

        $this->assertTrue(true);
    }

    /**
     * Test getPaymentFieldsParams method
     *
     * @return void
     */
    public function testGetPaymentFieldsParams(): void
    {
        $this->gateway->icon = 'test-icon';

        $currentUser = Mockery::mock('WP_User');
        $currentUser->ID = 1;
        $currentUser->user_email = 'test@example.com';

        $this->gateway->mercadopago->helpers->currentUser
            ->shouldReceive('getCurrentUser')
            ->andReturn($currentUser);

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MCO');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCheckoutPsePaymentMethods')
            ->andReturn([
                [
                    'financial_institutions' => [
                        ['id' => 'bank1', 'name' => 'Bank 1'],
                        ['id' => 'bank2', 'name' => 'Bank 2']
                    ]
                ]
            ]);

        $this->gateway->mercadopago->helpers->country
            ->shouldReceive('getCountryConfigs')
            ->andReturn(['currency' => 'COP']);

        $this->setNotAccessibleProperty($this->gateway, 'links', [
            'docs_integration_test' => 'https://example.com/test-docs',
            'mercadopago_terms_and_conditions' => 'https://example.com/terms'
        ]);

        $this->gateway->storeTranslations = [
            'test_mode_title' => 'Test Mode',
            'test_mode_description' => 'Test Description',
            'test_mode_link_text' => 'Test Link',
            'input_document_label' => 'Document',
            'input_document_helper_empty' => 'Empty Document',
            'input_document_helper_invalid' => 'Invalid Document',
            'input_document_helper_wrong' => 'Wrong Document',
            'pse_text_label' => 'PSE Label',
            'input_table_button' => 'Button',
            'terms_and_conditions_description' => 'Terms Description',
            'terms_and_conditions_link_text' => 'Terms Link',
            'person_type_label' => 'Person Type',
            'financial_institutions_label' => 'Financial Institutions',
            'financial_institutions_helper' => 'Helper',
            'financial_placeholder' => 'Placeholder',
            'message_error_amount' => 'Amount Error'
        ];

        \WP_Mock::userFunction('get_woocommerce_currency')
            ->andReturn('COP');

        \WP_Mock::userFunction('esc_js')
            ->with('test@example.com')
            ->andReturn('test@example.com');

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getAmountAndCurrency')
            ->andReturn(['amount' => 100, 'currencyRatio' => 1.0]);

        $params = $this->gateway->getPaymentFieldsParams();

        $this->assertIsArray($params);
        $this->assertArrayHasKey('checkout_blocks_row_image_src', $params);
        $this->assertArrayHasKey('payer_email', $params);
        $this->assertArrayHasKey('financial_institutions', $params);
        $this->assertEquals('test-icon', $params['checkout_blocks_row_image_src']);
        $this->assertEquals('test@example.com', $params['payer_email']);
    }

    /**
     * Test getPaymentFieldsParams with logged out user
     *
     * @return void
     */
    public function testGetPaymentFieldsParamsWithLoggedOutUser(): void
    {
        $this->gateway->icon = 'test-icon';

        $currentUser = Mockery::mock('WP_User');
        $currentUser->ID = 0; // Logged out user
        $currentUser->user_email = '';

        $this->gateway->mercadopago->helpers->currentUser
            ->shouldReceive('getCurrentUser')
            ->andReturn($currentUser);

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MCO');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCheckoutPsePaymentMethods')
            ->andReturn([
                [
                    'financial_institutions' => []
                ]
            ]);

        $this->gateway->mercadopago->helpers->country
            ->shouldReceive('getCountryConfigs')
            ->andReturn(['currency' => 'COP']);

        $this->setNotAccessibleProperty($this->gateway, 'links', [
            'docs_integration_test' => 'https://example.com/test-docs',
            'mercadopago_terms_and_conditions' => 'https://example.com/terms'
        ]);

        $this->gateway->storeTranslations = [
            'test_mode_title' => 'Test Mode',
            'test_mode_description' => 'Test Description',
            'test_mode_link_text' => 'Test Link',
            'input_document_label' => 'Document',
            'input_document_helper_empty' => 'Empty Document',
            'input_document_helper_invalid' => 'Invalid Document',
            'input_document_helper_wrong' => 'Wrong Document',
            'pse_text_label' => 'PSE Label',
            'input_table_button' => 'Button',
            'terms_and_conditions_description' => 'Terms Description',
            'terms_and_conditions_link_text' => 'Terms Link',
            'person_type_label' => 'Person Type',
            'financial_institutions_label' => 'Financial Institutions',
            'financial_institutions_helper' => 'Helper',
            'financial_placeholder' => 'Placeholder',
            'message_error_amount' => 'Amount Error'
        ];

        \WP_Mock::userFunction('get_woocommerce_currency')
            ->andReturn('COP');

        \WP_Mock::userFunction('esc_js')
            ->with(null)
            ->andReturn('');

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getAmountAndCurrency')
            ->andReturn(['amount' => 100, 'currencyRatio' => 1.0]);

        $params = $this->gateway->getPaymentFieldsParams();

        $this->assertIsArray($params);
        // esc_js(null) returns empty string '', not null
        $this->assertEquals('', $params['payer_email']); // Should be empty string for logged out user
    }

    /**
     * Test isAvailable method returns true for MCO
     *
     * @return void
     */
    public function testIsAvailableWithMCO(): void
    {
        global $mercadopago;

        // Ensure global mercadopago is initialized
        if (!isset($mercadopago)) {
            $mercadopago = Mockery::mock('stdClass');
            $mercadopago->helpers = Mockery::mock('stdClass');
            $mercadopago->helpers->country = Mockery::mock(\MercadoPago\Woocommerce\Helpers\Country::class);
        }

        $mercadopago->helpers->country
            ->shouldReceive('getPluginDefaultCountry')
            ->andReturn('CO'); // Colombia

        $result = PseGateway::isAvailable();

        $this->assertTrue($result);
    }

}
