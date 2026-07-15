<?php

namespace MercadoPago\Woocommerce\Tests\Integration\Subscriptions;

use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Helpers\AutomaticPaymentsClient;
use MercadoPago\Woocommerce\Helpers\SubscriptionsHelper;
use MercadoPago\Woocommerce\Tests\Mocks\MercadoPagoMock;
use MercadoPago\Woocommerce\Tests\Traits\FormMock;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Mock;

/**
 * Integration tests for the Payment Method Change flow (Fluxo C — 3a + 3b).
 *
 * Covers card swap: My Account -> Change Payment -> AP v2 add/remove.
 *
 * @spec feat-001 US-5 | DD-6, DD-14, DD-15
 * @covers \MercadoPago\Woocommerce\Gateways\CustomGateway::process_subscription_payment_method_change
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PaymentMethodChangeFlowTest extends TestCase
{
    use FormMock;

    private $gateway;

    private Mockery\MockInterface $subscriptionsHelper;

    private Mockery\MockInterface $automaticPaymentsClient;

    private Mockery\MockInterface $order;

    private Mockery\MockInterface $subscription;

    protected function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        if (!class_exists('WC_Subscriptions')) {
            require_once __DIR__ . '/../../Mocks/WcsStubs.php';
        }

        \WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment = true;

        $this->gateway = Mockery::mock(CustomGateway::class)->makePartial();
        $this->gateway->mercadopago = MercadoPagoMock::getWoocommerceMercadoPagoMock();

        $this->subscriptionsHelper = Mockery::mock(SubscriptionsHelper::class);
        $this->automaticPaymentsClient = Mockery::mock(AutomaticPaymentsClient::class);
        $this->automaticPaymentsClient->shouldReceive('log')->byDefault();

        $this->gateway->mercadopago->subscriptionsHelper = $this->subscriptionsHelper;
        $this->gateway->mercadopago->automaticPaymentsClient = $this->automaticPaymentsClient;

        $this->gateway->mercadopago->orderMetadata
            ->shouldReceive('markPaymentAsBlocks')->andReturnSelf()->byDefault();

        if (!defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 86400);
        }

        WP_Mock::userFunction('__')->andReturnUsing(fn($t) => $t)->byDefault();
        WP_Mock::userFunction('esc_html')->andReturnUsing(fn($t) => $t)->byDefault();
        WP_Mock::userFunction('get_current_user_id')->andReturn(1)->byDefault();
        WP_Mock::userFunction('set_transient')->andReturn(true)->byDefault();
        WP_Mock::userFunction('get_transient')->andReturn(false)->byDefault();
        WP_Mock::userFunction('delete_transient')->andReturn(true)->byDefault();
        WP_Mock::userFunction('wp_unslash')->andReturnUsing(fn($v) => $v)->byDefault();
        WP_Mock::userFunction('sanitize_text_field')->andReturnUsing(fn($v) => $v)->byDefault();

        $this->order = Mockery::mock(\WC_Order::class);
        $this->order->shouldReceive('get_id')->andReturn(42)->byDefault();

        $this->subscription = Mockery::mock();
        $this->subscription->shouldReceive('get_view_order_url')
            ->andReturn('https://shop.test/account/view-subscription/42')->byDefault();
        $GLOBALS['__mp_test_subscription'] = $this->subscription;

        WP_Mock::userFunction('wc_get_order')->andReturn($this->order)->byDefault();

        $this->gateway->mercadopago->storeConfig->shouldReceive('isTestMode')->andReturn(false)->byDefault();
        $this->subscriptionsHelper->shouldReceive('resolveAccessToken')
            ->andReturn('AT-prod')
            ->byDefault();

        $_POST['mercadopago_custom'] = ['token' => 'NEW-TOKEN'];
        $this->mockFormSanitizedPostData(['token' => 'NEW-TOKEN'], 'mercadopago_custom');

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
        Mockery::close();
        WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Add + Remove both succeed -> updates card metadata, returns success.
     */
    public function testAddAndRemoveSuccessUpdatesCardMetadata(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->with('SUBSC-1', 'NEW-TOKEN', 'AT-prod', 'idem-key-xyz', 'OLD-CARD')
            ->andReturn([
                'status'      => 200,
                'new_card_id' => 'NEW-CARD-123',
                'data'        => [
                    'profile' => [
                        'payment_methods' => [
                            ['card_id' => 'NEW-CARD-123', 'default' => true, 'last_four_digits' => '4242', 'brand' => 'visa'],
                        ],
                    ],
                ],
            ]);

        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->once()
            ->with('SUBSC-1', 'OLD-CARD', 'AT-prod')
            ->andReturn(['status' => 204, 'data' => [], 'error' => null]);

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')
            ->once()->with($this->subscription, '_mp_active_card_id', 'NEW-CARD-123');
        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')
            ->once()->with($this->subscription, '_mp_active_card_last_four', '4242');
        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')
            ->once()->with($this->subscription, '_mp_active_card_brand', 'visa');

        $result = $this->gateway->process_payment(42);

        $this->assertSame('success', $result['result']);
        $this->assertSame('https://shop.test/account/view-subscription/42', $result['redirect']);
    }

    /**
     * Add fails with a 4xx API error -> gateway returns failure.
     */
    public function testAddFailureReturnsFailure(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->andReturn([
                'status'      => 400,
                'new_card_id' => null,
                'data'        => ['error' => 'InvalidToken'],
            ]);

        $result = $this->gateway->process_payment(42);

        $this->assertSame('fail', $result['result']);
    }

    /**
     * No old card stored -> remove is never called, flow completes successfully.
     */
    public function testDoesNotCallRemoveWhenNoOldCard(): void
    {
        $this->subscriptionsHelper->shouldReceive('getSubscriptionMeta')
            ->with($this->subscription, '_mp_active_card_id', '')
            ->andReturn('');

        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->andReturn([
                'status'      => 200,
                'new_card_id' => 'NEW-CARD-456',
                'data'        => [
                    'profile' => [
                        'payment_methods' => [
                            ['card_id' => 'NEW-CARD-456', 'default' => true, 'last_four_digits' => '1234', 'brand' => 'master'],
                        ],
                    ],
                ],
            ]);

        $this->automaticPaymentsClient->shouldReceive('removePaymentMethod')->never();

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')->times(3);

        $result = $this->gateway->process_payment(42);

        $this->assertSame('success', $result['result']);
    }

    /**
     * Remove throws a network/transport exception -> inconsistency notice is queued.
     *
     * The catch block must call triggerCardChangeInconsistencyNotice so the store
     * owner sees an admin warning that the old card may be orphaned in the profile.
     */
    public function testRemoveExceptionTriggersInconsistencyNotice(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->andReturn([
                'status'      => 200,
                'new_card_id' => 'NEW-CARD-777',
                'data'        => [
                    'profile' => [
                        'payment_methods' => [
                            ['card_id' => 'NEW-CARD-777', 'default' => true, 'last_four_digits' => '7777', 'brand' => 'visa'],
                        ],
                    ],
                ],
            ]);

        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->once()
            ->andThrow(new \Exception('Connection timeout'));

        $noticeQueued = false;
        WP_Mock::userFunction('get_transient')
            ->with('mp_subscription_card_change_inconsistencies')
            ->andReturn(false);
        WP_Mock::userFunction('set_transient')
            ->with('mp_subscription_card_change_inconsistencies', Mockery::type('array'), DAY_IN_SECONDS)
            ->andReturnUsing(function () use (&$noticeQueued) {
                $noticeQueued = true;
                return true;
            });

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')->times(3);

        $result = $this->gateway->process_payment(42);

        $this->assertSame('success', $result['result']);
        $this->assertTrue($noticeQueued, 'Inconsistency notice must be queued when removePaymentMethod throws');
    }

    /**
     * Remove returns cannot_remove_default -> inconsistency notice is queued.
     */
    public function testRemoveCannotRemoveDefaultTriggersInconsistencyNotice(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->andReturn([
                'status'      => 200,
                'new_card_id' => 'NEW-CARD-888',
                'data'        => [
                    'profile' => [
                        'payment_methods' => [
                            ['card_id' => 'NEW-CARD-888', 'default' => true, 'last_four_digits' => '8888', 'brand' => 'master'],
                        ],
                    ],
                ],
            ]);

        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->once()
            ->andReturn(['status' => 422, 'data' => [], 'error' => 'cannot_remove_default']);

        $noticeQueued = false;
        WP_Mock::userFunction('get_transient')
            ->with('mp_subscription_card_change_inconsistencies')
            ->andReturn(false);
        WP_Mock::userFunction('set_transient')
            ->with('mp_subscription_card_change_inconsistencies', Mockery::type('array'), DAY_IN_SECONDS)
            ->andReturnUsing(function () use (&$noticeQueued) {
                $noticeQueued = true;
                return true;
            });

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')->times(3);

        $result = $this->gateway->process_payment(42);

        $this->assertSame('success', $result['result']);
        $this->assertTrue($noticeQueued, 'Inconsistency notice must be queued when cannot_remove_default');
    }

    /**
     * Remove returns last_payment_method error -> treated as silent success (card stays).
     */
    public function testLastPaymentMethodTreatedAsSilentSuccess(): void
    {
        $this->automaticPaymentsClient
            ->shouldReceive('addPaymentMethod')
            ->once()
            ->andReturn([
                'status'      => 200,
                'new_card_id' => 'NEW-CARD-789',
                'data'        => [
                    'profile' => [
                        'payment_methods' => [
                            ['card_id' => 'NEW-CARD-789', 'default' => true, 'last_four_digits' => '5678', 'brand' => 'amex'],
                        ],
                    ],
                ],
            ]);

        $this->automaticPaymentsClient
            ->shouldReceive('removePaymentMethod')
            ->once()
            ->andReturn(['status' => 422, 'data' => [], 'error' => 'last_payment_method']);

        $this->subscriptionsHelper->shouldReceive('setSubscriptionMeta')->times(3);

        $result = $this->gateway->process_payment(42);

        $this->assertSame('success', $result['result']);
    }
}
