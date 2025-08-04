<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Transactions\CreditsTransaction;
use Mockery\Exception\BadMethodCallException;
use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Gateways\CreditsGateway;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use Mockery;
use WP_Mock;

class CreditsGatewayTest extends TestCase
{
    private CreditsGateway $gateway;

    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();

        $this->gateway = Mockery::mock(CreditsGateway::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $this->gateway->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

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
        $order = Mockery::mock('WC_Order');

        WP_Mock::userFunction('wc_get_order')
            ->twice()
            ->with(1)
            ->andReturn($order);

        $this->gateway->mercadopago->helpers->cart
            ->expects()
            ->calculateSubtotalWithDiscount($this->gateway)
            ->andReturn($this->gateway->discount = 0);

        $this->gateway->mercadopago->helpers->cart
            ->expects()
            ->calculateSubtotalWithCommission($this->gateway)
            ->andReturn($this->gateway->commission = 0);

        $productionMode = $isTestMode ? 'no' : 'yes';

        $this->gateway->mercadopago->storeConfig
            ->expects()
            ->getProductionMode()
            ->andReturn($productionMode);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->setIsProductionModeData($order, $productionMode)
            ->andReturnSelf();

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->setUsedGatewayData($order, 'woo-mercado-pago-credits')
            ->andReturnSelf();

        $_POST['wc-woo-mercado-pago-credits-new-payment-method'] = $isBlocks ? 1 : null;

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->markPaymentAsBlocks($order, $isBlocks ? 'yes' : 'no')
            ->andReturnSelf();

        Mockery::mock('overload:' . CreditsTransaction::class)
            ->expects()
            ->createPreference()
            ->andReturn($redirect = [
                'init_point' => 'http://prodmode',
                'sandbox_init_point' => 'http://testmode',
            ]);

        $this->gateway->mercadopago->storeConfig
            ->expects()
            ->isTestMode()
            ->andReturn($isTestMode);

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
                'error',
                'MercadoPago_CreditsGateway',
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
}
