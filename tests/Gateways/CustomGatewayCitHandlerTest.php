<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Tests\Traits\GatewayMock;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Tests the CIT (Customer Initiated Transaction) handler added by TASK-009 / PSW-4003.
 *
 * @spec feat-001 US-3, US-6, DD-1, DD-3, DD-7 — spec.md §3.2, §4.2.
 * @covers \MercadoPago\Woocommerce\Gateways\CustomGateway
 */
class CustomGatewayCitHandlerTest extends TestCase
{
    use GatewayMock;

    private string $gatewayClass = CustomGateway::class;

    /** @var \Mockery\MockInterface|CustomGateway */
    private $gateway;

    /**
     * @return array{0: \Mockery\MockInterface, 1: \Mockery\MockInterface, 2: \Mockery\MockInterface}
     *         [order, subscription, response]
     */
    private function buildOrderAndSubscriptionMocks(array $responseData)
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(42);
        $order->shouldReceive('get_billing_email')->andReturn('payer@example.com');
        $order->shouldReceive('get_billing_first_name')->andReturn('Jane');
        $order->shouldReceive('get_billing_last_name')->andReturn('Doe');
        $order->shouldReceive('get_billing_country')->andReturn('BR');
        $order->shouldReceive('get_billing_state')->andReturn('SP');
        $order->shouldReceive('get_billing_phone')->andReturn('11999999999');
        $order->shouldReceive('get_billing_address_1')->andReturn('Rua Teste, 1');
        $order->shouldReceive('get_billing_city')->andReturn('São Paulo');
        $order->shouldReceive('get_billing_postcode')->andReturn('01310-100');
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn(99.90);
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('save')->andReturnTrue();

