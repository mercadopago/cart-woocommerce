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

    /**
     * Test processReturnFail execution to increase coverage
     * This test ensures processReturnFail is actually executed (not mocked) to cover all lines
     */
    public function testProcessReturnFailExecution(): void
    {
        // Set paymentMethodName to match PixGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', PixGateway::ID);

        $errorMessage = 'buyer_default';
        $translatedMessage = 'Translated error message';

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), PixGateway::LOG_SOURCE, Mockery::type('array'))
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
            ->with('woo_checkout_error', $translatedMessage, $errorMessage, PixGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($translatedMessage, 'error');

        $exception = new \Exception('Test exception');

        $result = $this->gateway->processReturnFail(
            $exception,
            $errorMessage,
            PixGateway::LOG_SOURCE,
            [],
            true
        );

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($translatedMessage, $result['message']);
    }

    /**
     * Test processPaymentInternal when email is invalid - executes processReturnFail directly
     * This increases coverage by executing the actual processReturnFail code path
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessPaymentInternalInvalidEmailExecutesProcessReturnFail(): void
    {
        // Set paymentMethodName to match PixGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', PixGateway::ID);

        // Use processPaymentInternalMock instead of processPaymentMock
        // because proccessPaymentInternal doesn't call calculateSubtotalWithDiscount
        $this->processPaymentInternalMock(false);

        // Mock Form::sanitizedPostData which is called in proccessPaymentInternal
        $this->mockFormSanitizedPostData([]);

        // Mock order with invalid email
        $this->order
            ->shouldReceive('get_billing_email')
            ->once()
            ->andReturn('invalid-email');

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), PixGateway::LOG_SOURCE, Mockery::type('array'))
            ->andReturnNull();
        $this->gateway->mercadopago->logs->file = $logsFileMock;

        // Mock errorMessages helper
        $this->gateway->mercadopago->helpers->errorMessages
            ->shouldReceive('findErrorMessage')
            ->once()
            ->with('invalid_email')
            ->andReturn('Invalid email message');

        // Mock datadog->sendEvent
        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('woo_checkout_error', 'Invalid email message', 'invalid_email', PixGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with('Invalid email message', 'error');

        $result = $this->gateway->proccessPaymentInternal($this->order);

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals('Invalid email message', $result['message']);
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
            ->with('public/checkouts/pix-checkout.php', ['test' => 'params']);

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

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(false);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->with('checkouts/pix/pix')
            ->andReturn('test-image-url');

        $this->gateway->storeTranslations = [
            'test_mode_title' => 'Test Mode',
            'test_mode_description' => 'Test Description',
            'pix_template_title' => 'PIX Title',
            'pix_template_subtitle' => 'PIX Subtitle',
            'pix_template_alt' => 'PIX Alt',
            'terms_and_conditions_description' => 'Terms Description',
            'terms_and_conditions_link_text' => 'Terms Link',
            'message_error_amount' => 'Amount Error',
        ];

        $this->setNotAccessibleProperty($this->gateway, 'links', [
            'mercadopago_terms_and_conditions' => 'https://example.com/terms'
        ]);

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getAmountAndCurrency')
            ->andReturn(['amount' => 100, 'currencyRatio' => 1.0]);

        $params = $this->gateway->getPaymentFieldsParams();

        $this->assertIsArray($params);
        $this->assertArrayHasKey('test_mode', $params);
        $this->assertArrayHasKey('pix_template_src', $params);
        $this->assertArrayHasKey('icon', $params);
        $this->assertEquals('test-icon', $params['icon']);
    }

    /**
     * Test getCheckoutExpirationDate method
     *
     * @return void
     */
    public function testGetCheckoutExpirationDate(): void
    {
        $this->gateway->shouldReceive('get_option')
            ->with('expiration_date', '30 minutes')
            ->andReturn('60 minutes');

        $result = $this->gateway->getCheckoutExpirationDate();

        $this->assertEquals('60 minutes', $result);
    }

    /**
     * Test getCheckoutExpirationDate with default value
     *
     * @return void
     */
    public function testGetCheckoutExpirationDateWithDefault(): void
    {
        $this->gateway->shouldReceive('get_option')
            ->with('expiration_date', '30 minutes')
            ->andReturn('30 minutes');

        $result = $this->gateway->getCheckoutExpirationDate();

        $this->assertEquals('30 minutes', $result);
    }



    /**
     * Test generatePixImage with array qrCodeBase64
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneratePixImageWithArrayQrCodeBase64(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');
        $qrCodeBase64 = ['base64_string_1', 'base64_string_2'];

        $this->mockFormSanitizedGetData(['id' => $orderId]);

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrBase64Meta')
            ->with($order)
            ->andReturn($qrCodeBase64);

        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getBase64Image')
            ->once()
            ->with('base64_string_2'); // array_pop returns last element

        $this->gateway->generatePixImage();

        $this->assertTrue(true);
    }

    /**
     * Test generatePixImage with string qrCodeBase64
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneratePixImageWithStringQrCodeBase64(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');
        $qrCodeBase64 = 'base64_string';

        $this->mockFormSanitizedGetData(['id' => $orderId]);

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrBase64Meta')
            ->with($order)
            ->andReturn($qrCodeBase64);

        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getBase64Image')
            ->once()
            ->with($qrCodeBase64);

        $this->gateway->generatePixImage();

        $this->assertTrue(true);
    }

    /**
     * Test renderOrderReceivedTemplate when pixOn is false
     *
     * @return void
     */
    public function testRenderOrderReceivedTemplatePixOnFalse(): void
    {
        $order = Mockery::mock('WC_Order');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixOnMeta')
            ->with($order)
            ->andReturn([false]);

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->never();

        $this->gateway->renderOrderReceivedTemplate($order);

        $this->assertTrue(true);
    }

    /**
     * Test renderOrderReceivedTemplate when order status is not pending
     *
     * @return void
     */
    public function testRenderOrderReceivedTemplateStatusNotPending(): void
    {
        $order = Mockery::mock('WC_Order');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixOnMeta')
            ->with($order)
            ->andReturn([true]);

        $order->shouldReceive('get_status')
            ->andReturn('processing');

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->never();

        $this->gateway->renderOrderReceivedTemplate($order);

        $this->assertTrue(true);
    }

    /**
     * Test renderOrderReceivedTemplate with valid data
     *
     * @return void
     */
    public function testRenderOrderReceivedTemplateWithValidData(): void
    {
        $order = Mockery::mock('WC_Order');
        $orderId = 123;

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixOnMeta')
            ->with($order)
            ->andReturn([true]);

        $order->shouldReceive('get_status')
            ->andReturn('pending');

        $order->shouldReceive('get_id')
            ->andReturn($orderId);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrCodeMeta')
            ->with($order)
            ->andReturn('qr_code_string');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrBase64Meta')
            ->with($order)
            ->andReturn('qr_code_base64');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixExpirationDateData')
            ->with($order)
            ->andReturn('2024-12-31');

        $this->gateway->mercadopago->hooks->options
            ->shouldReceive('get')
            ->with('siteurl')
            ->andReturn('https://example.com');

        // Note: get_loaded_extensions() is a PHP internal function and cannot be mocked
        // The code will use the actual function, which is fine for testing

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->with('public/mp-pix-image')
            ->andReturn('https://example.com/pix-image.css');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreStyle')
            ->once()
            ->with('mp_pix_image', 'https://example.com/pix-image.css');

        $this->gateway->storeTranslations = [
            'expiration_date_text' => 'Expires on'
        ];

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/order/pix-order-received-image.php', Mockery::type('array'));

        $this->gateway->renderOrderReceivedTemplate($order);

        $this->assertTrue(true);
    }

    /**
     * Test registerPixPoolingScript when order status is not pending
     *
     * @return void
     */
    public function testRegisterPixPoolingScriptStatusNotPending(): void
    {
        $order = Mockery::mock('WC_Order');

        $order->shouldReceive('get_status')
            ->andReturn('processing');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreScript')
            ->never();

        $this->gateway->registerPixPoolingScript($order);

        $this->assertTrue(true);
    }

    /**
     * Test registerPixPoolingScript when order status is pending
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRegisterPixPoolingScriptStatusPending(): void
    {
        $order = Mockery::mock('WC_Order');
        $orderId = 123;

        $order->shouldReceive('get_status')
            ->andReturn('pending');

        $order->shouldReceive('get_id')
            ->andReturn($orderId);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getJsAsset')
            ->with('checkouts/pix/mp-pix-pooling')
            ->andReturn('https://example.com/pix-pooling.js');

        Mockery::mock('alias:WC_AJAX')
            ->shouldReceive('get_endpoint')
            ->with(PixGateway::PIX_PAYMENT_STATUS_ENDPOINT)
            ->andReturn('https://example.com/ajax-endpoint');

        \WP_Mock::userFunction('wp_create_nonce')
            ->with('mp_pix_polling_nonce')
            ->andReturn('test-nonce');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreScript')
            ->once()
            ->with(
                'wc_mercadopago_pix_pooling',
                'https://example.com/pix-pooling.js',
                Mockery::type('array')
            );

        $this->gateway->registerPixPoolingScript($order);

        $this->assertTrue(true);
    }

    /**
     * Test registerApprovedPaymentStyles method
     *
     * @return void
     */
    public function testRegisterApprovedPaymentStyles(): void
    {
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->with('public/mp-pix-approved')
            ->andReturn('https://example.com/pix-approved.css');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreStyle')
            ->once()
            ->with('mp_pix_appproved', 'https://example.com/pix-approved.css');

        $this->gateway->registerApprovedPaymentStyles();

        $this->assertTrue(true);
    }

    /**
     * Test renderPixPaymentApprovedTemplate method
     *
     * @return void
     */
    public function testRenderPixPaymentApprovedTemplate(): void
    {
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->with('public/mp-pix-approved')
            ->andReturn('https://example.com/pix-approved.css');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreStyle')
            ->once()
            ->with('mp_pix_appproved', 'https://example.com/pix-approved.css');

        $this->gateway->storeTranslations = [
            'approved_template_title' => 'Payment Approved',
            'approved_template_description' => 'Your payment has been approved'
        ];

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/order/pix-payment-approved.php', Mockery::type('array'));

        $this->gateway->renderPixPaymentApprovedTemplate();

        $this->assertTrue(true);
    }

    /**
     * Test renderThankYouPage when pix_payment_approved is true
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRenderThankYouPagePaymentApproved(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        // Ensure orderMeta is initialized
        if (!$this->gateway->orderMeta) {
            $this->gateway->orderMeta = Mockery::mock(\MercadoPago\Woocommerce\Hooks\OrderMeta::class);
        }

        $this->gateway->orderMeta
            ->shouldReceive('get')
            ->with($order, 'pix_payment_approved')
            ->andReturn(true);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->with('public/mp-pix-approved')
            ->andReturn('https://example.com/pix-approved.css');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreStyle')
            ->once()
            ->with('mp_pix_appproved', 'https://example.com/pix-approved.css');

        $this->gateway->storeTranslations = [
            'approved_template_title' => 'Payment Approved',
            'approved_template_description' => 'Your payment has been approved'
        ];

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/order/pix-payment-approved.php', Mockery::type('array'));

        $this->gateway->renderThankYouPage($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test renderThankYouPage when qrCodeBase64 and qrCode are empty
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRenderThankYouPageEmptyQrCodes(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        // Ensure orderMeta is initialized
        if (!$this->gateway->orderMeta) {
            $this->gateway->orderMeta = Mockery::mock(\MercadoPago\Woocommerce\Hooks\OrderMeta::class);
        }

        $this->gateway->orderMeta
            ->shouldReceive('get')
            ->with($order, 'pix_payment_approved')
            ->andReturn(false);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTransactionAmountMeta')
            ->with($order)
            ->andReturn(100.00);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrCodeMeta')
            ->with($order)
            ->andReturn('');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrBase64Meta')
            ->with($order)
            ->andReturn('');

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->never();

        $this->gateway->renderThankYouPage($orderId);

        $this->assertTrue(true);
    }

    /**
     * Test renderThankYouPage with valid qrCodes
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRenderThankYouPageWithValidQrCodes(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        // Ensure orderMeta is initialized
        if (!$this->gateway->orderMeta) {
            $this->gateway->orderMeta = Mockery::mock(\MercadoPago\Woocommerce\Hooks\OrderMeta::class);
        }

        $this->gateway->orderMeta
            ->shouldReceive('get')
            ->with($order, 'pix_payment_approved')
            ->andReturn(false);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getTransactionAmountMeta')
            ->with($order)
            ->andReturn(100.00);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrCodeMeta')
            ->with($order)
            ->andReturn('qr_code_string');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrBase64Meta')
            ->with($order)
            ->andReturn('qr_code_base64');

        $order->shouldReceive('get_status')
            ->andReturn('pending');

        $order->shouldReceive('get_id')
            ->andReturn($orderId);

        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'currency_symbol' => 'R$'
        ]);

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->with('public/mp-pix-thankyou')
            ->andReturn('https://example.com/pix-thankyou.css');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreStyle')
            ->once()
            ->with('mp_pix_thankyou', 'https://example.com/pix-thankyou.css');

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getJsAsset')
            ->with('checkouts/pix/mp-pix-pooling')
            ->andReturn('https://example.com/pix-pooling.js');

        Mockery::mock('alias:WC_AJAX')
            ->shouldReceive('get_endpoint')
            ->with(PixGateway::PIX_PAYMENT_STATUS_ENDPOINT)
            ->andReturn('https://example.com/ajax-endpoint');

        \WP_Mock::userFunction('wp_create_nonce')
            ->with('mp_pix_polling_nonce')
            ->andReturn('test-nonce');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreScript')
            ->once()
            ->with('wc_mercadopago_pix_pooling', 'https://example.com/pix-pooling.js', Mockery::type('array'));

        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->with('checkouts/pix/pix')
            ->andReturn('https://example.com/pix.png');

        $this->gateway->shouldReceive('getCheckoutExpirationDate')
            ->andReturn('30 minutes');

        $this->gateway->storeTranslations = [
            'title_purchase_pix' => 'Purchase PIX',
            'title_how_to_pay' => 'How to Pay',
            'step_one' => 'Step 1',
            'step_two' => 'Step 2',
            'step_three' => 'Step 3',
            'step_four' => 'Step 4',
            'text_amount' => 'Amount',
            'text_scan_qr' => 'Scan QR',
            'expiration_date_text' => 'Expires on',
            'text_description_qr' => 'QR Description',
            'text_button' => 'Button Text'
        ];

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/order/pix-order-received.php', Mockery::type('array'));

        $this->gateway->renderThankYouPage($orderId);

        $this->assertTrue(true);
    }
}
