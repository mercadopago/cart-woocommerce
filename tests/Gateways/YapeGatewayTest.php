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
use \WP_Mock;

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

        // Set paymentMethodName to match YapeGateway constructor (protected property)
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', 'woo-mercado-pago-yape');

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

        // Reset datadog mock to remove byDefault() and set specific expectation
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock
            ->expects()
            ->sendEvent('woo_checkout_error', $translatedMessage, $rejectedMessage, 'woo-mercado-pago-yape');
        $this->gateway->datadog = $datadogMock;

        $this->gateway->mercadopago->helpers->notices
            ->expects()
            ->storeNotice($translatedMessage, 'error');

        // Mock logs->file->error to avoid errors
        $this->gateway->mercadopago->logs->file
            ->shouldReceive('error')
            ->andReturnNull();

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

        // Set paymentMethodName to match YapeGateway constructor (protected property)
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', 'woo-mercado-pago-yape');

        $originalMessage = 'buyer_yape_default';
        $translatedMessage = '<strong>Yape declined your payment</strong>';

        // Mock processReturnFail dependencies
        $this->gateway->mercadopago->helpers->errorMessages
            ->expects()
            ->findErrorMessage($originalMessage)
            ->andReturn($translatedMessage);

        // Reset datadog mock to remove byDefault() and set specific expectation
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock
            ->expects()
            ->sendEvent('woo_checkout_error', $translatedMessage, $originalMessage, 'woo-mercado-pago-yape');
        $this->gateway->datadog = $datadogMock;

        $this->gateway->mercadopago->helpers->notices
            ->expects()
            ->storeNotice($translatedMessage, 'error');

        // Mock logs->file->error to avoid errors
        $this->gateway->mercadopago->logs->file
            ->shouldReceive('error')
            ->andReturnNull();

        // Exception is now caught and handled, returns fail array
        $result = $this->gateway->proccessPaymentInternal($this->order);

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($translatedMessage, $result['message']);
    }

    /**
     * Test processReturnFail execution to increase coverage
     * This test ensures processReturnFail is actually executed (not mocked) to cover all lines
     */
    public function testProcessReturnFailExecution(): void
    {
        // Set paymentMethodName to match YapeGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', YapeGateway::ID);

        $errorMessage = 'buyer_yape_default';
        $translatedMessage = 'Translated error message';

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), YapeGateway::LOG_SOURCE, Mockery::type('array'))
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
            ->with('woo_checkout_error', $translatedMessage, $errorMessage, YapeGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($translatedMessage, 'error');

        $exception = new \Exception('Test exception');

        $result = $this->gateway->processReturnFail(
            $exception,
            $errorMessage,
            YapeGateway::LOG_SOURCE,
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
        $result = $this->gateway->formFieldsMainSection();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('card_info_helper', $result);
        $this->assertArrayHasKey('currency_conversion', $result);
        $this->assertArrayHasKey('advanced_configuration_title', $result);
        $this->assertArrayHasKey('advanced_configuration_description', $result);
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
            'site_id' => 'MPE',
            'currency' => 'PEN'
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
            'custom_checkout' => []
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
            ->with('public/checkouts/yape-checkout.php', ['test' => 'params']);

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
        $this->gateway->icon = 'test-icon-url';

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(false);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->andReturn('test-image-url');

        $linksProperty = (new \ReflectionClass($this->gateway))->getProperty('links');
        $linksProperty->setAccessible(true);
        $linksProperty->setValue($this->gateway, [
            'docs_integration_test' => 'https://test-docs.com',
            'mercadopago_terms_and_conditions' => 'https://terms.com'
        ]);

        $this->gateway->storeTranslations = [
            'test_mode_title' => 'Test Mode',
            'test_mode_description' => 'Test Description',
            'test_mode_link_text' => 'Test Link',
            'terms_and_conditions_description' => 'Terms Description',
            'terms_and_conditions_link_text' => 'Terms Link',
            'yape_input_field_label' => 'Input Label',
            'checkout_notice_message' => 'Notice Message',
            'yape_title' => 'Yape Title',
            'yape_subtitle' => 'Yape Subtitle',
            'input_code_label' => 'Code Label',
            'footer_text' => 'Footer Text',
            'yape_tooltip_text' => 'Tooltip Text',
            'yape_input_code_error_message1' => 'Error Message 1',
            'yape_input_code_error_message2' => 'Error Message 2',
            'yape_phone_number_error_message1' => 'Phone Error 1',
            'yape_phone_number_error_message2' => 'Phone Error 2'
        ];

        $result = $this->gateway->getPaymentFieldsParams();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('test_mode', $result);
        $this->assertArrayHasKey('test_mode_title', $result);
        $this->assertArrayHasKey('input_code_icon', $result);
        $this->assertArrayHasKey('checkout_blocks_row_image_src', $result);
    }

    /**
     * Test getRejectedPaymentErrorKey with valid statusDetail
     *
     * @return void
     */
    public function testGetRejectedPaymentErrorKeyWithValidStatusDetail(): void
    {
        $statusDetail = 'cc_rejected_insufficient_amount';
        $expectedKey = 'buyer_yape_' . $statusDetail;

        $this->gateway->mercadopago->storeTranslations->buyerRefusedMessages = [
            $expectedKey => 'Error message'
        ];

        $result = $this->gateway->getRejectedPaymentErrorKey($statusDetail);

        $this->assertEquals($expectedKey, $result);
    }

    /**
     * Test getRejectedPaymentErrorKey with invalid statusDetail
     *
     * @return void
     */
    public function testGetRejectedPaymentErrorKeyWithInvalidStatusDetail(): void
    {
        $statusDetail = 'invalid_status';

        $this->gateway->mercadopago->storeTranslations->buyerRefusedMessages = [];

        $result = $this->gateway->getRejectedPaymentErrorKey($statusDetail);

        $this->assertEquals('buyer_yape_default', $result);
    }

    /**
     * Test isAvailable returns true when country is MPE
     *
     * @return void
     */
    public function testIsAvailableReturnsTrue(): void
    {
        global $mercadopago;

        $originalMercadopago = $GLOBALS['mercadopago'] ?? null;

        $mercadopago = Mockery::mock();
        $mercadopago->helpers = Mockery::mock();
        $mercadopago->helpers->country = Mockery::mock();

        $mercadopago->helpers->country
            ->shouldReceive('getPluginDefaultCountry')
            ->andReturn('PE'); // COUNTRY_CODE_MPE = 'PE'

        $GLOBALS['mercadopago'] = $mercadopago;
        $result = YapeGateway::isAvailable();

        // Restore original
        if ($originalMercadopago !== null) {
            $GLOBALS['mercadopago'] = $originalMercadopago;
        }

        $this->assertTrue($result);
    }

    /**
     * Test isAvailable returns false when country is not MPE
     *
     * @return void
     */
    public function testIsAvailableReturnsFalse(): void
    {
        global $mercadopago;

        $mercadopago = Mockery::mock();
        $mercadopago->helpers = Mockery::mock();
        $mercadopago->helpers->country = Mockery::mock();

        $mercadopago->helpers->country
            ->shouldReceive('getPluginDefaultCountry')
            ->andReturn('MLB');

        $this->assertFalse(YapeGateway::isAvailable());
    }

    /**
     * Test handleResponseStatus when response is not an array (triggers exception)
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalResponseNotArray(): void
    {
        $this->processPaymentInternalMock(false);

        $_POST['mercadopago_yape'] = 1;
        $this->mockFormSanitizedPostData([], 'mercadopago_yape');

        // Mock YapeTransaction to return null (not an array)
        Mockery::mock('overload:' . YapeTransaction::class)
            ->expects()
            ->createPayment()
            ->andReturn(null);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->setCustomMetadata($this->order, null);

        // Set paymentMethodName to match YapeGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', YapeGateway::ID);

        // Mock processReturnFail dependencies
        $this->gateway->mercadopago->helpers->errorMessages
            ->shouldReceive('findErrorMessage')
            ->with('buyer_yape_default')
            ->andReturn('Translated error message');

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), YapeGateway::LOG_SOURCE, Mockery::type('array'))
            ->andReturnNull();
        $this->gateway->mercadopago->logs->file = $logsFileMock;

        // Mock datadog->sendEvent
        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('woo_checkout_error', 'Translated error message', 'buyer_yape_default', YapeGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with('Translated error message', 'error');

        // Exception is caught and handled, returns fail array
        $result = $this->gateway->proccessPaymentInternal($this->order);

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals('Translated error message', $result['message']);
    }
}
