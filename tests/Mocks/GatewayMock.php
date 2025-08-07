<?php

namespace MercadoPago\Woocommerce\Tests\Mocks;

use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperties;
use Mockery;
use WP_Mock;

trait GatewayMock
{
    use WoocommerceMock;
    use SetNotAccessibleProperties;

    /**
     * @var Mockery\MockInterface|\WC_Order
     */
    private $order;

    public function setUp(): void
    {
        // All content on gatewaySetup() to simplify extending setUp()
        $this->gatewaySetup();
    }

    private function gatewaySetup()
    {
        $this->woocommerceSetUp();

        $this->gateway = Mockery::mock($this->gatewayClass)->makePartial();
        $this->gateway->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
    }

    private function abstractGatewayProcessPaymentMock(bool $isBlocks, bool $isTestMode = false): void
    {
        $this->order = Mockery::mock('WC_Order');

        WP_Mock::userFunction('wc_get_order')
            ->twice()
            ->with(1)
            ->andReturn($this->order);

        $this->gateway->mercadopago->helpers->cart
            ->expects()
            ->calculateSubtotalWithDiscount($this->gateway)
            ->andReturn($this->gateway->discount = 0);

        $this->gateway->mercadopago->helpers->cart
            ->expects()
            ->calculateSubtotalWithCommission($this->gateway)
            ->andReturn($this->gateway->commission = 0);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->markPaymentAsBlocks($this->order, $isBlocks ? 'yes' : 'no')
            ->andReturnSelf();

        $productionMode = $isTestMode ? 'no' : 'yes';

        $this->gateway->mercadopago->storeConfig
            ->expects()
            ->getProductionMode()
            ->andReturn($productionMode);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->setIsProductionModeData($this->order, $productionMode)
            ->andReturnSelf();

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->setUsedGatewayData($this->order, $this->gatewayClass::ID)
            ->andReturnSelf();
    }

    private function mockGatewayLinks(array $keys)
    {
        $this->setNotAccessibleProperty($this->gateway, 'links', MercadoPagoMock::fillArray($keys, random()->url()));
    }
}
