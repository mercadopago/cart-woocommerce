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
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = Mockery::mock(File::class);
        $logsMock->file->shouldReceive('info')->andReturn(null)->byDefault();
        $logsMock->file->shouldReceive('error')->andReturn(null)->byDefault();

        $this->notification = new CoreNotification(
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logsMock,
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
     * When the refund_id was already applied (persisted at refund time by RefundHandler, which
     * also recorded the per-payment refunded amount), the notification skips the WooCommerce
     * refund while scheduling a repair for a possible failed amount write.
     */
    public function testHandleRefundNotificationSkipsWhenRefundAlreadyApplied(): void
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
                    'refunds'         => [$refundId => ['id' => $refundId]],
                    'payment_type_id' => 'credit_card',
                ],
            ],
        ];

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldNotReceive('save');

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldReceive('isRefundIdApplied')
            ->once()
            ->with($order, $refundId)
            ->andReturn(true);
        $orderStatusMock->shouldReceive('queuePaymentRefundReconciliation')
            ->once()->with($order, '123456', $refundId);

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

        // The applied ID must not create another WooCommerce refund or increment metadata.
        $notification->shouldNotReceive('updatePaymentDetails');

        $notification->handleSuccessfulRequestInternal($data, $order);

        // Behavioural guarantees (isRefundIdApplied once, no updatePaymentDetails, no save,
        // wc_create_refund never) are enforced by the Mockery/WP_Mock expectations verified here.
        \WP_Mock::tearDown();
        $this->assertTrue(true);
    }

    public function testAppliedRefundUsesSingleOrderPaymentWhenNotificationOmitsMapping(): void
    {
        \WP_Mock::setUp();
        \WP_Mock::userFunction('wc_create_refund', ['times' => 0]);

        $refundId = '99999';
        $data = [
            'refunds_notifying' => [['id' => $refundId, 'amount' => 10.00]],
            'payments_details' => [[
                'id' => '123456',
                'refunds' => [],
                'payment_type_id' => 'credit_card',
            ]],
        ];
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_status')->andReturn('processing');
        $order->shouldReceive('get_meta')->with(PaymentMetadata::PAYMENT_IDS_META_KEY)
            ->once()->andReturn('123456');
        $order->shouldNotReceive('save');

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldReceive('isRefundIdApplied')
            ->once()->with($order, $refundId)->andReturn(true);
        $orderStatusMock->shouldReceive('queuePaymentRefundReconciliation')
            ->once()->with($order, '123456', $refundId);

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
        $notification->shouldNotReceive('updatePaymentDetails');

        $notification->handleSuccessfulRequestInternal($data, $order);

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
                        $appliedId => ['id' => $appliedId],
                        $newId => ['id' => $newId, 'amount' => 7.00, 'metadata' => ['origin' => 'painel_woocommerce']],
                    ],
                ],
            ],
        ];

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_status')->andReturn('processing');
        // Only the second refund (new, panel-origin) syncs metadata and saves; the first is skipped.
        $order->shouldReceive('save')->once();

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        // isRefundIdApplied MUST be queried for BOTH ids — proof the loop continued past the skip.
        $orderStatusMock->shouldReceive('isRefundIdApplied')->once()->with($order, $appliedId)->andReturn(true);
        $orderStatusMock->shouldReceive('isRefundIdApplied')->once()->with($order, $newId)->andReturn(false);
        $orderStatusMock->shouldReceive('queuePaymentRefundReconciliation')
            ->once()->with($order, '123456', $appliedId);

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

        // The first refund (already applied) is skipped — no updatePaymentDetails. The second
        // (new, panel-origin, shouldProcessRefund=false) takes the incremental path (two args).
        // Neither creates a WC refund.
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
