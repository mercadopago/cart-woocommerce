<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\BasicGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
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
    use WoocommerceMock;

    public function testProcessPaymentModal()
    {
        $gateway = Mockery::mock(BasicGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_checkout_payment_url')
            ->once()
            ->andReturn('http://localhost');
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $mercadopagoMock->hooks->options->shouldReceive('getGatewayOption')
            ->once()
            ->andReturn('modal');
        $gateway->mercadopago = $mercadopagoMock;

        $gateway->shouldReceive('saveOrderMetadata')
            ->once()
            ->with($order)
            ->andReturn([]);

        $result = $gateway->process_payment(1);
        $this->assertEquals([
            'result' => 'success',
            'redirect' => 'http://localhost',
        ], $result);
    }

    public function testProcessPaymentRedirect()
    {
        $gateway = Mockery::mock(BasicGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $order = Mockery::mock('WC_Order');
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $mercadopagoMock->hooks->options->shouldReceive('getGatewayOption')
            ->once()
            ->andReturn('redirect');
        $mercadopagoMock->storeConfig->shouldReceive('isTestMode')
            ->once()
            ->andReturn(false);
        $gateway->mercadopago = $mercadopagoMock;

        $gateway->shouldReceive('saveOrderMetadata')
            ->once()
            ->with($order)
            ->andReturn([]);

        $basicTransactionMock = Mockery::mock('overload:' . BasicTransaction::class);
        $basicTransactionMock->shouldReceive('createPreference')
            ->andReturn([
                'init_point' => 'http://localhost',
            ]);

        $result = $gateway->process_payment(1);
        $this->assertEquals([
            'result' => 'success',
            'redirect' => 'http://localhost',
        ], $result);
    }

    public function testProcessPaymentFail()
    {
        $gateway = Mockery::mock(BasicGateway::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $order = Mockery::mock('WC_Order');
        WP_Mock::userFunction('wc_get_order')
            ->once()
            ->with(1)
            ->andReturn($order);

        $mercadopagoMock = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $mercadopagoMock->hooks->options->shouldReceive('getGatewayOption')
            ->once()
            ->andReturn('redirect');
        $mercadopagoMock->helpers->notices->shouldReceive('storeNotice')
            ->once()
            ->with('error', 'error')
            ->andReturn(null);
        $gateway->mercadopago = $mercadopagoMock;

        $gateway->shouldReceive('saveOrderMetadata')
            ->once()
            ->with($order)
            ->andReturn([]);

        $basicTransactionMock = Mockery::mock('overload:' . BasicTransaction::class);
        $basicTransactionMock->shouldReceive('createPreference')
            ->andThrow(new \Exception('new exception'));

        $result = $gateway->process_payment(1);
        $this->assertEquals([
            'result' => 'fail',
            'redirect' => '',
            'message' => 'error'
        ], $result);
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
