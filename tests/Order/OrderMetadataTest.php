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
            ->andReturn('','123456789: 2024-03-20T10:00:00.000-04:00');
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
