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

        // Valid credentials by default; override in tests that exercise the missing-credentials path.
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsPublicKey')->byDefault()->andReturn('APP_USR-public-key');
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCredentialsAccessToken')->byDefault()->andReturn('APP_USR-access-token');
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getCustIdFromAT')->byDefault()->andReturn('test-cust-id');

        $this->gateway->mercadopago->storeConfig
            ->shouldReceive('getProductionMode')->byDefault()->andReturn('yes');

        // Initialize datadog property to avoid uninitialized property errors
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Metrics\Datadog::class);
        $datadogMock->shouldReceive('sendEvent')->byDefault();
        $this->gateway->datadog = $datadogMock;

        // AbstractGateway::registerCheckoutScripts() resolves the checkout validation
        // endpoint URL via WC_AJAX::get_endpoint(); provide a default so the shared
        // script registration does not fail. Specific tests may override this.
        Mockery::mock('alias:WC_AJAX')
            ->shouldReceive('get_endpoint')
            ->byDefault()
            ->andReturn('https://store.test/?wc-ajax=endpoint');

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
