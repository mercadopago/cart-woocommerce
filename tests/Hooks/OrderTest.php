<?php

namespace MercadoPago\Woocommerce\Tests\Hooks;

use Exception;
use MercadoPago\Woocommerce\Hooks\Order;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Helpers\Url;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Tests\Traits\WoocommerceMock;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;
use WC_Order;

class OrderTest extends TestCase
{
    use WoocommerceMock;

    private Order $order;

    private OrderStatus $orderStatusMock;

    private OrderMetadata $orderMetadataMock;

    private AdminTranslations $adminTranslationsMock;

    private Url $urlMock;

    private Logs $logsMock;

    private array $statusSyncTranslations;

    private static \ReflectionClass $orderReflection;

    public static function setUpBeforeClass(): void
    {
        self::$orderReflection = new \ReflectionClass(Order::class);
    }

    public function setUp(): void
    {
        $this->statusSyncTranslations = [
            'metabox_title'              => 'Payment Status',
            'card_title'                 => 'Card Title',
            'link_description_success'   => 'Success Link',
            'link_description_pending'   => 'Pending Link',
            'link_description_failure'   => 'Failure Link',
            'sync_button_success'        => 'Sync Success',
            'sync_button_pending'        => 'Sync Pending',
            'sync_button_failure'        => 'Sync Failure',
            'alert_title_accredited'     => 'Payment Approved',
            'description_accredited'     => 'Payment was approved',
            'alert_title_generic'        => 'Generic Alert',
            'description_generic'        => 'Generic Description',
        ];

        $this->orderStatusMock = Mockery::mock(OrderStatus::class);

        $this->orderMetadataMock = Mockery::mock(OrderMetadata::class);
        $this->orderMetadataMock->shouldIgnoreMissing();

        $this->adminTranslationsMock = Mockery::mock(AdminTranslations::class);
        $this->adminTranslationsMock->statusSync = $this->statusSyncTranslations;
        $this->adminTranslationsMock->links = ['reasons_refusals' => 'https://www.mercadopago.com/reasons'];

        $this->urlMock = Mockery::mock(Url::class);
        $this->urlMock->shouldReceive('getImageAsset')->andReturn('icon.png')->byDefault();

        $this->logsMock = Mockery::mock(Logs::class);
        $fileMock = Mockery::mock(File::class);
        $fileMock->shouldIgnoreMissing();
        $this->logsMock->file = $fileMock;

        $this->order = self::$orderReflection->newInstanceWithoutConstructor();

        $this->setPrivateProperty('orderStatus', $this->orderStatusMock);
        $this->setPrivateProperty('orderMetadata', $this->orderMetadataMock);
        $this->setPrivateProperty('adminTranslations', $this->adminTranslationsMock);
        $this->setPrivateProperty('url', $this->urlMock);
        $this->setPrivateProperty('logs', $this->logsMock);
    }

    public function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        WP_Mock::tearDown();
        Mockery::close();
    }

    /**
     * getMetaboxData — early return scenarios
     */
    
    public function testGetMetaboxDataReturnsEmptyWhenLastPaymentIsFalse(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderStatusMock->shouldReceive('getAllPaymentsData')
            ->with($orderMock)
            ->andReturn([]);

        $result = $this->invokeGetMetaboxData($orderMock);

        $this->assertSame([], $result);
    }

