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
}
