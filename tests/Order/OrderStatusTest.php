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
        $orderMock->shouldReceive('add_order_note')->with('Mercado Pago: Partially Refunded: 25.5');

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
        $orderMock->shouldReceive('add_order_note')->with('Mercado Pago: Partially Refunded: 50.75');

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

    public function testIsPartialRefundPaymentsAllScenarios(): void
    {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('isPartialRefund');
        $method->setAccessible(true);

        $orderMock1 = Mockery::mock(WC_Order::class);
        $orderMock1->shouldReceive('get_total')->andReturn(100.0);
        $data1 = [
            'transaction_amount' => 100.0,
            'transaction_amount_refunded' => 100.0,
            'total_refunded' => 50.0
        ];
        $result1 = $method->invoke($this->orderStatus, $data1, $orderMock1);
        $this->assertFalse($result1);

        $orderMock2 = Mockery::mock(WC_Order::class);
        $orderMock2->shouldReceive('get_total')->andReturn(100.0);
        $data2 = [
            'transaction_amount' => 100.0,
            'total_refunded' => 100.0,
            'transaction_amount_refunded' => 0
        ];
        $result2 = $method->invoke($this->orderStatus, $data2, $orderMock2);
        $this->assertFalse($result2);

        $orderMock3 = Mockery::mock(WC_Order::class);
        $orderMock3->shouldReceive('get_total')->andReturn(100.0);
        $data3 = [
            'transaction_amount' => 100.0,
            'total_refunded' => 50.0,
            'transaction_amount_refunded' => 50.0
        ];
        $result3 = $method->invoke($this->orderStatus, $data3, $orderMock3);
        $this->assertTrue($result3);
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

        $result = $this->orderStatus->getPaymentsData($orderMock);
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

        $this->orderStatus->getPaymentsData($orderMock);
    }

    public function testGetPaymentsDataEmptyPaymentIds(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $result = $this->orderStatus->getPaymentsData($orderMock);
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

        $result = $this->orderStatus->getPaymentsData($orderMock);

        $expected = [
            ['id' => '123', 'status' => 'approved'],
            ['id' => '456', 'status' => 'refunded']
        ];

        $this->assertEquals($expected, $result);
    }

    public function testIsPartialRefundMerchantOrderOneRefund(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $data = ['transaction_type' => 'merchant_order'];

        $paymentsData = [
            ['id' => '123', 'status' => 'approved', 'status_detail' => 'accredited'],
            ['id' => '456', 'status' => 'refunded', 'status_detail' => 'refunded']
        ];

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('123,456');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock1 = Mockery::mock(Response::class);
        $responseMock1->shouldReceive('getStatus')->andReturn(200);
        $responseMock1->shouldReceive('getData')->andReturn($paymentsData[0]);

        $responseMock2 = Mockery::mock(Response::class);
        $responseMock2->shouldReceive('getStatus')->andReturn(200);
        $responseMock2->shouldReceive('getData')->andReturn($paymentsData[1]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock1);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/456', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock2);

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('isPartialRefund');
        $method->setAccessible(true);

        $result = $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertTrue($result);
    }

    public function testIsPartialRefundMerchantOrderMultiplePartialRefunds(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $data = ['transaction_type' => 'merchant_order'];

        $paymentsData = [
            ['id' => '123', 'status' => 'approved', 'status_detail' => 'partially_refunded'],
            ['id' => '456', 'status' => 'refunded', 'status_detail' => 'partially_refunded']
        ];

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('123,456');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock1 = Mockery::mock(Response::class);
        $responseMock1->shouldReceive('getStatus')->andReturn(200);
        $responseMock1->shouldReceive('getData')->andReturn($paymentsData[0]);

        $responseMock2 = Mockery::mock(Response::class);
        $responseMock2->shouldReceive('getStatus')->andReturn(200);
        $responseMock2->shouldReceive('getData')->andReturn($paymentsData[1]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock1);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/456', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock2);

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('isPartialRefund');
        $method->setAccessible(true);

        $result = $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertTrue($result);
    }

    public function testIsPartialRefundMerchantOrderMixedRefundStatuses(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $data = ['transaction_type' => 'merchant_order'];

        $paymentsData = [
            ['id' => '123', 'status' => 'approved', 'status_detail' => 'partially_refunded'],
            ['id' => '456', 'status' => 'refunded', 'status_detail' => 'refunded']
        ];

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('123,456');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock1 = Mockery::mock(Response::class);
        $responseMock1->shouldReceive('getStatus')->andReturn(200);
        $responseMock1->shouldReceive('getData')->andReturn($paymentsData[0]);

        $responseMock2 = Mockery::mock(Response::class);
        $responseMock2->shouldReceive('getStatus')->andReturn(200);
        $responseMock2->shouldReceive('getData')->andReturn($paymentsData[1]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock1);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/456', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock2);

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('isPartialRefund');
        $method->setAccessible(true);

        $result = $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertTrue($result);
    }

    public function testIsPartialRefundMerchantOrderFullRefund(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $data = ['transaction_type' => 'merchant_order'];

        $paymentsData = [
            ['id' => '123', 'status' => 'refunded', 'status_detail' => 'refunded'],
            ['id' => '456', 'status' => 'refunded', 'status_detail' => 'by_admin']
        ];

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('123,456');

        $this->sellerMock->shouldReceive('getCredentialsAccessToken')
            ->andReturn('access_token_123');

        $responseMock1 = Mockery::mock(Response::class);
        $responseMock1->shouldReceive('getStatus')->andReturn(200);
        $responseMock1->shouldReceive('getData')->andReturn($paymentsData[0]);

        $responseMock2 = Mockery::mock(Response::class);
        $responseMock2->shouldReceive('getStatus')->andReturn(200);
        $responseMock2->shouldReceive('getData')->andReturn($paymentsData[1]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock1);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/456', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock2);

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('isPartialRefund');
        $method->setAccessible(true);

        $result = $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertFalse($result);
    }

    public function testIsPartialRefundMerchantOrderApiError(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
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
}