public function testGetMetaboxDataReturnsEmptyWhenExceptionIsThrown(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderStatusMock->shouldReceive('getAllPaymentsData')
            ->with($orderMock)
            ->andThrow(new Exception('API error'));

        $result = $this->invokeGetMetaboxData($orderMock);

        $this->assertSame([], $result);
    }

    /**
     * getMetaboxData — status mapping via dataProvider
     */

    /**
     * @dataProvider metaboxStatusProvider
     */
    public function testGetMetaboxDataReturnsCorrectDataForPaymentStatus(
        string $status,
        string $statusDetail,
        string $expectedColor,
        string $expectedButtonText
    ): void {
        $orderMock = Mockery::mock(WC_Order::class);
        $paymentData = [
            'status' => $status,
            'status_detail' => $statusDetail,
            'payment_type_id' => 'credit_card',
        ];

        $this->orderStatusMock->shouldReceive('getAllPaymentsData')
            ->with($orderMock)
            ->andReturn([$paymentData]);

        $result = $this->invokeGetMetaboxData($orderMock);

        $this->assertSame('Card Title', $result['card_title']);
        $this->assertSame($expectedColor, $result['border_left_color']);
        $this->assertSame($expectedButtonText, $result['sync_button_text']);
    }

    public static function metaboxStatusProvider(): array
    {
        return [
            'approved'     => ['approved',     'accredited',              '#00A650', 'Sync Success'],
            'authorized'   => ['authorized',   'accredited',              '#00A650', 'Sync Success'],
            'pending'      => ['pending',      'pending_waiting_payment', '#f73',    'Sync Pending'],
            'in_process'   => ['in_process',   'pending_review_manual',   '#f73',    'Sync Pending'],
            'rejected'     => ['rejected',     'cc_rejected_other_reason','#F23D4F', 'Sync Failure'],
            'refunded'     => ['refunded',     'refunded',                '#F23D4F', 'Sync Failure'],
            'charged_back' => ['charged_back', 'charged_back',            '#F23D4F', 'Sync Failure'],
        ];
    }

    /**
     * getLastPaymentInfo
     */

    public function testGetLastPaymentInfoReturnsFalseWhenNoPayments(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderStatusMock->shouldReceive('getAllPaymentsData')
            ->with($orderMock)
            ->andReturn([]);

        $result = $this->invokeGetLastPaymentInfo($orderMock);

        $this->assertFalse($result);
    }

    public function testGetLastPaymentInfoReturnsFalseOnException(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderStatusMock->shouldReceive('getAllPaymentsData')
            ->with($orderMock)
            ->andThrow(new Exception('API error'));

        $result = $this->invokeGetLastPaymentInfo($orderMock);

        $this->assertFalse($result);
    }

    public function testGetLastPaymentInfoReturnsLastPayment(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $payments = [
            ['status' => 'approved', 'status_detail' => 'accredited', 'payment_type_id' => 'credit_card'],
            ['status' => 'pending', 'status_detail' => 'pending_waiting', 'payment_type_id' => 'ticket'],
        ];

        $this->orderStatusMock->shouldReceive('getAllPaymentsData')
            ->with($orderMock)
            ->andReturn($payments);

        $result = $this->invokeGetLastPaymentInfo($orderMock);

        $this->assertSame('pending', $result['status']);
        $this->assertSame('ticket', $result['payment_type_id']);
    }

    public function testGetLastPaymentInfoChangesStatusDetailForSingleRefundedByAdmin(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $payments = [
            ['status' => 'refunded', 'status_detail' => 'by_admin', 'payment_type_id' => 'credit_card'],
        ];

        $this->orderStatusMock->shouldReceive('getAllPaymentsData')
            ->with($orderMock)
            ->andReturn($payments);

        $result = $this->invokeGetLastPaymentInfo($orderMock);

        $this->assertSame('refunded', $result['status_detail']);
    }

    /**
     * hasMultiplePayments / hasRefundedPayments
     */

    public function testHasMultiplePaymentsReturnsTrueForMultiple(): void
    {
        $result = $this->invokePrivateMethod('hasMultiplePayments', [
            [['id' => 1], ['id' => 2]]
        ]);
        $this->assertTrue($result);
    }

    public function testHasMultiplePaymentsReturnsFalseForSingle(): void
    {
        $result = $this->invokePrivateMethod('hasMultiplePayments', [
            [['id' => 1]]
        ]);
        $this->assertFalse($result);
    }

    public function testHasRefundedPaymentsReturnsTrueForRefunded(): void
    {
        $result = $this->invokePrivateMethod('hasRefundedPayments', [
            [
                ['status' => 'approved', 'status_detail' => 'accredited'],
                ['status' => 'refunded', 'status_detail' => 'refunded'],
            ]
        ]);
        $this->assertTrue($result);
    }

    public function testHasRefundedPaymentsReturnsTrueForPartiallyRefunded(): void
    {
        $result = $this->invokePrivateMethod('hasRefundedPayments', [
            [
                ['status' => 'approved', 'status_detail' => 'partially_refunded'],
            ]
        ]);
        $this->assertTrue($result);
    }

    public function testHasRefundedPaymentsReturnsFalseWhenNoRefunds(): void
    {
        $result = $this->invokePrivateMethod('hasRefundedPayments', [
            [
                ['status' => 'approved', 'status_detail' => 'accredited'],
            ]
        ]);
        $this->assertFalse($result);
    }

    /**
     * isRefundMetadataUpToDate
     */

    public function testIsRefundMetadataUpToDateReturnsTrueWhenMatches(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $paymentsData = [
            ['id' => '123', 'transaction_amount_refunded' => 25.5],
        ];

        $this->orderMetadataMock->shouldReceive('hasMetadataField')
            ->with($orderMock, 'Mercado Pago - Payment 123', '[Refund')
            ->andReturn(true);
        $this->orderMetadataMock->shouldReceive('getMetadataFieldValue')
            ->with($orderMock, 'Mercado Pago - Payment 123', 'Refund')
            ->andReturn('25.5');

        $result = $this->invokePrivateMethod('isRefundMetadataUpToDate', [$orderMock, $paymentsData]);
        $this->assertTrue($result);
    }

    public function testIsRefundMetadataUpToDateReturnsFalseWhenFieldMissing(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $paymentsData = [
            ['id' => '123', 'transaction_amount_refunded' => 25.5],
        ];

        $this->orderMetadataMock->shouldReceive('hasMetadataField')
            ->with($orderMock, 'Mercado Pago - Payment 123', '[Refund')
            ->andReturn(false);

        $result = $this->invokePrivateMethod('isRefundMetadataUpToDate', [$orderMock, $paymentsData]);
        $this->assertFalse($result);
    }

    public function testIsRefundMetadataUpToDateReturnsFalseWhenAmountsDiffer(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $paymentsData = [
            ['id' => '123', 'transaction_amount_refunded' => 50.0],
        ];

        $this->orderMetadataMock->shouldReceive('hasMetadataField')
            ->with($orderMock, 'Mercado Pago - Payment 123', '[Refund')
            ->andReturn(true);
        $this->orderMetadataMock->shouldReceive('getMetadataFieldValue')
            ->with($orderMock, 'Mercado Pago - Payment 123', 'Refund')
            ->andReturn('25.5');

        $result = $this->invokePrivateMethod('isRefundMetadataUpToDate', [$orderMock, $paymentsData]);
        $this->assertFalse($result);
    }

    public function testIsRefundMetadataUpToDateReturnsFalseOnException(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $paymentsData = [
            ['id' => '123', 'transaction_amount_refunded' => 25.5],
        ];

        $this->orderMetadataMock->shouldReceive('hasMetadataField')
            ->andThrow(new Exception('DB error'));

        $result = $this->invokePrivateMethod('isRefundMetadataUpToDate', [$orderMock, $paymentsData]);
        $this->assertFalse($result);
    }

    /**
     * updateMetadataIfStatusChanged
     */

    public function testUpdateMetadataSkipsWhenCompletedAndApproved(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_status')->andReturn('completed');

        $this->orderMetadataMock->shouldNotReceive('updateOrderCustomFieldsAfterSync');

        $this->invokePrivateMethod('updateMetadataIfStatusChanged', [$orderMock, 'approved']);
    }

    public function testUpdateMetadataUpdatesOnStatusChange(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_status')->andReturn('pending');
        $orderMock->shouldReceive('get_meta')->andReturn('')->byDefault();

        $this->orderStatusMock->shouldReceive('mapMpStatusToWoocommerceStatus')
            ->with('approved')
            ->andReturn('processing');

        $this->orderStatusMock->shouldReceive('getAllPaymentsData')
            ->with($orderMock)
            ->andReturn([['id' => '123']]);

        $this->orderMetadataMock->shouldReceive('updateOrderCustomFieldsAfterSync')
            ->once()
            ->with($orderMock, [['id' => '123']]);

        $this->invokePrivateMethod('updateMetadataIfStatusChanged', [$orderMock, 'approved']);
    }

    public function testUpdateMetadataHandlesException(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_status')->andThrow(new Exception('error'));

        $this->invokePrivateMethod('updateMetadataIfStatusChanged', [$orderMock, 'approved']);
        $this->assertTrue(true, 'Exception must not propagate');
    }

    /**
     * sendEventOnAction / sendEventOnSelect
     */

    public function testSendEventOnActionDelegatesToDatadog(): void
    {
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Singleton\Singleton::class);
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('order_sync_status_action', 'success', null);
        $this->setPrivateProperty('datadog', $datadogMock);

        $this->invokePrivateMethod('sendEventOnAction', ['success']);
    }

    public function testSendEventOnActionPassesErrorMessage(): void
    {
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Singleton\Singleton::class);
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('order_sync_status_action', 'error', 'some error');
        $this->setPrivateProperty('datadog', $datadogMock);

        $this->invokePrivateMethod('sendEventOnAction', ['error', 'some error']);
    }

    public function testSendEventOnSelectDelegatesToDatadog(): void
    {
        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Singleton\Singleton::class);
        $datadogMock->shouldReceive('sendEvent')
            ->once()
            ->with('order_toggle_cron', 'hourly');
        $this->setPrivateProperty('datadog', $datadogMock);

        $this->invokePrivateMethod('sendEventOnSelect', ['hourly']);
    }

    /**
     * syncOrderStatus
     */

    public function testSyncOrderStatusCallsProcessStatusWithNotificationData(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_status')->andReturn('pending')->byDefault();
        $orderMock->shouldReceive('get_meta')->andReturn('')->byDefault();

        $this->orderStatusMock->shouldReceive('getLastNotification')
            ->with($orderMock)
            ->andReturn([['status' => 'approved', 'id' => '123']]);

        // updateMetadataIfStatusChanged dependencies
        $this->orderStatusMock->shouldReceive('mapMpStatusToWoocommerceStatus')->andReturn('processing')->byDefault();
        $this->orderStatusMock->shouldReceive('getAllPaymentsData')->andReturn([])->byDefault();

        $this->orderMetadataMock->shouldReceive('getUsedGatewayData')->andReturn('woo-mercado-pago-custom');
        $this->orderMetadataMock->shouldReceive('updateOrderCustomFieldsAfterSync')->byDefault();

        // Core assertion: processStatus is called with correct gateway
        $this->orderStatusMock->shouldReceive('processStatus')
            ->once()
            ->with('approved', Mockery::type('array'), $orderMock, 'woo-mercado-pago-custom');

        $this->order->syncOrderStatus($orderMock);
    }

    public function testSyncOrderStatusReturnsEarlyWhenNoNotification(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderStatusMock->shouldReceive('getLastNotification')
            ->with($orderMock)
            ->andReturn([]);

        $this->orderStatusMock->shouldNotReceive('processStatus');

        $this->order->syncOrderStatus($orderMock);
    }

    public function testSyncOrderStatusHandlesException(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderStatusMock->shouldReceive('getLastNotification')
            ->with($orderMock)
            ->andThrow(new Exception('API error'));

        $this->orderStatusMock->shouldNotReceive('processStatus');

        $this->order->syncOrderStatus($orderMock);
        $this->assertTrue(true, 'Exception must not propagate');
    }

    /**
     * selectSyncPendingStatusOrdersCron
     */

    public function testSelectSyncPendingStatusOrdersCronRegistersWhenNotNo(): void
    {
        $cronMock = Mockery::mock(\MercadoPago\Woocommerce\Helpers\Cron::class);
        $cronMock->shouldReceive('registerScheduledEvent')
            ->once()
            ->with('hourly', 'mercadopago_sync_pending_status_order_action');
        $this->setPrivateProperty('cron', $cronMock);

        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Singleton\Singleton::class);
        $datadogMock->shouldReceive('sendEvent')->once();
        $this->setPrivateProperty('datadog', $datadogMock);

        $this->order->selectSyncPendingStatusOrdersCron('hourly');
    }

    public function testSelectSyncPendingStatusOrdersCronUnregistersWhenNo(): void
    {
        $cronMock = Mockery::mock(\MercadoPago\Woocommerce\Helpers\Cron::class);
        $cronMock->shouldReceive('unregisterScheduledEvent')
            ->once()
            ->with('mercadopago_sync_pending_status_order_action');
        $this->setPrivateProperty('cron', $cronMock);

        $datadogMock = Mockery::mock(\MercadoPago\Woocommerce\Libraries\Singleton\Singleton::class);
        $datadogMock->shouldReceive('sendEvent')->once();
        $this->setPrivateProperty('datadog', $datadogMock);

        $this->order->selectSyncPendingStatusOrdersCron('no');
    }

    /**
     * registerMetaBox
     */

    public function testRegisterMetaBoxAddsActionsForBothOrderScreens(): void
    {
        $callback = function () {};

        WP_Mock::expectActionAdded('add_meta_boxes_shop_order', $callback);
        WP_Mock::expectActionAdded('add_meta_boxes_woocommerce_page_wc-orders', $callback);

        $this->order->registerMetaBox($callback);
    }

    /**
     * addMetaBox
     */

    public function testAddMetaBoxCallsWordPressAddMetaBox(): void
    {
        WP_Mock::userFunction('add_meta_box', [
            'times' => 1,
            'args' => ['test_id', 'Test Title', Mockery::type('Closure')],
        ]);

        $this->order->addMetaBox('test_id', 'Test Title', 'template.php', ['key' => 'value']);
    }

    /**
     * Hook registration methods
     */

    public function testRegisterOrderDetailsAfterOrderTableAddsAction(): void
    {
        $callback = function () {};
        WP_Mock::expectActionAdded('woocommerce_order_details_after_order_table', $callback);

        $this->order->registerOrderDetailsAfterOrderTable($callback);
    }

    public function testRegisterEmailBeforeOrderTableAddsAction(): void
    {
        $callback = function () {};
        WP_Mock::expectActionAdded('woocommerce_email_before_order_table', $callback);

        $this->order->registerEmailBeforeOrderTable($callback);
    }

    public function testRegisterAdminOrderTotalsAfterTotalAddsAction(): void
    {
        $callback = function () {};
        WP_Mock::expectActionAdded('woocommerce_admin_order_totals_after_total', $callback);

        $this->order->registerAdminOrderTotalsAfterTotal($callback);
    }

    /**
     * addOrderNote
     */

    public function testAddOrderNoteDelegatesToWcOrder(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('add_order_note')
            ->once()
            ->with('Test note', 0, false);

        $this->order->addOrderNote($orderMock, 'Test note');
    }

    public function testAddOrderNotePassesCustomerNoteAndUser(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('add_order_note')
            ->once()
            ->with('Customer note', 1, true);

        $this->order->addOrderNote($orderMock, 'Customer note', 1, true);
    }

    /**
     * setTicketMetadata
     */

    public function testSetTicketMetadataSavesExternalResourceUrl(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $data = ['transaction_details' => ['external_resource_url' => 'https://example.com/ticket']];

        $this->orderMetadataMock->shouldReceive('setTicketTransactionDetailsData')
            ->once()
            ->with($orderMock, 'https://example.com/ticket');
        $orderMock->shouldReceive('save')->once();

        $this->order->setTicketMetadata($orderMock, $data);
    }

    /**
     * setPixMetadata
     */

    public function testSetPixMetadataSavesAllPixFields(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $gatewayMock = Mockery::mock(\MercadoPago\Woocommerce\Gateways\PixGateway::class);

        $data = [
            'transaction_amount' => 100.50,
            'point_of_interaction' => [
                'transaction_data' => [
                    'qr_code_base64' => 'base64string',
                    'qr_code' => '00020101021226',
                ],
            ],
        ];

        $this->orderMetadataMock->shouldReceive('setTransactionAmountData')->once()->with($orderMock, 100.50);
        $this->orderMetadataMock->shouldReceive('setPixQrBase64Data')->once()->with($orderMock, 'base64string');
        $this->orderMetadataMock->shouldReceive('setPixQrCodeData')->once()->with($orderMock, '00020101021226');
        $gatewayMock->shouldReceive('getCheckoutExpirationDate')->once()->andReturn('2026-04-01');
        $this->orderMetadataMock->shouldReceive('setPixExpirationDateData')->once()->with($orderMock, '2026-04-01');
        $this->orderMetadataMock->shouldReceive('setPixOnData')->once()->with($orderMock, 1);
        $orderMock->shouldReceive('save')->once();

        $this->order->setPixMetadata($gatewayMock, $orderMock, $data);
    }

    /**
     * Helpers
     */

    private function invokeGetMetaboxData(WC_Order $order): array
    {
        $reflection = new \ReflectionMethod(Order::class, 'getMetaboxData');
        $reflection->setAccessible(true);
        return $reflection->invoke($this->order, $order);
    }

    private function invokeGetLastPaymentInfo(WC_Order $order)
    {
        $reflection = new \ReflectionMethod(Order::class, 'getLastPaymentInfo');
        $reflection->setAccessible(true);
        return $reflection->invoke($this->order, $order);
    }

    private function invokePrivateMethod(string $method, array $args)
    {
        $reflection = new \ReflectionMethod(Order::class, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($this->order, ...$args);
    }

    private function setPrivateProperty(string $property, $value): void
    {
        $reflection = new \ReflectionProperty(Order::class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($this->order, $value);
    }
}
