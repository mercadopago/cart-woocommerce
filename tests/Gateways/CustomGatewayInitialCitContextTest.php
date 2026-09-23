<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * @covers \MercadoPago\Woocommerce\Gateways\CustomGateway
 */
class CustomGatewayInitialCitContextTest extends TestCase
{
    use GatewayMock;

    private string $gatewayClass = CustomGateway::class;

    /**
     * @var \Mockery\MockInterface|CustomGateway
     */
    private $gateway;

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitialCitContextIsFalseForRegularCheckout(): void
    {
        $this->loadWcsContext(false);
        $this->mockAutomaticPaymentsOptions();
        $this->mockRequestContext(false, 0);

        $this->assertFalse($this->invokeInitialCitContext());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitialCitContextIsTrueForSubscriptionCartAndRendersFlag(): void
    {
        $this->loadWcsContext(true);
        $this->mockAutomaticPaymentsOptions();
        $this->mockRequestContext(false, 0);

        $params = $this->getPaymentFieldsParams();

        $this->assertTrue($params['is_cit_initial_context']);
        $this->assertStringContainsString(
            'data-mp-cit-initial-context="true"',
            $this->renderCustomCheckout($params)
        );

        $params['is_cit_initial_context'] = false;
        $this->assertStringContainsString(
            'data-mp-cit-initial-context="false"',
            $this->renderCustomCheckout($params)
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitialCitContextIsTrueForInitialSubscriptionOrderPay(): void
    {
        $this->loadWcsContext(false, 'parent');
        $this->mockAutomaticPaymentsOptions();
        $this->mockRequestContext(false, 42);

        $order = Mockery::mock(\WC_Order::class);
        WP_Mock::userFunction('wc_get_order')->once()->with(42)->andReturn($order);

        $this->assertTrue($this->invokeInitialCitContext());
        $this->assertSame(
            ['parent', 'resubscribe', 'switch'],
            $GLOBALS['__psw_4486_received_order_types']
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitialCitContextIsFalseWhenAutomaticPaymentsAreOff(): void
    {
        $this->loadWcsContext(true);
        $this->mockAutomaticPaymentsOptions('yes', 'yes');

        $this->gateway->mercadopago->helpers->url->shouldNotReceive('validateGetVar');
        WP_Mock::userFunction('get_query_var')->never();

        $this->assertFalse($this->invokeInitialCitContext());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitialCitContextIsFalseForChangePaymentQuery(): void
    {
        $this->loadWcsContext(true);
        $this->mockAutomaticPaymentsOptions();
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('validateGetVar')
            ->once()
            ->with('change_payment_method')
            ->andReturn(true);
        WP_Mock::userFunction('get_query_var')->never();

        $this->assertFalse($this->invokeInitialCitContext());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitialCitContextIsFalseForChangePaymentRuntimeFlag(): void
    {
        $this->loadWcsContext(true);
        \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = true;
        $this->mockAutomaticPaymentsOptions();

        $this->gateway->mercadopago->helpers->url->shouldNotReceive('validateGetVar');
        WP_Mock::userFunction('get_query_var')->never();

        $this->assertFalse($this->invokeInitialCitContext());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInitialCitContextIsFalseForRenewalOrderPay(): void
    {
        $this->loadWcsContext(false, 'renewal');
        $this->mockAutomaticPaymentsOptions();
        $this->mockRequestContext(false, 84);

        $order = Mockery::mock(\WC_Order::class);
        WP_Mock::userFunction('wc_get_order')->once()->with(84)->andReturn($order);

        $this->assertFalse($this->invokeInitialCitContext());
        $this->assertSame(
            ['parent', 'resubscribe', 'switch'],
            $GLOBALS['__psw_4486_received_order_types'],
            'Using "any" here would incorrectly classify renewal orders as an initial CIT.'
        );
    }

    private function loadWcsContext(bool $cartContainsSubscription, string $orderKind = 'standard'): void
    {
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!class_exists('WC_Subscriptions_Cart')) {
            eval(
                'class WC_Subscriptions_Cart {'
                . ' public static function cart_contains_subscription(): bool {'
                . ' return (bool) ($GLOBALS["__psw_4486_cart_contains_subscription"] ?? false);'
                . ' }'
                . '}'
            );
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            eval('class WC_Subscriptions_Change_Payment_Gateway { public static $is_request_to_change_payment = false; }');
        }
        if (!function_exists('wcs_order_contains_subscription')) {
            eval(
                'function wcs_order_contains_subscription('
                . '$order, $orderTypes = ["parent", "resubscribe", "switch"]'
                . ') {'
                . ' $types = (array) $orderTypes;'
                . ' $GLOBALS["__psw_4486_received_order_types"] = $types;'
                . ' $kind = $GLOBALS["__psw_4486_order_kind"] ?? "standard";'
                . ' return in_array("any", $types, true) || in_array($kind, $types, true);'
                . ' }'
            );
        }

        $GLOBALS['__psw_4486_cart_contains_subscription'] = $cartContainsSubscription;
        $GLOBALS['__psw_4486_order_kind'] = $orderKind;
        $GLOBALS['__psw_4486_received_order_types'] = null;
        \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;
    }

    private function mockAutomaticPaymentsOptions(string $acceptManual = 'no', string $turnOffAutomatic = 'no'): void
    {
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_accept_manual_renewals', 'no')
            ->andReturn($acceptManual)
            ->byDefault();
        WP_Mock::userFunction('get_option')
            ->with('woocommerce_subscriptions_turn_off_automatic_payments', 'no')
            ->andReturn($turnOffAutomatic)
            ->byDefault();
    }

    private function mockRequestContext(bool $isChangePayment, int $orderPayId): void
    {
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('validateGetVar')
            ->once()
            ->with('change_payment_method')
            ->andReturn($isChangePayment);
        WP_Mock::userFunction('get_query_var')
            ->once()
            ->with('order-pay')
            ->andReturn($orderPayId);
    }

    private function invokeInitialCitContext(): bool
    {
        $method = (new \ReflectionClass(CustomGateway::class))
            ->getMethod('isInitialSubscriptionPaymentContext');
        $method->setAccessible(true);

        return $method->invoke($this->gateway);
    }

    private function getPaymentFieldsParams(): array
    {
        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getAmountAndCurrency')
            ->once()
            ->andReturn(['amount' => 0.0, 'currencyRatio' => 1.0]);
        $this->gateway->shouldReceive('getWalletButtonEnabled')->once()->andReturn(false);
        $this->gateway->mercadopago->sellerConfig
            ->shouldReceive('getSiteId')
            ->andReturn('MLB');
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getImageAsset')
            ->andReturn('https://example.com/asset.svg');
        $this->gateway->mercadopago->helpers->links
            ->shouldReceive('getPrivacyPolicyLink')
            ->andReturn('https://example.com/privacy');

        $property = (new \ReflectionClass($this->gateway))->getProperty('countryConfigs');
        $property->setAccessible(true);
        $property->setValue($this->gateway, ['site_id' => 'MLB']);

        return $this->gateway->getPaymentFieldsParams();
    }

    private function renderCustomCheckout(array $params): string
    {
        WP_Mock::userFunction('esc_attr')->andReturnArg(0);
        WP_Mock::userFunction('esc_html')->andReturnArg(0);
        WP_Mock::userFunction('esc_textarea')->andReturnArg(0);
        WP_Mock::userFunction('esc_url')->andReturnArg(0);

        extract($params, EXTR_SKIP);

        ob_start();
        require __DIR__ . '/../../templates/public/checkouts/custom-checkout.php';

        return (string) ob_get_clean();
    }
}
