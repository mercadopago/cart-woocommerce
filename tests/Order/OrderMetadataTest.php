<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Hooks\OrderMeta;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use Mockery;
use WP_Mock;
use WC_Order;

class OrderMetadataTest extends TestCase
{
    private OrderMeta $orderMetaMock;

    private \WC_Order $orderMock;

    private OrderMetadata $orderMetadata;

    public function setUp(): void
    {
        WoocommerceMock::setupClassMocks();
        WP_Mock::setUp();

        $this->orderMetaMock = Mockery::mock(OrderMeta::class);
        $this->orderMock = Mockery::mock('WC_Order');
        $this->orderMetadata = new OrderMetadata($this->orderMetaMock);
    }

    public function tearDown(): void
    {
        Mockery::close();
    }

    public function testGetUsedGatewayData()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, '_used_gateway')->andReturn('test_gateway');
        $result = $this->orderMetadata->getUsedGatewayData($this->orderMock);
        $this->assertEquals('test_gateway', $result);
    }

    public function testGetIsProductionModeData()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'is_production_mode')->andReturn(true);
        $result = $this->orderMetadata->getIsProductionModeData($this->orderMock);
        $this->assertTrue($result);
    }

    public function testGetDiscountData()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'Mercado Pago: discount')->andReturn(10);
        $result = $this->orderMetadata->getDiscountData($this->orderMock);
        $this->assertEquals(10, $result);
    }

    public function testGetCommissionData()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'Mercado Pago: commission')->andReturn(5);
        $result = $this->orderMetadata->getCommissionData($this->orderMock);
        $this->assertEquals(5, $result);
    }

    public function testGetInstallmentsMeta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_installments')->andReturn(12);
        $result = $this->orderMetadata->getInstallmentsMeta($this->orderMock);
        $this->assertEquals(12, $result);
    }

    public function testGetTransactionDetailsMeta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_transaction_details')->andReturn('details');
        $result = $this->orderMetadata->getTransactionDetailsMeta($this->orderMock);
        $this->assertEquals('details', $result);
    }

    public function testGetTransactionAmountMeta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_transaction_amount')->andReturn(100.00);
        $result = $this->orderMetadata->getTransactionAmountMeta($this->orderMock);
        $this->assertEquals(100.00, $result);
    }

    public function testGetTotalPaidAmountMeta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_total_paid_amount')->andReturn(95.00);
        $result = $this->orderMetadata->getTotalPaidAmountMeta($this->orderMock);
        $this->assertEquals(95.00, $result);
    }

    public function testGetPaymentsIdMeta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, '_Mercado_Pago_Payment_IDs', true)->andReturn('123');
        $result = $this->orderMetadata->getPaymentsIdMeta($this->orderMock);
        $this->assertEquals('123', $result);
    }

    public function testGetTicketTransactionDetailsMeta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, '_transaction_details_ticket')->andReturn('ticket_details');
        $result = $this->orderMetadata->getTicketTransactionDetailsMeta($this->orderMock);
        $this->assertEquals('ticket_details', $result);
    }

    public function testGetPixQrBase64Meta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_pix_qr_base64')->andReturn('base64string');
        $result = $this->orderMetadata->getPixQrBase64Meta($this->orderMock);
        $this->assertEquals('base64string', $result);
    }

    public function testGetPixQrCodeMeta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_pix_qr_code')->andReturn('qrcode');
        $result = $this->orderMetadata->getPixQrCodeMeta($this->orderMock);
        $this->assertEquals('qrcode', $result);
    }

    public function testGetPixExpirationDateData()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'checkout_pix_date_expiration')->andReturn('2023-12-31');
        $result = $this->orderMetadata->getPixExpirationDateData($this->orderMock);
        $this->assertEquals('2023-12-31', $result);
    }

    public function testGetPixOnMeta()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'pix_on')->andReturn(true);
        $result = $this->orderMetadata->getPixOnMeta($this->orderMock);
        $this->assertTrue($result);
    }

    public function testGetPaymentBlocks()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'blocks_payment')->andReturn('blocked');
        $result = $this->orderMetadata->getPaymentBlocks($this->orderMock);
        $this->assertEquals('blocked', $result);
    }

    public function testSetUsedGatewayData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, '_used_gateway', 'test_gateway')->once();
        $this->orderMetadata->setUsedGatewayData($this->orderMock, 'test_gateway');
        $this->assertTrue(true);
    }

    public function testSetIsProductionModeData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'is_production_mode', true)->once();
        $this->orderMetadata->setIsProductionModeData($this->orderMock, true);
        $this->assertTrue(true);
    }

    public function testSetDiscountData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'Mercado Pago: discount', 10)->once();
        $this->orderMetadata->setDiscountData($this->orderMock, 10);
        $this->assertTrue(true);
    }

    public function testSetCommissionData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'Mercado Pago: commission', 5)->once();
        $this->orderMetadata->setCommissionData($this->orderMock, 5);
        $this->assertTrue(true);
    }

    public function testSetInstallmentsData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_installments', 12)->once();
        $this->orderMetadata->setInstallmentsData($this->orderMock, 12);
        $this->assertTrue(true);
    }

    public function testSetTransactionDetailsData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_transaction_details', 'details')->once();
        $this->orderMetadata->setTransactionDetailsData($this->orderMock, 'details');
        $this->assertTrue(true);
    }

    public function testSetTransactionAmountData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_transaction_amount', 100.00)->once();
        $this->orderMetadata->setTransactionAmountData($this->orderMock, 100.00);
        $this->assertTrue(true);
    }

    public function testSetTotalPaidAmountData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_total_paid_amount', 95.00)->once();
        $this->orderMetadata->setTotalPaidAmountData($this->orderMock, 95.00);
        $this->assertTrue(true);
    }

    public function testSetPaymentsIdData()
    {
        $this->orderMetaMock->shouldReceive('add')->with($this->orderMock, '_Mercado_Pago_Payment_IDs', '123')->once();
        $this->orderMetadata->setPaymentsIdData($this->orderMock, '123');
        $this->assertTrue(true);
    }

    public function testSetTicketTransactionDetailsData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, '_transaction_details_ticket', 'ticket_details')->once();
        $this->orderMetadata->setTicketTransactionDetailsData($this->orderMock, 'ticket_details');
        $this->assertTrue(true);
    }

    public function testSetPixQrBase64Data()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_pix_qr_base64', 'base64string')->once();
        $this->orderMetadata->setPixQrBase64Data($this->orderMock, 'base64string');
        $this->assertTrue(true);
    }

    public function testSetPixQrCodeData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_pix_qr_code', 'qrcode')->once();
        $this->orderMetadata->setPixQrCodeData($this->orderMock, 'qrcode');
        $this->assertTrue(true);
    }

    public function testSetPixExpirationDateData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'checkout_pix_date_expiration', '2023-12-31')->once();
        $this->orderMetadata->setPixExpirationDateData($this->orderMock, '2023-12-31');
        $this->assertTrue(true);
    }

    public function testSetPixOnData()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'pix_on', true)->once();
        $this->orderMetadata->setPixOnData($this->orderMock, true);
        $this->assertTrue(true);
    }

    public function testMarkPaymentAsBlocks()
    {
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'blocks_payment', 'blocked')->once();
        $this->orderMetadata->markPaymentAsBlocks($this->orderMock, 'blocked');
        $this->assertTrue(true);
    }

    public function testSetCustomMetadata()
    {
        $data = [
            'installments' => 12,
            'transaction_details' => [
                'installment_amount' => 100.00,
                'total_paid_amount' => 1200.00
            ],
            'transaction_amount' => 1200.00,
            'id' => '123',
            'date_created' => '2024-03-20T10:00:00.000-04:00'
        ];

        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_installments', 12.0)->once();
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_transaction_details', 100.0)->once();
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_transaction_amount', 1200.0)->once();
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_total_paid_amount', 1200.0)->once();
        
        // Mock the updatePaymentsOrderMetadata method calls
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, '_Mercado_Pago_Payment_IDs', true)->andReturn(null);
        $this->orderMetaMock->shouldReceive('add')->with($this->orderMock, '_Mercado_Pago_Payment_IDs', '123')->once();
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'PAYMENT_ID: DATE')->andReturn('', '123: 2024-03-20T10:00:00.000-04:00');
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'PAYMENT_ID: DATE', '123: 2024-03-20T10:00:00.000-04:00')->once();
        
        $this->orderMock->shouldReceive('save')->once();
        
        $this->orderMetadata->setCustomMetadata($this->orderMock, $data);
        $this->assertTrue(true);
    }

    public function testSetSupertokenMetadata()
    {
        $data = [
            'installments' => 12,
            'transaction_details' => [
                'installment_amount' => 100.00,
                'total_paid_amount' => 1200.00
            ],
            'transaction_amount' => 1200.00,
            'id' => '123'
        ];

        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_installments', 12.0)->once();
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_transaction_details', 100.0)->once();
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_transaction_amount', 1200.0)->once();
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'mp_total_paid_amount', 1200.0)->once();
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'checkout', 'custom')->once();
        $this->orderMetaMock->shouldReceive('update')->with($this->orderMock, 'checkout_type', 'super_token')->once();
        
        // Mock the updatePaymentsOrderMetadata method calls - setSupertokenMetadata passes ['id' => $data['id']] 
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, '_Mercado_Pago_Payment_IDs', true)->andReturn(null);
        $this->orderMetaMock->shouldReceive('add')->with($this->orderMock, '_Mercado_Pago_Payment_IDs', '123')->once();
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'PAYMENT_ID: DATE')->andReturn('');
        // formatPaymentDetail will return empty string because no date_created exists in ['id' => '123']
        // No update to PAYMENT_ID: DATE since paymentDetailValue will be empty
        
        $this->orderMock->shouldReceive('save')->once();
        
        $this->orderMetadata->setSupertokenMetadata($this->orderMock, $data, (object) ['checkout' => 'custom', 'checkout_type' => 'super_token']);
        $this->assertTrue(true);
    }

    public function testGetSyncCronErrorCountValueReturnsZeroWhenErrorCountIsNull()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_sync_order_error_count')->andReturn(null);
        $result = $this->invokePrivateMethod($this->orderMetadata, 'getSyncCronErrorCountValue', [$this->orderMock]);
        $this->assertEquals(0, $result);
    }

    public function testGetSyncCronErrorCountValueReturnsZeroWhenErrorCountIsEmpty()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_sync_order_error_count')->andReturn('');
        $result = $this->invokePrivateMethod($this->orderMetadata, 'getSyncCronErrorCountValue', [$this->orderMock]);
        $this->assertEquals(0, $result);
    }

    public function testGetSyncCronErrorCountValueReturnsErrorCountWhenNotEmpty()
    {
        $this->orderMetaMock->shouldReceive('get')->with($this->orderMock, 'mp_sync_order_error_count')->andReturn(3);
        $result = $this->invokePrivateMethod($this->orderMetadata, 'getSyncCronErrorCountValue', [$this->orderMock]);
        $this->assertEquals(3, $result);
    }

    public function testUpdatePaymentsOrderMetadata()
    {
        // Arrange
        $paymentData = [
            'id' => '123456789',
            'date_created' => '2024-03-20T10:00:00.000-04:00'
        ];
        $this->orderMock->shouldReceive('get_meta')
            ->with('_Mercado_Pago_Payment_IDs', Mockery::any())
            ->andReturn('');
        $this->orderMock->shouldReceive('get_meta')
            ->with('PAYMENT_ID: DATE', true)
            ->andReturn('', '123456789: 2024-03-20T10:00:00.000-04:00');
        $this->orderMock->shouldReceive('add_meta_data')
            ->with('_Mercado_Pago_Payment_IDs', '123456789', false)
            ->once();
        $this->orderMock->shouldReceive('update_meta_data')
            ->with('PAYMENT_ID: DATE', '123456789: 2024-03-20T10:00:00.000-04:00')
            ->once();
        $this->orderMock->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $orderMeta = new \MercadoPago\Woocommerce\Hooks\OrderMeta();
        $orderMetadata = new \MercadoPago\Woocommerce\Order\OrderMetadata($orderMeta);

        // Act
        $orderMetadata->updatePaymentsOrderMetadata($this->orderMock, $paymentData);

        // Assert
        $this->assertTrue(true);
    }

    public function testUpdatePaymentsOrderMetadataWithExistingPayments()
    {
        // Arrange
        $paymentData = [
            'id' => '987654321',
            'date_created' => '2024-03-21T10:00:00.000-04:00'
        ];
        $this->orderMock->shouldReceive('get_meta')
            ->with('_Mercado_Pago_Payment_IDs', Mockery::any())
            ->andReturn('123456789');
        $this->orderMock->shouldReceive('get_meta')
            ->with('PAYMENT_ID: DATE', Mockery::any())
            ->andReturn('123456789: 2024-03-20T10:00:00.000-04:00', "123456789: 2024-03-20T10:00:00.000-04:00,\n987654321: 2024-03-21T10:00:00.000-04:00");
        $this->orderMock->shouldReceive('update_meta_data')
            ->with('_Mercado_Pago_Payment_IDs', Mockery::any())
            ->once();
        $this->orderMock->shouldReceive('update_meta_data')
            ->with('PAYMENT_ID: DATE', "123456789: 2024-03-20T10:00:00.000-04:00,\n987654321: 2024-03-21T10:00:00.000-04:00")
            ->once();
        $this->orderMock->shouldReceive('save')->twice();

        $orderMeta = new \MercadoPago\Woocommerce\Hooks\OrderMeta();
        $orderMetadata = new \MercadoPago\Woocommerce\Order\OrderMetadata($orderMeta);

        // Act
        $orderMetadata->updatePaymentsOrderMetadata($this->orderMock, $paymentData);

        // Assert
        $this->assertTrue(true);
    }

    public function testUpdatePaymentsOrderMetadataWithInvalidPaymentFormat()
    {
        // Arrange
        $paymentData = [
            'id' => '987654321',
            'date_created' => '2024-03-21T10:00:00.000-04:00'
        ];
        $this->orderMock->shouldReceive('get_meta')
            ->with('_Mercado_Pago_Payment_IDs', Mockery::any())
            ->andReturn('');
        $this->orderMock->shouldReceive('get_meta')
            ->with('PAYMENT_ID: DATE', Mockery::any())
            ->andReturn('invalid_format', 'invalid_format' . ",\n" . '987654321: 2024-03-21T10:00:00.000-04:00');
        $this->orderMock->shouldReceive('add_meta_data')
            ->with('_Mercado_Pago_Payment_IDs', '987654321', false)
            ->once();
        $this->orderMock->shouldReceive('update_meta_data')
            ->with('PAYMENT_ID: DATE', 'invalid_format' . ",\n" . '987654321: 2024-03-21T10:00:00.000-04:00')
            ->once();
        $this->orderMock->shouldReceive('save')
            ->once()
            ->andReturn(true);

        $orderMeta = new \MercadoPago\Woocommerce\Hooks\OrderMeta();
        $orderMetadata = new \MercadoPago\Woocommerce\Order\OrderMetadata($orderMeta);

        // Act
        $orderMetadata->updatePaymentsOrderMetadata($this->orderMock, $paymentData);

        // Assert
        $this->assertTrue(true);
    }
    public function testUpdatePaymentsOrderMetadataWithFeeDetails()
    {
        // Arrange
        $paymentData = [
            'id' => '123456789',
            'date_created' => '2024-03-20T10:00:00.000-04:00',
            'fee_details' => [
                [
                    'type' => 'mercadopago_fee',
                    'amount' => 3.3
                ],
                [
                    'type' => 'mercadopago_commission',
                    'amount' => 1.5
                ]
            ]
        ];

        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, '_Mercado_Pago_Payment_IDs', true)
            ->andReturn('');

        $this->orderMetaMock->shouldReceive('add')
            ->with($this->orderMock, '_Mercado_Pago_Payment_IDs', '123456789')
            ->once();

        // Mock expectations for updatePaymentDetails
        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, 'PAYMENT_ID: DATE')
            ->andReturn('');

        $this->orderMetaMock->shouldReceive('update')
            ->with($this->orderMock, 'PAYMENT_ID: DATE', '123456789: 2024-03-20T10:00:00.000-04:00')
            ->once();

        // Mock expectations for updateLatestPaymentId
        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, 'PAYMENT_ID: DATE')
            ->andReturn('123456789: 2024-03-20T10:00:00.000-04:00');

        // Mock expectations for addFeeDetails
        $this->orderMetaMock->shouldReceive('update')
            ->with($this->orderMock, 'mercadopago_fee', 3.3)
            ->once();
        $this->orderMetaMock->shouldReceive('update')
            ->with($this->orderMock, 'mercadopago_commission', 1.5)
            ->once();

        // Act
        $this->orderMetadata->updatePaymentsOrderMetadata($this->orderMock, $paymentData);

        // Assert
        $this->assertTrue(true); // Mockery will verify all expectations
    }

    public function testUpdatePaymentsOrderMetadataWithoutFeeDetails()
    {
        // Arrange
        $paymentData = [
            'id' => '123456789',
            'date_created' => '2024-03-20T10:00:00.000-04:00'
            // No fee_details key
        ];

        // Mock expectations for initializePaymentMetadata
        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, '_Mercado_Pago_Payment_IDs', true)
            ->andReturn('');

        $this->orderMetaMock->shouldReceive('add')
            ->with($this->orderMock, '_Mercado_Pago_Payment_IDs', '123456789')
            ->once();

        // Mock expectations for updatePaymentDetails
        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, 'PAYMENT_ID: DATE')
            ->andReturn('');

        $this->orderMetaMock->shouldReceive('update')
            ->with($this->orderMock, 'PAYMENT_ID: DATE', '123456789: 2024-03-20T10:00:00.000-04:00')
            ->once();

        // Mock expectations for updateLatestPaymentId
        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, 'PAYMENT_ID: DATE')
            ->andReturn('123456789: 2024-03-20T10:00:00.000-04:00');

        // No expectations for addFeeDetails since fee_details is not present

        // Act
        $this->orderMetadata->updatePaymentsOrderMetadata($this->orderMock, $paymentData);

        // Assert
        $this->assertTrue(true); // Mockery will verify all expectations
    }

    public function testUpdatePaymentsOrderMetadataWithInvalidFeeDetails()
    {
        // Arrange
        $paymentData = [
            'id' => '123456789',
            'date_created' => '2024-03-20T10:00:00.000-04:00',
            'fee_details' => [
                [
                    'testA' => 'mercadopago_fee',
                    'testB' => 3.3
                ]
            ]
        ];

        // Mock expectations for initializePaymentMetadata
        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, '_Mercado_Pago_Payment_IDs', true)
            ->andReturn('');

        $this->orderMetaMock->shouldReceive('add')
            ->with($this->orderMock, '_Mercado_Pago_Payment_IDs', '123456789')
            ->once();

        // Mock expectations for updatePaymentDetails
        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, 'PAYMENT_ID: DATE')
            ->andReturn('');

        $this->orderMetaMock->shouldReceive('update')
            ->with($this->orderMock, 'PAYMENT_ID: DATE', '123456789: 2024-03-20T10:00:00.000-04:00')
            ->once();

        // Mock expectations for updateLatestPaymentId
        $this->orderMetaMock->shouldReceive('get')
            ->with($this->orderMock, 'PAYMENT_ID: DATE')
            ->andReturn('123456789: 2024-03-20T10:00:00.000-04:00');

        // No expectations for addFeeDetails since fee_details is not present

        // Act
        $this->orderMetadata->updatePaymentsOrderMetadata($this->orderMock, $paymentData);

        // Assert
        $this->assertTrue(true); // Mockery will verify all expectations
    }

    /**
     * Helper method to invoke private or protected methods for testing.
     *
     * @param object $object
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     */
    private function invokePrivateMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
