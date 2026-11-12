<?php

namespace MercadoPago\Woocommerce\Tests\Notification;

use MercadoPago\Woocommerce\Api\Core\CoreRequestContext;
use MercadoPago\Woocommerce\Api\Core\NotificationsClient;
use MercadoPago\Woocommerce\Helpers\Device;
use MercadoPago\Woocommerce\Helpers\PaymentMetadata;
use MercadoPago\Woocommerce\Helpers\Strings;
use Mockery;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Configs\Store;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Notification\AbstractNotification;
use MercadoPago\Woocommerce\Notification\CoreNotification;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\DenyAllSmartPixNotificationAuthenticator;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationHandler;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixCoreNotificationIdentifiers;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixEffectOwnershipStore;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationBody;
use MercadoPago\Woocommerce\SmartPix\Adapters\Notification\SmartPixNotificationLinkage;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixNotificationReceiver;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixOrderQuery;
use MercadoPago\Woocommerce\SmartPix\UseCases\SmartPixStateApplicator;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixStateReducer;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;
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
            Mockery::mock(Store::class),
            Mockery::mock(NotificationsClient::class)
        );
    }

    /**
     * @testWith [[],[]]
     *           [{"refunds_notifying": []},[]]
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

        if (empty($data["refunds_notifying"])) {
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
    public function testHandleSuccessfulRequestIgnoresOrdersOwnedBySmartPixOrders(): void
    {
        \WP_Mock::setUp();
        \WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        $order->shouldNotReceive('save');
        $order->shouldNotReceive('update_meta_data');
        \WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 200, 'message' => 'Notification ignored'], 200],
            'times' => 1,
        ]);

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldReceive('isOwnedBySmartPixOrders')->once()->with($order)->andReturn(true);
        $orderStatusMock->shouldNotReceive('processStatus');

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
            Mockery::mock(NotificationsClient::class),
        ])->makePartial();
        $notification->shouldNotReceive('handleSuccessfulRequestInternal');

        $notification->handleSuccessfulRequest(['external_reference' => 'WC-321', 'status' => 'approved']);

        $this->assertTrue(true);
    }

    public function testHandleSuccessfulRequestKeepsLegacyProcessingForOrdersNotOwnedBySmartPix(): void
    {
        \WP_Mock::setUp();
        \WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        \WP_Mock::userFunction('wp_send_json', ['times' => 0]);

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        \WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $order]);

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldReceive('isOwnedBySmartPixOrders')->once()->with($order)->andReturn(false);

        $data = ['external_reference' => 'WC-321', 'status' => 'approved'];
        $notification = $this->guardedNotification($orderStatusMock, $this->ignoringFileLog());
        $notification->shouldReceive('handleSuccessfulRequestInternal')->once()->with($data, $order);

        $notification->handleSuccessfulRequest($data);

        $this->assertTrue(true);
    }

    /**
     * @dataProvider unresolvedOrderProvider
     */
    public function testHandleSuccessfulRequestRejectsUnresolvedOrdersBeforeTheSmartPixGuard(bool $orderExists): void
    {
        \WP_Mock::setUp();
        \WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(999);
        \WP_Mock::userFunction('wc_get_order', ['args' => [321], 'times' => 1, 'return' => $orderExists ? $order : false]);
        $expectedMessage = $orderExists ? 'Order error' : 'Order is invalid';
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 422, 'message' => $expectedMessage], 422],
            'times' => 1,
            'return' => function () {
                // Mirrors wp_send_json() terminating the request.
                throw new \Error('wp_send_json terminated the request');
            },
        ]);

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldNotReceive('isOwnedBySmartPixOrders');
        $orderStatusMock->shouldNotReceive('processStatus');

        $notification = $this->guardedNotification($orderStatusMock, $this->ignoringFileLog());
        $notification->shouldNotReceive('handleSuccessfulRequestInternal');

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('wp_send_json terminated the request');
        $notification->handleSuccessfulRequest(['external_reference' => 'WC-321']);
    }

    public static function unresolvedOrderProvider(): array
    {
        return [
            'missing order' => [false],
            'order id mismatch' => [true],
        ];
    }

    public function testSmartPixIgnoredNotificationLogsOnlyAFixedReason(): void
    {
        \WP_Mock::setUp();
        \WP_Mock::userFunction('get_option', ['args' => ['_mp_store_identificator', 'WC-'], 'return' => 'WC-']);
        \WP_Mock::userFunction('wp_send_json', ['times' => 1]);

        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_id')->andReturn(321);
        \WP_Mock::userFunction('wc_get_order', ['args' => [321], 'return' => $order]);

        $orderStatusMock = Mockery::mock(OrderStatus::class);
        $orderStatusMock->shouldReceive('isOwnedBySmartPixOrders')->andReturn(true);

        $fileMock = Mockery::mock(File::class);
        $fileMock->shouldReceive('info')->once()->with('Starting to process update...', AbstractNotification::class);
        $fileMock->shouldReceive('info')->once()
            ->with('Legacy notification ignored for a Smart Pix Orders contract', AbstractNotification::class);
        $fileMock->shouldNotReceive('error');
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = $fileMock;

        $notification = $this->guardedNotification($orderStatusMock, $logsMock);
        $notification->handleSuccessfulRequest([
            'external_reference' => 'WC-321',
            'payer' => ['email' => 'buyer@example.test'],
        ]);

        $this->assertTrue(true);
    }

    private function ignoringFileLog(): Logs
    {
        $fileMock = Mockery::mock(File::class);
        $fileMock->shouldIgnoreMissing();
        $logsMock = Mockery::mock(Logs::class);
        $logsMock->file = $fileMock;

        return $logsMock;
    }

    private function guardedNotification(OrderStatus $orderStatus, Logs $logs)
    {
        return Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            $orderStatus,
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
            Mockery::mock(NotificationsClient::class),
        ])->makePartial();
    }

    public function testHandleRefundNotificationSkipsWhenRefundAlreadyApplied(): void
    {
        // The shared setup initializes WP_Mock before these expectations are created.
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
            Mockery::mock(NotificationsClient::class),
        ])->makePartial();

        // The applied ID must not create another WooCommerce refund or increment metadata.
        $notification->shouldNotReceive('updatePaymentDetails');

        $notification->handleSuccessfulRequestInternal($data, $order);

        // Behavioural guarantees (isRefundIdApplied once, no updatePaymentDetails, no save,
        // wc_create_refund never) are enforced by the Mockery/WP_Mock expectations verified here.
    }

    public function testAppliedRefundUsesSingleOrderPaymentWhenNotificationOmitsMapping(): void
    {
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
            Mockery::mock(NotificationsClient::class),
        ])->makePartial();
        $notification->shouldNotReceive('updatePaymentDetails');

        $notification->handleSuccessfulRequestInternal($data, $order);

    }

    /**
     * Loop guarantee: with two refunds in the same notification where the first is already
     * applied (skip) and the second is new, the second MUST still be processed. This exercises
     * the `continue` in handleRefundNotification — a `break` regression would leave the second
     * refund unprocessed (isRefundIdApplied never queried for it).
     */
    public function testHandleRefundNotificationSkipsFirstButProcessesSecondRefund(): void
    {
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
            Mockery::mock(NotificationsClient::class),
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

    public function testLegacyNotificationIdKeepsTheCoreFetchAndProcessingPath(): void
    {
        \WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        // A Smart Pix branch would answer through wp_send_json; the legacy success path never does.
        \WP_Mock::userFunction('wp_send_json', ['times' => 0]);

        $data = ['external_reference' => 'WC-321', 'status' => 'approved'];
        $fetched = Mockery::mock(\MercadoPago\Woocommerce\Api\Core\Models\Notification::class);
        $fetched->shouldReceive('toArray')->once()->andReturn($data);
        $client = Mockery::mock(NotificationsClient::class);
        $client->shouldReceive('find')->once()->with('P-123', Mockery::type(CoreRequestContext::class))->andReturn($fetched);
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('SYNTHETIC_ACCESS_TOKEN');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getIntegratorId')->andReturn('');

        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $this->ignoringFileLog(),
            Mockery::mock(OrderStatus::class),
            $seller,
            $store,
            $client,
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $notification->shouldReceive('getInput')->andReturn('{"notification_id":"P-123"}');
        $notification->shouldReceive('handleSuccessfulRequest')->once()->with($data);

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    /**
     * @testWith ["{\"notification_id\":\"PPORDSYNTHETIC001\"}"]
     *           ["\"PPORDSYNTHETIC001\""]
     */
    public function testSmartPixIdsAreRoutedToTheSmartPixHandlerBeforeTheLegacyValidation(string $posted): void
    {
        \WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 500, 'message' => SmartPixNotificationReceiver::MESSAGE_FAILED], 500],
            'times' => 1,
        ]);
        $smartPixClient = Mockery::mock(NotificationsClient::class);
        $smartPixClient->shouldReceive('find')->once()
            ->with('PPORDSYNTHETIC001', Mockery::type(CoreRequestContext::class))
            ->andThrow(new \RuntimeException('SYNTHETIC_SECRET_VALUE'));
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once()->with('Received data content', AbstractNotification::class, ['source_news' => 'webhooks']);
        $file->shouldNotReceive('error');

        $notification = $this->routedNotification($posted, $legacyClient, $file, $this->smartPixHandler($smartPixClient));
        $notification->shouldNotReceive('handleSuccessfulRequest');

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    public function testOversizedSmartPixBodyIsRejectedBeforeFetchingOrDecoding(): void
    {
        $posted = '{"notification_id":"PPORDSYNTHETIC001","padding":"'
            . str_repeat('x', SmartPixNotificationBody::DEFAULT_MAX_BYTES) . '"}';
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 413, 'message' => SmartPixNotificationReceiver::MESSAGE_TOO_LARGE], 413],
            'times' => 1,
        ]);
        $smartPixClient = Mockery::mock(NotificationsClient::class);
        $smartPixClient->shouldNotReceive('find');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once()->with('Received data content', AbstractNotification::class, ['source_news' => 'webhooks']);
        $file->shouldNotReceive('error');

        $notification = $this->routedNotification($posted, $legacyClient, $file, $this->smartPixHandler($smartPixClient));
        $notification->shouldNotReceive('handleSuccessfulRequest');

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    public function testOversizedSmartPixBodyIsRejectedAfterABoundedReadWithoutLoadingTheWholeBody(): void
    {
        $limit = SmartPixNotificationBody::DEFAULT_MAX_BYTES;
        $posted = '{"notification_id":"PPORDSYNTHETIC001","padding":"' . str_repeat('x', 4 * $limit) . '"}';
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 413, 'message' => SmartPixNotificationReceiver::MESSAGE_TOO_LARGE], 413],
            'times' => 1,
        ]);
        $smartPixClient = Mockery::mock(NotificationsClient::class);
        $smartPixClient->shouldNotReceive('find');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');

        $notification = $this->routedNotification(
            $posted,
            $legacyClient,
            $this->ignoringFileLog()->file,
            $this->smartPixHandler($smartPixClient),
            false
        );
        $notification->shouldReceive('getInput')->once()->with($limit + 1)->andReturn(substr($posted, 0, $limit + 1));
        $notification->shouldNotReceive('handleSuccessfulRequest');

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    /**
     * Only Smart Pix bodies are bounded: a large legacy body keeps the legacy fetch.
     *
     * @testWith ["P-123"]
     *           ["M-456"]
     */
    public function testOversizedLegacyBodyKeepsTheLegacyFetch(string $id): void
    {
        \WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        \WP_Mock::userFunction('wp_send_json', ['times' => 0]);
        $data = ['external_reference' => 'WC-321', 'status' => 'approved'];
        $fetched = Mockery::mock(\MercadoPago\Woocommerce\Api\Core\Models\Notification::class);
        $fetched->shouldReceive('toArray')->once()->andReturn($data);
        $smartPixClient = Mockery::mock(NotificationsClient::class);
        $smartPixClient->shouldNotReceive('find');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldReceive('find')->once()->with($id, Mockery::type(CoreRequestContext::class))->andReturn($fetched);
        $posted = (string) json_encode([
            'notification_id' => $id,
            'padding' => str_repeat('x', SmartPixNotificationBody::DEFAULT_MAX_BYTES),
        ]);

        $notification = $this->routedNotification(
            $posted,
            $legacyClient,
            $this->ignoringFileLog()->file,
            $this->smartPixHandler($smartPixClient)
        );
        $notification->shouldReceive('handleSuccessfulRequest')->once()->with($data);

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    public function testOversizedLegacyBodyIsReadWholeOnceAfterTheBoundedRead(): void
    {
        $limit = SmartPixNotificationBody::DEFAULT_MAX_BYTES;
        \WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        \WP_Mock::userFunction('wp_send_json', ['times' => 0]);
        $data = ['external_reference' => 'WC-321', 'status' => 'approved'];
        $fetched = Mockery::mock(\MercadoPago\Woocommerce\Api\Core\Models\Notification::class);
        $fetched->shouldReceive('toArray')->once()->andReturn($data);
        $smartPixClient = Mockery::mock(NotificationsClient::class);
        $smartPixClient->shouldNotReceive('find');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldReceive('find')->once()->with('P-123', Mockery::type(CoreRequestContext::class))->andReturn($fetched);
        $posted = (string) json_encode(['notification_id' => 'P-123', 'padding' => str_repeat('x', 2 * $limit)]);

        $notification = $this->routedNotification(
            $posted,
            $legacyClient,
            $this->ignoringFileLog()->file,
            $this->smartPixHandler($smartPixClient),
            false
        );
        $notification->shouldReceive('getInput')->once()->with($limit + 1)->andReturn(substr($posted, 0, $limit + 1));
        $notification->shouldReceive('getInput')->once()->withNoArgs()->andReturn($posted);
        $notification->shouldReceive('handleSuccessfulRequest')->once()->with($data);

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    public function testSmallBodyIsReadOnceWithinTheLimit(): void
    {
        \WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        \WP_Mock::userFunction('wp_send_json', ['times' => 0]);
        $data = ['external_reference' => 'WC-321', 'status' => 'approved'];
        $fetched = Mockery::mock(\MercadoPago\Woocommerce\Api\Core\Models\Notification::class);
        $fetched->shouldReceive('toArray')->once()->andReturn($data);
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldReceive('find')->once()->with('P-123', Mockery::type(CoreRequestContext::class))->andReturn($fetched);

        $notification = $this->routedNotification(
            '{"notification_id":"P-123"}',
            $legacyClient,
            $this->ignoringFileLog()->file,
            null,
            false
        );
        $notification->shouldReceive('getInput')->once()->with(SmartPixNotificationBody::DEFAULT_MAX_BYTES + 1)
            ->andReturn('{"notification_id":"P-123"}');
        $notification->shouldReceive('handleSuccessfulRequest')->once()->with($data);

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    /** A Smart Pix id placed after the inspected prefix is still caught once the body is decoded. */
    public function testOversizedBodyNamingASmartPixIdBeyondThePrefixIsRejectedWithoutFetching(): void
    {
        $posted = '{"padding":"' . str_repeat('x', SmartPixNotificationBody::DEFAULT_MAX_BYTES)
            . '","notification_id":"PPORDSYNTHETIC001"}';
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 413, 'message' => SmartPixNotificationReceiver::MESSAGE_TOO_LARGE], 413],
            'times' => 1,
        ]);
        $smartPixClient = Mockery::mock(NotificationsClient::class);
        $smartPixClient->shouldNotReceive('find');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once()->with('Received data content', AbstractNotification::class, ['source_news' => 'webhooks']);
        $file->shouldNotReceive('error');

        $notification = $this->routedNotification($posted, $legacyClient, $file, $this->smartPixHandler($smartPixClient));
        $notification->shouldNotReceive('handleSuccessfulRequest');

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    /** The size is known without the handler, so a composition failure still answers 413. */
    public function testOversizedSmartPixBodyAnswersTooLargeWhenTheHandlerCannotBeComposed(): void
    {
        // The plugin container is not loaded, so the default factory cannot compose the handler.
        $GLOBALS['mercadopago'] = null;
        $posted = '"PPORDSYNTHETIC001' . str_repeat(' ', SmartPixNotificationBody::DEFAULT_MAX_BYTES) . '"';
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 413, 'message' => SmartPixNotificationReceiver::MESSAGE_TOO_LARGE], 413],
            'times' => 1,
        ]);
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once();
        $file->shouldReceive('error')->once()->with('Smart Pix notification could not be handled', CoreNotification::class);

        $notification = $this->routedNotification($posted, $legacyClient, $file);
        $notification->shouldNotReceive('handleSuccessfulRequest');

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    /**
     * @testWith ["{\"notification_id\":\"PPORD-SYNTHETIC001\"}"]
     *           ["\"PPORD-SYNTHETIC001\""]
     *           ["{\"notification_id\":\"ppordsynthetic001\"}"]
     *           ["{\"notification_id\":\"PPORDSYNTHETIC001\\n\"}"]
     *           ["{\"notification_id\":\"PPORD\"}"]
     *           ["{\"notification_id\":123}"]
     *           ["{\"notification_id\":\"X-123\"}"]
     *           ["\"P-123-456\""]
     */
    public function testIdsOutsideBothFormatsKeepTheLegacyInvalidIdAnswer(string $posted): void
    {
        $data = ['source_news' => 'webhooks'];
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 400, 'message' => 'Invalid notification id'], 400],
            'times' => 1,
        ]);
        $smartPixClient = Mockery::mock(NotificationsClient::class);
        $smartPixClient->shouldNotReceive('find');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once();
        $file->shouldReceive('error')->once()->with('Invalid notification id', CoreNotification::class, $data);

        $notification = $this->routedNotification($posted, $legacyClient, $file, $this->smartPixHandler($smartPixClient));
        $notification->shouldNotReceive('handleSuccessfulRequest');

        $notification->handleReceivedNotification($data);

        $this->assertTrue(true);
    }

    /**
     * @testWith ["P-123"]
     *           ["M-456"]
     */
    public function testLegacyIdsKeepTheLegacyFetchWhenASmartPixHandlerIsAvailable(string $id): void
    {
        \WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        \WP_Mock::userFunction('wp_send_json', ['times' => 0]);
        $data = ['external_reference' => 'WC-321', 'status' => 'approved'];
        $fetched = Mockery::mock(\MercadoPago\Woocommerce\Api\Core\Models\Notification::class);
        $fetched->shouldReceive('toArray')->once()->andReturn($data);
        $smartPixClient = Mockery::mock(NotificationsClient::class);
        $smartPixClient->shouldNotReceive('find');
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldReceive('find')->once()->with($id, Mockery::type(CoreRequestContext::class))->andReturn($fetched);

        $notification = $this->routedNotification(
            (string) json_encode(['notification_id' => $id]),
            $legacyClient,
            $this->ignoringFileLog()->file,
            $this->smartPixHandler($smartPixClient)
        );
        $notification->shouldReceive('handleSuccessfulRequest')->once()->with($data);

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    /**
     * @testWith ["P-123"]
     *           ["M-456"]
     */
    public function testLegacyFetchFailureKeepsTheLegacyLogAndExceptionMessage(string $id): void
    {
        \WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 500, 'message' => 'Synthetic legacy failure'], 500],
            'times' => 1,
        ]);
        $data = ['source_news' => 'webhooks'];
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldReceive('find')->once()->andThrow(new \Exception('Synthetic legacy failure'));
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once();
        $file->shouldReceive('error')->once()->with('Synthetic legacy failure', CoreNotification::class, $data);

        $notification = $this->routedNotification((string) json_encode(['notification_id' => $id]), $legacyClient, $file);
        $notification->shouldNotReceive('handleSuccessfulRequest');

        $notification->handleReceivedNotification($data);

        $this->assertTrue(true);
    }

    public function testSmartPixHandlerCompositionFailureAnswersTheFixedServerErrorWithAFixedLog(): void
    {
        // The plugin container is not loaded, so the default factory cannot compose the handler.
        $GLOBALS['mercadopago'] = null;
        \WP_Mock::userFunction('wp_is_mobile', ['return' => false]);
        \WP_Mock::userFunction('wp_send_json', [
            'args' => [['status' => 500, 'message' => SmartPixNotificationReceiver::MESSAGE_FAILED], 500],
            'times' => 1,
        ]);
        $legacyClient = Mockery::mock(NotificationsClient::class);
        $legacyClient->shouldNotReceive('find');
        $file = Mockery::mock(File::class);
        $file->shouldReceive('info')->once();
        $file->shouldReceive('error')->once()->with('Smart Pix notification could not be handled', CoreNotification::class);

        $notification = $this->routedNotification('{"notification_id":"PPORDSYNTHETIC001"}', $legacyClient, $file);
        $notification->shouldNotReceive('handleSuccessfulRequest');

        $notification->handleReceivedNotification(['source_news' => 'webhooks']);

        $this->assertTrue(true);
    }

    private function routedNotification(
        string $posted,
        NotificationsClient $legacyClient,
        File $file,
        ?SmartPixCoreNotificationHandler $handler = null,
        bool $stubInput = true
    ) {
        $logs = Mockery::mock(Logs::class);
        $logs->file = $file;
        $seller = Mockery::mock(Seller::class);
        $seller->shouldReceive('getCredentialsAccessToken')->andReturn('SYNTHETIC_ACCESS_TOKEN');
        $store = Mockery::mock(Store::class);
        $store->shouldReceive('getIntegratorId')->andReturn('');

        $notification = Mockery::mock(CoreNotification::class, [
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            Mockery::mock(OrderStatus::class),
            $seller,
            $store,
            $legacyClient,
            $handler,
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        if ($stubInput) {
            $notification->shouldReceive('getInput')->andReturn($posted);
        }

        return $notification;
    }

    /** A real handler whose only reachable collaborator is the given notifications client. */
    private function smartPixHandler(NotificationsClient $client): SmartPixCoreNotificationHandler
    {
        $metadata = Mockery::mock(OrderMetadata::class);
        $seller = Mockery::mock(Seller::class);
        $orderClient = Mockery::mock(SmartPixOrderClient::class);
        $orderClient->shouldNotReceive('create', 'query', 'cancel', 'refund');
        $ownership = Mockery::mock(SmartPixEffectOwnershipStore::class);
        $ownership->shouldNotReceive('acquire');
        $telemetry = new SmartPixTelemetry();
        $receiver = new SmartPixNotificationReceiver(
            new SmartPixNotificationBody(),
            new DenyAllSmartPixNotificationAuthenticator(),
            new SmartPixCoreNotificationIdentifiers(),
            new SmartPixNotificationLinkage($metadata, $seller),
            new SmartPixOrderQuery($orderClient, $metadata, $seller, $telemetry, 'notification'),
            new SmartPixStateApplicator($metadata, new SmartPixStateReducer(), $seller, $ownership, $telemetry),
            $telemetry
        );

        return new SmartPixCoreNotificationHandler($client, $receiver);
    }

    public function testGetRequestContext()
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

        $method = new \ReflectionMethod(CoreNotification::class, 'getRequestContext');
        $method->setAccessible(true);
        $context = $method->invoke($this->notification);

        $this->assertInstanceOf(CoreRequestContext::class, $context);
        $this->assertSame([
            'Authorization' => 'Bearer ' . $accessToken,
            'x-platform-id' => MP_PLATFORM_ID,
            'x-product-id' => $productId,
            'x-integrator-id' => $integratorId,
        ], $context->toHeaders());
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

    public function testEmptyRefundsNotifyingProcessesRegularPaymentStatus(): void
    {
        $data = [
            'status' => 'approved',
            'refunds_notifying' => [],
        ];

        $order = Mockery::mock(WC_Order::class);
        $order->expects()->get_status()->andReturn('pending');
        $order->expects()->save();

        $orderStatus = Mockery::mock(OrderStatus::class)->makePartial();
        $orderStatus->expects()
            ->processStatus('approved', $data, $order, Mockery::type('string'))
            ->andReturn(true);

        $logs = Mockery::mock(Logs::class);
        $logs->file = Mockery::mock(File::class);
        $logs->file->shouldReceive('info')->andReturnNull();

        $notification = new CoreNotification(
            Mockery::mock(MercadoPagoGatewayInterface::class),
            $logs,
            $orderStatus,
            Mockery::mock(Seller::class),
            Mockery::mock(Store::class),
            Mockery::mock(NotificationsClient::class)
        );

        $notification->handleSuccessfulRequestInternal($data, $order);
    }

    /**
     * Tests that a successful refund notification emits mp_refund_success with
     * the order's checkout_type as the payment_method tag (PSW-4309).
     */
    public function testHandleRefundNotificationEmitsSuccessMetricWithCheckoutType(): void
    {
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
            Mockery::mock(NotificationsClient::class),
        ])->makePartial();

        $notification->shouldReceive('getProcessedStatus')->andReturn('refunded');
        // processStatus returns true (refund applied) → success metric must be emitted.
        $notification->shouldReceive('processStatus')->once()->andReturn(true);

        $notification->handleSuccessfulRequestInternal($data, $order);

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
            Mockery::mock(NotificationsClient::class),
        ])->makePartial();

        $notification->shouldReceive('getProcessedStatus')->andReturn('refunded');
        // processStatus returns false (wc_create_refund failed) → no success metric.
        $notification->shouldReceive('processStatus')->once()->andReturn(false);

        $notification->handleSuccessfulRequestInternal($data, $order);

    }

    /**
     * Tests that a validation failure (missing refund_id) emits mp_refund_error
     * with the order's checkout_type as the payment_method tag (PSW-4309).
     */
    public function testHandleRefundNotificationEmitsErrorMetricWhenRefundIdMissing(): void
    {

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
            Mockery::mock(NotificationsClient::class),
        ])->makePartial();

        $notification->handleSuccessfulRequestInternal($data, $order);

    }
}
