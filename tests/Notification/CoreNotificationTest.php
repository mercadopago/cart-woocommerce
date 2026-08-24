<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use MercadoPago\PP\Sdk\Sdk;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Strings;
use Mockery;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use WC_Order;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CoreNotificationTest extends TestCase
{
    use WoocommerceMock;
    use MockeryPHPUnitIntegration;

    private $notification;

    public function setUp(): void
    {
        $this->notification = new CoreNotification(
            Mockery::mock(MercadoPagoGatewayInterface::class),
            Mockery::mock(Logs::class),
            Mockery::mock(OrderStatus::class),
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class)
        );
    }

    /**
     * @testWith [[],[]]
     *           [{"refunds_notifying": "fake"},{"payment_type_id": "creditcard", "payment_method_info": {"installments": 2, "installment_amount": 10, "last_four_digits": "1234"}, "total_amount":10, "paid_amount": 10}]
     *           [{"current_refund": {"id": 1, "amount": 1}},{"refunds": {"1":{}}}]
     */
    public function testUpdatePaymentDetails(array $data, array $payment)
    {
        $data = array_merge([
            "payments_details" => [
                $payment = array_merge([
                    "id" => random()->numberBetween(1),
                    "payment_type_id" => random()->word()
                ], $payment)
            ]
        ], $data);

        $paymentData = (object) [
            "refund" => random()->optional()->numberBetween()
        ];

        $refundedAmount = $paymentData->refund ?? 0;
        if (isset($data["current_refund"]) && isset($payment["refunds"][$data["current_refund"]["id"]])) {
            $refundedAmount += $data["current_refund"]["amount"];
        }

        Mockery::getConfiguration()->setConstantsMap([
            PaymentMetadata::class => [
                'PAYMENT_IDS_META_KEY' => random()->word(),
            ]
        ]);

        $paymentMetadata = Mockery::mock("overload:" . PaymentMetadata::class)
            ->expects()
            ->getPaymentMetaKey($payment["id"])
            ->andReturn("PaymentMetaKey")
            ->getMock()
            ->expects()
            ->extractPaymentDataFromMeta(null)
            ->andReturn($paymentData)
            ->getMock()
            ->expects()
            ->formatPaymentMetadata($payment, $refundedAmount)
            ->andReturn(["formatedPaymentMetadata"])
            ->getMock();

        $order = Mockery::mock(WC_Order::class)
            ->expects()
            ->get_meta("PaymentMetaKey")
            ->getMock()
            ->expects()
            ->update_meta_data("PaymentMetaKey", ["formatedPaymentMetadata"])
            ->getMock();

        if (Strings::contains($payment["payment_type_id"], "card")) {
            $order
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/installments$/"),
                    $payment["payment_method_info"]["installments"]
                )
                ->getMock()
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/installment_amount$/"),
                    $payment["payment_method_info"]["installment_amount"]
                )
                ->getMock()
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/transaction_amount$/"),
                    $payment["total_amount"]
                )
                ->getMock()
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/total_paid_amount$/"),
                    $payment["paid_amount"]
                )
                ->getMock()
                ->expects()
                ->update_meta_data(
                    Mockery::pattern("/card_last_four_digits$/"),
                    $payment["payment_method_info"]["last_four_digits"]
                );
        }

        if (!isset($data["refunds_notifying"])) {
            $paymentMetadata
                ->expects()
                ->joinPaymentIds([$payment["id"]])
                ->andReturn($payment["id"]);
            $order
                ->expects()
                ->update_meta_data(PaymentMetadata::PAYMENT_IDS_META_KEY, $payment["id"]);
        }

        $this->notification->updatePaymentDetails($order, $data);
    }

    /**
     * Dedup-skip metadata sync is authoritative: with authoritativeRefund=true the per-payment
     * refunded total is synced from the MP payload rather than incremented. This keeps it correct
     * for panel-origin refunds — RefundHandler persists only the refund_id, never the per-payment
     * amount, so the normal incrementing path never runs for them.
     *
     * The refund amount lives in refunds_notifying[], NOT in payment['refunds'] (which only carries
     * id/status/notifying/metadata). The sync cross-references by refund id: it sums the amounts of
     * this payment's refunds that are present in refunds_notifying. Regression guard for the
     * multi-payment double-refund risk (a stale per-payment total would let a later refund read an
     * over-stated remaining balance).
     */
    public function testUpdatePaymentDetailsAuthoritativeRefundUsesNotifyingAmounts(): void
    {
        $paymentId = '123456';
        $data = [
            // The amounts live here, keyed by refund id — mirrors the real MP payload.
            'refunds_notifying' => [
                ['id' => 'r1', 'amount' => 4.00],
                ['id' => 'r2', 'amount' => 6.00],
            ],
            'payments_details'  => [
                [
                    'id'              => $paymentId,
                    'payment_type_id' => 'pix',
                    // payment['refunds'] carries NO amount — only id/status/notifying/metadata,
                    // exactly like the payload captured in homolog.
                    'refunds'         => [
                        'r1' => ['id' => 'r1', 'status' => 'approved', 'notifying' => true],
                        'r2' => ['id' => 'r2', 'status' => 'approved', 'notifying' => true],
                    ],
                ],
            ],
        ];

        // Stored value is lower than the payload sum — the sync must take the payload total.
        $paymentData = (object) ['refund' => 4.00];
        $expectedRefunded = 10.00; // 4.00 + 6.00 cross-referenced from refunds_notifying

        Mockery::mock("overload:" . PaymentMetadata::class)
            ->expects()->getPaymentMetaKey($paymentId)->andReturn("PaymentMetaKey")->getMock()
            ->expects()->extractPaymentDataFromMeta(null)->andReturn($paymentData)->getMock()
            ->expects()->formatPaymentMetadata(Mockery::type('array'), $expectedRefunded)
            ->andReturn(["formatted"])->getMock();

        $order = Mockery::mock(WC_Order::class)
            ->expects()->get_meta("PaymentMetaKey")->getMock()
            ->expects()->update_meta_data("PaymentMetaKey", ["formatted"])->getMock();

        $this->notification->updatePaymentDetails($order, $data, true);
    }

    /**
     * Regression guard (PSW-4213): payment['refunds'] in the real MP payload does NOT contain an
     * `amount` key — only id/status/notifying/metadata. An earlier implementation summed
     * array_column($payment['refunds'], 'amount'), which silently returned 0 and ZEROED a
     * previously-recorded refunded amount on every webhook redelivery. The sync must never reduce
     * the stored total when the payload carries no usable amount for the payment's refunds.
     */
    public function testUpdatePaymentDetailsAuthoritativeRefundDoesNotZeroWhenRefundsLackAmount(): void
    {
        $paymentId = '123456';
        $data = [
            'refunds_notifying' => [
                // A different refund id than the one on the payment → no cross-reference match.
                ['id' => 'other', 'amount' => 9.99],
            ],
            'payments_details'  => [
                [
                    'id'              => $paymentId,
                    'payment_type_id' => 'account_money',
                    // Real shape: refund present, but WITHOUT an amount key.
                    'refunds'         => [
                        'r1' => ['id' => 'r1', 'status' => 'approved', 'notifying' => true],
                    ],
                ],
            ],
        ];

        $paymentData = (object) ['refund' => 14.90]; // must be preserved, not zeroed
        $expectedRefunded = 14.90;

        Mockery::mock("overload:" . PaymentMetadata::class)
            ->expects()->getPaymentMetaKey($paymentId)->andReturn("PaymentMetaKey")->getMock()
            ->expects()->extractPaymentDataFromMeta(null)->andReturn($paymentData)->getMock()
            ->expects()->formatPaymentMetadata(Mockery::type('array'), $expectedRefunded)
            ->andReturn(["formatted"])->getMock();

        $order = Mockery::mock(WC_Order::class)
            ->expects()->get_meta("PaymentMetaKey")->getMock()
            ->expects()->update_meta_data("PaymentMetaKey", ["formatted"])->getMock();

        $this->notification->updatePaymentDetails($order, $data, true);
    }

    /**
     * Authoritative-sync guard: when the MP payload carries no refunds for a payment, the sync
     * must NOT zero out a previously stored refunded amount (e.g. a multi-payment notification
     * that only details the payment being refunded). The stored value is preserved.
     */
    public function testUpdatePaymentDetailsAuthoritativeRefundKeepsStoredWhenPayloadHasNoRefunds(): void
    {
        $paymentId = '123456';
        $data = [
            'refunds_notifying' => [['id' => 'r1', 'amount' => 4.00]],
            'payments_details'  => [
                [
                    'id'              => $paymentId,
                    'payment_type_id' => 'pix',
                    'refunds'         => [], // no refunds for this payment in the payload
                ],
            ],
        ];

        $paymentData = (object) ['refund' => 3.00]; // must be preserved, not zeroed
        $expectedRefunded = 3.00;

        Mockery::mock("overload:" . PaymentMetadata::class)
            ->expects()->getPaymentMetaKey($paymentId)->andReturn("PaymentMetaKey")->getMock()
            ->expects()->extractPaymentDataFromMeta(null)->andReturn($paymentData)->getMock()
            ->expects()->formatPaymentMetadata(Mockery::type('array'), $expectedRefunded)
            ->andReturn(["formatted"])->getMock();

        $order = Mockery::mock(WC_Order::class)
            ->expects()->get_meta("PaymentMetaKey")->getMock()
            ->expects()->update_meta_data("PaymentMetaKey", ["formatted"])->getMock();

        $this->notification->updatePaymentDetails($order, $data, true);
    }

    /**
     * When the refund_id was already applied (persisted at refund time), the notification
     * must skip the full refund flow and still sync payment-details metadata.
     */
    public function testHandleRefundNotificationSkipsAndSyncsMetadataWhenRefundAlreadyApplied(): void
    {
        // WP_Mock must be initialized BEFORE creating any Mockery mock: WP_Mock::setUp()
        // resets the Mockery container, which would orphan mocks created earlier.
        \WP_Mock::setUp();
        // Explicit guarantee (not relying on the function being undefined): the dedup skip
        // must never create a WooCommerce refund object.
        \WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $refundId = '99999';
        $data = [
            'refunds_notifying' => [
                ['id' => $refundId, 'amount' => 10.00],
            ],
            'payments_details' => [
                [
                    'id'              => '123456',
                    'refunds'         => [],
                    'payment_type_id' => 'credit_card',
                ],
            ],
        ];

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('save')->once();

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldReceive('isRefundIdApplied')
            ->once()
            ->with($order, $refundId)
            ->andReturn(true);

        $fileMock = Mockery::mock(File::class);
        $fileMock->shouldIgnoreMissing();
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = $fileMock;

        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logsMock,
            $orderStatusMock,
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
        ])->makePartial();

        // updatePaymentDetails must be called to sync metadata, but the refund flow
        // (processStatus / wc_create_refund) must NOT run. The dedup skip syncs in
        // authoritative mode (third arg true) so the per-payment refunded total is
        // recomputed from the payload rather than incremented.
        $notification->expects()
            ->updatePaymentDetails($order, Mockery::type('array'), true)
            ->once();

        $notification->handleSuccessfulRequestInternal($data, $order);

        // Behavioural guarantees (save once, updatePaymentDetails once, isRefundIdApplied once,
        // wc_create_refund never) are enforced by the Mockery/WP_Mock expectations verified here.
        \WP_Mock::tearDown();
        $this->assertTrue(true);
    }

    /**
     * Loop guarantee: with two refunds in the same notification where the first is already
     * applied (skip) and the second is new, the second MUST still be processed. This exercises
     * the `continue` in handleRefundNotification — a `break` regression would leave the second
     * refund unprocessed (isRefundIdApplied never queried for it).
     */
    public function testHandleRefundNotificationSkipsFirstButProcessesSecondRefund(): void
    {
        // WP_Mock must be initialized BEFORE creating any Mockery mock (see note above).
        \WP_Mock::setUp();
        \WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $appliedId = 'already_applied_id';
        $newId     = 'new_refund_id';

        $data = [
            'refunds_notifying' => [
                ['id' => $appliedId, 'amount' => 5.00],
                ['id' => $newId, 'amount' => 7.00],
            ],
            'payments_details' => [
                [
                    'id'              => '123456',
                    'payment_type_id' => 'credit_card',
                    // The new refund carries a panel origin so shouldProcessRefund() returns
                    // false and the flow lands on updatePaymentDetails (no processStatus mock needed).
                    'refunds'         => [
                        $newId => ['id' => $newId, 'amount' => 7.00, 'metadata' => ['origin' => 'painel_woocommerce']],
                    ],
                ],
            ],
        ];

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_status')->andReturn('processing');
        // Both iterations sync metadata and save once each.
        $order->shouldReceive('save')->twice();

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        // isRefundIdApplied MUST be queried for BOTH ids — proof the loop continued past the skip.
        $orderStatusMock->shouldReceive('isRefundIdApplied')->once()->with($order, $appliedId)->andReturn(true);
        $orderStatusMock->shouldReceive('isRefundIdApplied')->once()->with($order, $newId)->andReturn(false);

        $fileMock = Mockery::mock(File::class);
        $fileMock->shouldIgnoreMissing();
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = $fileMock;

        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logsMock,
            $orderStatusMock,
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
        ])->makePartial();

        // Both refunds land on updatePaymentDetails but through different paths:
        //  - first (dedup skip) syncs in authoritative mode (third arg true);
        //  - second (panel-origin, shouldProcessRefund=false) takes the normal incremental
        //    path (two args, authoritativeRefund defaults to false).
        // Neither creates a WC refund.
        $notification->expects()
            ->updatePaymentDetails($order, Mockery::type('array'), true)
            ->once();
        $notification->expects()
            ->updatePaymentDetails($order, Mockery::type('array'))
            ->once();

        $notification->handleSuccessfulRequestInternal($data, $order);

        // The continue-not-break guarantee is enforced by the two isRefundIdApplied
        // expectations (one per id) verified here; wc_create_refund is asserted never called.
        \WP_Mock::tearDown();
        $this->assertTrue(true);
    }

    /**
     * @testWith [{"notification_id": "P-67890"}]
     *           ["P-67890"]
     */
    public function testGetNotificationId($input)
    {
        $notification = Mockery::mock(CoreNotification::class)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial()
            ->expects()
            ->getInput()
            ->andReturn(json_encode($input))
            ->getMock();

        $this->assertEquals("P-67890", $notification->getNotificationId());
    }

    /**
     * @testWith ["P-12345", true]
     *           ["M-12345", true]
     *           ["12345", false]
     *           ["P-12345-12345", false]
     *           ["P12345", false]
     *           ["P-ABCDE", false]
     *           ["P-", false]
     */
    public function testValidateNotificationId(string $id, bool $expected)
    {
        $this->assertEquals($expected, $this->notification->validateNotificationId($id));
    }

    public function testGetSdkInstance()
    {
        $this->notification->seller
            ->expects()
            ->getCredentialsAccessToken()
            ->andReturn(
                $accessToken = random()->uuid()
            );

        Mockery::mock("alias:" . Device::class)
            ->expects()
            ->getDeviceProductId()
            ->andReturn(
                $productId = random()->uuid()
            );

        $this->notification->store
            ->expects()
            ->getIntegratorId()
            ->andReturn(
                $integratorId = random()->uuid()
            );

        Mockery::mock("overload:" . Sdk::class)
            ->shouldReceive("__construct")
            ->once()
            ->with($accessToken, MP_PLATFORM_ID, $productId, $integratorId);

        $this->assertInstanceOf(Sdk::class, $this->notification->getSdkInstance());
    }

    /**
     * @testWith [[]]
     *           [{"payer": {"email": "fake@fake"}}]
     *           [{"payments_details": {"fake": "fake"}}]
     */
    public function testGetProcessedStatus(array $data): void
    {
        $data = array_merge([
            'status' => random()->word()
        ], $data);

        $order = Mockery::mock(WC_Order::class)
            ->expects()
            ->save()
            ->getMock();

        if (!empty($data['payer']['email'])) {
            $order
                ->expects()
                ->update_meta_data('Buyer email', $data['payer']['email']);
        }

        $notification = Mockery::mock(CoreNotification::class)->makePartial();

        if (!empty($data['payments_details'])) {
            $notification
                ->expects()
                ->updatePaymentDetails($order, $data);
        }

        $notification->getProcessedStatus($order, $data);
    }

    /**
     * Tests that a successful refund notification emits mp_refund_success with
     * the order's checkout_type as the payment_method tag (PSW-4309).
     */
    public function testHandleRefundNotificationEmitsSuccessMetricWithCheckoutType(): void
    {
        \WP_Mock::setUp();
        \WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $refundId = 'mp_refund_123';
        $data = [
            'refunds_notifying' => [
                ['id' => $refundId, 'amount' => 10.00],
            ],
            'payments_details' => [
                [
                    'id'              => '999',
                    'payment_type_id' => 'account_money',
                    'refunds'         => [
                        $refundId => ['id' => $refundId, 'amount' => 10.00],
                    ],
                ],
            ],
        ];

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('get_meta')->with('checkout_type')->andReturn('super_token');

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldReceive('isRefundIdApplied')->once()->with($order, $refundId)->andReturn(false);
        $orderStatusMock->shouldReceive('mapMpStatusToWoocommerceStatus')->andReturn('refunded');

        $fileMock = Mockery::mock(File::class);
        $fileMock->shouldIgnoreMissing();
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = $fileMock;

        $datadogMock = Mockery::mock('alias:MercadoPago\Woocommerce\Libraries\Metrics\Datadog');
        $datadogMock->shouldReceive('getInstance')->andReturnSelf();
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_refund_success', 'refund_success', 'origin_mercadopago', 'super_token');

        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logsMock,
            $orderStatusMock,
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
        ])->makePartial();

        $notification->shouldReceive('getProcessedStatus')->andReturn('refunded');
        // processStatus returns true (refund applied) → success metric must be emitted.
        $notification->shouldReceive('processStatus')->once()->andReturn(true);

        $notification->handleSuccessfulRequestInternal($data, $order);

        \WP_Mock::tearDown();
        $this->assertTrue(true);
    }

    /**
     * A refund that fails to be created (processStatus returns false) must NOT emit
     * mp_refund_success — the failure is already reported as mp_refund_error inside
     * refundedFlow. Guards against success-metric pollution (tcidre review, PSW-4213).
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testHandleRefundNotificationDoesNotEmitSuccessWhenRefundNotApplied(): void
    {
        \WP_Mock::setUp();
        \WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $refundId = 'mp_refund_456';
        $data = [
            'refunds_notifying' => [
                ['id' => $refundId, 'amount' => 10.00],
            ],
            'payments_details' => [
                [
                    'id'              => '999',
                    'payment_type_id' => 'account_money',
                    'refunds'         => [
                        $refundId => ['id' => $refundId, 'amount' => 10.00],
                    ],
                ],
            ],
        ];

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_status')->andReturn('processing');

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldReceive('isRefundIdApplied')->once()->with($order, $refundId)->andReturn(false);
        $orderStatusMock->shouldReceive('mapMpStatusToWoocommerceStatus')->andReturn('refunded');

        $fileMock = Mockery::mock(File::class);
        $fileMock->shouldIgnoreMissing();
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = $fileMock;

        $datadogMock = Mockery::mock('alias:MercadoPago\Woocommerce\Libraries\Metrics\Datadog');
        $datadogMock->shouldReceive('getInstance')->andReturnSelf();
        // The success metric must NOT be emitted when the refund was not applied.
        $datadogMock->shouldReceive('sendEvent')->with('mp_refund_success', Mockery::any(), Mockery::any(), Mockery::any())->never();

        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logsMock,
            $orderStatusMock,
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
        ])->makePartial();

        $notification->shouldReceive('getProcessedStatus')->andReturn('refunded');
        // processStatus returns false (wc_create_refund failed) → no success metric.
        $notification->shouldReceive('processStatus')->once()->andReturn(false);

        $notification->handleSuccessfulRequestInternal($data, $order);

        \WP_Mock::tearDown();
        $this->assertTrue(true);
    }

    /**
     * Tests that a validation failure (missing refund_id) emits mp_refund_error
     * with the order's checkout_type as the payment_method tag (PSW-4309).
     */
    public function testHandleRefundNotificationEmitsErrorMetricWhenRefundIdMissing(): void
    {
        \WP_Mock::setUp();

        $data = [
            'refunds_notifying' => [
                ['amount' => 5.00], // no 'id' key → refundId=null
            ],
            'payments_details' => [
                ['id' => '888', 'payment_type_id' => 'credit_card'],
            ],
        ];

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('get_meta')->with('checkout_type')->andReturn('custom');

        $fileMock = Mockery::mock(File::class);
        $fileMock->shouldIgnoreMissing();
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = $fileMock;

        $datadogMock = Mockery::mock('alias:MercadoPago\Woocommerce\Libraries\Metrics\Datadog');
        $datadogMock->shouldReceive('getInstance')->andReturnSelf();
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('mp_refund_error', 'validation_failed', 'Refund ID not found in notification', 'custom');

        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logsMock,
            Mockery::mock(OrderStatus::class),
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
        ])->makePartial();

        $notification->handleSuccessfulRequestInternal($data, $order);

        \WP_Mock::tearDown();
        $this->assertTrue(true);
    }
}
