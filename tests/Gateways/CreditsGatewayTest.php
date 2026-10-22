<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use MercadoPago\Woocommerce\Transactions\CreditsTransaction;
use Mockery\Exception\BadMethodCallException;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\CreditsGateway;
use Mockery;
use WP_Mock;

class CreditsGatewayTest extends TestCase
{
    use GatewayMock;

    private const PREVIEW_GIFS = [
        'pt' => [
            'desktop' => 'https://http2.mlstatic.com/storage/cpp/static-files/8afbe775-e8c3-4fa1-b013-ab7f079872b7.gif',
            'mobile' => 'https://http2.mlstatic.com/storage/cpp/static-files/8bcbd873-6ec3-45eb-bccf-47bdcd9af255.gif',
        ],
        'es' => [
            'desktop' => 'https://http2.mlstatic.com/storage/cpp/static-files/e6af4c4b-bede-4a6a-8711-b3d19fe423e3.gif',
            'mobile' => 'https://http2.mlstatic.com/storage/cpp/static-files/a91b365a-73dc-461a-9f3f-f8b3329ae5d2.gif',
        ],
    ];

    private string $gatewayClass = CreditsGateway::class;

    /**
     * @var \Mockery\MockInterface|CreditsGateway
     */
    private $gateway;

    /**
     * @testWith ["MLA", "https://http2.mlstatic.com/storage/cpp/static-files/a91b365a-73dc-461a-9f3f-f8b3329ae5d2.gif"]
     *           ["MLB", "https://http2.mlstatic.com/storage/cpp/static-files/8bcbd873-6ec3-45eb-bccf-47bdcd9af255.gif"]
     *           ["ROLA", "https://http2.mlstatic.com/storage/cpp/static-files/a91b365a-73dc-461a-9f3f-f8b3329ae5d2.gif"]
     */
    public function testGetCreditsGifMobilePath(string $siteId, string $url)
    {
        WP_Mock::userFunction('get_locale')->andReturn('en_US');
        WP_Mock::onFilter('plugin_locale')->with('en_US', 'woocommerce-mercadopago')->reply('en_US');
        $this->assertEquals($url, $this->gateway->getCreditsGifMobilePath($siteId));
    }

    /**
     * @testWith ["MLA", "https://http2.mlstatic.com/storage/cpp/static-files/e6af4c4b-bede-4a6a-8711-b3d19fe423e3.gif"]
     *           ["MLB", "https://http2.mlstatic.com/storage/cpp/static-files/8afbe775-e8c3-4fa1-b013-ab7f079872b7.gif"]
     *           ["ROLA", "https://http2.mlstatic.com/storage/cpp/static-files/e6af4c4b-bede-4a6a-8711-b3d19fe423e3.gif"]
     */
    public function testGetCreditsGifDesktopPath(string $siteId, string $url)
    {
        WP_Mock::userFunction('get_locale')->andReturn('en_US');
        WP_Mock::onFilter('plugin_locale')->with('en_US', 'woocommerce-mercadopago')->reply('en_US');
        $this->assertEquals($url, $this->gateway->getCreditsGifDesktopPath($siteId));
    }

    /** @dataProvider previewLocaleProvider */
    public function testCheckoutPreviewUsesStoreLanguage(string $site, $locale, string $expected, ?string $filteredLocale = null): void
    {
        WP_Mock::userFunction('get_locale')->andReturn($locale);
        WP_Mock::onFilter('plugin_locale')->with($locale, 'woocommerce-mercadopago')->reply($filteredLocale ?? $locale);
        $this->gateway->icon = '';
        $this->gateway->mercadopago->sellerConfig->shouldReceive('getSiteId')->andReturn($site);
        $this->gateway->mercadopago->helpers->url->shouldReceive('getImageAsset')->andReturnUsing(function ($path) {
            return 'https://fixture.test/' . $path . '.png';
        });
        $this->gateway->mercadopago->helpers->url->shouldReceive('getCssAsset', 'getJsAsset')->andReturn('https://fixture.test/asset');
        $scriptParams = [];
        $templateParams = [];
        $this->gateway->mercadopago->hooks->scripts->shouldReceive('registerCreditsAdminStyle');
        $this->gateway->mercadopago->hooks->scripts->shouldReceive('registerCreditsAdminScript')
            ->andReturnUsing(function ($name, $file, $params) use (&$scriptParams) {
                $scriptParams = $params;
            });
        $this->gateway->mercadopago->hooks->template->shouldReceive('getWoocommerceTemplateHtml')
            ->andReturnUsing(function ($template, $params) use (&$templateParams) {
                if ($template === 'admin/components/credits-info-example.php') {
                    $templateParams = $params;
                }
                return '';
            });
        $this->gateway->mercadopago->hooks->options->shouldReceive('getGatewayOption')->andReturn(1);

        $fields = $this->gateway->formFieldsMainSection();
        $this->assertSame('https://fixture.test/checkouts/credits/' . $expected . '_checkout_preview.png', $fields['checkout_visualization']['image']);
        $this->assertSame(self::PREVIEW_GIFS[$expected]['desktop'], $scriptParams['viewDesktop']);
        $this->assertSame(self::PREVIEW_GIFS[$expected]['mobile'], $scriptParams['viewMobile']);
        $this->assertSame(self::PREVIEW_GIFS[$expected]['desktop'], $templateParams['viewDesktop']);
    }

