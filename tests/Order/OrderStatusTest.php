<?php

namespace MercadoPago\Woocommerce\Tests\Order;

use MercadoPago\Woocommerce\Order\OrderStatus;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Helpers\Requester;
use MercadoPago\Woocommerce\Translations\StoreTranslations;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\File;
use MercadoPago\Woocommerce\Libraries\Logs\Transports\Remote;
use MercadoPago\PP\Sdk\HttpClient\Response;
use PHPUnit\Framework\TestCase;
use Mockery;
use WP_Mock;
use WC_Order;

class OrderStatusTest extends TestCase
{
    private OrderStatus $orderStatus;
    private StoreTranslations $storeTranslationsMock;
    private OrderMetadata $orderMetadataMock;
    private Seller $sellerMock;
    private Requester $requesterMock;
    private Logs $logsMock;

    public function setUp(): void
    {
        WP_Mock::setUp();

        $this->storeTranslationsMock = Mockery::mock(StoreTranslations::class);
        $this->storeTranslationsMock->orderStatus = [
            'payment_approved' => 'Payment Approved',
            'cho_approved' => 'Payment Approved',
            'cho_pending' => 'Payment Pending',
            'cho_default' => 'Default Message',
            'in_process' => 'In Process',
            'rejected' => 'Rejected',
            'refunded' => 'Refunded',
            'partial_refunded' => 'Partially Refunded: ',
            'cancelled' => 'Cancelled',
            'in_mediation' => 'In Mediation',
            'charged_back' => 'Charged Back',
            'validate_order_1' => 'Validate Order 1',
            'validate_order_2' => 'Validate Order 2'
        ];
        $this->storeTranslationsMock->commonMessages = [
            'cho_approved' => 'Payment Approved',
            'cho_pending' => 'Payment Pending',
            'cho_default' => 'Default Message'
        ];

        $this->orderMetadataMock = Mockery::mock(OrderMetadata::class);
        $this->sellerMock = Mockery::mock(Seller::class);
        $this->requesterMock = Mockery::mock(Requester::class);

        $this->logsMock = Mockery::mock(Logs::class);
        $fileMock = Mockery::mock(File::class);
        $remoteMock = Mockery::mock(Remote::class);
        $fileMock->shouldIgnoreMissing();
        $remoteMock->shouldIgnoreMissing();
        $this->logsMock->file = $fileMock;
        $this->logsMock->remote = $remoteMock;

        $this->orderStatus = new OrderStatus(
            $this->storeTranslationsMock,
            $this->orderMetadataMock,
            $this->sellerMock,
            $this->requesterMock,
            $this->logsMock
        );
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
    }

    public function testGetOrderStatusMessageReturnsCorrectMessage(): void
    {
        $message = $this->orderStatus->getOrderStatusMessage('approved');
        $this->assertEquals('Payment Approved', $message);

        $message = $this->orderStatus->getOrderStatusMessage('unknown_status');
        $this->assertEquals('Default Message', $message);
    }

    public function testMapMpStatusToWoocommerceStatusReturnsCorrectWoocommerceStatus(): void
    {
        $statusMappings = [
            'pending' => 'pending',
            'approved' => 'processing',
            'inprocess' => 'on-hold',
            'inmediation' => 'on-hold',
            'rejected' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            'chargedback' => 'refunded',
        ];

        foreach ($statusMappings as $mpStatus => $expectedWooStatus) {
            $result = $this->orderStatus->mapMpStatusToWoocommerceStatus($mpStatus);
            $this->assertEquals($expectedWooStatus, $result);
        }
    }

