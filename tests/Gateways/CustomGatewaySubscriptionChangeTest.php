<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use MercadoPago\Woocommerce\Tests\Traits\SetNotAccessibleProperty;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Integration-style tests for the customer-initiated card change flow
 * (CustomGateway::process_subscription_payment_method_change).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CustomGatewaySubscriptionChangeTest extends TestCase
{
    use FormMock;
    use SetNotAccessibleProperty;
    use WoocommerceMock;

    /**
     * @var Mockery\MockInterface|CustomGateway
     */
    private $gateway;

    /**
     * @var Mockery\MockInterface|SubscriptionsHelper
     */
    private $subscriptionsHelper;

    /**
     * @var Mockery\MockInterface|AutomaticPaymentsClient
     */
    private $automaticPaymentsClient;

    /**
     * @var Mockery\MockInterface
     */
    private $order;

    /**
     * @var Mockery\MockInterface
     */
    private $subscription;

    /**
     * @var Mockery\MockInterface
     */
    private $formMock;

    protected function setUp(): void
    {
        // WoocommerceMock@before already calls WP_Mock::setUp() and mocks WC_*.

        // Stubs WCS class detection in isWcsActive() + change-payment branch.
        if (!class_exists('WC_Subscriptions')) {
            eval('class WC_Subscriptions {}');
        }
        if (!class_exists('WC_Subscriptions_Change_Payment_Gateway')) {
            eval('class WC_Subscriptions_Change_Payment_Gateway { public static $is_request_to_change_payment = false; }');
        }
        \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = true;

        if (!function_exists('wcs_order_contains_subscription')) {
            eval('function wcs_order_contains_subscription($order) { return true; }');
        }
        if (!function_exists('wcs_get_subscription')) {
            eval('function wcs_get_subscription($id) { return $GLOBALS["__mp_test_subscription"] ?? null; }');
        }

        $this->gateway                 = Mockery::mock(CustomGateway::class)->makePartial();
        $this->gateway->mercadopago    = MercadoPagoMock::getWoocommerceMercadoPagoMock();
        $this->subscriptionsHelper     = Mockery::mock(SubscriptionsHelper::class);
        $this->automaticPaymentsClient = Mockery::mock(AutomaticPaymentsClient::class);
        $this->automaticPaymentsClient->shouldReceive('log')->byDefault();

        $this->gateway->mercadopago->subscriptionsHelper = $this->subscriptionsHelper;
        $this->gateway->mercadopago->automaticPaymentsClient      = $this->automaticPaymentsClient;

        // getCheckoutFormData() marca o pedido com a origem (Blocks ou Classic);
        // não relevante para esta suite, então aceitamos a chamada silenciosamente.
        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('markPaymentAsBlocks')->andReturnSelf()->byDefault();

        if (!defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 86400);
        }

        WP_Mock::userFunction('__')->andReturnUsing(function ($text) { return $text; })->byDefault();
        WP_Mock::userFunction('esc_html')->andReturnUsing(function ($t) { return $t; })->byDefault();
        WP_Mock::userFunction('get_current_user_id')->andReturn(1)->byDefault();
        WP_Mock::userFunction('set_transient')->andReturn(true)->byDefault();
        WP_Mock::userFunction('get_transient')->andReturn(false)->byDefault();
        WP_Mock::userFunction('delete_transient')->andReturn(true)->byDefault();
        WP_Mock::userFunction('wp_unslash')->andReturnUsing(function ($v) { return $v; })->byDefault();
        WP_Mock::userFunction('sanitize_text_field')->andReturnUsing(function ($v) { return $v; })->byDefault();

        $this->order = Mockery::mock(\WC_Order::class);
        $this->order->shouldReceive('get_id')->andReturn(42)->byDefault();

        $this->subscription = Mockery::mock();
        $this->subscription->shouldReceive('get_view_order_url')->andReturn('https://shop.test/account/view-subscription/42')->byDefault();
        $GLOBALS['__mp_test_subscription'] = $this->subscription;

        WP_Mock::userFunction('wc_get_order')->andReturn($this->order)->byDefault();

        // Access token resolution is delegated to SubscriptionsHelper::resolveAccessToken.
        // Prod/test selection is covered by SubscriptionsHelperTest; here we mock the result.
        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->andReturn(false)->byDefault();
        $this->subscriptionsHelper->shouldReceive('resolveAccessToken')
            ->andReturn('AT-prod')
            ->byDefault();

        // Form post data: new token from MP.js.
        $_POST['mercadopago_custom'] = ['token' => 'NEW-TOKEN'];
        $this->formMock = $this->mockFormWithCustomSetup(function ($mock) {
            $mock->shouldReceive('sanitizedPostData')->with('mercadopago_custom')->andReturn(['token' => 'NEW-TOKEN'])->byDefault();
        });

        // Subscription meta defaults — overridden per test.
        $this->subscriptionsHelper->shouldReceive('getSubscriptionMeta')
            ->with($this->subscription, '_mp_subscription_id', '')
            ->andReturn('SUBSC-1')
            ->byDefault();
        $this->subscriptionsHelper->shouldReceive('getSubscriptionMeta')
            ->with($this->subscription, '_mp_active_card_id', '')
            ->andReturn('OLD-CARD')
            ->byDefault();
        $this->subscriptionsHelper->shouldReceive('buildAddPaymentMethodSeed')
            ->andReturn('pm-add:SUBSC-1:NEW-TOKEN-PREFIX')
            ->byDefault();
        $this->subscriptionsHelper->shouldReceive('generateIdempotencyKey')
            ->andReturn('idem-key-xyz')
            ->byDefault();
        $this->subscriptionsHelper->shouldReceive('mapApiErrorToUserMessage')
            ->andReturn('')
            ->byDefault();
    }

    protected function tearDown(): void
    {
        \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = false;
        unset($GLOBALS['__mp_test_subscription']);
        // WoocommerceMock@after handles WP_Mock::tearDown() + Mockery::close().
    }

    /** AC-1 + GATE: 3a OK + 3b OK → meta atualizada (card_id, last_four, brand). */
    public function testChangePaymentBothAddAndRemoveSucceedUpdatesMeta(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->with('SUBSC-1', 'NEW-TOKEN', 'AT-prod', 'idem-key-xyz', 'OLD-CARD')
            ->andReturn([
                'status' => 200,
                'data'   => [
                    'profile' => [
                        'payment_methods' => [
                            ['card_id' => 'NEW-CARD', 'default' => true, 'last_four_digits' => '4242', 'brand' => 'visa'],
                            ['card_id' => 'OLD-CARD', 'default' => false],
                        ],
                    ],
                ],
                'new_card_id' => 'NEW-CARD',
            ]);

        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->once()
            ->with('SUBSC-1', 'OLD-CARD', 'AT-prod')
            ->andReturn(['status' => 200, 'data' => [], 'error' => null]);

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')
            ->once()->with($this->subscription, '_mp_active_card_id', 'NEW-CARD');
        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')
            ->once()->with($this->subscription, '_mp_active_card_last_four', '4242');
        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')
            ->once()->with($this->subscription, '_mp_active_card_brand', 'visa');

        WP_Mock::userFunction('set_transient')
            ->once()
            ->with('mp_wcs_pm_success_1', \Mockery::type('string'), 60);

        $result = $this->gateway->process_payment(42);

        $this->assertSame('success', $result['result']);
        $this->assertStringContainsString('view-subscription', $result['redirect']);
    }

    /** AC-2: 3a falha → retorna failure, sem mexer em meta. */
    public function testChangePayment3aFailureReturnsFailureAndDoesNotTouchMeta(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->andReturn([
                'status' => 422,
                'data'   => ['code' => 'PaymentRejected'],
                'new_card_id' => null,
            ]);
        $this->automaticPaymentsClient->shouldNotReceive('removePaymentMethod');
        $this->subscriptionsHelper->shouldNotReceive('setSubscriptionMeta');

        $result = $this->gateway->process_payment(42);

        $this->assertSame('fail', $result['result']);
    }

    /** AC-3: `_mp_active_card_id` null → não chama 3b. */
    public function testChangePaymentDoesNotCall3bWhenNoOldCard(): void
    {
        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->with($this->subscription, '_mp_active_card_id', '')
            ->andReturn(''); // sem cartão anterior

        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->with('SUBSC-1', 'NEW-TOKEN', 'AT-prod', 'idem-key-xyz', null)
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'NEW-CARD', 'default' => true]]]],
                'new_card_id' => 'NEW-CARD',
            ]);

        $this->automaticPaymentsClient->shouldNotReceive('removePaymentMethod');
        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')
            ->once()->with($this->subscription, '_mp_active_card_id', 'NEW-CARD');

        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /** AC-3: token gerou o mesmo card → não chama 3b. */
    public function testChangePaymentDoesNotCall3bWhenNewCardEqualsOld(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'OLD-CARD', 'default' => true]]]],
                'new_card_id' => 'OLD-CARD',
            ]);
        $this->automaticPaymentsClient->shouldNotReceive('removePaymentMethod');

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta');
        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /** AC-4: 3b retorna LastPaymentMethod → success silencioso, meta atualizada. */
    public function testChangePaymentLastPaymentMethodTreatedAsSilentSuccess(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'NEW-CARD', 'default' => true]]]],
                'new_card_id' => 'NEW-CARD',
            ]);
        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->once()
            ->andReturn(['status' => 422, 'data' => ['code' => 'LastPaymentMethod'], 'error' => 'last_payment_method']);

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta');

        $result = $this->gateway->process_payment(42);

        $this->assertSame('success', $result['result']);
    }

    /** _mp_subscription_id vazio → log error + falha antes de chamar API. */
    public function testChangePaymentFailsWhenSubscriptionIdIsMissing(): void
    {
        $this->subscriptionsHelper
            ->shouldReceive('getSubscriptionMeta')
            ->with($this->subscription, '_mp_subscription_id', '')
            ->andReturn('');

        $this->automaticPaymentsClient->shouldNotReceive('addPaymentMethod');

        $result = $this->gateway->process_payment(42);
        $this->assertSame('fail', $result['result']);
    }

    /** Token vazio no POST → falha antes de chamar API (via reflection — evita dupla alias mock). */
    public function testChangePaymentFailsWhenTokenIsEmpty(): void
    {
        $reflection  = new \ReflectionClass(CustomGateway::class);
        $instance    = $reflection->newInstanceWithoutConstructor();
        $instance->mercadopago = $this->gateway->mercadopago;

        // Injeta checkout com token vazio diretamente via método público de dados do pedido.
        // Como o método é privado, invocamos via reflection passando um $order que retorna
        // dados sem token — mais simples que re-mockar o alias Form::class.
        $method = $reflection->getMethod('paymentMethodChangeFailure');
        $method->setAccessible(true);
        $result = $method->invoke($instance, 'token vazio');

        $this->assertSame('fail', $result['result']);
        $this->assertSame('token vazio', $result['message']);
    }

    /** Fluxo real: token ausente no checkout → falha sem chamar a API AP v2. */
    public function testChangePaymentFailsWhenCheckoutTokenIsEmpty(): void
    {
        $this->formMock->shouldReceive('sanitizedPostData')
            ->with('mercadopago_custom')
            ->andReturn(['token' => '']);

        $this->automaticPaymentsClient->shouldNotReceive('addPaymentMethod');

        $result = $this->gateway->process_payment(42);
        $this->assertSame('fail', $result['result']);
    }

    /** Access token vazio (credencial não configurada) → falha sem chamar API. */
    public function testChangePaymentFailsWhenAccessTokenIsMissing(): void
    {
        $this->subscriptionsHelper->shouldReceive('resolveAccessToken')
            ->andReturn('');

        $this->automaticPaymentsClient->shouldNotReceive('addPaymentMethod');

        $result = $this->gateway->process_payment(42);
        $this->assertSame('fail', $result['result']);
    }

    /** Gateway forwards the resolved access token to the AP v2 client. */
    public function testChangePaymentForwardsResolvedAccessToken(): void
    {
        $this->subscriptionsHelper->shouldReceive('resolveAccessToken')
            ->andReturn('AT-test');

        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->with('SUBSC-1', 'NEW-TOKEN', 'AT-test', Mockery::any(), Mockery::any())
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'NEW-CARD', 'default' => true]]]],
                'new_card_id' => 'NEW-CARD',
            ]);
        $this->automaticPaymentsClient->shouldReceive('removePaymentMethod')
            ->andReturn(['status' => 200, 'data' => [], 'error' => null]);
        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta');

        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /** Exception em addPaymentMethod → retorna falha. */
    public function testChangePaymentReturnsFailureWhenAddMethodThrows(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->andThrow(new \Exception('network error'));

        $this->subscriptionsHelper->shouldNotReceive('setSubscriptionMeta');

        $result = $this->gateway->process_payment(42);
        $this->assertSame('fail', $result['result']);
    }

    /** AC-4: 3b retorna CannotRemoveDefault → success + transient de fila para admin notice. */
    public function testChangePaymentCannotRemoveDefaultTriggersAdminNotice(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'NEW-CARD', 'default' => true]]]],
                'new_card_id' => 'NEW-CARD',
            ]);
        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->andReturn(['status' => 422, 'data' => ['code' => 'CannotRemoveDefault'], 'error' => 'cannot_remove_default']);

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta');

        WP_Mock::userFunction('get_transient')
            ->with('mp_subscription_card_change_inconsistencies')
            ->andReturn(false);
        WP_Mock::userFunction('set_transient')
            ->once()
            ->with(
                'mp_subscription_card_change_inconsistencies',
                \Mockery::on(fn($v) => is_array($v) && count($v) === 1),
                \Mockery::any()
            )
            ->andReturn(true);

        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /** P1 Codex: new_card_id === null com status 200 (mesmo cartão) → success no-op. */
    public function testChangePaymentSameCardReAddedReturnsSuccessNoOp(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'OLD-CARD', 'default' => true]]]],
                'new_card_id' => null, // mesmo cartão
            ]);
        $this->automaticPaymentsClient->shouldNotReceive('removePaymentMethod');
        $this->subscriptionsHelper->shouldNotReceive('setSubscriptionMeta');
        WP_Mock::userFunction('set_transient')
            ->once()
            ->with('mp_wcs_pm_success_1', \Mockery::type('string'), 60);

        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /** 3b exception silenciosa → sucesso parcial, meta atualizada. */
    public function testChangePaymentRemoveMethodExceptionIsSilentSuccess(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'NEW-CARD', 'default' => true]]]],
                'new_card_id' => 'NEW-CARD',
            ]);
        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->andThrow(new \Exception('timeout'));

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta');

        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /** 3a retorna 200 e new_card_id é null (nenhum PM default encontrado) → success no-op. */
    public function testChangePaymentNoDefaultCardFoundReturnsSuccessNoOp(): void
    {
        // payment_methods vazio → extractDefaultCardId retorna null → no-op success
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => []]],
                'new_card_id' => null,
            ]);
        $this->automaticPaymentsClient->shouldNotReceive('removePaymentMethod');
        $this->subscriptionsHelper->shouldNotReceive('setSubscriptionMeta');
        WP_Mock::userFunction('set_transient')
            ->once()
            ->with('mp_wcs_pm_success_1', \Mockery::type('string'), 60);

        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /** 3a OK mas PM retornado não tem last_four_digits nem brand → só card_id é persistido. */
    public function testChangePaymentPersistsOnlyCardIdWhenPmHasNoExtraFields(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'NEW-CARD', 'default' => true]]]],
                'new_card_id' => 'NEW-CARD',
            ]);
        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->andReturn(['status' => 200, 'data' => [], 'error' => null]);

        // Só _mp_active_card_id deve ser chamado (sem last_four_digits nem brand no PM)
        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')
            ->once()->with($this->subscription, '_mp_active_card_id', 'NEW-CARD');
        $this->subscriptionsHelper->shouldNotReceive('setSubscriptionMeta')
            ->with($this->subscription, '_mp_active_card_last_four', \Mockery::any());

        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /** findPaymentMethodByCardId: array com elemento não-array é ignorado; card_id correto é encontrado. */
    public function testFindPaymentMethodByCardIdSkipsNonArrayElements(): void
    {
        $reflection = new \ReflectionClass(\MercadoPago\Woocommerce\Gateways\CustomGateway::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $method     = $reflection->getMethod('findPaymentMethodByCardId');
        $method->setAccessible(true);

        $result = $method->invoke($instance, ['not-an-array', ['card_id' => 'FOUND', 'default' => true]], 'FOUND');
        $this->assertSame('FOUND', $result['card_id']);
    }

    /** findPaymentMethodByCardId: nenhum PM tem o card_id procurado → return []. */
    public function testFindPaymentMethodByCardIdReturnsEmptyWhenNotFound(): void
    {
        $reflection = new \ReflectionClass(\MercadoPago\Woocommerce\Gateways\CustomGateway::class);
        $instance   = $reflection->newInstanceWithoutConstructor();
        $method     = $reflection->getMethod('findPaymentMethodByCardId');
        $method->setAccessible(true);

        $result = $method->invoke($instance, [['card_id' => 'OTHER-CARD']], 'NOT-FOUND');
        $this->assertSame([], $result);
    }

    /** 3a falha com mensagem customizada de mapApiErrorToUserMessage. */
    public function testChangePaymentFailsWithUserMessageFromApiError(): void
    {
        $this->subscriptionsHelper
            ->shouldReceive('mapApiErrorToUserMessage')
            ->andReturn('Cartão recusado pelo banco.');

        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->andReturn([
                'status' => 422,
                'data'   => ['error' => 'PaymentRejected', 'code' => 'CPP_001'],
                'new_card_id' => null,
            ]);

        $result = $this->gateway->process_payment(42);

        $this->assertSame('fail', $result['result']);
        $this->assertSame('Cartão recusado pelo banco.', $result['message']);
    }

    /** wcs_get_subscription() retorna false → usa $order como fallback de subscription. */
    public function testChangePaymentFallsBackToOrderWhenSubscriptionNotFound(): void
    {
        // Sobrescreve wcs_get_subscription para retornar false
        $GLOBALS['__mp_test_subscription'] = false;
        // O $order é usado como fallback — ele tem get_id() e get_view_order_url()
        $this->order->shouldReceive('get_view_order_url')->andReturn('https://shop.test/order/42');

        // getSubscriptionMeta chamado com $order (o fallback)
        $this->subscriptionsHelper->shouldReceive('getSubscriptionMeta')
            ->with($this->order, '_mp_subscription_id', '')
            ->andReturn('SUBSC-FALLBACK');
        $this->subscriptionsHelper->shouldReceive('getSubscriptionMeta')
            ->with($this->order, '_mp_active_card_id', '')
            ->andReturn('');

        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->with('SUBSC-FALLBACK', 'NEW-TOKEN', 'AT-prod', Mockery::any(), null)
            ->andReturn([
                'status' => 200,
                'data'   => ['profile' => ['payment_methods' => [['card_id' => 'NEW-CARD', 'default' => true]]]],
                'new_card_id' => 'NEW-CARD',
            ]);

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta');

        $result = $this->gateway->process_payment(42);
        $this->assertSame('success', $result['result']);
    }

    /* ───────────────────── displayCardChangeInconsistencyNotice ───────────────────── */

    /** Retorna cedo quando get_current_screen não existe. */
    public function testDisplayInconsistencyNoticeDoesNothingWhenGetCurrentScreenAbsent(): void
    {
        // get_current_screen NÃO é mockado → function_exists retorna false → early return.
        $this->gateway->displayCardChangeInconsistencyNotice();
        $this->addToAssertionCount(1); // nenhuma exception = sucesso
    }

    /** Retorna cedo quando a tela não é de subscriptions. */
    public function testDisplayInconsistencyNoticeDoesNothingOnWrongScreen(): void
    {
        $screen     = Mockery::mock();
        $screen->id = 'wc-settings';

        WP_Mock::userFunction('get_current_screen')->andReturn($screen);

        $this->gateway->displayCardChangeInconsistencyNotice();
        $this->addToAssertionCount(1);
    }

    /** Retorna cedo quando get_transient retorna vazio. */
    public function testDisplayInconsistencyNoticeDoesNothingWhenNoMessages(): void
    {
        if (!function_exists('get_current_screen')) {
            eval('function get_current_screen() { global $__mp_test_screen; return $__mp_test_screen ?? null; }');
        }
        $GLOBALS['__mp_test_screen'] = (object) ['id' => 'shop_subscription'];

        WP_Mock::userFunction('get_transient')
            ->with('mp_subscription_card_change_inconsistencies')
            ->andReturn(false);

        $this->gateway->displayCardChangeInconsistencyNotice();
        $this->addToAssertionCount(1);

        unset($GLOBALS['__mp_test_screen']);
    }

    /** Exibe notices e deleta transient via get/delete_transient (compatível com object cache). */
    public function testDisplayInconsistencyNoticeRendersAndClearsTransient(): void
    {
        if (!function_exists('get_current_screen')) {
            eval('function get_current_screen() { global $__mp_test_screen; return $__mp_test_screen ?? null; }');
        }
        $GLOBALS['__mp_test_screen'] = (object) ['id' => 'shop_subscription'];

        WP_Mock::userFunction('get_transient')
            ->with('mp_subscription_card_change_inconsistencies')
            ->andReturn(['critical inconsistency message']);
        WP_Mock::userFunction('delete_transient')
            ->once()
            ->with('mp_subscription_card_change_inconsistencies');
        WP_Mock::userFunction('esc_html')->andReturnArg(0);

        ob_start();
        $this->gateway->displayCardChangeInconsistencyNotice();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-error', $output);
        $this->assertStringContainsString('critical inconsistency message', $output);

        unset($GLOBALS['__mp_test_screen']);
    }

    /* ─────────────────────── restoreChangePaymentError ─────────────────────── */

    /** POST request → early return, transient never read. */
    public function testRestoreChangePaymentErrorNoOpsOnPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        WP_Mock::userFunction('get_transient')->never();

        $this->gateway->restoreChangePaymentError();
        $this->addToAssertionCount(1);
    }

    /** GET with no stored transient → no notice added. */
    public function testRestoreChangePaymentErrorNoOpsWhenTransientEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        WP_Mock::userFunction('get_transient')
            ->once()
            ->with('mp_wcs_pm_error_1')
            ->andReturn(false);

        $this->gateway->restoreChangePaymentError();
        $this->addToAssertionCount(1);
    }

    /** GET with stored transient → queues WC notice and deletes transient. */
    public function testRestoreChangePaymentErrorAddsNoticeAndDeletesTransient(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        WP_Mock::userFunction('get_transient')
            ->once()
            ->with('mp_wcs_pm_error_1')
            ->andReturn('We could not process the card change. Please try again.');
        WP_Mock::userFunction('wc_add_notice')
            ->once()
            ->with('We could not process the card change. Please try again.', 'error');
        WP_Mock::userFunction('delete_transient')
            ->once()
            ->with('mp_wcs_pm_error_1');

        $this->gateway->restoreChangePaymentError();
        $this->addToAssertionCount(1);
    }

    /* ─────────────────────── restoreChangePaymentSuccess ─────────────────────── */

    /** POST request → early return, transient never read. */
    public function testRestoreChangePaymentSuccessNoOpsOnPost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        WP_Mock::userFunction('get_transient')->never();

        $this->gateway->restoreChangePaymentSuccess();
        $this->addToAssertionCount(1);
    }

    /** GET with no stored transient → no notice added. */
    public function testRestoreChangePaymentSuccessNoOpsWhenTransientEmpty(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        WP_Mock::userFunction('get_transient')
            ->once()
            ->with('mp_wcs_pm_success_1')
            ->andReturn(false);

        $this->gateway->restoreChangePaymentSuccess();
        $this->addToAssertionCount(1);
    }

    /** GET with stored transient → queues WC success notice and deletes transient. */
    public function testRestoreChangePaymentSuccessAddsNoticeAndDeletesTransient(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        WP_Mock::userFunction('get_transient')
            ->once()
            ->with('mp_wcs_pm_success_1')
            ->andReturn('Payment method updated successfully.');
        WP_Mock::userFunction('wc_add_notice')
            ->once()
            ->with('Payment method updated successfully.', 'success');
        WP_Mock::userFunction('delete_transient')
            ->once()
            ->with('mp_wcs_pm_success_1');

        $this->gateway->restoreChangePaymentSuccess();
        $this->addToAssertionCount(1);
    }
}