    public function previewLocaleProvider(): array
    {
        $cases = [];
        foreach (['MLA', 'MLM', 'MLB'] as $site) {
            foreach (['pt_BR' => 'pt', 'pt_PT' => 'pt', 'es' => 'es', 'es_AR' => 'es', 'es_MX' => 'es', 'es_ES' => 'es', 'en_US' => 'es', 'fr_FR' => 'es'] as $locale => $image) {
                $expected = !in_array($locale, ['en_US', 'fr_FR'], true) ? $image : ($site === 'MLB' ? 'pt' : 'es');
                $cases[$site . '/' . $locale] = [$site, $locale, $expected];
            }
        }
        foreach (['empty' => '', 'null' => null, 'array' => [], 'path' => '../../pt'] as $key => $locale) {
            $cases['unexpected locale/' . $key] = ['MLB', $locale, 'pt'];
        }
        $cases['plugin locale filter'] = ['MLA', 'es_AR', 'pt', 'pt_BR'];
        $cases['MLC preview fallback'] = ['MLC', 'en_US', 'es'];
        $cases['unknown site fallback'] = ['UNKNOWN', 'en_US', 'es'];
        return $cases;
    }

    public function testGetCheckoutName(): void
    {
        $this->assertSame($this->gateway->getCheckoutName(), 'checkout-credits');
    }

    /** @dataProvider presentationNamesProvider */
    public function testCopyUsesTranslatedNamesForEachSurface(string $name, string $promotion, string $alternative): void
    {
        $this->gateway->mercadopago->sellerConfig->shouldNotReceive('getSiteId');
        $this->gateway->adminTranslations = [
            'gateway_title' => $name,
            'gateway_method_title' => 'Old method title',
            'title_default' => 'Checkout without card',
            'credits_promotion_name' => $promotion,
            'credits_alternative_name' => $alternative,
            'header_title' => '12 {credits_name}',
            'tooltip_component_option1' => '12 {credits_promotion_name}',
            'tooltip_component_option2' => 'Buy {credits_alternative_name}',
            'guarantee' => '100% protected by Mercado Pago',
        ];
        $this->gateway->storeTranslations = [
            'tooltip_component_option1' => '12 {credits_name}',
            'modal_step_1' => 'Select {credits_name}',
        ];
        $prepare = new \ReflectionMethod(CreditsGateway::class, 'prepareCreditsCopy');
        $prepare->setAccessible(true);
        $prepare->invoke($this->gateway);

        foreach (['gateway_title', 'gateway_method_title', 'title_default'] as $key) {
            $this->assertSame($name, $this->gateway->adminTranslations[$key]);
        }
        $this->assertSame('12 ' . $name, $this->gateway->adminTranslations['header_title']);
        $this->assertSame('12 ' . $promotion, $this->gateway->adminTranslations['tooltip_component_option1']);
        $this->assertSame('Buy ' . $alternative, $this->gateway->adminTranslations['tooltip_component_option2']);
        $this->assertSame('12 ' . $name, $this->gateway->storeTranslations['tooltip_component_option1']);
        $this->assertSame('Select ' . $name, $this->gateway->storeTranslations['modal_step_1']);
        $this->assertSame('100% protected by Mercado Pago', $this->gateway->adminTranslations['guarantee']);
    }

    public function presentationNamesProvider(): array
    {
        return [
            'Spanish' => ['Mercado Crédito', 'Crédito de Mercado Pago', 'Crédito de Mercado Pago'],
            'Portuguese' => ['Mercado Parcelado', 'Crédito de Mercado Parcelado', 'Mercado Parcelado'],
            'English' => ['Buy now, pay later', 'Buy now, pay later', 'Buy now, pay later'],
        ];
    }

    public function testValidateFields(): void
    {
        $this->assertSame($this->gateway->validate_fields(), true);
    }

    public function testSavingTheLocalizedDefaultStoresEmptyAndPreservesOtherProductNames(): void
    {
        $this->gateway->adminTranslations = ['gateway_title' => 'Mercado Crédito'];
        $this->gateway->shouldReceive('validate_text_field')->once()
            ->with('title', ' Mercado Crédito ')->andReturn('Mercado Crédito');
        $this->gateway->shouldReceive('validate_text_field')->once()
            ->with('title', 'Mercado Parcelado')->andReturn('Mercado Parcelado');
        $before = $this->gateway->settings;

        $this->assertSame('', $this->gateway->validate_title_field('title', ' Mercado Crédito '));
        $this->assertSame('Mercado Parcelado', $this->gateway->validate_title_field('title', 'Mercado Parcelado'));
        $this->assertSame($before, $this->gateway->settings);
    }

