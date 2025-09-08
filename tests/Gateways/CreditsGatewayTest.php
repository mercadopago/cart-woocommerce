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

        $_POST['wc-woo-mercado-pago-credits-new-payment-method'] = $isBlocks ? 1 : null;

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
}
