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
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

    /**
     * @dataProvider currencyRatioFallbackProvider
     */
    public function testRefundedFlowPartialRefundWithInvalidCurrencyRatioFallsBackToOne($currencyRatioValue): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total')->andReturn(100.0);
        $orderMock->shouldReceive('get_id')->andReturn(123);
        $orderMock->shouldReceive('get_total_refunded')->andReturn(0.0);
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn($currencyRatioValue);
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

    public static function currencyRatioFallbackProvider(): array
    {
        return [
            'null value' => [null],
            'empty string' => [''],
            'zero integer' => [0],
            'zero float' => [0.0],
            'zero string' => ['0'],
            'false value' => [false],
        ];
    }

    public function testRefundedFlowMultipleRefundsFindsCorrectOne(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total')->andReturn(100.0);
        $orderMock->shouldReceive('get_id')->andReturn(123);
        $orderMock->shouldReceive('get_total_refunded')->andReturn(0.0);
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);

        $paymentsData = [
            ['transaction_amount_refunded' => 25.50],
            ['transaction_amount_refunded' => 10.25],
            ['transaction_amount_refunded' => 5.00]
        ];
        $result = $method->invoke($this->orderStatus, $paymentsData, $orderMock);
        $this->assertEquals(40.75, $result);

        $paymentsDataNoRefunds = [
            ['transaction_amount_refunded' => 0],
            ['transaction_amount_refunded' => 0]
        ];
        $result = $method->invoke($this->orderStatus, $paymentsDataNoRefunds, $orderMock);
        $this->assertEquals(0.0, $result);

        $result = $method->invoke($this->orderStatus, [], $orderMock);
        $this->assertEquals(0.0, $result);

        $paymentsDataMissingField = [
            ['transaction_amount_refunded' => 15.00],
            ['id' => 'payment_2']
        ];
        $result = $method->invoke($this->orderStatus, $paymentsDataMissingField, $orderMock);
        $this->assertEquals(15.00, $result);
    }

    public function testRefundAlreadyProcessedLogic(): void
    {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('refundAlreadyProcessed');
        $method->setAccessible(true);

        $orderMock1 = Mockery::mock(WC_Order::class);
        $orderMock1->shouldReceive('get_total_refunded')->andReturn(50.0);
        $orderMock1->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
        $paymentsData = [
            ['transaction_amount_refunded' => 25.0],
            ['transaction_amount_refunded' => 20.0]
        ];
        $result = $method->invoke($this->orderStatus, $orderMock1, $paymentsData);
        $this->assertTrue($result, 'Should return true when MP refunded (45.0) <= WC refunded (50.0)');

        $orderMock2 = Mockery::mock(WC_Order::class);
        $orderMock2->shouldReceive('get_total_refunded')->andReturn(45.0);
        $orderMock2->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
        $result = $method->invoke($this->orderStatus, $orderMock2, $paymentsData);
        $this->assertTrue($result, 'Should return true when MP refunded (45.0) = WC refunded (45.0)');

        $orderMock3 = Mockery::mock(WC_Order::class);
        $orderMock3->shouldReceive('get_total_refunded')->andReturn(40.0);
        $orderMock3->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
        $result = $method->invoke($this->orderStatus, $orderMock3, $paymentsData);
        $this->assertFalse($result, 'Should return false when MP refunded (45.0) > WC refunded (40.0)');

        $orderMock4 = Mockery::mock(WC_Order::class);
        $orderMock4->shouldReceive('get_total_refunded')->andReturn(0.0);
        $orderMock4->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
        $paymentsDataNoRefunds = [
            ['transaction_amount_refunded' => 0],
            ['transaction_amount_refunded' => 0]
        ];
        $result = $method->invoke($this->orderStatus, $orderMock4, $paymentsDataNoRefunds);
        $this->assertTrue($result, 'Should return true when no refunds exist');

        $orderMock5 = Mockery::mock(WC_Order::class);
        $orderMock5->shouldReceive('get_total_refunded')->andReturn(10.0);
        $orderMock5->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
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
        $orderMock1->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
        $paymentsData = [
            ['transaction_amount_refunded' => 30.0],
            ['transaction_amount_refunded' => 20.0]
        ];
        $result = $method->invoke($this->orderStatus, $orderMock1, $paymentsData);
        $this->assertEquals(25.0, $result);

        $orderMock2 = Mockery::mock(WC_Order::class);
        $orderMock2->shouldReceive('get_total_refunded')->andReturn(60.0);
        $orderMock2->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
        $result = $method->invoke($this->orderStatus, $orderMock2, $paymentsData);
        $this->assertEquals(0.0, $result);

        $orderMock3 = Mockery::mock(WC_Order::class);
        $orderMock3->shouldReceive('get_total_refunded')->andReturn(50.0);
        $orderMock3->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
        $result = $method->invoke($this->orderStatus, $orderMock3, $paymentsData);
        $this->assertEquals(0.0, $result);

        $orderMock4 = Mockery::mock(WC_Order::class);
        $orderMock4->shouldReceive('get_total_refunded')->andReturn(10.25);
        $orderMock4->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(1.0);
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock1)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
            ->andReturn('access_token_test');

        $result = $this->orderStatus->getAllPaymentsData($orderMock1);
        $this->assertEquals([], $result);

        $this->expectException(\Exception::class);

        $orderMock2 = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock2)
            ->andReturn('INVALID_PAYMENT');

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock2)
            ->andReturn('no');

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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
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

    /**
     * Test processStatus executes without errors for valid statuses
     */
    public function testProcessStatusExecutesForValidStatuses(): void
    {
        $orderMock = Mockery::mock(WC_Order::class);
        $data = ['id' => '123'];
        $usedGateway = 'test_gateway';

        $orderMock->shouldReceive('get_status')->andReturn('pending');
        $orderMock->shouldReceive('add_order_note');
        $orderMock->shouldReceive('update_status');
        $orderMock->shouldReceive('get_id')->andReturn(123);
        $orderMock->shouldReceive('needs_processing')->andReturn(true);
        $orderMock->shouldReceive('payment_complete');

        $this->orderMetadataMock->shouldReceive('updateOrderCustomFieldsAfterSync');
        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')->andReturn('');
        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')->andReturn('no');
        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')->andReturn('test_token');
        $this->requesterMock->shouldReceive('get')->andReturn(
            Mockery::mock('MercadoPago\Woocommerce\IO\ApiResponse')
                ->shouldReceive('getStatus')->andReturn(200)
                ->shouldReceive('getData')->andReturn([])
                ->getMock()
        );

        \WP_Mock::onFilter('woocommerce_payment_complete_order_status')
            ->with('processing', 123, $orderMock)
            ->reply('processing');

        $validStatuses = [
            'approved',
            'pending',
            'in_process',
            'rejected',
            'refunded',
            'cancelled',
            'in_mediation',
            'charged_back'
        ];

        foreach ($validStatuses as $status) {
            $this->orderStatus->processStatus($status, $data, $orderMock, $usedGateway);
            $this->assertTrue(true);
        }
    }

    /**
     * Data provider for getAccessTokenForOrder
     */
    public function accessTokenForOrderProvider(): array
    {
        return [
            'production_mode_yes' => ['yes', 'access_token_prod', 'prod'],
            'production_mode_no' => ['no', 'access_token_test', 'test'],
            'production_mode_null_fallback' => [null, 'access_token_default', 'fallback'],
        ];
    }

    /**
     * Test getAccessTokenForOrder using is_production_mode
     *
     * @dataProvider accessTokenForOrderProvider
     */
    public function testGetAccessTokenForOrder(?string $isProductionMode, string $expectedToken, string $case): void
    {
        $orderMock = Mockery::mock(WC_Order::class);

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn($isProductionMode);

        if ($case === 'prod') {
            $this->sellerMock->shouldReceive('getCredentialsAccessTokenProd')
                ->andReturn($expectedToken);
        } elseif ($case === 'test') {
            $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
                ->andReturn($expectedToken);
        } else {
            $this->sellerMock->shouldReceive('getCredentialsAccessToken')
                ->andReturn($expectedToken);
        }

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('getAccessTokenForOrder');
        $method->setAccessible(true);

        $result = $method->invoke($this->orderStatus, $orderMock);

        $this->assertEquals($expectedToken, $result);
    }

    /**
     * Data provider for calculateTotalRefunded with different currency ratios
     */
    public function calculateTotalRefundedCurrencyRatioProvider(): array
    {
        return [
            'ratio_1.0_no_conversion' => [1.0, 150.0, 'Should return 150.0 with ratio 1.0'],
            'ratio_2.0_divide_by_2' => [2.0, 75.0, 'Should return 75.0 with ratio 2.0'],
            'ratio_0.5_multiply_by_2' => [0.5, 300.0, 'Should return 300.0 with ratio 0.5'],
            'ratio_3.5_divide_by_3.5' => [3.5, 42.857142857142854, 'Should return 42.857... with ratio 3.5'],
            'ratio_null_defaults_to_1.0' => [null, 150.0, 'Should return 150.0 with null ratio (defaults to 1.0)'],
        ];
    }

    /**
     * Test calculateTotalRefunded with different currency ratios
     *
     * @dataProvider calculateTotalRefundedCurrencyRatioProvider
     */
    public function testCalculateTotalRefundedWithDifferentCurrencyRatios($currencyRatio, float $expectedResult, string $message): void
    {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('calculateTotalRefunded');
        $method->setAccessible(true);

        $paymentsData = [
            ['transaction_amount_refunded' => 100.0],
            ['transaction_amount_refunded' => 50.0]
        ];

        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn($currencyRatio);

        $result = $method->invoke($this->orderStatus, $paymentsData, $orderMock);
        $this->assertEquals($expectedResult, $result, $message);
    }

    /**
     * Test calculateTotalRefunded with valid currency ratios
     */
    public function testCalculateTotalRefundedWithValidRatio(): void
    {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('calculateTotalRefunded');
        $method->setAccessible(true);

        $paymentsData = [
            ['transaction_amount_refunded' => 100.0],
            ['transaction_amount_refunded' => 50.0]
        ];

        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn(5.25);

        $result = $method->invoke($this->orderStatus, $paymentsData, $orderMock);

        $this->assertEquals(28.57, round($result, 2), 'Valid ratio 5.25 should return approximately 150.0/5.25 ≈ 28.57');
    }

    /**
     * Test calculateTotalRefunded with invalid currency ratios that should default to 1.0
     */
    public function testCalculateTotalRefundedWithInvalidRatio(): void
    {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('calculateTotalRefunded');
        $method->setAccessible(true);

        $paymentsData = [
            ['transaction_amount_refunded' => 100.0],
            ['transaction_amount_refunded' => 50.0]
        ];

        $invalidCases = [
            ['value' => '', 'description' => 'Empty string should default to ratio 1.0'],
            ['value' => 0, 'description' => 'Zero value should default to ratio 1.0'],
            ['value' => '0', 'description' => 'Zero string should default to ratio 1.0'],
            ['value' => 'abc', 'description' => 'Non-numeric string should default to ratio 1.0'],
            ['value' => [], 'description' => 'Array should default to ratio 1.0'],
            ['value' => (object)['ratio' => 5.0], 'description' => 'Object should default to ratio 1.0'],
            ['value' => null, 'description' => 'Null should default to ratio 1.0'],
            ['value' => false, 'description' => 'False should default to ratio 1.0']
        ];

        foreach ($invalidCases as $case) {
            $orderMock = Mockery::mock(WC_Order::class);
            $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn($case['value']);
            $result = $method->invoke($this->orderStatus, $paymentsData, $orderMock);
            $this->assertEquals(150.0, $result, $case['description']);
        }
    }

    /**
     * Data provider for refundedFlow with different currency ratios
     */
    public function refundedFlowCurrencyRatioProvider(): array
    {
        return [
            'ratio_2.0_mp_amount_50' => [
                'currency_ratio' => 2.0,
                'mp_refund_amount' => 50.0,
                'expected_wc_amount' => 25.0,
                'description' => 'Should convert 50.0 MP to 25.0 WC with ratio 2.0'
            ],
            'ratio_0.5_mp_amount_25' => [
                'currency_ratio' => 0.5,
                'mp_refund_amount' => 25.0,
                'expected_wc_amount' => 50.0,
                'description' => 'Should convert 25.0 MP to 50.0 WC with ratio 0.5'
            ],
            'ratio_1.0_mp_amount_100' => [
                'currency_ratio' => 1.0,
                'mp_refund_amount' => 100.0,
                'expected_wc_amount' => 100.0,
                'description' => 'Should convert 100.0 MP to 100.0 WC with ratio 1.0'
            ],
            'ratio_3.0_mp_amount_90' => [
                'currency_ratio' => 3.0,
                'mp_refund_amount' => 90.0,
                'expected_wc_amount' => 30.0,
                'description' => 'Should convert 90.0 MP to 30.0 WC with ratio 3.0'
            ],
        ];
    }

    /**
     * Test refundedFlow with different currency ratios for specific refund amounts
     *
     * @dataProvider refundedFlowCurrencyRatioProvider
     */
    public function testRefundedFlowWithDifferentCurrencyRatios(
        float $currencyRatio,
        float $mpRefundAmount,
        float $expectedWcAmount,
        string $description
    ): void {
        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total')->andReturn(100.0);
        $orderMock->shouldReceive('get_id')->andReturn(123);
        $orderMock->shouldReceive('get_total_refunded')->andReturn(0.0);
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn($currencyRatio);
        $orderMock->shouldReceive('add_order_note');

        $paymentsData = [
            [
                'id' => 'payment_123',
                'status' => 'approved',
                'status_detail' => 'accredited',
                'transaction_amount' => 100.0,
                'transaction_amount_refunded' => 25.0
            ]
        ];

        $this->orderMetadataMock->shouldReceive('getPaymentsIdMeta')
            ->with($orderMock)
            ->andReturn('payment_123');

        $this->orderMetadataMock->shouldReceive('getIsProductionModeData')
            ->with($orderMock)
            ->andReturn('no');

        $this->sellerMock->shouldReceive('getCredentialsAccessTokenTest')
            ->andReturn('access_token_123');

        $responseMock = Mockery::mock(Response::class);
        $responseMock->shouldReceive('getStatus')->andReturn(200);
        $responseMock->shouldReceive('getData')->andReturn($paymentsData[0]);

        $this->requesterMock->shouldReceive('get')
            ->with('/v1/payments/payment_123', ['Authorization: Bearer access_token_123'])
            ->andReturn($responseMock);

        $data = [
            'notification_id' => 'notification_123',
            'current_refund' => ['id' => 'refund_123'],
            'refunds_notifying' => [
                ['id' => 'refund_123', 'amount' => $mpRefundAmount] // Amount in MP currency
            ]
        ];

        WP_Mock::userFunction('wc_create_refund', [
            'times' => 1,
            'args' => [
                ['amount' => $expectedWcAmount, 'reason' => 'Refunded', 'order_id' => 123]
            ],
            'return' => true
        ]);

        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('refundedFlow');
        $method->setAccessible(true);

        $method->invoke($this->orderStatus, $data, $orderMock);
        $this->assertTrue(true, $description);
    }

    /**
     * Data provider for getUnprocessedRefundAmount with different currency ratios
     */
    public function getUnprocessedRefundAmountCurrencyRatioProvider(): array
    {
        return [
            'ratio_2.0_wc_refunded_25' => [
                'currency_ratio' => 2.0,
                'wc_refunded' => 25.0,
                'expected_unprocessed' => 50.0,
                'description' => 'MP total: 150.0, converted: 75.0, unprocessed: 50.0 with ratio 2.0'
            ],
            'ratio_0.5_wc_refunded_100' => [
                'currency_ratio' => 0.5,
                'wc_refunded' => 100.0,
                'expected_unprocessed' => 200.0,
                'description' => 'MP total: 150.0, converted: 300.0, unprocessed: 200.0 with ratio 0.5'
            ],
            'ratio_1.0_wc_refunded_50' => [
                'currency_ratio' => 1.0,
                'wc_refunded' => 50.0,
                'expected_unprocessed' => 100.0,
                'description' => 'MP total: 150.0, converted: 150.0, unprocessed: 100.0 with ratio 1.0'
            ],
            'ratio_3.0_wc_refunded_10' => [
                'currency_ratio' => 3.0,
                'wc_refunded' => 10.0,
                'expected_unprocessed' => 40.0,
                'description' => 'MP total: 150.0, converted: 50.0, unprocessed: 40.0 with ratio 3.0'
            ],
        ];
    }

    /**
     * Test getUnprocessedRefundAmount with different currency ratios
     *
     * @dataProvider getUnprocessedRefundAmountCurrencyRatioProvider
     */
    public function testGetUnprocessedRefundAmountWithDifferentRatios(
        float $currencyRatio,
        float $wcRefunded,
        float $expectedUnprocessed,
        string $description
    ): void {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('getUnprocessedRefundAmount');
        $method->setAccessible(true);

        $paymentsData = [
            ['transaction_amount_refunded' => 100.0],
            ['transaction_amount_refunded' => 50.0]
        ];

        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total_refunded')->andReturn($wcRefunded);
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn($currencyRatio);

        $result = $method->invoke($this->orderStatus, $orderMock, $paymentsData);
        $this->assertEquals($expectedUnprocessed, $result, $description);
    }

    /**
     * Data provider for refundAlreadyProcessed with different currency ratios
     */
    public function refundAlreadyProcessedCurrencyRatioProvider(): array
    {
        return [
            'ratio_2.0_wc_greater_than_mp' => [
                'currency_ratio' => 2.0,
                'wc_refunded' => 80.0,
                'expected_result' => true,
                'description' => 'Should return true when WC refunded (80.0) > MP converted (75.0)'
            ],
            'ratio_0.5_wc_less_than_mp' => [
                'currency_ratio' => 0.5,
                'wc_refunded' => 250.0,
                'expected_result' => false,
                'description' => 'Should return false when WC refunded (250.0) < MP converted (300.0)'
            ],
            'ratio_1.0_wc_equals_mp' => [
                'currency_ratio' => 1.0,
                'wc_refunded' => 150.0,
                'expected_result' => true,
                'description' => 'Should return true when WC refunded (150.0) = MP converted (150.0)'
            ],
            'ratio_3.0_wc_greater_than_mp' => [
                'currency_ratio' => 3.0,
                'wc_refunded' => 60.0,
                'expected_result' => true,
                'description' => 'Should return true when WC refunded (60.0) > MP converted (50.0)'
            ],
            'ratio_0.25_wc_less_than_mp' => [
                'currency_ratio' => 0.25,
                'wc_refunded' => 500.0,
                'expected_result' => false,
                'description' => 'Should return false when WC refunded (500.0) < MP converted (600.0)'
            ],
        ];
    }

    /**
     * Test refundAlreadyProcessed with different currency ratios
     *
     * @dataProvider refundAlreadyProcessedCurrencyRatioProvider
     */
    public function testRefundAlreadyProcessedWithDifferentRatios(
        float $currencyRatio,
        float $wcRefunded,
        bool $expectedResult,
        string $description
    ): void {
        $reflection = new \ReflectionClass($this->orderStatus);
        $method = $reflection->getMethod('refundAlreadyProcessed');
        $method->setAccessible(true);

        $paymentsData = [
            ['transaction_amount_refunded' => 100.0],
            ['transaction_amount_refunded' => 50.0]
        ];

        $orderMock = Mockery::mock(WC_Order::class);
        $orderMock->shouldReceive('get_total_refunded')->andReturn($wcRefunded);
        $orderMock->shouldReceive('get_meta')->with('_currency_ratio')->andReturn($currencyRatio);

        $result = $method->invoke($this->orderStatus, $orderMock, $paymentsData);
        $this->assertEquals($expectedResult, $result, $description);
    }
}