    /** @dataProvider rawTitleProvider */
    public function testReadingTheTitlePreservesTheRawSettings(?string $raw, ?string $global, string $expected): void
    {
        $this->gateway->adminTranslations = ['gateway_title' => 'Mercado Parcelado'];
        if ($raw === null) {
            unset($this->gateway->settings['title']);
        } else {
            $this->gateway->settings['title'] = $raw;
        }
        $before = $this->gateway->settings;
        $this->gateway->mercadopago->hooks->options->shouldReceive('get')
            ->with('title', 'Mercado Parcelado')->andReturn($global ?? 'Mercado Parcelado');

        $this->assertSame($expected, $this->gateway->get_option('title'));
        $this->assertSame($before, $this->gateway->settings);
    }

    public function rawTitleProvider(): array
    {
        return [
            'legacy gateway title' => ['Checkout without card', null, 'Mercado Parcelado'],
            'empty gateway title' => ['', null, 'Mercado Parcelado'],
            'empty gateway title overrides global' => ['', 'My legacy credit method', 'Mercado Parcelado'],
            'legacy global fallback without gateway title' => [null, 'My legacy credit method', 'My legacy credit method'],
        ];
    }

    public function testSavingTheDefaultKeepsFollowingTheLanguageDespiteALegacyGlobalTitle(): void
    {
        $this->gateway->adminTranslations = ['gateway_title' => 'Mercado Parcelado'];
        $this->gateway->shouldReceive('validate_text_field')->once()
            ->with('title', 'Mercado Parcelado')->andReturn('Mercado Parcelado');
        $this->gateway->mercadopago->hooks->options->shouldReceive('get')
            ->with('title', Mockery::any())->andReturn('My legacy credit method');

        $this->gateway->settings['title'] = $this->gateway->validate_title_field('title', 'Mercado Parcelado');
        $this->assertSame('', $this->gateway->settings['title']);
        $this->assertSame('Mercado Parcelado', $this->gateway->get_option('title'));

        $this->gateway->adminTranslations['gateway_title'] = 'Buy now, pay later';
        $this->assertSame('Buy now, pay later', $this->gateway->get_option('title'));
        $this->assertSame('', $this->gateway->settings['title']);
    }

    /**
     * @testWith [true, true]
     *           [false, false]
     */
    public function testProcessPaymentSuccess(bool $isBlocks, bool $isTestMode)
    {
        $this->abstractGatewayProcessPaymentMock($isBlocks, $isTestMode);

        // Mock get_id() specifically for this test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        $_POST['wc-woo-mercado-pago-credits-new-payment-method'] = $isBlocks ? 1 : null;

        // Mock Form::sanitizedPostData() to avoid WordPress function errors
        WP_Mock::userFunction('sanitize_post', [
            'return' => function ($data) {
                return $data;
            }
        ]);
        WP_Mock::userFunction('map_deep', [
            'return' => function ($data, $callback) {
                return is_array($data) ? array_map($callback, $data) : $callback($data);
            }
        ]);
        WP_Mock::userFunction('sanitize_text_field', [
            'return' => function ($data) {
                return $data;
            }
        ]);

        Mockery::mock('overload:' . CreditsTransaction::class)
            ->expects()
            ->createPreference()
            ->andReturn($redirect = [
                'id' => '123456789-credits-preference',
                'init_point' => random()->url(),
                'sandbox_init_point' => random()->url(),
            ]);

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('addPreferenceId')
            ->once()
            ->with($this->order, $redirect['id'])
            ->andReturn(true);

        $this->gateway->mercadopago->storeConfig
            ->expects()
            ->isTestMode()
            ->andReturn($isTestMode);

        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeNotice')
            ->andReturnNull();

        $this->assertEquals(
            [
                'result' => 'success',
                'redirect' => $isTestMode ? $redirect['sandbox_init_point'] : $redirect['init_point'],
            ],
            $this->gateway->process_payment(1)
        );
    }

    public function testProcessPaymentFail()
    {
        WP_Mock::userFunction('wc_get_order');

        $this->gateway
            ->expects()
            ->processReturnFail(
                Mockery::type(BadMethodCallException::class),
                Mockery::type('string'),
                CreditsGateway::LOG_SOURCE,
                [],
                true
            )->andReturn($return = [
                'result' => 'fail',
                'redirect' => '',
                'message' => 'error',
            ]);

        $this->assertEquals(
            $return,
            $this->gateway->process_payment(1)
        );
    }

