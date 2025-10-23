<?php

namespace MercadoPago\Woocommerce\Tests\Traits;

use MercadoPago\Woocommerce\Tests\Mocks\ArrayMock;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use Mockery;
use WP_Mock;

trait GatewayMock
{
    use WoocommerceMock;
    use SetNotAccessibleProperty;

    /**
     * @var Mockery\MockInterface|\WC_Order
     */
    private $order;

    /**
     * @before
     */
    public function gatewaySetup()
    {
        $this->gateway = Mockery::mock($this->gatewayClass)->makePartial();
        $this->gateway->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        MercadoPagoMock::mockTranslations($this->gateway, ['storeTranslations', 'adminTranslations']);
        $this->setNotAccessibleProperty($this->gateway, 'links', new ArrayMock(fn() => random()->url()));

        // Initialize settings property to avoid undefined property errors
        $this->gateway->settings = [
            'currency_conversion' => 'no',
            'enabled' => 'yes',
            'title' => 'Test Gateway',
        ];
    }

    private function processPaymentInternalMock(bool $isBlocks): void
    {
        $this->order = Mockery::mock(\WC_Order::class);

        $this->gateway->mercadopago->orderMetadata
            ->expects()
            ->markPaymentAsBlocks($this->order, $isBlocks ? 'yes' : 'no')
            ->andReturnSelf();
    }

    private function abstractGatewayProcessPaymentMock(bool $isBlocks, bool $isTestMode = false): void
    {
        $this->processPaymentInternalMock($isBlocks);

        WP_Mock::userFunction('wc_get_order')
            ->once()
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
}
