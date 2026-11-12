<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Hooks\Scripts;
use MercadoPago\Woocommerce\Tests\SmartPix\SmartPixStatusLockDatabase;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Tests\Traits\AssertArrayMap;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use MercadoPago\Woocommerce\Transactions\PixTransaction;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use Mockery;
use WP_Mock;

class PixGatewayTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use GatewayMock;
    use AssertArrayMap;
    use FormMock;
    use \MercadoPago\Woocommerce\Tests\SmartPix\SmartPixOrderDetailsFixture;

    private string $gatewayClass = PixGateway::class;

    /**
     * @var \Mockery\MockInterface|PixGateway
     */
    private $gateway;

    public function testGetCheckoutName(): void
    {
        $this->assertEquals('checkout-pix', $this->gateway->getCheckoutName());
    }

    /**
     * @dataProvider smartPixEnvironmentProvider
     */
    public function testRegisterCheckoutScriptsAddsSmartPixOnlyForMlb(
        string $siteId,
        bool $isTestMode,
        ?string $expectedEnvironment
    ): void {
        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, [
            'site_id' => $siteId,
            'currency' => $siteId === Country::SITE_ID_MLB ? 'BRL' : 'ARS',
        ]);

        $this->gateway->mercadopago->hooks->scripts->shouldIgnoreMissing();
        $this->gateway->mercadopago->helpers->url->shouldIgnoreMissing();
        $this->gateway->mercadopago->woocommerce->version = '9.0.0';
        $this->gateway->shouldAllowMockingProtectedMethods();

        WP_Mock::userFunction('get_stylesheet', ['return' => 'storefront']);

        if ($expectedEnvironment === null) {
            $this->gateway->mercadopago->hooks->scripts
                ->shouldNotReceive('registerSmartPixCheckoutAssets');
            $this->gateway->mercadopago->hooks->scripts
                ->shouldNotReceive('registerSmartPixLocalCheckoutAssets');
        } else {
            $this->gateway->mercadopago->storeConfig
                ->shouldReceive('isTestMode')
                ->never()
                ->andReturn($isTestMode);
            $this->gateway->mercadopago->helpers->url
                ->shouldReceive('getMercadoPagoSdkUrl')
                ->once()
                ->andReturn('https://sdk.mercadopago.com/js/v2');
            $this->gateway->mercadopago->hooks->scripts
                ->shouldReceive('registerCheckoutScript')
                ->once()
                ->with('wc_mercadopago_sdk', 'https://sdk.mercadopago.com/js/v2');
            $this->gateway->mercadopago->hooks->scripts
                ->shouldReceive('hasSmartPixLocalCheckoutAssets')->once()->andReturn(true);
            $this->gateway->mercadopago->hooks->scripts
                ->shouldReceive('registerSmartPixLocalCheckoutAssets')->once();
            $this->gateway->mercadopago->hooks->scripts
                ->shouldNotReceive('registerSmartPixCheckoutAssets');
        }

        $this->gateway->registerCheckoutScripts();
        $this->addToAssertionCount(1);
    }

    /** @dataProvider smartPixStoreModeProvider */
    public function testLocalSmartPixModeFallsBackToTheProductionCdnWhenTheLocalBuildIsMissing(bool $testMode): void
    {
        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, ['site_id' => Country::SITE_ID_MLB, 'currency' => 'BRL']);
        $this->gateway->mercadopago->hooks->scripts->shouldIgnoreMissing();
        $this->gateway->mercadopago->helpers->url->shouldIgnoreMissing();
        $this->gateway->mercadopago->woocommerce->version = '9.0.0';
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->never()->andReturn($testMode);
        WP_Mock::userFunction('get_stylesheet', ['return' => 'storefront']);
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('hasSmartPixLocalCheckoutAssets')->once()->andReturn(false);
        $this->gateway->mercadopago->hooks->scripts->shouldNotReceive('registerSmartPixLocalCheckoutAssets');
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerSmartPixCheckoutAssets')->once()->with(Scripts::SMART_PIX_ENV_PRODUCTION);

        $this->gateway->registerCheckoutScripts();
        $this->addToAssertionCount(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider smartPixStoreModeProvider
     */
    public function testRegistersDedicatedProductionAssetsWithoutALocalBuildRegardlessOfStoreMode(bool $testMode): void
    {
        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, ['site_id' => Country::SITE_ID_MLB, 'currency' => 'BRL']);
        $this->gateway->mercadopago->hooks->scripts->shouldIgnoreMissing();
        $this->gateway->mercadopago->helpers->url->shouldIgnoreMissing();
        $this->gateway->mercadopago->woocommerce->version = '9.0.0';
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('hasSmartPixLocalCheckoutAssets')->once()->andReturn(false);
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->never()->andReturn($testMode);
        WP_Mock::userFunction('get_stylesheet', ['return' => 'storefront']);
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerSmartPixCheckoutAssets')->once()
            ->with(Scripts::SMART_PIX_ENV_PRODUCTION);
        $this->gateway->mercadopago->hooks->scripts->shouldNotReceive('registerFastPaymentsCheckoutAssets');
        $this->gateway->mercadopago->hooks->scripts->shouldNotReceive('registerSmartPixLocalCheckoutAssets');
        $this->gateway->registerCheckoutScripts();
        $this->addToAssertionCount(1);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRegistersDedicatedHomologationAssetsOnlyThroughTheServerOverride(): void
    {
        define('MP_SMART_PIX_ENV', 'homologation');
        $countryConfigsProperty = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $countryConfigsProperty->setAccessible(true);
        $countryConfigsProperty->setValue($this->gateway, ['site_id' => Country::SITE_ID_MLB, 'currency' => 'BRL']);
        $this->gateway->mercadopago->hooks->scripts->shouldIgnoreMissing();
        $this->gateway->mercadopago->helpers->url->shouldIgnoreMissing();
        $this->gateway->mercadopago->woocommerce->version = '9.0.0';
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('hasSmartPixLocalCheckoutAssets')->once()->andReturn(false);
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->never();
        WP_Mock::userFunction('get_stylesheet', ['return' => 'storefront']);
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerSmartPixCheckoutAssets')->once()
            ->with(Scripts::SMART_PIX_ENV_HOMOLOGATION);
        $this->gateway->mercadopago->hooks->scripts->shouldNotReceive('registerFastPaymentsCheckoutAssets');
        $this->gateway->mercadopago->hooks->scripts->shouldNotReceive('registerSmartPixLocalCheckoutAssets');
        $this->gateway->registerCheckoutScripts();
        $this->addToAssertionCount(1);
    }

    /**
     * @return array<string, array{string, bool, string|null}>
     */
    public static function smartPixEnvironmentProvider(): array
    {
        return [
            'MLB test mode' => [Country::SITE_ID_MLB, true, Scripts::SMART_PIX_ENV_PRODUCTION],
            'MLB production mode' => [Country::SITE_ID_MLB, false, Scripts::SMART_PIX_ENV_PRODUCTION],
            'non-MLB store' => ['MLA', false, null],
        ];
    }

    public static function smartPixStoreModeProvider(): array
    {
        return ['test mode' => [true], 'production mode' => [false]];
    }

    public function processPaymentMock(?bool $isBlocks = null, bool $isSmartPix = false): void
    {
        $isBlocks ??= random()->boolean();

        $this->abstractGatewayProcessPaymentMock($isBlocks);

        $this->mockFormWithCustomSetup(function ($mock) use ($isSmartPix) {
            $mock->shouldReceive('sanitizedPostData')
                ->withNoArgs()
                ->andReturn(['mp-smart-pix-selected' => $isSmartPix ? '1' : '0']);
        });

        $_POST['wc-woo-mercado-pago-pix-new-payment-method'] = $isBlocks ? 1 : null;
        $this->stubOrderWithoutSmartPixAttempt();
    }

    /** A pending Pix order with no Smart Pix contract, as read by the repayment guard before conventional Pix. */
    private function stubOrderWithoutSmartPixAttempt(): void
    {
        $this->order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID)->byDefault();
        $this->order->shouldReceive('get_status')->andReturn('pending')->byDefault();
        $this->order->shouldReceive('is_paid')->andReturn(false)->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPaymentContract')->with($this->order)
            ->andReturn('')->byDefault();
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

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->endSmartPixAttempt($this->order);

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
    public function testConventionalPixWithoutMarkerStillCreatesPayment(): void
    {
        $this->processPaymentInternalMock(false);
        $this->stubOrderWithoutSmartPixAttempt();
        $this->mockFormSanitizedPostData([]);
        $this->order->shouldReceive('get_billing_email')->once()->andReturn('buyer@example.test');
        $calls = [];
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('endSmartPixAttempt')
            ->once()
            ->with($this->order)
            ->andReturnUsing(function () use (&$calls): void {
                $calls[] = 'end_smart_pix_attempt';
            });

        Mockery::mock('overload:' . PixTransaction::class)
            ->shouldReceive('createPayment')
            ->once()
            ->andReturnUsing(function () use (&$calls): array {
                $calls[] = 'create_payment';
                return [];
            });

        try {
            $this->gateway->proccessPaymentInternal($this->order);
            $this->fail('A conventional Pix response without status must fail.');
        } catch (ResponseStatusException $exception) {
            $this->assertSame(['end_smart_pix_attempt', 'create_payment'], $calls);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConventionalPixIsRefusedWithoutEndingASmartPixAttemptThatMayStillSettle(): void
    {
        $this->processPaymentInternalMock(false);
        $this->mockFormSanitizedPostData([]);
        $this->order->shouldReceive('get_billing_email')->once()->andReturn('buyer@example.test');
        $this->order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $this->order->shouldReceive('get_status')->andReturn('pending');
        $this->order->shouldReceive('is_paid')->andReturn(false);
        $this->order->shouldNotReceive('payment_complete');
        $metadata = $this->gateway->mercadopago->orderMetadata;
        $metadata->shouldReceive('getPaymentContract')->with($this->order)->andReturn(OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1);
        $metadata->shouldReceive('getPpOrderId')->with($this->order)->andReturn('PPORDSYNTHETIC001');
        $metadata->shouldNotReceive('endSmartPixAttempt', 'updatePaymentsOrderMetadata');
        Mockery::mock('overload:' . PixTransaction::class)->shouldNotReceive('createPayment');
        $this->gateway->storeTranslations = ['smart_pix_processing_message' => 'Synthetic processing message'];
        \WP_Mock::userFunction('wc_add_notice', ['times' => 1, 'args' => ['Synthetic processing message', 'notice']]);

        $this->assertSame(
            [
                'result' => 'failure',
                'message' => 'Synthetic processing message',
                'smart_pix_state' => 'processing',
                'smart_pix_message' => 'Synthetic processing message',
            ],
            $this->gateway->proccessPaymentInternal($this->order)
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSmartPixSelectionRoutesWithoutCallingConventionalPayments(): void
    {
        $this->processPaymentMock(false, true);
        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['site_id' => Country::SITE_ID_MLB]);
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', PixGateway::ID);
        $this->order->shouldReceive('get_id')->andReturn(1)->byDefault();
        $this->order->shouldReceive('get_billing_email')->once()->andReturn('buyer@example.test');
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('processSmartPix')
            ->once()
            ->with($this->order, Mockery::type('array'))
            ->andReturn([
                'result' => 'fail', 'redirect' => '', 'message' => 'smart_pix_processing_failed',
                'smart_pix_error' => 'smart_pix_processing_failed',
            ]);
        $this->gateway->mercadopago->orderMetadata->shouldNotReceive('updatePaymentsOrderMetadata', 'endSmartPixAttempt');
        $this->gateway->mercadopago->helpers->cart->shouldNotReceive('emptyCart');
        $this->order->shouldNotReceive('payment_complete');

        \WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        $this->gateway->datadog->shouldReceive('sendEvent')
            ->once()
            ->with(
                'smart_pix_order_creation', '1', 'classic', 'smart_transfer',
                Mockery::on(fn($details) => $details['event'] === 'order_creation_failed'
                    && $details['reason'] === 'unexpected_error' && $details['request_time'] >= 0)
            );

        $result = $this->gateway->process_payment(1);

        $this->assertSame('fail', $result['result']);
        $this->assertSame('', $result['redirect']);
        $this->assertSame('smart_pix_processing_failed', $result['message']);
        $this->assertSame('smart_pix_processing_failed', $result['smart_pix_error']);
        $this->assertNull($this->gateway->transaction);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInvalidPixSelectionKeepsTheUnmarkedUnavailableNotice(): void
    {
        $this->processPaymentInternalMock(false);
        $this->mockFormSanitizedPostData(['mp-smart-pix-selected' => 'unexpected']);
        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['site_id' => Country::SITE_ID_MLB]);
        $this->order->shouldReceive('get_billing_email')->once()->andReturn('buyer@example.test');
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')
            ->once()
            ->with('smart_pix_unavailable', 'error');

        $result = $this->gateway->proccessPaymentInternal($this->order);

        $this->assertSame('smart_pix_unavailable', $result['message']);
        $this->assertArrayNotHasKey('smart_pix_error', $result);
    }

    /**
     * @dataProvider invalidPixSelectionProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInvalidPixSelectionFailsWithoutCallingPayments($selection, string $siteId): void
    {
        $this->processPaymentInternalMock(false);
        $this->mockFormSanitizedPostData(['mp-smart-pix-selected' => $selection]);
        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['site_id' => $siteId]);
        $this->order->shouldReceive('get_billing_email')->once()->andReturn('buyer@example.test');

        $result = $this->gateway->proccessPaymentInternal($this->order);

        $this->assertSame('fail', $result['result']);
        $this->assertNull($this->gateway->transaction);
    }

    public static function invalidPixSelectionProvider(): array
    {
        return [
            'unknown marker' => ['unexpected', Country::SITE_ID_MLB],
            'non-string marker' => [['1'], Country::SITE_ID_MLB],
            'Smart Pix outside MLB' => ['1', Country::SITE_ID_MLA],
        ];
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

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->endSmartPixAttempt($this->order);

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
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testProcessReturnFailExecution(): void
    {
        $this->mockFormSanitizedPostData(['mp-smart-pix-selected' => '1']);

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
            ->with('woo_checkout_error', $translatedMessage, Mockery::type('string'), PixGateway::ID, ['cust_id' => 'test-cust-id', 'device' => 'unknown']);
        $this->gateway->datadog
            ->shouldNotReceive('sendEvent')
            ->with('smart_pix_order_creation', Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any());

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($translatedMessage, 'error');

        $exception = new \Exception("Provider\nexplicit\tfailure buyer@example.test token_secret");

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
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testComposingSmartPixAlwaysUsesTheRealBoundedOrdersClient(): void
    {
        define('MP_SMART_PIX_MOCK', true);
        define('MP_SMART_PIX_ORDERS_MOCK', true);
        $compose = (new \ReflectionClass(PixGateway::class))->getMethod('createSmartPixOrderClient');
        $compose->setAccessible(true);

        $this->assertInstanceOf(\MercadoPago\Woocommerce\SmartPix\Adapters\Http\RealSmartPixOrderClient::class, $compose->invoke($this->gateway));
    }

    /**
     * @dataProvider smartPixSurfaceProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenTheCheckoutSurfaceWhenReportingCreationThenTheSurfaceDetailMatches(bool $isOrderPay, array $post, string $expected): void
    {
        \WP_Mock::userFunction('is_checkout_pay_page', ['return' => $isOrderPay]);
        $_POST = $post;
        $surface = (new \ReflectionClass(PixGateway::class))->getMethod('smartPixSurface');
        $surface->setAccessible(true);

        $this->assertSame($expected, $surface->invoke($this->gateway));
    }

    public static function smartPixSurfaceProvider(): array
    {
        return [
            'order pay' => [true, ['wc-woo-mercado-pago-pix-new-payment-method' => '1'], 'order_pay'],
            'blocks'    => [false, ['wc-woo-mercado-pago-pix-new-payment-method' => '1'], 'blocks'],
            'classic'   => [false, [], 'classic'],
        ];
    }

    public function testGivenThePendingPagePollIntervalWhenComparedToTheStatusRateLimitThenEveryPollCanQueryOrders(): void
    {
        $interval = (new \ReflectionClassConstant(PixGateway::class, 'SMART_PIX_STATUS_POLLING_INTERVAL_MS'))->getValue();

        $this->assertLessThan($interval, \MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixStatusEndpoint::RATE_LIMIT_SECONDS * 1000);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenTheProviderReturnedARequestIdWhenReportingCreationThenTheMetricIncludesIt(): void
    {
        $this->processPaymentMock(false, true);
        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['site_id' => Country::SITE_ID_MLB]);
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', PixGateway::ID);
        $this->order->shouldReceive('get_id')->andReturn(1)->byDefault();
        $this->order->shouldReceive('get_billing_email')->once()->andReturn('buyer@example.test');
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('processSmartPix')->once()->andReturnUsing(function (): array {
            $this->setNotAccessibleProperty($this->gateway, 'smartPixCreationRequestId', 'request-12345678');
            return ['result' => 'failure', 'redirect' => ''];
        });
        \WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        $this->gateway->datadog->shouldReceive('sendEvent')->once()->with(
            'smart_pix_order_creation', '1', 'classic', 'smart_transfer',
            Mockery::on(fn($details) => $details['reason'] === 'unexpected_error'
                && $details['request_id'] === 'request-12345678' && $details['request_time'] >= 0)
        );

        $this->assertSame('failure', $this->gateway->process_payment(1)['result']);
    }

    /**
     * @dataProvider creationCategoryProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenAProcessorCategoryWhenSmartPixCreationFailsThenTheMetricReportsIt(string $category, string $expected): void
    {
        $this->processPaymentMock(false, true);
        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['site_id' => Country::SITE_ID_MLB]);
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', PixGateway::ID);
        $this->order->shouldReceive('get_id')->andReturn(1)->byDefault();
        $this->order->shouldReceive('get_billing_email')->once()->andReturn('buyer@example.test');
        $failure = (new \ReflectionClass(PixGateway::class))->getMethod('processSmartPixFailure');
        $failure->setAccessible(true);
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('processSmartPix')->once()->andReturnUsing(
            fn() => $failure->invoke($this->gateway, 'smart_pix_processing_failed', $category)
        );
        \WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->once();
        $this->gateway->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('smart_pix_order_creation', '1', 'classic', 'smart_transfer',
                Mockery::on(fn($details) => $details['reason'] === $expected && $details['event'] === 'order_creation_failed'));

        $this->assertSame('fail', $this->gateway->process_payment(1)['result']);
    }

    /**
     * @dataProvider unlinkedCreationFailureProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAnUnlinkedDispatchedAttemptStaysOnCheckoutWithoutAnotherCreate(string $failure): void
    {
        $this->mockFormSanitizedPostData([]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        $creationClass = \MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::class;
        $hash = str_repeat('a', 64);
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_order_key')->andReturn('SERVER_ORDER_KEY');
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('is_paid')->andReturn(false);
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn('4.00');
        $order->shouldReceive('save');
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_meta')->andReturnUsing(static function (string $key) use ($hash, $creationClass, $failure) {
            return [$creationClass::META_KEY => $hash, $creationClass::FAILURE_META_KEY => $failure === '' ? '' : $hash . ':' . $failure][$key] ?? '';
        });
        $this->stubOrderDetails($order);
        $order->shouldNotReceive('payment_complete');
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getCollectorId')->andReturn('123456');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPaymentContract')->andReturn('orders_smart_pix_v1');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getIdempotencyKey')->andReturn('STORED_KEY');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPpOrderId')->andReturn('');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('hasConventionalPixPayment')->andReturn(false);
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->once();
        $client = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient::class);
        $client->shouldNotReceive('create');
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('createSmartPixOrderClient')->once()->andReturn($client);
        $this->gateway->shouldNotReceive('get_return_url');
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('wp_generate_uuid4', ['return' => 'SERVER_CANDIDATE']);
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->options = 'wp_options';
        $process = (new \ReflectionClass(PixGateway::class))->getMethod('processSmartPix');
        $process->setAccessible(true);
        try {
            $response = $process->invoke($this->gateway, $order, [
                'mp-smart-pix-token' => 'SYNTHETIC_PROOF',
                'mp-smart-pix-open-finance-account-id' => '00000000-0000-4000-8000-000000000000',
            ]);
            $this->assertSame('fail', $response['result']);
            $this->assertSame('', $response['redirect']);
            $this->assertSame('smart_pix_processing_failed', $response['smart_pix_error']);
        } finally {
            unset($GLOBALS['wpdb']);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAPhpErrorBeforeDispatchStaysOnCheckoutWithTheGenericError(): void
    {
        $this->mockFormSanitizedPostData([]);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_order_key')->andReturn('SERVER_ORDER_KEY');
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('is_paid')->andReturn(false);
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn('4.00');
        $order->shouldReceive('save');
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_meta')->andReturn('');
        $this->stubOrderDetails($order);
        $order->shouldNotReceive('payment_complete');
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getCollectorId')->andReturn('123456');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPaymentContract')->andReturn('');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getIdempotencyKey')->andReturn('');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPpOrderId')->andReturn('');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('hasConventionalPixPayment')->andReturn(false);
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->once();
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock->shouldReceive('error')->once()
            ->with('Smart Pix creation failed before dispatch: TypeError', PixGateway::LOG_SOURCE);
        $logsFileMock->shouldReceive('error', 'warning', 'info')->withAnyArgs();
        $this->gateway->mercadopago->logs->file = $logsFileMock;
        $client = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient::class);
        $client->shouldNotReceive('create');
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('createSmartPixOrderClient')->once()->andReturn($client);
        $this->gateway->shouldNotReceive('get_return_url');
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('wp_generate_uuid4', ['return' => 'SERVER_CANDIDATE']);
        // A missing database handle makes the creation store constructor throw a TypeError.
        $GLOBALS['wpdb'] = null;
        $process = (new \ReflectionClass(PixGateway::class))->getMethod('processSmartPix');
        $process->setAccessible(true);
        try {
            $response = $process->invoke($this->gateway, $order, [
                'mp-smart-pix-token' => 'SYNTHETIC_PROOF',
                'mp-smart-pix-open-finance-account-id' => '00000000-0000-4000-8000-000000000000',
            ]);
            $this->assertSame('fail', $response['result']);
            $this->assertSame('smart_pix_processing_failed', $response['smart_pix_error']);
        } finally {
            unset($GLOBALS['wpdb']);
        }
    }

    /**
     * @dataProvider smartPixSurfaceProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenAnExistingPixWhenSmartPixIsSubmittedThenTheNativeNoticeExplainsThePendingPayment(
        bool $isOrderPay,
        array $post,
        string $surface
    ): void {
        $this->mockFormSanitizedPostData([]);
        $_POST = $post;
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => $isOrderPay]);
        WP_Mock::userFunction('wp_unslash')->andReturnArg(0);
        WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        WP_Mock::userFunction('wp_generate_uuid4', ['return' => 'SERVER_CANDIDATE']);
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_order_key')->andReturn('SERVER_ORDER_KEY');
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('is_paid')->andReturn(false);
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_meta')->andReturn('');
        $order->shouldReceive('save')->once();
        $order->shouldNotReceive('payment_complete', 'update_status');
        $metadata = $this->gateway->mercadopago->orderMetadata;
        $metadata->shouldReceive('hasConventionalPixPayment')->once()->with($order)->andReturn(true);
        $metadata->shouldNotReceive('getPaymentContract', 'linkSmartPixOrder', 'endSmartPixAttempt');
        $message = 'Já existe um pagamento pendente para este pedido. Para trocar a forma de pagamento, entre em contato com a loja.';
        $this->gateway->mercadopago->helpers->errorMessages->shouldReceive('findErrorMessage')
            ->once()->with('smart_pix_existing_pix')->andReturn($message);
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->once()->with(
            $message, 'error', ['mp-smart-pix-error' => 'smart_pix_processing_failed']
        );
        $client = Mockery::mock(\MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient::class);
        $client->shouldNotReceive('create');
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('createSmartPixOrderClient')->once()->andReturn($client);
        $this->gateway->shouldNotReceive('get_return_url');
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->options = 'wp_options';
        $process = (new \ReflectionClass(PixGateway::class))->getMethod('processSmartPix');
        $process->setAccessible(true);

        try {
            $response = $process->invoke($this->gateway, $order, ['key' => 'SERVER_ORDER_KEY']);

            $this->assertSame('fail', $response['result']);
            $this->assertSame('', $response['redirect']);
            $this->assertSame($message, $response['message']);
            $this->assertSame('smart_pix_processing_failed', $response['smart_pix_error']);
            $this->assertNull($this->gateway->transaction);
            $result = (new \ReflectionProperty(PixGateway::class, 'smartPixCreationResult'));
            $result->setAccessible(true);
            $this->assertSame('validation', $result->getValue($this->gateway), $surface);
        } finally {
            unset($GLOBALS['wpdb']);
        }
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAPhpErrorAfterDispatchStaysOnCheckoutWithTheGenericErrorAndIsLoggedAsAfterDispatch(): void
    {
        $meta = [];
        $response = $this->smartPixCreationResponse('processing', 'processing');
        // The Order was created, but linking its answer raises a PHP Error the processor does not catch.
        [$order] = $this->smartPixCreationContext($response, $meta, 'blocks', false, new \TypeError('SYNTHETIC_LINK_ERROR'));
        $this->mockFormSanitizedPostData([]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('WC', ['times' => 0]);
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->once();
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock->shouldReceive('error')->once()
            ->with('Smart Pix creation failed after dispatch: TypeError', PixGateway::LOG_SOURCE);
        $logsFileMock->shouldReceive('error', 'warning', 'info')->withAnyArgs();
        $this->gateway->mercadopago->logs->file = $logsFileMock;
        $this->gateway->mercadopago->orderStatus->shouldNotReceive('addSmartPixPendingNote', 'addSmartPixStatusNotes');
        $this->gateway->shouldNotReceive('get_return_url');
        $_POST['wc-woo-mercado-pago-pix-new-payment-method'] = '1';

        try {
            $result = $this->invokeSmartPixCreation($order, $this->smartPixCheckout());
        } finally {
            unset($_POST['wc-woo-mercado-pago-pix-new-payment-method']);
        }

        $this->assertSame('fail', $result['result']);
        $this->assertSame('smart_pix_processing_failed', $result['smart_pix_error']);
        // The admission stays recorded: the answered Order is never created a second time.
        $this->assertSame(hash('sha256', 'STORED_KEY'), $meta[\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::META_KEY]);
        $this->assertArrayNotHasKey('_mp_pp_order_id', $meta);
    }

    /**
     * @dataProvider failingCreationDiagnosticsProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenAPendingClassicCreationWhenTheBuyerSubmitsThenTheOrderIsLinkedAndTheBuyerWaitsOnTheStatusPage(bool $diagnosticsFail): void
    {
        $meta = [];
        $response = $this->smartPixCreationResponse('processing', 'processing');
        $response['observed'] = ['payment_status_detail' => 'waiting_transfer'];
        [$order, $wpdb] = $this->smartPixCreationContext($response, $meta, 'classic', $diagnosticsFail);
        $wpdb->shouldNotReceive('query')->with(Mockery::pattern('/ON DUPLICATE KEY/'));
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        $session = Mockery::mock('WC_Session_Handler');
        $session->shouldReceive('set')->with(\MercadoPago\Woocommerce\SmartPix\Adapters\WooCommerce\SmartPixCheckoutRecovery::SESSION_KEY, 321)->once();
        $session->shouldReceive('set')->with('order_awaiting_payment', 321)->once();
        $session->shouldReceive('save_data')->once();
        WP_Mock::userFunction('WC', ['return' => (object) ['session' => $session]]);
        $note = $this->gateway->mercadopago->orderStatus->shouldReceive('addSmartPixPendingNote')->with($order)->once();
        if ($diagnosticsFail) {
            $note->andThrow(new \RuntimeException('SYNTHETIC_NOTE_FAILURE'));
            $this->gateway->mercadopago->logs->file = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
            $this->gateway->mercadopago->logs->file->shouldReceive('warning')->once()
                ->with('Smart Transfer pending note could not be recorded.', PixGateway::LOG_SOURCE);
        }
        $this->gateway->shouldReceive('get_return_url')->with($order)->once()->andReturn('https://store.example.test/order-received/321');

        $result = $this->invokeSmartPixCreation($order, $this->smartPixCheckout());

        $this->assertSame(['result' => 'success', 'redirect' => 'https://store.example.test/order-received/321'], $result);
        $this->assertSame('ORDER_SYNTHETIC_001', $meta['_mp_pp_order_id']);
        $this->assertSame('sdk-instance-001', $meta['_mp_smart_pix_sdk_instance_id']);
        $this->assertSame(hash('sha256', 'STORED_KEY'), $meta[\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::META_KEY]);
        $this->assertSame('yes', $meta[\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::RESPONSE_META_KEY]);
        $details = $this->getNotAccessibleProperty($this->gateway, 'smartPixMetricDetails');
        $this->assertSame('pending', $details['payment_status']);
        $this->assertSame('waiting_transfer', $details['payment_status_detail']);
        $this->assertSame('sdk-instance-001', $details['sdk_instance_id']);
        $this->assertSame($diagnosticsFail ? null : 'request-12345678', $details['request_id'] ?? null);
        $this->assertSame($diagnosticsFail ? null : 'request-12345678', $this->getNotAccessibleProperty($this->gateway, 'smartPixCreationRequestId'));
        $this->assertSame('pending', $this->getNotAccessibleProperty($this->gateway, 'smartPixCreationResult'));
    }

    public static function failingCreationDiagnosticsProvider(): array
    {
        return ['diagnostics available' => [false], 'diagnostics and pending note fail' => [true]];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenAnAccreditedOrderPayCreationWithTheKeyOnlyInTheUrlThenTheSharedApplicatorOwnsTheApproval(): void
    {
        $meta = [];
        $response = $this->smartPixCreationResponse('terminal_success', 'approved');
        $response['observed'] = ['payment_status_detail' => 'accredited'];
        [$order, $wpdb] = $this->smartPixCreationContext($response, $meta, 'order_pay');
        // No effect ownership is available, so the sync response cannot complete the payment itself.
        $wpdb->shouldReceive('query')->with(Mockery::pattern('/ON DUPLICATE KEY/'))->once()->andReturn(false);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => true]);
        WP_Mock::userFunction('wp_unslash', ['return_arg' => 0]);
        WP_Mock::userFunction('WC', ['times' => 0]);
        $_GET['key'] = 'SERVER_ORDER_KEY';
        $this->gateway->mercadopago->orderStatus->shouldNotReceive('addSmartPixPendingNote', 'addSmartPixStatusNotes');
        $this->gateway->shouldReceive('get_return_url')->with($order)->once()->andReturn('https://store.example.test/order-received/321');

        try {
            $result = $this->invokeSmartPixCreation($order, $this->smartPixCheckout());
        } finally {
            unset($_GET['key']);
        }

        $this->assertSame(['result' => 'success', 'redirect' => 'https://store.example.test/order-received/321'], $result);
        $this->assertSame('ORDER_SYNTHETIC_001', $meta['_mp_pp_order_id']);
        $details = $this->getNotAccessibleProperty($this->gateway, 'smartPixMetricDetails');
        $this->assertSame('approved', $details['payment_status']);
        $this->assertSame('accredited', $details['payment_status_detail']);
        $this->assertSame('pending', $this->getNotAccessibleProperty($this->gateway, 'smartPixCreationResult'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenARejectedBlocksCreationWhenAppliedThenTheBuyerSeesTheRejectionWithoutAnyRedirect(): void
    {
        $meta = [];
        $response = $this->smartPixCreationResponse('terminal_failure', 'rejected');
        $response['outcome'] = 'failure';
        $response['error'] = ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_INSUFFICIENT_AMOUNT', 'retryable' => false];
        [$order, $wpdb] = $this->smartPixCreationContext($response, $meta, 'blocks');
        $wpdb->shouldReceive('query')->with(Mockery::pattern('/ON DUPLICATE KEY/'))->once()->andReturn(false);
        $this->mockFormSanitizedPostData([]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('WC', ['times' => 0]);
        $_POST['wc-woo-mercado-pago-pix-new-payment-method'] = '1';
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->once();
        $this->gateway->mercadopago->orderStatus->shouldNotReceive('addSmartPixPendingNote', 'addSmartPixStatusNotes');
        $this->gateway->shouldNotReceive('get_return_url');

        try {
            $result = $this->invokeSmartPixCreation($order, $this->smartPixCheckout());
        } finally {
            unset($_POST['wc-woo-mercado-pago-pix-new-payment-method']);
        }

        $this->assertSame('fail', $result['result']);
        $this->assertSame('', $result['redirect']);
        $this->assertSame('smart_pix_insufficient_balance', $result['smart_pix_error']);
        $this->assertSame('ORDER_SYNTHETIC_001', $meta['_mp_pp_order_id']);
        $this->assertSame('insufficient_balance', $this->getNotAccessibleProperty($this->gateway, 'smartPixCreationResult'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenARejectedCreationWithAnObservedStatusDetailThenTheMetricCarriesItAsTheRejectionReason(): void
    {
        $meta = [];
        $response = $this->smartPixCreationResponse('terminal_failure', 'rejected');
        $response['outcome'] = 'failure';
        $response['error'] = ['category' => 'rejected', 'code' => 'SMART_PIX_REJECTED_INSUFFICIENT_AMOUNT', 'retryable' => false];
        $response['observed'] = ['payment_status_detail' => 'insufficient_amount'];
        [$order, $wpdb] = $this->smartPixCreationContext($response, $meta, 'blocks');
        $wpdb->shouldReceive('query')->with(Mockery::pattern('/ON DUPLICATE KEY/'))->once()->andReturn(false);
        $this->mockFormSanitizedPostData([]);
        WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        WP_Mock::userFunction('WC', ['times' => 0]);
        $_POST['wc-woo-mercado-pago-pix-new-payment-method'] = '1';
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->once();
        $this->gateway->mercadopago->orderStatus->shouldNotReceive('addSmartPixPendingNote', 'addSmartPixStatusNotes');
        $this->gateway->shouldNotReceive('get_return_url');

        try {
            $result = $this->invokeSmartPixCreation($order, $this->smartPixCheckout());
        } finally {
            unset($_POST['wc-woo-mercado-pago-pix-new-payment-method']);
        }

        $this->assertSame('fail', $result['result']);
        $details = $this->getNotAccessibleProperty($this->gateway, 'smartPixMetricDetails');
        $this->assertSame('insufficient_amount', $details['payment_status_detail']);
        $this->assertSame('insufficient_amount', $details['reason']);
        $this->assertArrayNotHasKey('payment_status', $details);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenNoWooCommerceOrderWhenProcessingSmartPixThenItFailsAsValidationWithoutAnOrdersClient(): void
    {
        $this->mockFormSanitizedPostData([]);
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldNotReceive('createSmartPixOrderClient');
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')->once();
        $process = (new \ReflectionClass(PixGateway::class))->getMethod('processSmartPix');
        $process->setAccessible(true);

        $result = $process->invoke($this->gateway, null, $this->smartPixCheckout());

        $this->assertSame('fail', $result['result']);
        $this->assertSame('smart_pix_processing_failed', $result['smart_pix_error']);
        $this->assertSame('validation', $this->getNotAccessibleProperty($this->gateway, 'smartPixCreationResult'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenTheCheckoutSdkInstanceWhenReportingCreationThenTheMetricIdentifiesIt(): void
    {
        $this->abstractGatewayProcessPaymentMock(false);
        $this->mockFormSanitizedPostData([
            'mp-smart-pix-selected' => '1',
            \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext::SDK_FIELD => 'sdk-instance-001',
        ]);
        $this->stubOrderWithoutSmartPixAttempt();
        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['site_id' => Country::SITE_ID_MLB]);
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', PixGateway::ID);
        $this->order->shouldReceive('get_id')->andReturn(1)->byDefault();
        $this->order->shouldReceive('get_billing_email')->once()->andReturn('buyer@example.test');
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('processSmartPix')->once()->andReturn(['result' => 'failure', 'redirect' => '']);
        \WP_Mock::userFunction('is_checkout_pay_page', ['return' => false]);
        $this->gateway->datadog->shouldReceive('sendEvent')->once()->with(
            'smart_pix_order_creation', '1', 'classic', 'smart_transfer',
            Mockery::on(fn($details) => $details['sdk_instance_id'] === 'sdk-instance-001'
                && $details['reason'] === 'unexpected_error' && !isset($details['missing_fields']))
        );

        $this->assertSame('failure', $this->gateway->process_payment(1)['result']);
    }

    /** @return mixed */
    private function getNotAccessibleProperty($object, string $property)
    {
        $reflection = new \ReflectionProperty(PixGateway::class, $property);
        $reflection->setAccessible(true);
        return $reflection->getValue($object);
    }

    private function invokeSmartPixCreation($order, array $checkout): array
    {
        $process = (new \ReflectionClass(PixGateway::class))->getMethod('processSmartPix');
        $process->setAccessible(true);
        try {
            return $process->invoke($this->gateway, $order, $checkout);
        } finally {
            unset($GLOBALS['wpdb']);
        }
    }

    /** @return array<string, string> */
    private function smartPixCheckout(): array
    {
        return [
            'mp-smart-pix-token' => 'SYNTHETIC_PROOF',
            'mp-smart-pix-open-finance-account-id' => '00000000-0000-4000-8000-000000000000',
            \MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext::SDK_FIELD => 'sdk-instance-001',
        ];
    }

    /** @return array<string, mixed> A normalized Orders create result for the synthetic 4.00 BRL order. */
    private function smartPixCreationResponse(string $state, string $paymentState): array
    {
        return [
            'outcome' => 'success', 'state' => $state, 'order_reference' => 'ORDER_SYNTHETIC_001',
            'eligibility' => null, 'authorization' => null,
            'payments' => [['reference' => 'PAYMENT_SYNTHETIC_001', 'state' => $paymentState, 'amount' => ['value' => '4.00', 'currency' => 'BRL']]],
            'error' => null,
        ];
    }

    /**
     * A payable synthetic order with a stored Smart Pix attempt whose metadata lives in $meta, a
     * database that admits this creation once, and a dispatch client returning $response.
     *
     * @param array<string, mixed> $response
     * @param array<string, mixed> $meta
     * @return array{\Mockery\MockInterface, \Mockery\MockInterface} The order and the shared database.
     */
    private function smartPixCreationContext(
        array $response,
        array &$meta,
        string $surface,
        bool $diagnosticsFail = false,
        ?\Throwable $linkFailure = null
    ): array {
        WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        WP_Mock::userFunction('wp_generate_uuid4', ['return' => 'SERVER_CANDIDATE']);
        WP_Mock::userFunction('wp_cache_delete', ['return' => true]);
        $meta += ['_billing_cpf' => self::$syntheticMaskedCpf, '_mp_idempotency_key' => 'STORED_KEY'];
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldReceive('get_order_key')->andReturn('SERVER_ORDER_KEY');
        $order->shouldReceive('get_payment_method')->andReturn(PixGateway::ID);
        $order->shouldReceive('is_paid')->andReturn(false);
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn('4.00');
        $order->shouldReceive('save');
        $order->shouldReceive('save_meta_data');
        $order->shouldReceive('read_meta_data')->with(true);
        $order->shouldReceive('get_meta')->andReturnUsing(static function (string $key) use (&$meta) {
            return $meta[$key] ?? '';
        });
        $order->shouldReceive('update_meta_data')->andReturnUsing(static function (string $key, $value) use (&$meta): void {
            $meta[$key] = $value;
        });
        $this->stubOrderDetails($order);
        $order->shouldNotReceive('payment_complete', 'update_status', 'add_order_note');
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $metadata = $this->gateway->mercadopago->orderMetadata;
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn('orders_smart_pix_v1');
        $metadata->shouldReceive('getIdempotencyKey')->with($order)->andReturn('STORED_KEY');
        $metadata->shouldReceive('hasConventionalPixPayment')->with($order)->andReturn(false);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturnUsing(static function () use (&$meta): string {
            return $meta['_mp_pp_order_id'] ?? '';
        });
        $metadata->shouldReceive('linkSmartPixOrder')->with($order, 'ORDER_SYNTHETIC_001', Mockery::type('int'))->once()
            ->andReturnUsing(static function ($order, string $reference) use (&$meta, $linkFailure): void {
                if ($linkFailure !== null) {
                    throw $linkFailure;
                }
                $meta['_mp_pp_order_id'] = $reference;
            });
        $wpdb = Mockery::mock('wpdb');
        $wpdb->options = 'wp_options';
        $wpdb->last_error = '';
        $wpdb->shouldReceive('suppress_errors')->andReturn(false);
        $wpdb->shouldReceive('prepare')->andReturnUsing(static fn(string $sql): string => $sql);
        $wpdb->shouldReceive('query')->with(Mockery::pattern('/^INSERT IGNORE/'))->once()->andReturn(1);
        $wpdb->shouldReceive('get_var')->with(Mockery::pattern('/^SELECT option_value/'))->andReturn(hash('sha256', 'STORED_KEY'));
        $GLOBALS['wpdb'] = $wpdb;
        $client = Mockery::mock(implode(', ', [
            \MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationDispatchClient::class,
            \MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixRequestObservation::class,
            \MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixCreationRequestId::class,
        ]));
        $client->shouldReceive('create')->once()->andReturnUsing(
            static function (array $request, \MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment $payment, callable $onDispatch) use ($response, $surface): array {
                if ($request['surface'] !== $surface || $payment->getIdempotencyKey() !== 'STORED_KEY') {
                    throw new \LogicException('Unexpected Smart Pix creation request.');
                }
                $onDispatch();
                return $response;
            }
        );
        if ($diagnosticsFail) {
            $client->shouldReceive('requestDetails')->once()->andThrow(new \RuntimeException('SYNTHETIC_DIAGNOSTICS_FAILURE'));
            $client->shouldReceive('creationRequestId')->once()->andThrow(new \RuntimeException('SYNTHETIC_DIAGNOSTICS_FAILURE'));
        } else {
            $client->shouldReceive('requestDetails')->once()->andReturn(['request_id' => 'request-12345678']);
            $client->shouldReceive('creationRequestId')->once()->andReturn('request-12345678');
        }
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('createSmartPixOrderClient')->once()->andReturn($client);
        return [$order, $wpdb];
    }

    public static function unlinkedCreationFailureProvider(): array
    {
        return ['known validation' => ['validation'], 'known authentication' => ['authentication'],
            'no confirmed result' => ['']];
    }

    public function testTheSmartPixAccountProofIsReadWithoutTextSanitization(): void
    {
        $read = (new \ReflectionClass(PixGateway::class))->getMethod('readSmartPixToken');
        $read->setAccessible(true);
        $proof = 'proof%2Fpart  <b>tail</b>+=';
        $_POST['mp-smart-pix-token'] = addslashes($proof);
        WP_Mock::userFunction('wp_unslash', ['return' => static fn($value) => stripslashes($value)]);
        try {
            $this->assertSame($proof, $read->invoke($this->gateway, ['mp-smart-pix-token' => 'sanitized']));
            unset($_POST['mp-smart-pix-token']);
            $this->assertSame('from-checkout', $read->invoke($this->gateway, ['mp-smart-pix-token' => 'from-checkout']));
        } finally {
            unset($_POST['mp-smart-pix-token']);
        }
    }

    /** @dataProvider smartPixFailureResultProvider */
    public function testADeclineKeepsItsBuyerFacingClassAsTheCreationResult(string $buyerError, string $category, string $expected): void
    {
        $result = (new \ReflectionClass(PixGateway::class))->getMethod('smartPixFailureResult');
        $result->setAccessible(true);

        $this->assertSame($expected, $result->invoke(null, $buyerError, $category));
    }

    public static function smartPixFailureResultProvider(): array
    {
        return [
            'insufficient balance' => ['smart_pix_insufficient_balance', 'rejected', 'insufficient_balance'],
            'daily limit' => ['smart_pix_limit_exceeded', 'rejected', 'limit_exceeded'],
            'other decline' => ['smart_pix_rejected', 'rejected', 'rejected'],
            'processing failure keeps its category' => ['smart_pix_processing_failed', 'unavailable', 'unavailable'],
        ];
    }

    public static function creationCategoryProvider(): array
    {
        return [
            'orders 5xx'        => ['unavailable', 'unavailable'],
            'rate limited'      => ['rate_limit', 'rate_limit'],
            'outside closed set' => ['provider_said_something', 'unknown'],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenTheStatusEndpointWhenItReportsAMetricThenTheGatewaySendsItAsASmartPixEvent(): void
    {
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->options = 'wp_options';
        $compose = (new \ReflectionClass(PixGateway::class))->getMethod('createSmartPixStatusEndpoint');
        $compose->setAccessible(true);
        $endpoint = $compose->invoke($this->gateway);
        $reportMetric = new \ReflectionProperty($endpoint, 'reportMetric');
        $reportMetric->setAccessible(true);
        $this->gateway->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('smart_pix_polling_expired', '1', '', 'smart_transfer', [
                'payment_method' => 'smart_transfer', 'event' => 'polling_expired',
                'reason' => 'max_duration', 'missing_fields' => 'sdk_instance_id']);

        ($reportMetric->getValue($endpoint))('smart_pix_polling_expired', 'expired', 'max_duration');

        $this->assertTrue(true);
    }

    public function testGivenTelemetryFailsWhenSendingASmartPixMetricThenTheFailureIsSwallowed(): void
    {
        $send = (new \ReflectionClass(PixGateway::class))->getMethod('sendSmartPixMetric');
        $send->setAccessible(true);
        $this->gateway->datadog->shouldReceive('sendEvent')->once()->andThrow(new \RuntimeException('monitor down'));

        $send->invoke($this->gateway, 'smart_pix_polling_expired', 'expired', 'max_duration');

        $this->assertTrue(true);
    }

    public function testGivenAnyFailureWhenMappedToTheCreationResultThenOnlyTheClosedResultSetIsUsed(): void
    {
        $resultFor = (new \ReflectionClass(PixGateway::class))->getMethod('smartPixCreationResultFor');
        $resultFor->setAccessible(true);
        $privateMessage = 'buyer@example.test token_secret bank_account_123';
        $cases = [
            'validation'           => new InvalidCheckoutDataException($privateMessage),
            'insufficient_balance' => new InvalidCheckoutDataException('', 0, null, ['smart_pix_error' => 'smart_pix_insufficient_balance']),
            'rejected'             => new InvalidCheckoutDataException('', 0, null, ['smart_pix_error' => 'smart_pix_rejected']),
            'limit_exceeded'       => new InvalidCheckoutDataException('', 0, null, ['smart_pix_error' => 'smart_pix_limit_exceeded']),
            'unknown'              => new InvalidCheckoutDataException('', 0, null, ['smart_pix_error' => 'smart_pix_processing_failed']),
        ];

        foreach ($cases as $expected => $failure) {
            $this->assertSame($expected, $resultFor->invoke($this->gateway, $failure));
        }
        $this->assertSame('validation', $resultFor->invoke(
            $this->gateway,
            new InvalidCheckoutDataException('', 0, null, ['smart_pix_error' => $privateMessage])
        ));
        foreach ([new ResponseStatusException($privateMessage), new RejectedPaymentException($privateMessage), new \Exception($privateMessage)] as $failure) {
            $this->assertSame('unexpected_error', $resultFor->invoke($this->gateway, $failure));
        }
    }

    /**
     * @dataProvider smartPixFailureLogLevelProvider
     */
    public function testGivenASmartPixFailureWhenLoggedThenOnlyRejectionsAreWarningsWithoutTrace(
        \Exception $failure,
        string $expectedLevel
    ): void {
        $log = (new \ReflectionClass(PixGateway::class))->getMethod('logCheckoutFailure');
        $log->setAccessible(true);
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $otherLevel = $expectedLevel === 'warning' ? 'error' : 'warning';
        $logsFileMock->shouldNotReceive($otherLevel);
        $logsFileMock->shouldReceive($expectedLevel)
            ->once()
            ->with(
                Mockery::on(fn($message) => $expectedLevel === 'error'
                    ? $message === 'Checkout processing failed'
                    : strpos($message, 'Smart Pix rejection: ') === 0),
                PixGateway::LOG_SOURCE,
                Mockery::on(fn($context) => $expectedLevel === 'error'
                    ? ($context['exception_type'] ?? null) === (new \ReflectionClass($failure))->getShortName()
                    : $context === [])
            );
        $this->gateway->mercadopago->logs->file = $logsFileMock;

        $log->invoke($this->gateway, $failure, PixGateway::LOG_SOURCE, []);
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
    }

    public function smartPixFailureLogLevelProvider(): array
    {
        $buyerError = fn(string $code) => new InvalidCheckoutDataException($code, 0, null, ['smart_pix_error' => $code]);

        return [
            'rejected' => [$buyerError('smart_pix_rejected'), 'warning'],
            'insufficient balance' => [$buyerError('smart_pix_insufficient_balance'), 'warning'],
            'limit exceeded' => [$buyerError('smart_pix_limit_exceeded'), 'warning'],
            'processing failed' => [$buyerError('smart_pix_processing_failed'), 'error'],
            'invalid checkout data' => [new InvalidCheckoutDataException('invalid_pix_flow'), 'error'],
            'unexpected' => [new \Exception('boom'), 'error'],
        ];
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
            ->with('woo_checkout_error', 'Invalid email message', Mockery::type('string'), PixGateway::ID, ['cust_id' => 'test-cust-id', 'device' => 'unknown']);

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

        $smartPixImages = [
            'icons/icon-mp' => 'https://example.com/assets/images/icons/icon-mp.png',
            'icons/pix-icon' => 'https://example.com/assets/images/icons/pix-icon.png',
            'icons/smart-pix-banco-do-brasil.svg' => 'https://example.com/assets/images/icons/smart-pix-banco-do-brasil.svg',
            'icons/smart-pix-nubank.svg' => 'https://example.com/assets/images/icons/smart-pix-nubank.svg',
        ];

        foreach ($smartPixImages as $asset => $url) {
            $this->gateway->mercadopago->helpers->url
                ->shouldReceive('getImageAsset')
                ->with($asset)
                ->andReturn($url);
        }

        $this->gateway->storeTranslations = [
            'test_mode_title' => 'Test Mode',
            'test_mode_description' => 'Test Description',
            'pix_template_title' => 'PIX Title',
            'pix_template_subtitle' => 'PIX Subtitle',
            'pix_template_alt' => 'PIX Alt',
            'terms_and_conditions_description' => 'Terms Description',
            'terms_and_conditions_link_text' => 'Terms Link',
            'smart_pix_selected_account_text' => 'Sufficient balance for this purchase.',
            'message_error_amount' => 'Amount Error',
        ];

        $this->setNotAccessibleProperty($this->gateway, 'links', [
            'mercadopago_terms_and_conditions' => 'https://example.com/terms'
        ]);

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getAmountAndCurrency')
            ->andReturn(['amount' => 2657.6, 'currencyRatio' => 1.0]);

        $params = $this->gateway->getPaymentFieldsParams();

        foreach (['smart_pix_mock', 'smart_pix_orders_mock', 'smart_pix_orders_mock_nonce'] as $retiredField) {
            $this->assertArrayNotHasKey($retiredField, $params);
        }

        $this->assertIsArray($params);
        $this->assertArrayHasKey('test_mode', $params);
        $this->assertArrayHasKey('pix_template_src', $params);
        $this->assertArrayHasKey('icon', $params);
        $this->assertSame(2657.6, $params['amount']);
        $this->assertSame('2657.60', $params['smart_pix_amount']);
        $this->assertArrayHasKey('smart_pix_mp_icon_src', $params);
        $this->assertArrayHasKey('smart_pix_pix_icon_src', $params);
        $this->assertArrayHasKey('smart_pix_bb_icon_src', $params);
        $this->assertArrayHasKey('smart_pix_nubank_icon_src', $params);
        $this->assertSame(
            'Sufficient balance for this purchase.',
            $params['smart_pix_selected_account_text']
        );
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
     * Test generatePixImage with missing order id returns error image
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneratePixImageWithMissingOrderId(): void
    {
        $metricDetails = [
            'site_id' => 'MLB',
            'environment' => 'homol',
            'cust_id' => '123456',
        ];

        $this->mockFormWithCustomSetup(function ($mock) {
            $mock->shouldReceive('sanitizedGetData')->with('id')->andReturn(null);
        });

        \WP_Mock::userFunction('wc_get_order')->never();

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(true);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('123456');

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_qr_access', 'missing_order_id', null, 'pix', $metricDetails);

        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getErrorImage')
            ->once()
            ->andThrow(new \RuntimeException('exit'));

        $this->expectException(\RuntimeException::class);

        $this->gateway->generatePixImage();
    }

    /**
     * Test generatePixImage with missing order returns error image
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneratePixImageWithMissingOrder(): void
    {
        $orderId = 123;
        $metricDetails = [
            'site_id' => 'MLB',
            'environment' => 'homol',
            'cust_id' => '123456',
        ];

        $this->mockFormWithCustomSetup(function ($mock) use ($orderId) {
            $mock->shouldReceive('sanitizedGetData')->with('id')->andReturn($orderId);
        });

        \WP_Mock::userFunction('wc_get_order')
            ->with($orderId)
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(true);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('123456');

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_qr_access', 'order_not_found', null, 'pix', $metricDetails);

        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getErrorImage')
            ->once()
            ->andThrow(new \RuntimeException('exit'));

        $this->expectException(\RuntimeException::class);

        $this->gateway->generatePixImage();
    }

    /**
     * Test generatePixImage with missing QR code returns error image
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneratePixImageWithMissingQrCode(): void
    {
        $orderId = 123;
        $orderKey = 'wc_order_test123';
        $order = Mockery::mock('WC_Order');
        $metricDetails = [
            'site_id' => 'MLB',
            'environment' => 'homol',
            'cust_id' => '123456',
        ];

        $this->mockFormWithCustomSetup(function ($mock) use ($orderId, $orderKey) {
            $mock->shouldReceive('sanitizedGetData')->with('id')->andReturn($orderId);
            $mock->shouldReceive('sanitizedGetData')->with('key')->andReturn($orderKey);
        });

        \WP_Mock::userFunction('wc_get_order')
            ->with($orderId)
            ->andReturn($order);

        $order->shouldReceive('get_order_key')
            ->andReturn($orderKey);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrBase64Meta')
            ->with($order)
            ->andReturn(null);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(true);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('123456');

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_qr_access', 'qr_not_found', null, 'pix', $metricDetails);

        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getErrorImage')
            ->once()
            ->andThrow(new \RuntimeException('exit'));

        $this->expectException(\RuntimeException::class);

        $this->gateway->generatePixImage();
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
        $orderKey = 'wc_order_test123';
        $order = Mockery::mock('WC_Order');
        $qrCodeBase64 = ['base64_string_1', 'base64_string_2'];
        $metricDetails = [
            'site_id' => 'MLB',
            'environment' => 'homol',
            'cust_id' => '123456',
        ];

        $this->mockFormWithCustomSetup(function ($mock) use ($orderId, $orderKey) {
            $mock->shouldReceive('sanitizedGetData')->with('id')->andReturn($orderId);
            $mock->shouldReceive('sanitizedGetData')->with('key')->andReturn($orderKey);
        });

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        $order->shouldReceive('get_order_key')
            ->andReturn($orderKey);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrBase64Meta')
            ->with($order)
            ->andReturn($qrCodeBase64);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(true);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('123456');

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_qr_access', 'authorized', null, 'pix', $metricDetails);

        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getBase64Image')
            ->once()
            ->with('base64_string_2'); // array_pop returns last element

        $this->gateway->generatePixImage();
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
        $orderKey = 'wc_order_test123';
        $order = Mockery::mock('WC_Order');
        $qrCodeBase64 = 'base64_string';
        $metricDetails = [
            'site_id' => 'MLB',
            'environment' => 'homol',
            'cust_id' => '123456',
        ];

        $this->mockFormWithCustomSetup(function ($mock) use ($orderId, $orderKey) {
            $mock->shouldReceive('sanitizedGetData')->with('id')->andReturn($orderId);
            $mock->shouldReceive('sanitizedGetData')->with('key')->andReturn($orderKey);
        });

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        $order->shouldReceive('get_order_key')
            ->andReturn($orderKey);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPixQrBase64Meta')
            ->with($order)
            ->andReturn($qrCodeBase64);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(true);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('123456');

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_qr_access', 'authorized', null, 'pix', $metricDetails);

        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getBase64Image')
            ->once()
            ->with($qrCodeBase64);

        $this->gateway->generatePixImage();
    }

    /**
     * Test generatePixImage with invalid order key returns error image
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneratePixImageWithInvalidOrderKey(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');
        $metricDetails = [
            'site_id' => 'MLB',
            'environment' => 'homol',
            'cust_id' => '123456',
        ];

        $this->mockFormWithCustomSetup(function ($mock) use ($orderId) {
            $mock->shouldReceive('sanitizedGetData')->with('id')->andReturn($orderId);
            $mock->shouldReceive('sanitizedGetData')->with('key')->andReturn('wc_order_invalid');
        });

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        $order->shouldReceive('get_order_key')
            ->andReturn('wc_order_real_key');

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(true);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('123456');

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_qr_access', 'invalid_order_key', null, 'pix', $metricDetails);

        // getErrorImage() calls exit() in production, so we simulate it with an exception
        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getErrorImage')
            ->once()
            ->andThrow(new \RuntimeException('exit'));

        $this->expectException(\RuntimeException::class);

        $this->gateway->generatePixImage();
    }

    /**
     * Test generatePixImage with missing order key returns error image
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGeneratePixImageWithMissingOrderKey(): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');
        $metricDetails = [
            'site_id' => 'MLB',
            'environment' => 'homol',
            'cust_id' => '123456',
        ];

        $this->mockFormWithCustomSetup(function ($mock) use ($orderId) {
            $mock->shouldReceive('sanitizedGetData')->with('id')->andReturn($orderId);
            $mock->shouldReceive('sanitizedGetData')->with('key')->andReturn(null);
        });

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('isTestMode')
            ->andReturn(true);

        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')
            ->andReturn('123456');

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_qr_access', 'missing_order_key', null, 'pix', $metricDetails);

        // getErrorImage() calls exit() in production, so we simulate it with an exception
        $this->gateway->mercadopago->helpers->images
            ->shouldReceive('getErrorImage')
            ->once()
            ->andThrow(new \RuntimeException('exit'));

        $this->expectException(\RuntimeException::class);

        $this->gateway->generatePixImage();
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

        $order->shouldReceive('get_order_key')
            ->andReturn('wc_order_test123');

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

        $order->shouldReceive('get_order_key')
            ->andReturn('wc_order_test123');

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
                Mockery::on(function ($params) {
                    return isset($params['order_key']) && $params['order_key'] === 'wc_order_test123';
                })
            );

        $this->gateway->registerPixPoolingScript($order);
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

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPaymentContract')
            ->with($order)
            ->andReturn(null);

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

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPaymentContract')
            ->with($order)
            ->andReturn(null);

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
    }

    /**
     * Test renderThankYouPage with valid qrCodes
     *
     * @return void
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    /**
     * @dataProvider conventionalThankYouContractProvider
     * @param string|null $contract
     * @param string|null $ppOrderId
     */
    public function testRenderThankYouPageWithValidQrCodes($contract, $ppOrderId): void
    {
        $orderId = 123;
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->with(\MercadoPago\Woocommerce\SmartPix\Adapters\Persistence\SmartPixCreationStore::META_KEY, true, 'edit')->andReturn('');

        \WP_Mock::userFunction('wc_get_order')
            ->with(Mockery::any())
            ->andReturn($order);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPaymentContract')
            ->with($order)
            ->andReturn($contract);
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPpOrderId')
            ->with($order)
            ->andReturn($ppOrderId);

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

        $order->shouldReceive('get_order_key')
            ->andReturn('wc_order_test123');

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
        $this->gateway->mercadopago->hooks->template
            ->shouldNotReceive('getWoocommerceTemplate')
            ->with('public/order/smart-pix-status.php', Mockery::any());

        $this->gateway->renderThankYouPage($orderId);
    }

    /**
     * A Smart Pix attempt that never linked an Orders reference must not hide the conventional QR
     * the buyer paid with afterwards on the same order.
     *
     * @return array<string, array{string|null, string|null}>
     */
    public static function conventionalThankYouContractProvider(): array
    {
        return [
            'conventional Pix order'                 => [null, null],
            'Smart Pix attempt then conventional QR' => ['orders_smart_pix_v1', ''],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenASmartPixOrderWhenRenderingThankYouPageThenTheStatusPageReplacesTheQrCode(): void
    {
        $order = $this->smartPixStatusPageOrder('pending');
        $this->gateway->mercadopago->orderMetadata->shouldNotReceive('getPixQrCodeMeta', 'getPixQrBase64Meta');
        $this->gateway->mercadopago->orderMetadata->shouldNotReceive('setPpOrderTerminalReported');
        $this->gateway->datadog->shouldNotReceive('sendEvent');
        $this->expectSmartPixStatusPage(static function (array $args): bool {
            return $args['initial_state'] === 'pending'
                && $args['rejected_description'] === 'smart_pix_status_rejected_description'
                && !array_key_exists('qr_code', $args);
        });

        $this->gateway->renderThankYouPage(123);

        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenASmartPixOrderFirstRenderedAlreadyTerminalThenItsOutcomeIsReportedOnceFromOrderCreation(): void
    {
        $order = $this->smartPixStatusPageOrder('failed');
        $order->shouldReceive('get_date_created')->andReturn(new \DateTime('-42 seconds'));
        $order->shouldNotReceive('payment_complete', 'update_status');
        $metadata = $this->gateway->mercadopago->orderMetadata;
        $reported = '';
        $metadata->shouldReceive('getPpOrderTerminalReported')->with($order)->andReturnUsing(static function () use (&$reported) {
            return $reported;
        });
        $metadata->shouldReceive('setPpOrderTerminalReported')->with($order, 'rejected')->once()
            ->andReturnUsing(static function ($order, string $state) use (&$reported): void {
                $reported = $state;
            });
        $metadata->shouldReceive('getPpOrderPendingSince')->with($order)->andReturn((string) (time() - 42));
        $this->gateway->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('smart_pix_payment_status_observed', '1', '', 'smart_transfer',
                Mockery::on(fn($details) => $details['payment_status'] === 'rejected'
                    && $details['source'] === 'thankyou'
                    && in_array($details['pooling_time'], ['42000', '43000'], true)));
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'rejected', 2);

        $this->gateway->renderThankYouPage(123);
        // A second navigation creates a new gateway instance; reset only the per-request render guard.
        $this->setNotAccessibleProperty($this->gateway, 'renderedSmartPixThankYouOrders', []);
        $this->gateway->renderThankYouPage(123);

        $this->assertSame('rejected', $reported);
        $this->assertSame([], $GLOBALS['wpdb']->rows);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenFailingTelemetryWhenATerminalSmartPixPageRendersThenThePageIsStillRendered(): void
    {
        $order = $this->smartPixStatusPageOrder('processing');
        $metadata = $this->gateway->mercadopago->orderMetadata;
        $metadata->shouldReceive('getPpOrderTerminalReported')->with($order)->andReturn('');
        $metadata->shouldReceive('setPpOrderTerminalReported')->with($order, 'approved')->once();
        $metadata->shouldReceive('getPpOrderPendingSince')->with($order)->andReturn('');
        $this->gateway->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('smart_pix_payment_status_observed', '1', '', 'smart_transfer', [
                'payment_method' => 'smart_transfer', 'event' => 'payment_status_observed',
                'payment_status' => 'approved', 'source' => 'thankyou', 'missing_fields' => 'sdk_instance_id'])
            ->andThrow(new \RuntimeException('monitor down'));
        \WP_Mock::userFunction('wp_remote_post', ['times' => 0]);
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'approved');

        $this->gateway->renderThankYouPage(123);

        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenAnApprovedSmartPixOrderFirstObservedOnTheThankYouPageThenItsApprovalTimeIsMeasured(): void
    {
        $order = $this->smartPixStatusPageOrder('processing');
        $order->shouldReceive('get_date_created')->andReturn(new \DateTime('-42 seconds'));
        $order->shouldNotReceive('payment_complete', 'update_status');
        $metadata = $this->gateway->mercadopago->orderMetadata;
        $metadata->shouldReceive('getPpOrderTerminalReported')->with($order)->andReturn('');
        $metadata->shouldReceive('setPpOrderTerminalReported')->with($order, 'approved')->once();
        $metadata->shouldReceive('getPpOrderPendingSince')->with($order)->andReturn((string) (time() - 42));
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getCustIdFromAT')->andReturn('123');
        $this->gateway->mercadopago->woocommerce->version = '9.8.1';
        $this->gateway->datadog->shouldReceive('sendEvent')
            ->once()
            ->with(
                'smart_pix_payment_status_observed',
                '1',
                '',
                'smart_transfer',
                Mockery::on(fn($details) => $details['source'] === 'thankyou' && $details['payment_status'] === 'approved'
                    && $details['site_id'] === 'MLB' && $details['environment'] === 'homol' && $details['cust_id'] === '123')
            );
        $sent = [];
        \WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        \WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function (string $url, array $arguments) use (&$sent): array {
            $sent[] = [$url, json_decode($arguments['body'], true)];
            return [];
        });
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'approved');

        $this->gateway->renderThankYouPage(123);

        $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/metric/datadog/smb/smart_pix_payment_approval_time', $sent[0][0]);
        $this->assertContains($sent[0][1]['value'], [42, 43]);
        $this->assertSame('123', $sent[0][1]['details']['cust_id']);
        $this->assertSame('homol', $sent[0][1]['details']['environment']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenASucceededSmartPixCreationWhenReportedThenTheGatewayAlsoMeasuresItsCreationTime(): void
    {
        $send = (new \ReflectionClass(PixGateway::class))->getMethod('sendSmartPixMetric');
        $send->setAccessible(true);
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getCustIdFromAT')->andReturn('123');
        $this->gateway->mercadopago->woocommerce->version = '9.8.1';
        $this->gateway->datadog->shouldReceive('sendEvent')
            ->once()
            ->with('smart_pix_order_creation', '1', 'blocks', 'smart_transfer', [
                'payment_method' => 'smart_transfer', 'event' => 'order_creation_succeeded', 'payment_status' => 'pending',
                'request_time' => '432', 'site_id' => 'MLB', 'environment' => 'homol', 'cust_id' => '123',
                'missing_fields' => 'sdk_instance_id']);
        $sent = [];
        \WP_Mock::userFunction('wp_json_encode')->andReturnUsing('json_encode');
        \WP_Mock::userFunction('wp_remote_post', ['times' => 1])->andReturnUsing(function (string $url, array $arguments) use (&$sent): array {
            $sent[] = [$url, json_decode($arguments['body'], true)];
            return [];
        });

        $send->invoke($this->gateway, 'smart_pix_order_creation', 'pending', '0.432s', ['surface' => 'blocks']);

        $this->assertSame('https://api.mercadopago.com/ppcore/prod/monitor/v1/metric/datadog/smb/smart_pix_order_creation_time', $sent[0][0]);
        $this->assertSame(0.432, $sent[0][1]['value']);
        $this->assertSame('order_creation_succeeded', $sent[0][1]['details']['event']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider smartPixThankYouRejectionProvider
     */
    public function testSmartPixThankYouUsesThePersistedReasonAndCatalog($detail, string $expected): void
    {
        $order = $this->smartPixStatusPageOrder('failed');
        $metadata = $this->gateway->mercadopago->orderMetadata;
        $metadata->shouldReceive('getPpPaymentStatusDetail')->with($order)->andReturn($detail);
        $metadata->shouldReceive('getPpOrderTerminalReported')->andReturn('rejected');
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'rejected'
            && $args['initial_error'] === $expected && $args['new_purchase_url'] === 'https://store.test/shop/');
        $this->gateway->renderThankYouPage(123);
        $this->assertTrue(true);
    }

    public static function smartPixThankYouRejectionProvider(): array
    {
        return [
            ['insufficient_amount', 'smart_pix_insufficient_balance'],
            ['rejected_cap_exceeded', 'smart_pix_limit_exceeded'],
            ['rejected_account_error', 'smart_pix_rejected'],
            [null, 'smart_pix_rejected'],
            ['unknown_private_reason', 'smart_pix_rejected'],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider smartPixShopDestinationProvider
     */
    public function testSmartPixThankYouRespectsTheNativeShopDestination(int $shopPageId, string $destination): void
    {
        $this->smartPixStatusPageOrder('pending');
        WP_Mock::userFunction('wc_get_page_id', ['args' => ['shop'], 'return' => $shopPageId]);
        WP_Mock::userFunction('home_url', ['args' => ['/'], 'return' => 'https://store.test/']);
        $actualUrl = null;
        $this->expectSmartPixStatusPage(static function (array $args) use (&$actualUrl): bool {
            $actualUrl = $args['new_purchase_url'];
            return true;
        }, 1, $destination);
        $this->gateway->renderThankYouPage(123);
        $this->assertSame($destination, $actualUrl);
    }

    public static function smartPixShopDestinationProvider(): array
    {
        return [
            'configured shop with a custom permalink' => [42, 'https://store.test/catalogo/'],
            'native home fallback without a shop page' => [-1, 'https://store.test/'],
            'plugin customizes the destination without a shop page' => [-1, 'https://store.test/vitrine/'],
        ];
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSmartPixPendingPageResumesThePollingWindowOfTheAttempt(): void
    {
        $order = $this->smartPixStatusPageOrder('pending');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPpOrderPendingSince')->with($order)
            ->andReturn((string) (time() - 120));
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'pending', 1, 'https://store.test/shop/', 120000);
        $this->gateway->renderThankYouPage(123);
        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSmartPixPendingPageWithoutAnAttemptStartStartsAFreshWindow(): void
    {
        $this->smartPixStatusPageOrder('pending');
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'pending', 1, 'https://store.test/shop/', 0);
        $this->gateway->renderThankYouPage(123);
        $this->assertTrue(true);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSmartPixClassicPlacementPrecedesTheOverviewAndDoesNotDuplicateTheGatewayOutput(): void
    {
        $order = $this->smartPixStatusPageOrder('pending');
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'pending', 1, 'https://store.test/shop/', null, '<div data-mp-smart-pix-status></div>');
        $this->gateway->prepareSmartPixThankYouTemplate('checkout/thankyou.php', '', '', ['order' => $order]);
        $this->gateway->prepareSmartPixThankYou(123);
        // Separate WC_Order objects can represent the same authorized order.
        $templateOrder = Mockery::mock('WC_Order');
        $templateOrder->shouldReceive('get_id')->andReturn(123);
        ob_start();
        echo '<p class="woocommerce-thankyou-order-received">Native subtitle</p>';
        $this->gateway->renderSmartPixAfterOrderReceived('checkout/order-received.php', '', '', ['order' => $templateOrder]);
        echo '<ul class="woocommerce-order-overview"></ul>';
        $this->gateway->renderThankYouPage(123);
        $this->gateway->renderSmartPixAfterOrderReceived('checkout/thankyou.php', '', '', ['order' => $order]);
        $html = ob_get_clean();
        $this->assertSame('<p class="woocommerce-thankyou-order-received">Native subtitle</p><div data-mp-smart-pix-status></div><ul class="woocommerce-order-overview"></ul>', $html);
        $this->assertSame('<section>Next block</section>', $this->gateway->appendSmartPixAfterOrderStatus('<section>Next block</section>'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSmartPixBlocksPlacementUsesTheAuthorizedStatusContextAndConsumesItOnce(): void
    {
        $this->smartPixStatusPageOrder('pending');
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'pending', 1, 'https://store.test/shop/', null, '<div data-mp-smart-pix-status></div>');
        $native = '<section class="wp-block-woocommerce-order-confirmation-status"><h1>Native title</h1><p>Native subtitle</p></section>';
        $this->assertSame($native, $this->gateway->appendSmartPixAfterOrderStatus($native));
        $this->gateway->prepareSmartPixThankYou(123);
        $this->assertSame($native . '<div data-mp-smart-pix-status></div>', $this->gateway->appendSmartPixAfterOrderStatus($native));
        $this->assertSame($native, $this->gateway->appendSmartPixAfterOrderStatus($native));
        $this->gateway->renderThankYouPage(123);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSmartPixClassicFailurePrecedesTheUnchangedNativeNoticeAndActions(): void
    {
        $order = $this->smartPixStatusPageOrder('failed');
        $order->shouldNotReceive('payment_complete', 'update_status');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPpOrderTerminalReported')->andReturn('rejected');
        $status = '<div data-mp-smart-pix-status></div>';
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'rejected', 1, 'https://store.test/shop/', null, $status);
        ob_start();
        $this->gateway->prepareSmartPixThankYouTemplate('checkout/thankyou.php', '', '', ['order' => $order]);
        $this->assertSame('', ob_get_clean());

        $native = '<p class="woocommerce-thankyou-order-failed">Native failure</p>'
            . '<p class="woocommerce-thankyou-order-failed-actions"><a class="button pay" href="/native-pay">Pay</a>'
            . '<a class="button pay" href="/my-account">My account</a></p>';
        ob_start();
        echo '<h1>Native title</h1><div class="woocommerce-order">';
        $this->gateway->prepareSmartPixThankYou(123);
        echo $native;
        $this->gateway->renderThankYouPage(123);
        $this->gateway->renderSmartPixAfterOrderReceived('checkout/thankyou.php', '', '', ['order' => $order]);
        echo '</div>';
        $this->assertSame('<h1>Native title</h1><div class="woocommerce-order">' . $status . $native . '</div>', ob_get_clean());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider smartPixFailedBlockProvider
     */
    public function testSmartPixBlocksFailurePreservesTheNativeHeadingNoticeAndActions(string $heading): void
    {
        $order = $this->smartPixStatusPageOrder('failed');
        $order->shouldNotReceive('payment_complete', 'update_status');
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPpOrderTerminalReported')->andReturn('rejected');
        $status = '<div data-mp-smart-pix-status></div>';
        $this->expectSmartPixStatusPage(static fn(array $args): bool => $args['initial_state'] === 'rejected', 1, 'https://store.test/shop/', null, $status);
        $notice = '<p>Native failure</p><p class="wc-block-order-confirmation-status__actions">'
            . '<a href="/native-pay">Try again</a><a href="/my-account">My account</a></p>';
        $native = '<section class="wp-block-woocommerce-order-confirmation-status">' . $heading . $notice . '</section>';
        // An empty Classic template must not leave context that affects a subsequent native block.
        $this->gateway->prepareSmartPixThankYouTemplate('checkout/thankyou.php', '', '', ['order' => $order]);
        $this->gateway->prepareSmartPixThankYouTemplate('checkout/thankyou.php', '', '', []);
        $this->gateway->prepareSmartPixThankYouTemplate('unrelated.php', '', '', ['order' => $order]);
        ob_start();
        $this->gateway->prepareSmartPixThankYou(123);
        $this->assertSame('', ob_get_clean());
        $expected = $heading === '' ? $native . $status
            : '<section class="wp-block-woocommerce-order-confirmation-status">' . $heading . $status . $notice . '</section>';
        $this->assertSame($expected, $this->gateway->appendSmartPixAfterOrderStatus($native));
        $this->gateway->prepareSmartPixThankYou(123);
        $this->assertSame($native, $this->gateway->appendSmartPixAfterOrderStatus($native));
        $this->gateway->renderThankYouPage(123);
    }

    public static function smartPixFailedBlockProvider(): array
    {
        return [
            'native heading' => ['<h1>Order failed</h1>'],
            'customized heading' => ['<H1 id="native-title"><span>Order failed</span></H1>'],
            'custom layout without a heading' => [''],
        ];
    }

    public function testSmartPixNativePlacementIgnoresMissingOrUnrelatedOrdersAndTemplates(): void
    {
        $this->gateway->mercadopago->hooks->template->shouldNotReceive('getWoocommerceTemplate');
        WP_Mock::userFunction('wc_get_order')->with(0)->andReturn(false);
        $this->gateway->prepareSmartPixThankYou(0);
        $this->gateway->renderSmartPixAfterOrderReceived('checkout/order-received.php', '', '', []);
        $this->assertSame('native', $this->gateway->appendSmartPixAfterOrderStatus('native'));

        $order = $this->smartPixStatusPageOrder('pending');
        $this->gateway->prepareSmartPixThankYou(123);
        $this->gateway->renderSmartPixAfterOrderReceived('order/order-details.php', '', '', ['order' => $order]);
        $this->gateway->renderSmartPixAfterOrderReceived('checkout/order-received.php', '', '', []);
        $other = Mockery::mock('WC_Order');
        $other->shouldReceive('get_id')->andReturn(456);
        $this->gateway->renderSmartPixAfterOrderReceived('checkout/order-received.php', '', '', ['order' => $other]);
        $this->assertSame('', $this->gateway->appendSmartPixAfterOrderStatus(''));
        $this->assertSame('native', $this->gateway->appendSmartPixAfterOrderStatus('native'));
    }

    public function testSmartPixNativePlacementIgnoresConventionalPix(): void
    {
        $order = Mockery::mock('WC_Order');
        WP_Mock::userFunction('wc_get_order')->with(123)->andReturn($order);
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPaymentContract')->with($order)->andReturn('');
        $this->gateway->mercadopago->hooks->template->shouldNotReceive('getWoocommerceTemplate');
        $this->gateway->prepareSmartPixThankYou(123);
        $this->assertSame('native', $this->gateway->appendSmartPixAfterOrderStatus('native'));
    }

    public function testSmartPixPlacementRegistersOnlyNativeRenderHooksAndEarlyStyles(): void
    {
        WP_Mock::expectActionAdded('woocommerce_before_thankyou', [$this->gateway, 'prepareSmartPixThankYou']);
        WP_Mock::expectActionAdded('woocommerce_before_template_part', [$this->gateway, 'prepareSmartPixThankYouTemplate'], 10, 4);
        WP_Mock::expectActionAdded('woocommerce_after_template_part', [$this->gateway, 'renderSmartPixAfterOrderReceived'], 10, 4);
        WP_Mock::expectFilterAdded('render_block_woocommerce/order-confirmation-status', [$this->gateway, 'appendSmartPixAfterOrderStatus']);
        WP_Mock::expectFilterAdded('woocommerce_my_account_my_orders_actions', [$this->gateway, 'filterSmartPixThankYouActions'], 20, 2);
        WP_Mock::expectActionAdded('wp_enqueue_scripts', [$this->gateway, 'registerSmartPixThankYouStyles']);
        $this->gateway->registerSmartPixThankYouHooks();
        $this->addToAssertionCount(1);
    }

    /** @dataProvider smartPixThankYouActionProvider */
    public function testSmartPixThankYouHidesOnlyNativePaymentAndCancellationActions(
        bool $received,
        string $gateway,
        string $contract,
        string $reference,
        bool $started,
        bool $hide
    ): void {
        WP_Mock::userFunction('is_order_received_page')->andReturn($received);
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_payment_method')->andReturn($gateway);
        $order->shouldNotReceive('update_status', 'payment_complete', 'save');
        $metadata = $this->gateway->mercadopago->orderMetadata;
        $metadata->shouldReceive('getPaymentContract')->with($order)->andReturn($contract);
        $metadata->shouldReceive('getPpOrderId')->with($order)->andReturn($reference);
        $order->shouldReceive('get_meta')->andReturn($started ? str_repeat('a', 64) : '');
        $actions = [
            'pay' => ['url' => '/pay'], 'cancel' => ['url' => '/cancel'],
            'view' => ['url' => '/view'], 'custom' => ['url' => '/custom'],
        ];

        $this->assertSame(
            $hide ? ['view' => $actions['view'], 'custom' => $actions['custom']] : $actions,
            $this->gateway->filterSmartPixThankYouActions($actions, $order)
        );
    }

    public static function smartPixThankYouActionProvider(): array
    {
        $contract = OrderMetadata::PAYMENT_CONTRACT_ORDERS_SMART_PIX_V1;
        return [
            'linked Smart Pix' => [true, PixGateway::ID, $contract, 'PPORD123', false, true],
            'unlinked admitted attempt' => [true, PixGateway::ID, $contract, '', true, true],
            'ordinary Pix' => [true, PixGateway::ID, '', '', false, false],
            'different gateway' => [true, 'woo-mercado-pago-custom', $contract, 'PPORD123', false, false],
            'outside thank you' => [false, PixGateway::ID, $contract, 'PPORD123', false, false],
            'no attempt' => [true, PixGateway::ID, $contract, '', false, false],
        ];
    }

    /** @dataProvider smartPixStatusStylesPageProvider */
    public function testSmartPixStylesAreEnqueuedInTheHeadOfOrderReceivedPagesOnly(bool $received): void
    {
        WP_Mock::userFunction('is_order_received_page')->andReturn($received);
        $this->gateway->mercadopago->helpers->url->shouldReceive('getCssAsset')->with('public/mp-smart-pix-status')
            ->times($received ? 1 : 0)->andReturn('status.css');
        $this->gateway->mercadopago->hooks->scripts->shouldReceive('registerStoreStyle')->with('mp_smart_pix_status', 'status.css')
            ->times($received ? 1 : 0);
        $this->gateway->registerSmartPixThankYouStyles();
        $this->addToAssertionCount(1);
    }

    public static function smartPixStatusStylesPageProvider(): array
    {
        return [[true], [false]];
    }

    private function smartPixStatusPageOrder(string $status)
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_id')->andReturn(123);
        $order->shouldReceive('get_order_key')->andReturn('wc_order_SYNTHETIC');
        $order->shouldReceive('get_status')->andReturn($status);
        $order->shouldReceive('read_meta_data')->with(true);
        \WP_Mock::userFunction('wc_get_order')->with(123)->andReturn($order);
        $GLOBALS['wpdb'] = new SmartPixStatusLockDatabase();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPpPaymentStatusDetail')->with($order)->andReturn('')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPpOrderPendingSince')->with($order)->andReturn('')->byDefault();

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPaymentContract')
            ->with($order)
            ->andReturn('orders_smart_pix_v1');
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPpOrderId')
            ->with($order)
            ->andReturn('ORDER_SYNTHETIC_001');

        return $order;
    }

    private function expectSmartPixStatusPage(callable $templateArgsMatch, int $renders = 1, string $shopUrl = 'https://store.test/shop/', ?int $elapsedMs = null, string $templateOutput = ''): void
    {
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getCssAsset')
            ->with('public/mp-smart-pix-status')
            ->andReturn('https://example.com/smart-pix-status.css');
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getJsAsset')
            ->with('checkouts/pix/mp-smart-pix-status')
            ->andReturn('https://example.com/smart-pix-status.js');
        \WP_Mock::userFunction('wp_create_nonce')
            ->with('mp_smart_pix_order_status_nonce')
            ->andReturn('test-nonce');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreStyle')
            ->times($renders)
            ->with('mp_smart_pix_status', 'https://example.com/smart-pix-status.css');
        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerStoreScript')
            ->times($renders)
            ->with('wc_mercadopago_smart_pix_status', 'https://example.com/smart-pix-status.js', Mockery::on(
                static function (array $params) use ($elapsedMs): bool {
                    // The elapsed time is read from the clock: allow the second that may pass meanwhile.
                    $elapsed = $params['elapsed_ms'] ?? null;
                    unset($params['elapsed_ms']);
                    return $params === [
                        'order_id'        => 123,
                        'order_key'       => 'wc_order_SYNTHETIC',
                        'ajax_url'        => 'https://store.test/?wc-ajax=endpoint',
                        'nonce'           => 'test-nonce',
                        'interval_ms'     => 5000,
                        'max_duration_ms' => 900000,
                    ] && is_int($elapsed) && $elapsed >= ($elapsedMs ?? 0)
                        && ($elapsedMs === null || $elapsed <= $elapsedMs + 1000);
                }
            ));

        \WP_Mock::userFunction('wc_get_page_permalink', ['args' => ['shop'], 'return' => $shopUrl]);
        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('icons/smart-pix-approved.svg')->andReturn('https://store.test/approved.svg');
        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('icons/smart-pix-rejected.svg')->andReturn('https://store.test/rejected.svg');
        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')
            ->with('icons/smart-pix-info.svg')->andReturn('https://store.test/info.svg');

        $keys = [
            'smart_pix_status_finalizing_description', 'smart_pix_status_delayed_description',
            'smart_pix_status_waiting_description', 'smart_pix_status_long_wait_description',
            'smart_pix_status_insufficient_title', 'smart_pix_status_limit_title',
            'smart_pix_status_alternative_description', 'smart_pix_status_new_purchase',
            'smart_pix_status_processing_title', 'smart_pix_status_processing_description',
            'smart_pix_status_timeout_description', 'smart_pix_status_approved_title',
            'smart_pix_status_approved_description', 'smart_pix_status_rejected_title',
            'smart_pix_status_rejected_description', 'smart_pix_status_cancelled_title',
            'smart_pix_status_cancelled_description',
        ];
        $this->gateway->storeTranslations = array_combine($keys, $keys);

        $this->gateway->mercadopago->hooks->template
            ->shouldReceive('getWoocommerceTemplate')
            ->times($renders)
            ->with('public/order/smart-pix-status.php', Mockery::on($templateArgsMatch))
            ->andReturnUsing(static function () use ($templateOutput): void { echo $templateOutput; });
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testConstructorReturnsEarlyWhenNotBooted(): void
    {
        global $mercadopago;
        $mercadopago = Mockery::mock(\MercadoPago\Woocommerce\WoocommerceMercadoPago::class);
        $mercadopago->shouldReceive('booted')->andReturn(false);

        $gateway = new PixGateway();

        $this->assertFalse(isset($gateway->adminTranslations));
    }

    /**
     * The owner of the order (matching order_key) reaches the status read.
     *
     * @dataProvider conventionalPixStatusPayloadProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @param mixed $paymentData
     */
    public function testCheckPixPaymentStatusOwnerReadsStatus($paymentData, array $expected): void
    {
        $order = Mockery::mock('WC_Order');

        $this->mockFormWithCustomSetup(function ($mock) {
            $mock->shouldReceive('sanitizedPostData')->with('nonce')->andReturn('valid-nonce');
            $mock->shouldReceive('sanitizedPostData')->with('order_id')->andReturn(123);
            $mock->shouldReceive('sanitizedPostData')->with('order_key')->andReturn('wc_order_correct');
        });

        \WP_Mock::userFunction('wp_verify_nonce')
            ->with('valid-nonce', PixGateway::PIX_STATUS_NONCE)
            ->andReturn(true);

        \WP_Mock::userFunction('wc_get_order')
            ->with(123)
            ->andReturn($order);

        $order->shouldReceive('get_order_key')->andReturn('wc_order_correct');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPaymentsIdMeta')
            ->with($order)
            ->andReturn('pay-123');

        $response = new \MercadoPago\Woocommerce\Api\Http\HttpResponse(200, [], '', $paymentData);

        $this->gateway->mercadopago->helpers->requester
            ->shouldReceive('get')
            ->with('/v1/payments/pay-123', Mockery::type('array'))
            ->andReturn($response);

        $this->gateway->mercadopago->orderStatus->shouldNotReceive('processStatus');
        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');
        $order->shouldNotReceive('save');
        $publicResponse = null;
        \WP_Mock::userFunction('wp_send_json_success')->once()
            ->andReturnUsing(static function (array $payload) use (&$publicResponse): void {
                $publicResponse = $payload;
            });

        $this->gateway->checkPixPaymentStatus();

        $this->assertSame($expected, $publicResponse);
    }

    public static function conventionalPixStatusPayloadProvider(): array
    {
        return [
            'pending array' => [['status' => 'pending'], ['status' => 'pending', 'message' => 'Payment pending']],
            'pending object' => [(object) ['status' => 'pending'], ['status' => 'pending', 'message' => 'Payment pending']],
            'rejected payment' => [['status' => 'rejected'], ['status' => 'rejected', 'message' => 'Payment status: rejected']],
            'unusable response' => ['invalid-response', ['status' => null, 'message' => 'Payment status: ']],
        ];
    }

    /**
     * An approved payment whose status the order flow does not apply is neither
     * marked as processed nor reported as approved to the polling client.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCheckPixPaymentStatusDoesNotReportApprovalWhenStatusIsNotApplied(): void
    {
        $order = Mockery::mock('WC_Order');

        $this->mockFormWithCustomSetup(function ($mock) {
            $mock->shouldReceive('sanitizedPostData')->with('nonce')->andReturn('valid-nonce');
            $mock->shouldReceive('sanitizedPostData')->with('order_id')->andReturn(123);
            $mock->shouldReceive('sanitizedPostData')->with('order_key')->andReturn('wc_order_correct');
        });

        \WP_Mock::userFunction('wp_verify_nonce')
            ->with('valid-nonce', PixGateway::PIX_STATUS_NONCE)
            ->andReturn(true);

        \WP_Mock::userFunction('wc_get_order')
            ->with(123)
            ->andReturn($order);

        $order->shouldReceive('get_order_key')->andReturn('wc_order_correct');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('getPaymentsIdMeta')
            ->with($order)
            ->andReturn('pay-123');

        $response = Mockery::mock(\MercadoPago\Woocommerce\Api\Http\HttpResponse::class);
        $response->shouldReceive('getStatus')->andReturn(200);
        $response->shouldReceive('getData')->andReturn(['status' => 'approved']);

        $this->gateway->mercadopago->helpers->requester
            ->shouldReceive('get')
            ->with('/v1/payments/pay-123', Mockery::type('array'))
            ->andReturn($response);

        $this->gateway->orderMeta = Mockery::mock(\MercadoPago\Woocommerce\Hooks\OrderMeta::class);
        $this->gateway->orderMeta
            ->shouldReceive('get')
            ->with($order, 'pix_payment_approved')
            ->andReturn(false);
        $this->gateway->orderMeta->shouldNotReceive('update');

        $this->gateway->mercadopago->orderStatus = Mockery::mock(\MercadoPago\Woocommerce\Order\OrderStatus::class);
        $this->gateway->mercadopago->orderStatus
            ->shouldReceive('processStatus')
            ->once()
            ->with('approved', ['status' => 'approved'], $order, Mockery::type('string'))
            ->andReturn(false);
        // Not a Smart Pix order: no legacy payment trace is recorded.
        $this->gateway->mercadopago->orderStatus
            ->shouldReceive('isOwnedBySmartPixOrders')
            ->once()
            ->with($order)
            ->andReturn(false);

        \WP_Mock::userFunction('wp_send_json_success')
            ->once()
            ->with(['status' => 'not_applied', 'message' => 'Payment status was not applied']);

        $this->gateway->checkPixPaymentStatus();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testGivenAnInvalidNonceWhenCheckingSmartPixStatusThenTheGatewayAnswersForbiddenAndReportsTheReason(): void
    {
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->options = 'wp_options';
        $this->mockFormWithCustomSetup(function ($mock) {
            $mock->shouldReceive('sanitizedPostData')->withNoArgs()->andReturn(['nonce' => 'forged-nonce']);
        });

        \WP_Mock::userFunction('wp_verify_nonce')
            ->with('forged-nonce', 'mp_smart_pix_order_status_nonce')
            ->andReturn(false);
        \WP_Mock::userFunction('wc_get_order')->never();

        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getCustIdFromAT')->andReturn('123');
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->andReturn(true);

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('smart_pix_status_access', '1', '', 'smart_transfer', [
                'payment_method' => 'smart_transfer', 'event' => 'status_access_denied', 'reason' => 'invalid_nonce',
                'site_id' => 'MLB', 'environment' => 'homol', 'cust_id' => '123', 'missing_fields' => 'sdk_instance_id',
            ]);

        \WP_Mock::userFunction('wp_send_json_error')
            ->once()
            ->with(['message' => 'Forbidden'], 403);
        \WP_Mock::userFunction('wp_send_json_success')->never();

        $this->gateway->checkSmartPixOrderStatus();

        $this->assertTrue(true);
    }

    /**
     * A third party sending a mismatched order_key is rejected with 403 and
     * never reaches the payment read / processStatus path.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCheckPixPaymentStatusRejectsMismatchedOrderKey(): void
    {
        $order = Mockery::mock('WC_Order');

        $this->mockFormWithCustomSetup(function ($mock) {
            $mock->shouldReceive('sanitizedPostData')->with('nonce')->andReturn('valid-nonce');
            $mock->shouldReceive('sanitizedPostData')->with('order_id')->andReturn(123);
            $mock->shouldReceive('sanitizedPostData')->with('order_key')->andReturn('wc_order_attacker');
        });

        \WP_Mock::userFunction('wp_verify_nonce')
            ->with('valid-nonce', PixGateway::PIX_STATUS_NONCE)
            ->andReturn(true);

        \WP_Mock::userFunction('wc_get_order')
            ->with(123)
            ->andReturn($order);

        $order->shouldReceive('get_order_key')->andReturn('wc_order_victim');

        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->andReturn(false);

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_status_access', 'invalid_order_key', null, 'pix', Mockery::type('array'));

        \WP_Mock::userFunction('wp_send_json_error')
            ->once()
            ->with(['message' => 'Forbidden'], 403);

        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPaymentsIdMeta')->never();

        $this->expectException(ResponseStatusException::class);

        $this->gateway->checkPixPaymentStatus();
    }

    /**
     * A request without order_key is rejected with 403 and reported as missing.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCheckPixPaymentStatusRejectsMissingOrderKey(): void
    {
        $order = Mockery::mock('WC_Order');

        $this->mockFormWithCustomSetup(function ($mock) {
            $mock->shouldReceive('sanitizedPostData')->with('nonce')->andReturn('valid-nonce');
            $mock->shouldReceive('sanitizedPostData')->with('order_id')->andReturn(123);
            $mock->shouldReceive('sanitizedPostData')->with('order_key')->andReturn(null);
        });

        \WP_Mock::userFunction('wp_verify_nonce')
            ->with('valid-nonce', PixGateway::PIX_STATUS_NONCE)
            ->andReturn(true);

        \WP_Mock::userFunction('wc_get_order')
            ->with(123)
            ->andReturn($order);

        $order->shouldReceive('get_order_key')->andReturn('wc_order_victim');

        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->andReturn(false);

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_status_access', 'missing_order_key', null, 'pix', Mockery::type('array'));

        \WP_Mock::userFunction('wp_send_json_error')
            ->once()
            ->with(['message' => 'Forbidden'], 403);

        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPaymentsIdMeta')->never();

        $this->expectException(ResponseStatusException::class);

        $this->gateway->checkPixPaymentStatus();
    }

    /**
     * An unknown order is rejected with the same 403/Forbidden as a wrong key
     * (no absent-vs-mismatch oracle), reported server-side as order_not_found.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCheckPixPaymentStatusRejectsUnknownOrderWithoutOracle(): void
    {
        $this->mockFormWithCustomSetup(function ($mock) {
            $mock->shouldReceive('sanitizedPostData')->with('nonce')->andReturn('valid-nonce');
            $mock->shouldReceive('sanitizedPostData')->with('order_id')->andReturn(999);
            $mock->shouldReceive('sanitizedPostData')->with('order_key')->andReturn('wc_order_whatever');
        });

        \WP_Mock::userFunction('wp_verify_nonce')
            ->with('valid-nonce', PixGateway::PIX_STATUS_NONCE)
            ->andReturn(true);

        \WP_Mock::userFunction('wc_get_order')
            ->with(999)
            ->andReturn(false);

        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn('MLB');
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->andReturn(false);

        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('pix_status_access', 'order_not_found', null, 'pix', Mockery::type('array'));

        \WP_Mock::userFunction('wp_send_json_error')
            ->once()
            ->with(['message' => 'Forbidden'], 403);

        $this->gateway->mercadopago->orderMetadata->shouldReceive('getPaymentsIdMeta')->never();

        $this->expectException(ResponseStatusException::class);

        $this->gateway->checkPixPaymentStatus();
    }
}