    /**
     * Test process_payment when exception occurs - executes processReturnFail directly
     * This increases coverage by executing the actual processReturnFail code path
     */
    public function testProcessPaymentExceptionExecutesProcessReturnFail(): void
    {
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order = Mockery::mock('WC_Order'));

        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_total')->andReturn(100.0);

        // Set paymentMethodName to match CreditsGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', CreditsGateway::ID);

        // Initialize discount and commission properties
        $this->gateway->discount = 0;
        $this->gateway->commission = 0;

        // Mock cart calculations
        $this->gateway->mercadopago->helpers->cart
            ->shouldReceive('calculateSubtotalWithDiscount')
            ->once()
            ->andReturn(0);
        $this->gateway->mercadopago->helpers->cart
            ->shouldReceive('calculateSubtotalWithCommission')
            ->once()
            ->andReturn(0);

        // Mock store config
        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('getProductionMode')
            ->once()
            ->andReturn('yes');

        // Mock order metadata
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setIsProductionModeData')
            ->once()
            ->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setUsedGatewayData')
            ->once()
            ->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('markPaymentAsBlocks')
            ->once()
            ->with($order, 'no')
            ->andReturnSelf();
        $this->gateway->mercadopago->orderMetadata
            ->shouldNotReceive('addPreferenceId');

        // Mock WordPress functions that may be called
        WP_Mock::userFunction('sanitize_post', [
            'return' => function ($data) {
                return $data;
            }
        ]);
        WP_Mock::userFunction('map_deep', [
            'return' => function ($data, $callback) {
                return is_array($data) ? array_map($callback, $data) : $callback($data);
            }
        ]);
        WP_Mock::userFunction('sanitize_text_field', [
            'return' => function ($data) {
                return $data;
            }
        ]);

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        // This must be done BEFORE any calls that use logs->file
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        // Mock info() which is called before exception in proccessPaymentInternal
        $logsFileMock
            ->shouldReceive('info')
            ->once()
            ->with('Customer being redirected to Mercado Pago.', CreditsGateway::LOG_SOURCE)
            ->andReturnNull();
        // Mock error() which is called in processReturnFail
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), CreditsGateway::LOG_SOURCE, Mockery::type('array'))
            ->andReturnNull();
        $this->gateway->mercadopago->logs->file = $logsFileMock;

        // Mock setCheckoutSessionDataOnSessionHelperByOrderId which is called before proccessPaymentInternal
        $this->gateway
            ->shouldReceive('setCheckoutSessionDataOnSessionHelperByOrderId')
            ->once()
            ->with(1)
            ->andReturnNull();

        // Mock transaction creation to throw exception (simulating error)
        Mockery::mock('overload:' . \MercadoPago\Woocommerce\Transactions\CreditsTransaction::class)
            ->shouldReceive('createPreference')
            ->once()
            ->andThrow(new \Exception('Test exception'));

        // Mock errorMessages helper
        $this->gateway->mercadopago->helpers->errorMessages
            ->shouldReceive('findErrorMessage')
            ->once()
            ->with('buyer_default')
            ->andReturn('Translated error message');

        // Mock datadog->sendEvent
        $this->gateway->datadog
            ->shouldReceive('sendEvent')
            ->once()
            ->with('woo_checkout_error', 'Translated error message', Mockery::type('string'), CreditsGateway::ID, ['cust_id' => 'test-cust-id', 'device' => 'unknown']);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with('Translated error message', 'error');

        $result = $this->gateway->process_payment(1);

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals('Translated error message', $result['message']);
    }

    /**
     * Test processReturnFail execution to increase coverage
     * This test ensures processReturnFail is actually executed (not mocked) to cover all lines
     */
    public function testProcessReturnFailExecution(): void
    {
        // Set paymentMethodName to match CreditsGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', CreditsGateway::ID);

        $errorMessage = 'buyer_default';
        $translatedMessage = 'Translated error message';

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), CreditsGateway::LOG_SOURCE, Mockery::type('array'))
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
            ->with('woo_checkout_error', $translatedMessage, Mockery::type('string'), CreditsGateway::ID, ['cust_id' => 'test-cust-id', 'device' => 'unknown']);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($translatedMessage, 'error');

        $exception = new \Exception('Test exception');

        $result = $this->gateway->processReturnFail(
            $exception,
            $errorMessage,
            CreditsGateway::LOG_SOURCE,
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
    public function testConstructorReturnsEarlyWhenNotBooted(): void
    {
        global $mercadopago;
        $mercadopago = Mockery::mock(\MercadoPago\Woocommerce\WoocommerceMercadoPago::class);
        $mercadopago->shouldReceive('booted')->andReturn(false);

        $gateway = new CreditsGateway();

        $this->assertFalse(isset($gateway->adminTranslations));
    }
}
