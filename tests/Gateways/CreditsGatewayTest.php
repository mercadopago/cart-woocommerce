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
        $this->assertEquals($url, $this->gateway->getCreditsGifMobilePath($siteId));
    }

    /**
     * @testWith ["MLA", "https://http2.mlstatic.com/storage/cpp/static-files/e6af4c4b-bede-4a6a-8711-b3d19fe423e3.gif"]
     *           ["MLB", "https://http2.mlstatic.com/storage/cpp/static-files/8afbe775-e8c3-4fa1-b013-ab7f079872b7.gif"]
     *           ["ROLA", "https://http2.mlstatic.com/storage/cpp/static-files/e6af4c4b-bede-4a6a-8711-b3d19fe423e3.gif"]
     */
    public function testGetCreditsGifDesktopPath(string $siteId, string $url)
    {
        $this->assertEquals($url, $this->gateway->getCreditsGifDesktopPath($siteId));
    }

    public function testGetCheckoutName(): void
    {
        $this->assertSame($this->gateway->getCheckoutName(), 'checkout-credits');
    }

    public function testValidateFields(): void
    {
        $this->assertSame($this->gateway->validate_fields(), true);
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
                'init_point' => random()->url(),
                'sandbox_init_point' => random()->url(),
            ]);

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
            ->with('woo_checkout_error', 'Translated error message', 'buyer_default', CreditsGateway::ID);

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
            ->with('woo_checkout_error', $translatedMessage, $errorMessage, CreditsGateway::ID);

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
}