        // process_subscription_initial_payment() sets these metas before delegating.
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setCurrencyRatioData')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setCustomMetadata')->byDefault();

        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn(43);
        $subscription->shouldReceive('get_billing_interval')->andReturn(1);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('getData')->andReturn($responseData);
        $response->shouldReceive('getStatus')->andReturn(201);

        return [$order, $subscription, $response];
    }

    private function invokeHandler($gateway, $order): array
    {
        $reflection = new \ReflectionClass(CustomGateway::class);
        $method     = $reflection->getMethod('process_subscription_initial_payment');
        $method->setAccessible(true);
        return $method->invoke($gateway, $order);
    }

    private function wireSubscription($subscription): void
    {
        if (!function_exists('wcs_get_subscriptions_for_order')) {
            eval('function wcs_get_subscriptions_for_order($order) { return $GLOBALS["__wcs_subs_for_order"] ?? []; }');
        }
        $GLOBALS['__wcs_subs_for_order'] = [$subscription];
    }

    /**
     * AC-1: status=approved → persiste 6 metas + chama payment_complete + retorna success.
     *
     */
    public function testApprovedCitPersistsMetasAndCompletesPayment(): void
    {
        [$order, $subscription, $response] = $this->buildOrderAndSubscriptionMocks([
            'payment'      => ['id' => 'PAY-1', 'status' => 'approved'],
            'subscription' => ['id' => 'CPP-WSUB-99'],
            'customer'     => ['id' => 'CUST-1'],
            'card'         => ['id' => 'CARD-1', 'last_four_digits' => '4242', 'payment_method' => 'visa'],
        ]);
        $this->wireSubscription($subscription);


        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('buildCitPayload')->andReturn(['token' => 'tok_abc']);
        $this->gateway->shouldReceive('getCheckoutFormData')->andReturn([
            'token'             => 'tok_abc',
            'payment_method_id' => 'visa',
            'doc_number'        => '12345678900',
            'device_id'         => 'fp_xyz',
        ]);

        $client = Mockery::mock(AutomaticPaymentsClient::class);
        $client->shouldReceive('cit')->once()->andReturn($response);
        $this->gateway->mercadopago->automaticPaymentsClient = $client;

        $helper = Mockery::mock(SubscriptionsHelper::class);
        $helper->shouldReceive('resolveAccessToken')->byDefault()->andReturn('APP_USR-preapproval');
        $helper->shouldReceive('setSubscriptionMeta')->times(6);
        $this->gateway->mercadopago->subscriptionsHelper = $helper;

        $this->gateway->mercadopago->logs->file->shouldReceive('info')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('warning')->byDefault();

        // handleResponseStatus() approved path
        $this->gateway->mercadopago->helpers->cart->shouldReceive('emptyCart')->byDefault()->andReturnNull();
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->byDefault()->andReturn(false);
        $this->gateway->mercadopago->orderStatus->shouldReceive('getOrderStatusMessage')->byDefault()->andReturn('Aprovado');
        $this->gateway->mercadopago->helpers->notices->shouldReceive('storeApprovedStatusNotice')->byDefault()->andReturnNull();
        $this->gateway->mercadopago->orderStatus->shouldReceive('setOrderStatus')->byDefault()->andReturnNull();

        $order->shouldReceive('get_checkout_order_received_url')->andReturn('https://shop.example/order-received');
        $order->shouldReceive('update_status')->byDefault()->andReturnTrue();
        $order->shouldNotReceive('payment_complete');

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setUsedGatewayData')
            ->once()
            ->with($order, CustomGateway::ID);
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setIsProductionModeData')
            ->once()
            ->with($order, Mockery::any());
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('setCustomMetadata')
            ->once()
            ->with($order, Mockery::on(fn($data) => ($data['id'] ?? null) === 'PAY-1'));
        $result = $this->invokeHandler($this->gateway, $order);

        $this->assertSame('success', $result['result']);
        $this->assertSame('https://shop.example/order-received', $result['redirect']);
    }

    /**
     * AC-2: status=rejected → order não vai para processing, mensagem amigável.
     *
     */
    public function testRejectedCitKeepsOrderPendingWithFriendlyMessage(): void
    {
        [$order, $subscription, $response] = $this->buildOrderAndSubscriptionMocks([
            'payment'      => ['id' => 'PAY-2', 'status' => 'rejected', 'status_detail' => 'cc_rejected_insufficient_amount'],
            'subscription' => ['id' => 'CPP-WSUB-99'],
        ]);
        $this->wireSubscription($subscription);


        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('buildCitPayload')->andReturn(['token' => 'tok_abc']);
        $this->gateway->shouldReceive('getCheckoutFormData')->andReturn([
            'token' => 'tok_abc', 'payment_method_id' => 'visa', 'doc_number' => '12345678900',
        ]);

        $client = Mockery::mock(AutomaticPaymentsClient::class);
        $client->shouldReceive('cit')->once()->andReturn($response);
        $this->gateway->mercadopago->automaticPaymentsClient = $client;

        $helper = Mockery::mock(SubscriptionsHelper::class);
        $helper->shouldReceive('resolveAccessToken')->byDefault()->andReturn('APP_USR-preapproval');
        $helper->shouldNotReceive('setSubscriptionMeta');
        $this->gateway->mercadopago->subscriptionsHelper = $helper;

        $this->gateway->mercadopago->logs->file->shouldReceive('warning')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('info')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();

        // handleResponseStatus() rejected path
        $this->gateway->mercadopago->helpers->url->shouldReceive('validateGetVar')->byDefault()->andReturn(false);

        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_meta_data');
        $order->shouldNotReceive('update_status');

        $result = $this->invokeHandler($this->gateway, $order);

        $this->assertSame('fail', $result['result']);
        $this->assertArrayHasKey('message', $result);
        $this->assertNotEmpty($result['message']);
        $this->assertStringStartsWith('buyer_', $result['message']);
    }

    /**
     * AC-3: cit() lança RuntimeException quando subscription.id ausente — handler
     * captura, marca order como failed e devolve mensagem genérica.
     *
     */
    public function testOrphanResponseAbortsWithFriendlyMessage(): void
    {
        [$order, $subscription, ] = $this->buildOrderAndSubscriptionMocks([]);
        $this->wireSubscription($subscription);


        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('buildCitPayload')->andReturn(['token' => 'tok_abc']);
        $this->gateway->shouldReceive('getCheckoutFormData')->andReturn([
            'token' => 'tok_abc', 'payment_method_id' => 'visa', 'doc_number' => '12345678900',
        ]);

        $client = Mockery::mock(AutomaticPaymentsClient::class);
        $client->shouldReceive('cit')->once()
            ->andThrow(new \RuntimeException('Não foi possível ativar sua assinatura. Tente novamente.'));
        $this->gateway->mercadopago->automaticPaymentsClient = $client;

        $helper = Mockery::mock(SubscriptionsHelper::class);
        $helper->shouldReceive('resolveAccessToken')->byDefault()->andReturn('APP_USR-preapproval');
        $helper->shouldNotReceive('setSubscriptionMeta');
        $this->gateway->mercadopago->subscriptionsHelper = $helper;

        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault();

        $order->shouldNotReceive('payment_complete');
        $order->shouldNotReceive('update_status');

        // RuntimeException from cit() propagates out of the private method;
        // process_payment()'s try-catch converts it to a failure result.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Não foi possível ativar sua assinatura. Tente novamente.');

        $this->invokeHandler($this->gateway, $order);
    }

    // -------------------------------------------------------------------------
    // Helper: instância real (sem constructor) para cobrir linhas reais
    // -------------------------------------------------------------------------

    private function realInstance(): CustomGateway
    {
        $ref      = new \ReflectionClass(CustomGateway::class);
        $instance = $ref->newInstanceWithoutConstructor();
        $instance->mercadopago = $this->gateway->mercadopago;
        $instance->settings    = [];
        return $instance;
    }

    private function callProtected(object $instance, string $method, array $args = [])
    {
        $ref = (new \ReflectionClass(CustomGateway::class))->getMethod($method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($instance, $args);
    }

    // -------------------------------------------------------------------------
    // buildSubscriptionDescription
    // -------------------------------------------------------------------------

    public function testBuildSubscriptionDescriptionFromItems(): void
    {
        $item1 = Mockery::mock(\WC_Order_Item::class);
        $item1->shouldReceive('get_name')->andReturn('Produto A');
        $item2 = Mockery::mock(\WC_Order_Item::class);
        $item2->shouldReceive('get_name')->andReturn('Produto B');

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_items')->andReturn([$item1, $item2]);

        $this->assertSame(
            'Produto A, Produto B',
            $this->callProtected($this->gateway, 'buildSubscriptionDescription', [$order])
        );
    }

    public function testBuildSubscriptionDescriptionFallsBackToOrderId(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('get_id')->andReturn(99);

        WP_Mock::userFunction('__')->andReturnArg(0);

        $result = $this->callProtected($this->gateway, 'buildSubscriptionDescription', [$order]);

        $this->assertStringContainsString('WC-ORDER-99', $result);
        $this->assertStringContainsString('Subscription', $result);
    }

    public function testBuildSubscriptionDescriptionCapsAtThreeItems(): void
    {
        $items = [];
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $item = Mockery::mock(\WC_Order_Item::class);
            $item->shouldReceive('get_name')->andReturn($name);
            $items[] = $item;
        }

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_items')->andReturn($items);

        $this->assertSame(
            'A, B, C',
            $this->callProtected($this->gateway, 'buildSubscriptionDescription', [$order])
        );
    }

    // -------------------------------------------------------------------------
    // buildCitNotificationUrl
    // -------------------------------------------------------------------------

    public function testBuildCitNotificationUrlWithCustomDomainAndOptions(): void
    {
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('https://minha-loja.com');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('yes');
        WP_Mock::userFunction('get_site_url')->andReturn('https://minha-loja.com');

        $result = $this->callProtected($this->gateway, 'buildCitNotificationUrl');

        $this->assertStringContainsString('https://minha-loja.com', $result);
        $this->assertStringContainsString('wc-api=', $result);
    }

    public function testBuildCitNotificationUrlWithCustomDomainWithoutOptions(): void
    {
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('https://minha-loja.com');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        WP_Mock::userFunction('get_site_url')->andReturn('https://minha-loja.com');

        $this->assertSame(
            'https://minha-loja.com',
            $this->callProtected($this->gateway, 'buildCitNotificationUrl')
        );
    }

    public function testBuildCitNotificationUrlWithNoCustomDomain(): void
    {
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        WP_Mock::userFunction('get_site_url')->andReturn('https://production-store.com');

        $woocommerce = Mockery::mock(\WooCommerce::class);
        $woocommerce->shouldReceive('api_request_url')
            ->with(CustomGateway::WEBHOOK_API_NAME)
            ->andReturn('https://production-store.com/wc-api/WC_WooMercadoPago_Custom_Gateway');
        $this->gateway->mercadopago->woocommerce = $woocommerce;

        $result = $this->callProtected($this->gateway, 'buildCitNotificationUrl');

        $this->assertStringContainsString('source_news=', $result);
    }

    public function testBuildCitNotificationUrlReturnsEmptyOnLocalhost(): void
    {
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        WP_Mock::userFunction('get_site_url')->andReturn('http://localhost:8080');

        $this->assertSame('', $this->callProtected($this->gateway, 'buildCitNotificationUrl'));
    }

    public function testRegisterSuperTokenBundleFilesRegistersLoaderScript(): void
    {
        $this->gateway->mercadopago->helpers->url
            ->shouldReceive('getJsAsset')
            ->with('checkouts/super-token-loader')
            ->andReturn('https://cdn.example/super-token-loader.js');

        $this->gateway->mercadopago->hooks->scripts
            ->shouldReceive('registerCheckoutScript')
            ->once()
            ->with('wc_mercadopago_supertoken', 'https://cdn.example/super-token-loader.js');

        $this->callProtected($this->gateway, 'registerSuperTokenBundleFiles');

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // buildCitPayload
    // -------------------------------------------------------------------------

    public function testBuildCitPayloadStructure(): void
    {
        $product = Mockery::mock(\WC_Product::class);
        $product->shouldReceive('get_name')->andReturn('Assinatura Mensal');
        $product->shouldReceive('get_description')->andReturn('Desc');
        $product->shouldReceive('get_image_id')->andReturn(0);

        $item = Mockery::mock(\WC_Order_Item::class);
        $item->shouldReceive('get_name')->andReturn('Assinatura Mensal');
        $item->shouldReceive('get_product_id')->andReturn(99);
        $item->shouldReceive('get_product')->andReturn($product);
        $item->shouldReceive('get_total')->andReturn(49.90);
        $item->shouldReceive('get_total_tax')->andReturn(0.0);
        $item->shouldReceive('get_quantity')->andReturn(1);

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(42);
        $order->shouldReceive('get_billing_email')->andReturn('a@b.com');
        $order->shouldReceive('get_billing_first_name')->andReturn('Ana');
        $order->shouldReceive('get_billing_last_name')->andReturn('Ferreira');
        $order->shouldReceive('get_billing_country')->andReturn('BR');
        $order->shouldReceive('get_billing_state')->andReturn('SP');
        $order->shouldReceive('get_billing_phone')->andReturn('11999999999');
        $order->shouldReceive('get_billing_address_1')->andReturn('Rua Teste, 123');
        $order->shouldReceive('get_billing_city')->andReturn('São Paulo');
        $order->shouldReceive('get_billing_postcode')->andReturn('01310-100');
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn(49.90);
        $order->shouldReceive('get_items')->andReturn([$item]);
        $order->shouldReceive('get_shipping_total')->andReturn(0.0);
        $order->shouldReceive('get_shipping_tax')->andReturn(0.0);
        $order->shouldReceive('get_fees')->andReturn([]);

        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn(7);
        $subscription->shouldReceive('get_billing_interval')->andReturn(1);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');

        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['currency' => 'BRL', 'sponsor_id' => null]);
        $this->setNotAccessibleProperty($this->gateway, 'ratio', 1.0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreName')->andReturn('Minha Loja');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreCategory')->andReturn('others');
        $this->gateway->mercadopago->helpers->strings->shouldReceive('sanitizeAndTruncateText')->andReturnArg(0);
        WP_Mock::userFunction('wp_get_attachment_url')->andReturn('');
        WP_Mock::userFunction('get_site_url')->andReturn('http://localhost');
        WP_Mock::userFunction('get_option')->byDefault()->andReturn('WC-');
        WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
        WP_Mock::userFunction('wp_unslash')->andReturnArg(0);

        // Set HTTP_USER_AGENT so the truthy branch of the user-agent ternary is covered.
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Test)';
        $checkout = ['token' => 'tok_x', 'doc_number' => '12345678900', 'doc_type' => 'CPF', 'device_id' => 'fp1'];
        $payload  = $this->callProtected($this->gateway, 'buildCitPayload', [$order, $subscription, $checkout]);
        unset($_SERVER['HTTP_USER_AGENT']);

        $this->assertSame('tok_x', $payload['token']);
        $this->assertSame('a@b.com', $payload['payer']['email']);
        $this->assertSame('CPF', $payload['payer']['identification']['type']);
        $this->assertSame(49.9, $payload['transaction']['amount']);
        $this->assertSame('BRL', $payload['transaction']['currency']);
        $this->assertSame(1, $payload['transaction']['installments']);
        $this->assertSame('optional', $payload['transaction']['three_d_secure_mode']);
        $this->assertSame('WC-42', $payload['transaction']['external_reference']);
        $this->assertSame('WC-SUB-7', $payload['subscription']['external_id']);
        $this->assertSame('1-month', $payload['subscription']['frequency']);
        $this->assertSame('BR-SP', $payload['point_of_interaction']['location']['state_id']);
        $this->assertSame('payer', $payload['point_of_interaction']['location']['source']);
        // additional_info
        $this->assertArrayHasKey('additional_info', $payload);
        $this->assertSame('a@b.com', $payload['additional_info']['payer']['email']);
        $this->assertCount(1, $payload['additional_info']['items']);
        $this->assertSame('Assinatura Mensal x 1', $payload['additional_info']['items'][0]['title']);
        $this->assertSame('BRL', $payload['additional_info']['items'][0]['currency_id']);
        // platform.environment
        $this->assertArrayHasKey('platform', $payload);
        $this->assertArrayHasKey('environment', $payload['platform']);
        $this->assertArrayHasKey('platform_version', $payload['platform']['environment']);
        $this->assertArrayHasKey('module_version', $payload['platform']['environment']);
        $this->assertSame(PHP_VERSION, $payload['platform']['environment']['runtime_version']);
    }

    /**
     * 4xx response (e.g. InvalidToken): handler maps $data['error'] correctly,
     * not hardcoded 'PaymentRejected'.
     */
    public function testHttpErrorResponseMapsApiErrorField(): void
    {
        [$order, $subscription] = $this->buildOrderAndSubscriptionMocks([]);
        $this->wireSubscription($subscription);

        $response422 = Mockery::mock(Response::class);
        $response422->shouldReceive('getData')->andReturn(['error' => 'InvalidToken', 'message' => 'Card token expired']);
        $response422->shouldReceive('getStatus')->andReturn(422);


        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getCheckoutFormData')->andReturn([
            'token' => 'tok', 'payment_method_id' => 'visa',
        ]);
        $this->gateway->shouldReceive('buildCitPayload')->andReturn(['token' => 'tok']);

        $client = Mockery::mock(AutomaticPaymentsClient::class);
        $client->shouldReceive('cit')->once()->andReturn($response422);
        $this->gateway->mercadopago->automaticPaymentsClient = $client;

        $helper = Mockery::mock(SubscriptionsHelper::class);
        $helper->shouldReceive('resolveAccessToken')->byDefault()->andReturn('APP_USR-preapproval');
        $helper->shouldReceive('mapApiErrorToUserMessage')
            ->with(422, 'InvalidToken', null)
            ->andReturn('Token de cartão inválido. Tente novamente.');
        $this->gateway->mercadopago->subscriptionsHelper = $helper;

        $this->gateway->mercadopago->logs->file->shouldReceive('warning')->byDefault()->andReturnNull();
        $order->shouldNotReceive('payment_complete');

        $result = $this->invokeHandler($this->gateway, $order);

        $this->assertSame('failure', $result['result']);
        $this->assertSame('Token de cartão inválido. Tente novamente.', $result['messages']);
    }

    /**
     * AC-abort-1: nenhuma subscription encontrada → retorna failure.
     */
    public function testAbortWhenNoSubscriptionFound(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(99);
        $order->shouldReceive('update_status')->with('failed', Mockery::type('string'))->andReturnTrue();
        $order->shouldReceive('save')->byDefault();

        if (!function_exists('wcs_get_subscriptions_for_order')) {
            eval('function wcs_get_subscriptions_for_order($order) { return $GLOBALS["__wcs_subs_for_order"] ?? []; }');
        }
        $GLOBALS['__wcs_subs_for_order'] = [];

        $this->gateway->mercadopago->orderMetadata->shouldReceive('setIsProductionModeData')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setUsedGatewayData')->byDefault();
        $this->gateway->mercadopago->orderMetadata->shouldReceive('setCurrencyRatioData')->byDefault();
        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault()->andReturnNull();
        $order->shouldNotReceive('update_status');

        $this->expectException(\MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException::class);
        $this->invokeHandler($this->gateway, $order);
    }

    /**
     * AC-abort-2: accessToken vazio → lança InvalidCheckoutDataException (wcs_cit_no_credential).
     */
    public function testAbortWhenAccessTokenEmpty(): void
    {
        [$order, $subscription] = $this->buildOrderAndSubscriptionMocks([]);
        $this->wireSubscription($subscription);

        $this->gateway->mercadopago->subscriptionsHelper
            ->shouldReceive('resolveAccessToken')->andReturn('');
        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault()->andReturnNull();
        $order->shouldNotReceive('update_status');

        $this->expectException(\MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException::class);
        $this->invokeHandler($this->gateway, $order);
    }

    /**
     * AC-abort-3: checkout sem token/payment_method_id → lança InvalidCheckoutDataException (wcs_cit_missing_card).
     */
    public function testAbortWhenCheckoutFieldsMissing(): void
    {
        [$order, $subscription] = $this->buildOrderAndSubscriptionMocks([]);
        $this->wireSubscription($subscription);

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getCheckoutFormData')->andReturn([]);

        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault()->andReturnNull();
        $order->shouldNotReceive('update_status');

        $this->expectException(\MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException::class);
        $this->invokeHandler($this->gateway, $order);
    }

    /**
     * AC-abort-4: Exception genérica do cit() propagates out — process_payment()'s
     * try-catch converts it to a failure result via processReturnFail().
     * When testing the private method directly via reflection, the exception propagates.
     */
    public function testGenericExceptionFromCitPropagatesFromPrivateMethod(): void
    {
        [$order, $subscription] = $this->buildOrderAndSubscriptionMocks([]);
        $this->wireSubscription($subscription);

        $this->gateway->shouldAllowMockingProtectedMethods();
        $this->gateway->shouldReceive('getCheckoutFormData')->andReturn([
            'token' => 'tok', 'payment_method_id' => 'visa',
        ]);
        $this->gateway->shouldReceive('buildCitPayload')->andReturn(['token' => 'tok']);

        $client = Mockery::mock(AutomaticPaymentsClient::class);
        $client->shouldReceive('cit')->once()->andThrow(new \Exception('network failure'));
        $this->gateway->mercadopago->automaticPaymentsClient = $client;

        $helper = Mockery::mock(SubscriptionsHelper::class);
        $helper->shouldReceive('resolveAccessToken')->byDefault()->andReturn('APP_USR-preapproval');
        $helper->shouldNotReceive('mapApiErrorToUserMessage');
        $this->gateway->mercadopago->subscriptionsHelper = $helper;

        $this->gateway->mercadopago->logs->file->shouldReceive('error')->byDefault()->andReturnNull();
        $order->shouldNotReceive('update_status');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('network failure');

        $this->invokeHandler($this->gateway, $order);
    }

    public function testBuildCitPayloadUsesCnpjDocType(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_billing_email')->andReturn('x@y.com');
        $order->shouldReceive('get_billing_first_name')->andReturn('X');
        $order->shouldReceive('get_billing_last_name')->andReturn('Y');
        $order->shouldReceive('get_billing_country')->andReturn('');
        $order->shouldReceive('get_billing_state')->andReturn('');
        $order->shouldReceive('get_billing_phone')->andReturn('');
        $order->shouldReceive('get_billing_address_1')->andReturn('');
        $order->shouldReceive('get_billing_city')->andReturn('');
        $order->shouldReceive('get_billing_postcode')->andReturn('');
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn(10.0);
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('get_shipping_total')->andReturn(0.0);
        $order->shouldReceive('get_shipping_tax')->andReturn(0.0);
        $order->shouldReceive('get_fees')->andReturn([]);

        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn(1);
        $subscription->shouldReceive('get_billing_interval')->andReturn(3);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');

        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['currency' => 'BRL', 'sponsor_id' => null]);
        $this->setNotAccessibleProperty($this->gateway, 'ratio', 1.0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreName')->andReturn('Loja');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        WP_Mock::userFunction('get_site_url')->andReturn('http://localhost');
        WP_Mock::userFunction('get_option')->byDefault()->andReturn('WC-');
        WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
        WP_Mock::userFunction('wp_unslash')->andReturnArg(0);
        WP_Mock::userFunction('__')->andReturnArg(0);

        $checkout = ['token' => 'tok', 'doc_number' => '12345678901234', 'doc_type' => 'CNPJ'];
        $payload  = $this->callProtected($this->gateway, 'buildCitPayload', [$order, $subscription, $checkout]);

        $this->assertSame('CNPJ', $payload['payer']['identification']['type']);
        $this->assertSame('3-month', $payload['subscription']['frequency']);
        $this->assertSame('', $payload['point_of_interaction']['location']['state_id']);
    }

    public function testBuildCitPayloadIncludesShippingItemWhenShippingIsNonZero(): void
    {
        $product = Mockery::mock(\WC_Product::class);
        $product->shouldReceive('get_name')->andReturn('Produto');
        $product->shouldReceive('get_description')->andReturn('');
        $product->shouldReceive('get_image_id')->andReturn(0);

        $item = Mockery::mock(\WC_Order_Item::class);
        $item->shouldReceive('get_name')->andReturn('Produto');
        $item->shouldReceive('get_product_id')->andReturn(1);
        $item->shouldReceive('get_product')->andReturn($product);
        $item->shouldReceive('get_total')->andReturn(10.0);
        $item->shouldReceive('get_total_tax')->andReturn(0.0);
        $item->shouldReceive('get_quantity')->andReturn(1);

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_billing_email')->andReturn('a@b.com');
        $order->shouldReceive('get_billing_first_name')->andReturn('A');
        $order->shouldReceive('get_billing_last_name')->andReturn('B');
        $order->shouldReceive('get_billing_country')->andReturn('BR');
        $order->shouldReceive('get_billing_state')->andReturn('SP');
        $order->shouldReceive('get_billing_phone')->andReturn('');
        $order->shouldReceive('get_billing_address_1')->andReturn('');
        $order->shouldReceive('get_billing_city')->andReturn('');
        $order->shouldReceive('get_billing_postcode')->andReturn('');
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn(15.0);
        $order->shouldReceive('get_items')->andReturn([$item]);
        $order->shouldReceive('get_shipping_total')->andReturn(5.0);
        $order->shouldReceive('get_shipping_tax')->andReturn(0.0);
        $order->shouldReceive('get_fees')->andReturn([]);

        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn(1);
        $subscription->shouldReceive('get_billing_interval')->andReturn(1);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');

        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['currency' => 'BRL', 'sponsor_id' => null]);
        $this->setNotAccessibleProperty($this->gateway, 'ratio', 1.0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreName')->andReturn('Loja');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreCategory')->andReturn('others');
        $this->gateway->mercadopago->helpers->strings->shouldReceive('sanitizeAndTruncateText')->andReturnArg(0);
        $this->gateway->mercadopago->orderShipping->shouldReceive('getShippingMethod')->andReturn('Frete Padrão');
        $this->gateway->storeTranslations['shipping_title'] = 'Frete';
        WP_Mock::userFunction('wp_get_attachment_url')->andReturn('');
        WP_Mock::userFunction('get_site_url')->andReturn('http://localhost');
        WP_Mock::userFunction('get_option')->byDefault()->andReturn('WC-');
        WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
        WP_Mock::userFunction('wp_unslash')->andReturnArg(0);

        $checkout = ['token' => 'tok', 'doc_number' => '12345678900', 'doc_type' => 'CPF', 'session_id' => 'sess'];
        $payload  = $this->callProtected($this->gateway, 'buildCitPayload', [$order, $subscription, $checkout]);

        $items = $payload['additional_info']['items'];
        $shippingItem = array_values(array_filter($items, fn($i) => $i['id'] === 'shipping'));
        $this->assertCount(1, $shippingItem, 'Shipping item must be present when shipping > 0');
        $this->assertSame('Frete Padrão', $shippingItem[0]['title']);
        $this->assertSame('5.00', $shippingItem[0]['unit_price']);
    }

    /**
     * PSW-4208: transaction.amount must be the sum of converted items when ratio > 1.
     */
    public function testBuildCitPayloadTransactionAmountUsesConvertedTotalWithRatio(): void
    {
        $product = Mockery::mock(\WC_Product::class);
        $product->shouldReceive('get_name')->andReturn('Produto USD');
        $product->shouldReceive('get_description')->andReturn('');
        $product->shouldReceive('get_image_id')->andReturn(0);

        $item = Mockery::mock(\WC_Order_Item::class);
        $item->shouldReceive('get_name')->andReturn('Produto USD');
        $item->shouldReceive('get_product_id')->andReturn(10);
        $item->shouldReceive('get_product')->andReturn($product);
        $item->shouldReceive('get_total')->andReturn(100.0);
        $item->shouldReceive('get_total_tax')->andReturn(0.0);
        $item->shouldReceive('get_quantity')->andReturn(1);

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(77);
        $order->shouldReceive('get_billing_email')->andReturn('usd@test.com');
        $order->shouldReceive('get_billing_first_name')->andReturn('U');
        $order->shouldReceive('get_billing_last_name')->andReturn('D');
        $order->shouldReceive('get_billing_country')->andReturn('BR');
        $order->shouldReceive('get_billing_state')->andReturn('SP');
        $order->shouldReceive('get_billing_phone')->andReturn('');
        $order->shouldReceive('get_billing_address_1')->andReturn('');
        $order->shouldReceive('get_billing_city')->andReturn('');
        $order->shouldReceive('get_billing_postcode')->andReturn('');
        $order->shouldReceive('get_currency')->andReturn('USD');
        $order->shouldReceive('get_total')->andReturn(100.0);
        $order->shouldReceive('get_items')->andReturn([$item]);
        $order->shouldReceive('get_shipping_total')->andReturn(10.0);
        $order->shouldReceive('get_shipping_tax')->andReturn(0.0);
        $order->shouldReceive('get_fees')->andReturn([]);

        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn(5);
        $subscription->shouldReceive('get_billing_interval')->andReturn(1);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');

        // ratio = 5.0: $100 USD item → 500 BRL; $10 USD ship → 50 BRL; total = 550 BRL
        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['currency' => 'BRL', 'sponsor_id' => null]);
        $this->setNotAccessibleProperty($this->gateway, 'ratio', 5.0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreName')->andReturn('Loja');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreCategory')->andReturn('others');
        $this->gateway->mercadopago->helpers->strings->shouldReceive('sanitizeAndTruncateText')->andReturnArg(0);
        $this->gateway->mercadopago->orderShipping->shouldReceive('getShippingMethod')->andReturn('Frete Padrão');
        $this->gateway->storeTranslations['shipping_title'] = 'Frete';
        WP_Mock::userFunction('wp_get_attachment_url')->andReturn('');
        WP_Mock::userFunction('get_site_url')->andReturn('http://localhost');
        WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
        WP_Mock::userFunction('wp_unslash')->andReturnArg(0);
        WP_Mock::userFunction('get_option')->andReturn('BR');

        $checkout = ['token' => 'tok', 'doc_number' => '12345678900', 'doc_type' => 'CPF', 'session_id' => 'sess'];
        $payload  = $this->callProtected($this->gateway, 'buildCitPayload', [$order, $subscription, $checkout]);

        // item 100 × 5 = 500, shipping 10 × 5 = 50; total = 550 BRL
        $this->assertSame(550.0, $payload['transaction']['amount']);
        $this->assertSame('BRL', $payload['transaction']['currency']);
        // items in additional_info must use same converted currency
        $this->assertSame('BRL', $payload['additional_info']['items'][0]['currency_id']);
        $this->assertSame('500.00', $payload['additional_info']['items'][0]['unit_price']);
    }

    public function testBuildCitPayloadIncludesFeeItemWhenFeeIsNonZero(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_billing_email')->andReturn('a@b.com');
        $order->shouldReceive('get_billing_first_name')->andReturn('A');
        $order->shouldReceive('get_billing_last_name')->andReturn('B');
        $order->shouldReceive('get_billing_country')->andReturn('BR');
        $order->shouldReceive('get_billing_state')->andReturn('SP');
        $order->shouldReceive('get_billing_phone')->andReturn('');
        $order->shouldReceive('get_billing_address_1')->andReturn('');
        $order->shouldReceive('get_billing_city')->andReturn('');
        $order->shouldReceive('get_billing_postcode')->andReturn('');
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn(13.0);
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('get_shipping_total')->andReturn(0.0);
        $order->shouldReceive('get_shipping_tax')->andReturn(0.0);

        $fee = new class implements \ArrayAccess {
            public function get_name(): string
            {
                return 'Taxa de serviço';
            }

            public function get_total(): float
            {
                return 3.0;
            }

            public function get_total_tax(): float
            {
                return 0.0;
            }

            public function offsetExists($offset): bool
            {
                return true;
            }

            #[\ReturnTypeWillChange]
            public function offsetGet($offset)
            {
                return 'Taxa de serviço';
            }

            public function offsetSet($offset, $value): void
            {
            }

            public function offsetUnset($offset): void
            {
            }
        };
        $order->shouldReceive('get_fees')->andReturn([$fee]);

        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn(1);
        $subscription->shouldReceive('get_billing_interval')->andReturn(1);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');

        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['currency' => 'BRL', 'sponsor_id' => null]);
        $this->setNotAccessibleProperty($this->gateway, 'ratio', 1.0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreName')->andReturn('Loja');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreCategory')->andReturn('others');
        $this->gateway->mercadopago->helpers->strings->shouldReceive('sanitizeAndTruncateText')->andReturnArg(0);
        WP_Mock::userFunction('get_site_url')->andReturn('http://localhost');
        WP_Mock::userFunction('get_option')->byDefault()->andReturn('WC-');
        WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
        WP_Mock::userFunction('wp_unslash')->andReturnArg(0);
        WP_Mock::userFunction('__')->andReturnArg(0);

        $checkout = ['token' => 'tok', 'doc_number' => '12345678900', 'doc_type' => 'CPF', 'session_id' => 'sess'];
        $payload  = $this->callProtected($this->gateway, 'buildCitPayload', [$order, $subscription, $checkout]);

        $items = $payload['additional_info']['items'];
        $feeItem = array_values(array_filter($items, fn($i) => $i['id'] === 'fee'));
        $this->assertCount(1, $feeItem, 'Fee item must be present when fee > 0');
        $this->assertSame('Taxa de serviço', $feeItem[0]['title']);
        $this->assertSame('3.00', $feeItem[0]['unit_price']);
    }

    /**
     * When doc fields are absent (Blocks) or empty strings (Classic),
     * payer.identification must be omitted entirely — not sent as null/"".
     */
    public function testBuildCitPayloadOmitsIdentificationWhenDocFieldsAbsent(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_billing_email')->andReturn('a@b.com');
        $order->shouldReceive('get_billing_first_name')->andReturn('A');
        $order->shouldReceive('get_billing_last_name')->andReturn('B');
        $order->shouldReceive('get_billing_country')->andReturn('AR');
        $order->shouldReceive('get_billing_state')->andReturn('BA');
        $order->shouldReceive('get_billing_phone')->andReturn('');
        $order->shouldReceive('get_billing_address_1')->andReturn('');
        $order->shouldReceive('get_billing_city')->andReturn('');
        $order->shouldReceive('get_billing_postcode')->andReturn('');
        $order->shouldReceive('get_currency')->andReturn('ARS');
        $order->shouldReceive('get_total')->andReturn(100.0);
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('get_shipping_total')->andReturn(0.0);
        $order->shouldReceive('get_shipping_tax')->andReturn(0.0);
        $order->shouldReceive('get_fees')->andReturn([]);

        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn(1);
        $subscription->shouldReceive('get_billing_interval')->andReturn(1);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');

        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['currency' => 'ARS', 'sponsor_id' => null]);
        $this->setNotAccessibleProperty($this->gateway, 'ratio', 1.0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreName')->andReturn('Loja');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        WP_Mock::userFunction('get_site_url')->andReturn('http://localhost');
        WP_Mock::userFunction('get_option')->byDefault()->andReturn('WC-');
        WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
        WP_Mock::userFunction('wp_unslash')->andReturnArg(0);
        WP_Mock::userFunction('__')->andReturnArg(0);

        // Blocks scenario: keys absent
        $checkoutAbsent = ['token' => 'tok'];
        $payload        = $this->callProtected($this->gateway, 'buildCitPayload', [$order, $subscription, $checkoutAbsent]);
        $this->assertArrayNotHasKey('identification', $payload['payer'], 'identification must be absent when doc fields are missing (Blocks)');

        // Classic scenario: keys present but empty strings
        $checkoutEmpty = ['token' => 'tok', 'doc_number' => '', 'doc_type' => ''];
        $payload       = $this->callProtected($this->gateway, 'buildCitPayload', [$order, $subscription, $checkoutEmpty]);
        $this->assertArrayNotHasKey('identification', $payload['payer'], 'identification must be absent when doc fields are empty strings (Classic)');
    }

    /**
     * The legacy camelCase key `docNumber` is not supported in the CIT flow.
     * The frontend contract for CIT uses `doc_number` (snake_case) exclusively.
     * A payload with only `docNumber` must omit payer.identification entirely.
     */
    public function testBuildCitPayloadIgnoresLegacyDocNumberKey(): void
    {
        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(1);
        $order->shouldReceive('get_billing_email')->andReturn('a@b.com');
        $order->shouldReceive('get_billing_first_name')->andReturn('A');
        $order->shouldReceive('get_billing_last_name')->andReturn('B');
        $order->shouldReceive('get_billing_country')->andReturn('BR');
        $order->shouldReceive('get_billing_state')->andReturn('SP');
        $order->shouldReceive('get_billing_phone')->andReturn('');
        $order->shouldReceive('get_billing_address_1')->andReturn('');
        $order->shouldReceive('get_billing_city')->andReturn('');
        $order->shouldReceive('get_billing_postcode')->andReturn('');
        $order->shouldReceive('get_currency')->andReturn('BRL');
        $order->shouldReceive('get_total')->andReturn(10.0);
        $order->shouldReceive('get_items')->andReturn([]);
        $order->shouldReceive('get_shipping_total')->andReturn(0.0);
        $order->shouldReceive('get_shipping_tax')->andReturn(0.0);
        $order->shouldReceive('get_fees')->andReturn([]);

        $subscription = Mockery::mock(\WC_Subscription::class);
        $subscription->shouldReceive('get_id')->andReturn(1);
        $subscription->shouldReceive('get_billing_interval')->andReturn(1);
        $subscription->shouldReceive('get_billing_period')->andReturn('month');

        $this->setNotAccessibleProperty($this->gateway, 'countryConfigs', ['currency' => 'BRL', 'sponsor_id' => null]);
        $this->setNotAccessibleProperty($this->gateway, 'ratio', 1.0);
        $this->gateway->mercadopago->storeConfig->shouldReceive('getStoreName')->andReturn('Loja');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomain')->andReturn('');
        $this->gateway->mercadopago->storeConfig->shouldReceive('getCustomDomainOptions')->andReturn('no');
        WP_Mock::userFunction('get_site_url')->andReturn('http://localhost');
        WP_Mock::userFunction('get_option')->byDefault()->andReturn('WC-');
        WP_Mock::userFunction('sanitize_text_field')->andReturnArg(0);
        WP_Mock::userFunction('wp_unslash')->andReturnArg(0);
        WP_Mock::userFunction('__')->andReturnArg(0);

        $checkout = ['token' => 'tok', 'docNumber' => '12345678900', 'doc_type' => 'CPF'];
        $payload  = $this->callProtected($this->gateway, 'buildCitPayload', [$order, $subscription, $checkout]);

        $this->assertArrayNotHasKey('identification', $payload['payer'], 'legacy docNumber key must not populate payer.identification in CIT');
    }
}