    public function testInMediationFlowAddsOrderNotesAndUpdatesStatus(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_status')->andReturn('pending');
        $orderMock->shouldReceive('update_status')->with('on-hold');
        $orderMock->shouldReceive('add_order_note')->with('Mercado Pago: In Mediation');

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('inMediationFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $orderMock);
        $this->assertTrue(true);
    }

    public function testChargedBackFlowAddsOrderNotesAndUpdatesStatus(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_status')->andReturn('pending');
        $orderMock->shouldReceive('update_status')->with('refunded');
        $orderMock->shouldReceive('add_order_note')->with('Mercado Pago: Charged Back');

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('chargedBackFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $orderMock);
        $this->assertTrue(true);
    }

    public function testRefundedFlowFullRefundUpdatesOrderStatus(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total')->andReturn(100.0);
        $orderMock->shouldReceive('update_status')->with('refunded', 'Mercado Pago: Refunded');

        $data = [
            'notification_id' => 'notification_123',
            'transaction_amount' => 100.0,
            'total_refunded' => 100.0,
            'transaction_amount_refunded' => 100.0,
        ];

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('refundedFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertTrue(true);
    }

    public function testRefundedFlowPartialRefundCreatesRefundAndAddsNote(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total')->andReturn(100.0);
        $orderMock->shouldReceive('get_id')->andReturn(123);
        $orderMock->shouldReceive('get_total_refunded')->andReturn(0.0);
        $orderMock->shouldReceive('add_order_note')->with('Mercado Pago: Partially Refunded: 25.5');

        $paymentsData = [
            [
                'id' => 'payment_123',
                'status' => 'approved',
                'status_detail' => 'accredited',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 25.5
            ]
        ];

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('payment_123');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(200);
        $responseMock->shouldReceive('getData')->andReturn($paymentsData[0]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/payment_123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock);

        $data = [
            'notification_id' => 'notification_123',
            'transaction_amount' => 100.0,
            'total_refunded' => 25.5,
            'transaction_amount_refunded' => 25.5,
            'current_refund' => ['id' => 'refund_123'],
            'refunds_notifying' => [
                ['id' => 'refund_123', 'amount' => 25.5]
            ]
        ];

        WP_Mock::userFunction('wc_create_refund', [
            'times' => 1,
            'args' => [
                ['amount' => 25.5, 'reason' => 'Refunded', 'order_id' => 123]
            ],
            'return' => true
        ]);

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('refundedFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertTrue(true);
    }

    public function testRefundedFlowMultipleRefundsFindsCorrectOne(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total')->andReturn(100.0);
        $orderMock->shouldReceive('get_id')->andReturn(123);
        $orderMock->shouldReceive('get_total_refunded')->andReturn(0.0);
        $orderMock->shouldReceive('add_order_note')->with('Mercado Pago: Partially Refunded: 50.75');

        $paymentsData = [
            [
                'id' => 'payment_123',
                'status' => 'approved',
                'status_detail' => 'accredited',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 50.75
            ]
        ];

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('payment_123');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(200);
        $responseMock->shouldReceive('getData')->andReturn($paymentsData[0]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/payment_123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock);

        $data = [
            'notification_id' => 'notification_123',
            'transaction_amount' => 100.0,
            'total_refunded' => 50.75,
            'transaction_amount_refunded' => 50.75,
            'current_refund' => ['id' => 'refund_456'],
            'refunds_notifying' => [
                ['id' => 'refund_123', 'amount' => 25.5],
                ['id' => 'refund_456', 'amount' => 50.75],
                ['id' => 'refund_789', 'amount' => 10.0]
            ]
        ];

        WP_Mock::userFunction('wc_create_refund', [
            'times' => 1,
            'args' => [
                ['amount' => 50.75, 'reason' => 'Refunded', 'order_id' => 123]
            ],
            'return' => true
        ]);

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('refundedFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertTrue(true);
    }

    public function testGetRefundedStatusDetailBasedOnPaymentStatus(): void
    {
        $paymentsData1 = [
            [
                'status' => 'refunded',
                'status_detail' => 'refunded',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 100.0
            ]
        ];
        $result1 = $this->orderStatus->getRefundedStatusDetail($paymentsData1);
        $this->assertEquals(['title' => 'refunded', 'description' => 'refunded'], $result1);

        $paymentsData2 = [
            [
                'status' => 'approved',
                'status_detail' => 'accredited',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 0
            ]
        ];
        $result2 = $this->orderStatus->getRefundedStatusDetail($paymentsData2);
        $this->assertEquals(['title' => 'approved', 'description' => 'partially_refunded'], $result2);

        $paymentsData3 = [
            [
                'status' => 'approved',
                'status_detail' => 'accredited',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 50.0
            ]
        ];
        $result3 = $this->orderStatus->getRefundedStatusDetail($paymentsData3);
        $this->assertEquals(['title' => 'approved', 'description' => 'partially_refunded'], $result3);
    }

    public function testGetRefundedStatusDetailVariousPaymentStatuses(): void
    {
        $paymentsDataWithApproved = [
            ['id' => '123', 'status' => 'approved', 'status_detail' => 'accredited'],
            ['id' => '456', 'status' => 'refunded', 'status_detail' => 'refunded']
        ];

        $result = $this->orderStatus->getRefundedStatusDetail($paymentsDataWithApproved);
        $expected = ['title' => 'approved', 'description' => 'partially_refunded'];
        $this->assertEquals($expected, $result);

        $paymentsDataWithPartiallyRefunded = [
            ['id' => '123', 'status' => 'approved', 'status_detail' => 'partially_refunded'],
            ['id' => '456', 'status' => 'refunded', 'status_detail' => 'refunded']
        ];

        $result = $this->orderStatus->getRefundedStatusDetail($paymentsDataWithPartiallyRefunded);
        $expected = ['title' => 'approved', 'description' => 'partially_refunded'];
        $this->assertEquals($expected, $result);

        $paymentsDataWithoutApproved = [
            ['id' => '123', 'status' => 'refunded', 'status_detail' => 'refunded'],
            ['id' => '456', 'status' => 'refunded', 'status_detail' => 'by_admin']
        ];

        $result = $this->orderStatus->getRefundedStatusDetail($paymentsDataWithoutApproved);
        $expected = ['title' => 'refunded', 'description' => 'refunded'];
        $this->assertEquals($expected, $result);

        $emptyPaymentsData = [];
        $result = $this->orderStatus->getRefundedStatusDetail($emptyPaymentsData);
        $expected = ['title' => 'refunded', 'description' => 'refunded'];
        $this->assertEquals($expected, $result);
    }

    public function testGetPaymentsDataSuccessfulResponse(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('123,456');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMocks = [];
        $expectedData = [];
        $paymentIds = ['123', '456'];
        $statuses = ['approved', 'refunded'];
        $statusDetails = ['partially_refunded', 'refunded'];

        foreach ($paymentIds as $index => $paymentId) {
            $responseMocks[$index] = Mockery::mock(Response::class);
            $responseMocks[$index]->shouldReceive('getStatus')->andReturn(200);

            $data = [
                'id' => $paymentId,
                'status' => $statuses[$index],
                'status_detail' => $statusDetails[$index]
            ];

            $responseMocks[$index]->shouldReceive('getData')->andReturn($data);
            $expectedData[] = $data;

            $this->requesterMock->shouldReceive('get')
                ->with("/v1/payments/$paymentId", ['Authorization: Bearer access_token_123'])
                ->andReturn($responseMocks[$index]);
        }

        $result = $this->orderStatus->getAllPaymentsData($orderMock);
        $this->assertEquals($expectedData, $result);
    }

    public function testGetPaymentsDataApiError(): void
    {
        $this->expectException(\Exception::class);

        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('123');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(400);
        $responseMock->shouldReceive('getData')->andReturn(['error' => 'Invalid request']);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock);

        $this->orderStatus->getAllPaymentsData($orderMock);
    }

    public function testGetPaymentsDataEmptyPaymentIds(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $result = $this->orderStatus->getAllPaymentsData($orderMock);
        $this->assertEquals([], $result);
    }

    public function testGetPaymentsDataHandlesWhitespace(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn(' 123 , 456 , ');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock1 = Mockery::mock(Response::class);
        $responseMock1->shouldReceive('getStatus')->andReturn(200);
        $responseMock1->shouldReceive('getData')->andReturn(['id' => '123', 'status' => 'approved']);

        $responseMock2 = Mockery::mock(Response::class);
        $responseMock2->shouldReceive('getStatus')->andReturn(200);
        $responseMock2->shouldReceive('getData')->andReturn(['id' => '456', 'status' => 'refunded']);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock1);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/456', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock2);

        $result = $this->orderStatus->getAllPaymentsData($orderMock);

        $expected = [
            ['id' => '123', 'status' => 'approved'],
            ['id' => '456', 'status' => 'refunded']
        ];

        $this->assertEquals($expected, $result);
    }


    public function testGetRefundedStatusDetailMerchantOrderOneRefund(): void
    {
        $paymentsData = [
            [
                'id' => '123',
                'status' => 'approved',
                'status_detail' => 'accredited',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 0.0
            ],
            [
                'id' => '456',
                'status' => 'refunded',
                'status_detail' => 'refunded',
                'transaction_amount' => 50.0,
                'transaction_amount_refunded' => 50.0
            ]
        ];

        $result = $this->orderStatus->getRefundedStatusDetail($paymentsData);
        $expected = ['title' => 'approved', 'description' => 'partially_refunded'];
        $this->assertEquals($expected, $result);
    }

    public function testGetRefundedStatusDetailMerchantOrderMultiplePartialRefunds(): void
    {
        $paymentsData = [
            [
                'id' => '123',
                'status' => 'approved',
                'status_detail' => 'partially_refunded',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 25.0
            ],
            [
                'id' => '456',
                'status' => 'approved',
                'status_detail' => 'partially_refunded',
                'transaction_amount' => 50.0,
                'transaction_amount_refunded' => 30.0
            ]
        ];

        $result = $this->orderStatus->getRefundedStatusDetail($paymentsData);
        $expected = ['title' => 'approved', 'description' => 'partially_refunded'];
        $this->assertEquals($expected, $result);
    }

    public function testGetRefundedStatusDetailMerchantOrderMixedRefundStatuses(): void
    {
        $paymentsData = [
            [
                'id' => '123',
                'status' => 'approved',
                'status_detail' => 'partially_refunded',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 25.0
            ],
            [
                'id' => '456',
                'status' => 'refunded',
                'status_detail' => 'refunded',
                'transaction_amount' => 50.0,
                'transaction_amount_refunded' => 50.0
            ]
        ];

        $result = $this->orderStatus->getRefundedStatusDetail($paymentsData);
        $expected = ['title' => 'approved', 'description' => 'partially_refunded'];
        $this->assertEquals($expected, $result);
    }

    public function testGetRefundedStatusDetailMerchantOrderFullRefund(): void
    {
        $paymentsData = [
            ['id' => '123', 'status' => 'refunded', 'status_detail' => 'refunded'],
            ['id' => '456', 'status' => 'refunded', 'status_detail' => 'by_admin']
        ];

        $result = $this->orderStatus->getRefundedStatusDetail($paymentsData);
        $expected = ['title' => 'refunded', 'description' => 'refunded'];
        $this->assertEquals($expected, $result);
    }

    public function testRefundedFlowApiError(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total_refunded')->andReturn(0.0);

        $data = [
            'notification_id' => 'notification_123',
            'transaction_type' => 'merchant_order'
        ];

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('123');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(400);
        $responseMock->shouldReceive('getData')->andReturn(['error' => 'Invalid request']);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock);

        $fileMock = $this->logsMock->file;
        $fileMock->shouldReceive('error')
            ->with('Mercado Pago: Error processing refund validation: {"error":"Invalid request"}', 'MercadoPago\Woocommerce\Order\OrderStatus')
            ->once();

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('refundedFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertTrue(true);
    }

    public function testCalculateTotalRefundedWithMultiplePayments(): void
    {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('calculateTotalRefunded');
        $method->setAccessible(true);

        $paymentsData = [
            ['transaction_amount_refunded' => 25.50],
            ['transaction_amount_refunded' => 10.25],
            ['transaction_amount_refunded' => 5.00]
        ];
        $result = $method->invoke($this->orderStatus, $paymentsData);
        $this->assertEquals(40.75, $result);

        $paymentsDataNoRefunds = [
            ['transaction_amount_refunded' => 0],
            ['transaction_amount_refunded' => 0]
        ];
        $result = $method->invoke($this->orderStatus, $paymentsDataNoRefunds);
        $this->assertEquals(0.0, $result);

        $result = $method->invoke($this->orderStatus, []);
        $this->assertEquals(0.0, $result);

        $paymentsDataMissingField = [
            ['transaction_amount_refunded' => 15.00],
            ['id' => 'payment_2']
        ];
        $result = $method->invoke($this->orderStatus, $paymentsDataMissingField);
        $this->assertEquals(15.00, $result);
    }

    public function testRefundAlreadyProcessedLogic(): void
    {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('refundAlreadyProcessed');
        $method->setAccessible(true);

        $orderMock1 = Mockery::mock(WC_Order::class);
        $orderMock1->shouldReceive('get_total_refunded')->andReturn(50.0);
        $paymentsData = [
            ['transaction_amount_refunded' => 25.0],
            ['transaction_amount_refunded' => 20.0]
        ];
        $result = $method->invoke($this->orderStatus, $orderMock1, $paymentsData);
        $this->assertTrue($result, 'Should return true when MP refunded (45.0) <= WC refunded (50.0)');

        $orderMock2 = Mockery::mock(WC_Order::class);
        $orderMock2->shouldReceive('get_total_refunded')->andReturn(45.0);
        $result = $method->invoke($this->orderStatus, $orderMock2, $paymentsData);
        $this->assertTrue($result, 'Should return true when MP refunded (45.0) = WC refunded (45.0)');

        $orderMock3 = Mockery::mock(WC_Order::class);
        $orderMock3->shouldReceive('get_total_refunded')->andReturn(40.0);
        $result = $method->invoke($this->orderStatus, $orderMock3, $paymentsData);
        $this->assertFalse($result, 'Should return false when MP refunded (45.0) > WC refunded (40.0)');

        $orderMock4 = Mockery::mock(WC_Order::class);
        $orderMock4->shouldReceive('get_total_refunded')->andReturn(0.0);
        $paymentsDataNoRefunds = [
            ['transaction_amount_refunded' => 0],
            ['transaction_amount_refunded' => 0]
        ];
        $result = $method->invoke($this->orderStatus, $orderMock4, $paymentsDataNoRefunds);
        $this->assertTrue($result, 'Should return true when no refunds exist');

        $orderMock5 = Mockery::mock(WC_Order::class);
        $orderMock5->shouldReceive('get_total_refunded')->andReturn(10.0);
        $paymentsDataHighRefund = [
            ['transaction_amount_refunded' => 35.0],
            ['transaction_amount_refunded' => 25.0]
        ];
        $result = $method->invoke($this->orderStatus, $orderMock5, $paymentsDataHighRefund);
        $this->assertFalse($result, 'Should return false when MP refunded (60.0) > WC refunded (10.0)');
    }

    public function testGetUnprocessedRefundAmountCalculations(): void
    {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('getUnprocessedRefundAmount');
        $method->setAccessible(true);

        $orderMock1 = Mockery::mock(WC_Order::class);
        $orderMock1->shouldReceive('get_total_refunded')->andReturn(25.0);
        $paymentsData = [
            ['transaction_amount_refunded' => 30.0],
            ['transaction_amount_refunded' => 20.0]
        ];
        $result = $method->invoke($this->orderStatus, $orderMock1, $paymentsData);
        $this->assertEquals(25.0, $result);

        $orderMock2 = Mockery::mock(WC_Order::class);
        $orderMock2->shouldReceive('get_total_refunded')->andReturn(60.0);
        $result = $method->invoke($this->orderStatus, $orderMock2, $paymentsData);
        $this->assertEquals(0.0, $result);

        $orderMock3 = Mockery::mock(WC_Order::class);
        $orderMock3->shouldReceive('get_total_refunded')->andReturn(50.0);
        $result = $method->invoke($this->orderStatus, $orderMock3, $paymentsData);
        $this->assertEquals(0.0, $result);

        $orderMock4 = Mockery::mock(WC_Order::class);
        $orderMock4->shouldReceive('get_total_refunded')->andReturn(10.25);
        $paymentsDataDecimals = [
            ['transaction_amount_refunded' => 15.75],
            ['transaction_amount_refunded' => 8.50]
        ];
        $result = $method->invoke($this->orderStatus, $orderMock4, $paymentsDataDecimals);
        $this->assertEquals(14.0, $result);
    }

    public function testGetAllPaymentsDataErrorHandling(): void
    {
        $orderMock1 = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock1)
            ->andReturn('');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_test');

        $result = $this->orderStatus->getAllPaymentsData($orderMock1);
        $this->assertEquals([], $result);

        $this->expectException(\Exception::class);

        $orderMock2 = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock2)
            ->andReturn('INVALID_PAYMENT');

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(404);
        $responseMock->shouldReceive('getData')->andReturn(['error' => 'Payment not found']);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/INVALID_PAYMENT', ['Authorization: Bearer access_token_test'])
            ->andReturn($responseMock);

        $this->orderStatus->getAllPaymentsData($orderMock2);
    }

    public function testGetLastNotificationMerchantOrderHandling(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('PAY_123,PAY_456');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_test');

        $paymentResponse = Mockery::mock(Response::class);
        $paymentResponse->shouldReceive('getStatus')->andReturn(200);
        $paymentResponse->shouldReceive('getData')->andReturn([
            'notification_url' => 'https://api.mercadopago.com/merchant-order-notification/12345',
            'order' => ['id' => 'ORDER_789']
        ]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/PAY_456', ['Authorization: Bearer access_token_test'])
            ->andReturn($paymentResponse);

        $notificationData = [
            'id' => 'M-ORDER_789',
            'type' => 'merchant_order',
            'status' => 'approved'
        ];
        $notificationResponse = Mockery::mock(Response::class);
        $notificationResponse->shouldReceive('getStatus')->andReturn(200);
        $notificationResponse->shouldReceive('getData')->andReturn($notificationData);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/asgard/notification/M-ORDER_789', ['Authorization: Bearer access_token_test'])
            ->andReturn($notificationResponse);

        $result = $this->orderStatus->getLastNotification($orderMock);
        $this->assertEquals([$notificationData], $result);
        $this->assertCount(1, $result);
    }

    public function testGetLastNotificationErrorHandling(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('PAY_INVALID');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_test');

        $paymentResponse = Mockery::mock(Response::class);
        $paymentResponse->shouldReceive('getStatus')->andReturn(200);
        $paymentResponse->shouldReceive('getData')->andReturn([
            'notification_url' => '',
            'order' => ['id' => 'ORDER_123']
        ]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/PAY_INVALID', ['Authorization: Bearer access_token_test'])
            ->andReturn($paymentResponse);

        $fileMock = $this->logsMock->file;
        $fileMock->shouldReceive('error')
            ->with('Mercado Pago: Error getting last notification: Notification URL not found for payment ID: PAY_INVALID', 'MercadoPago\Woocommerce\Order\OrderStatus')
            ->once();

        $result = $this->orderStatus->getLastNotification($orderMock);
        $this->assertEquals([], $result);
    }

    public function testGetLastNotificationApiError(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('PAY_API_ERROR');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_test');

        $paymentResponse = Mockery::mock(Response::class);
        $paymentResponse->shouldReceive('getStatus')->andReturn(404);
        $paymentResponse->shouldReceive('getData')->andReturn(['error' => 'Payment not found']);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/PAY_API_ERROR', ['Authorization: Bearer access_token_test'])
            ->andReturn($paymentResponse);

        $fileMock = $this->logsMock->file;
        $fileMock->shouldReceive('error')
            ->with('Mercado Pago: Error getting last notification: {"error":"Payment not found"}', 'MercadoPago\Woocommerce\Order\OrderStatus')
            ->once();

        $result = $this->orderStatus->getLastNotification($orderMock);
        $this->assertEquals([], $result);
    }
}
