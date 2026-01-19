<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use Mockery\Exception\BadMethodCallException;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\BasicGateway;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Transactions\BasicTransaction;
use Mockery;
use WP_Mock;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class BasicGatewayTest extends TestCase
{
    use GatewayMock;

    private string $gatewayClass = BasicGateway::class;

    /**
     * @var \Mockery\MockInterface|BasicGateway
     */
    private $gateway;

    private function processPaymentMock(bool $isBlocks): void
    {
        $this->abstractGatewayProcessPaymentMock($isBlocks);

        $_POST['wc-woo-mercado-pago-basic-new-payment-method'] = $isBlocks ? 1 : null;
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testProcessPaymentModal(bool $isBlocks)
    {
        $this->processPaymentMock($isBlocks);

        // Mock get_id() specifically for this test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        $this->order->shouldReceive('get_checkout_payment_url')
            ->once()
            ->andReturn('http://localhost');

        $this->gateway->mercadopago->hooks->options->shouldReceive('getGatewayOption')
            ->once()
            ->andReturn('modal');

        $result = $this->gateway->process_payment(1);
        $this->assertEquals([
            'result' => 'success',
            'redirect' => 'http://localhost',
        ], $result);
    }

    /**
     * @testWith [true]
     *           [false]
     */
    public function testProcessPaymentRedirect(bool $isBlocks)
    {
        $this->processPaymentMock($isBlocks);

        // Mock get_id() specifically for this test
        $this->order->shouldReceive('get_id')
            ->andReturn(1)
            ->byDefault();

        $this->gateway->mercadopago->hooks->options->shouldReceive('getGatewayOption')
            ->once()
            ->andReturn('redirect');
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')
            ->once()
            ->andReturn(false);

        Mockery::mock('overload:' . BasicTransaction::class)
            ->shouldReceive('createPreference')
            ->andReturn([
                'init_point' => 'http://localhost',
            ]);

        $result = $this->gateway->process_payment(1);
        $this->assertEquals([
            'result' => 'success',
            'redirect' => 'http://localhost',
        ], $result);
    }

    public function testProcessPaymentFail()
    {
        WP_Mock::userFunction('wc_get_order');

        $this->gateway
            ->expects()
            ->processReturnFail(
                Mockery::type(BadMethodCallException::class),
                Mockery::type('string'),
                BasicGateway::LOG_SOURCE,
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

        // Set paymentMethodName to match BasicGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', BasicGateway::ID);

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

        // Mock hooks->options to throw exception (simulating error)
        $this->gateway->mercadopago->hooks->options
            ->shouldReceive('getGatewayOption')
            ->once()
            ->andThrow(new \Exception('Test exception'));

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), BasicGateway::LOG_SOURCE, Mockery::type('array'))
            ->andReturnNull();
        $this->gateway->mercadopago->logs->file = $logsFileMock;

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
            ->with('woo_checkout_error', 'Translated error message', 'buyer_default', BasicGateway::ID);

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

    public function testRenderOrderForm()
    {
        $preferenceId = 123;
        $publicKey = 'expected_public_key';
        $payWithMpTitle = 'expected_pay_with_mp_title';
        $cancelUrlText = 'expected_cancel_url_text';
        $cancelOrderUrl = 'http://localhost';
        $gateway = Mockery::mock(BasicGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_cancel_order_url')
            ->once()
            ->andReturn($cancelOrderUrl);
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $mercadopagoMock->sellerConfig
            ->shouldReceive('getCredentialsPublicKey')
            ->once()
            ->andReturn($publicKey);
        $mercadopagoMock->hooks->template->shouldReceive('getWoocommerceTemplate')
            ->once()
            ->with('public/receipt/preference-modal.php', [
                'public_key'          => $publicKey,
                'preference_id'       => $preferenceId,
                'pay_with_mp_title'   => $payWithMpTitle,
                'cancel_url'          => $cancelOrderUrl,
                'cancel_url_text'     => $cancelUrlText,
            ])
            ->andReturn('template');
        $gateway->mercadopago = $mercadopagoMock;
        $gateway->storeTranslations = array();
        $gateway->storeTranslations['pay_with_mp_title'] = $payWithMpTitle;
        $gateway->storeTranslations['cancel_url_text'] = $cancelUrlText;

        $basicTransactionMock = Mockery::mock('overload:' . BasicTransaction::class);
        $basicTransactionMock->shouldReceive('createPreference')
            ->andReturn([
                'id' => $preferenceId,
            ]);

        $result = $gateway->renderOrderForm(1);
        $this->assertNull($result);
    }

    public function testGetCheckoutName(): void
    {
        $gateway = Mockery::mock(BasicGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->assertSame($gateway->getCheckoutName(), 'checkout-basic');
    }

    /**
     * Test processReturnFail execution to increase coverage
     * This test ensures processReturnFail is actually executed (not mocked) to cover all lines
     */
    public function testProcessReturnFailExecution(): void
    {
        // Set paymentMethodName to match BasicGateway constructor
        $this->setNotAccessibleProperty($this->gateway, 'paymentMethodName', BasicGateway::ID);

        $errorMessage = 'buyer_default';
        $translatedMessage = 'Translated error message';

        // Create a fresh logs->file mock without byDefault() to avoid conflicts
        $logsFileMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Logs\Transports\File::class);
        $logsFileMock
            ->shouldReceive('error')
            ->once()
            ->with(Mockery::type('string'), BasicGateway::LOG_SOURCE, Mockery::type('array'))
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
            ->with('woo_checkout_error', $translatedMessage, $errorMessage, BasicGateway::ID);

        // Mock notices
        $this->gateway->mercadopago->helpers->notices
            ->shouldReceive('storeNotice')
            ->once()
            ->with($translatedMessage, 'error');

        $exception = new \Exception('Test exception');

        $result = $this->gateway->processReturnFail(
            $exception,
            $errorMessage,
            BasicGateway::LOG_SOURCE,
            [],
            true
        );

        $this->assertEquals('fail', $result['result']);
        $this->assertEquals('', $result['redirect']);
        $this->assertEquals($translatedMessage, $result['message']);
    }
}
