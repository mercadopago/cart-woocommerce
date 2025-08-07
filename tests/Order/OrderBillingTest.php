<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Order\OrderBilling;
use Mockery;

class OrderBillingTest extends TestCase
{
    use WoocommerceMock;

    private \WC_Order $orderMock;

    private OrderBilling $orderBilling;

    public function setUp(): void
    {
        $this->woocommerceSetUp();

        $this->orderMock = Mockery::mock('WC_Order');
        $this->orderBilling = new OrderBilling();
    }

    public function testGetFirstName()
    {
        $this->orderMock->shouldReceive('get_billing_first_name')->andReturn('John');
        $this->assertEquals('John', $this->orderBilling->getFirstName($this->orderMock));
    }

    public function testGetLastName()
    {
        $this->orderMock->shouldReceive('get_billing_last_name')->andReturn('Doe');
        $this->assertEquals('Doe', $this->orderBilling->getLastName($this->orderMock));
    }

    public function testGetLastNameFromFirstName()
    {
        $this->orderMock->shouldReceive('get_billing_last_name')->andReturn('');
        $this->orderMock->shouldReceive('get_billing_first_name')->andReturn('John Doe');
        $this->assertEquals('Doe', $this->orderBilling->getLastName($this->orderMock));
    }

    public function testGetPhone()
    {
        $this->orderMock->shouldReceive('get_billing_phone')->andReturn('1234567890');
        $this->assertEquals('1234567890', $this->orderBilling->getPhone($this->orderMock));
    }

    public function testGetEmail()
    {
        $this->orderMock->shouldReceive('get_billing_email')->andReturn('test@test.com');
        $this->assertEquals('test@test.com', $this->orderBilling->getEmail($this->orderMock));
    }

    public function testGetFullAddress()
    {
        $this->orderMock->shouldReceive('get_billing_address_1')->andReturn('Street 123');
        $this->orderMock->shouldReceive('get_billing_address_2')->andReturn('Apt 4B');
        $this->orderMock->shouldReceive('get_billing_city')->andReturn('City');
        $this->orderMock->shouldReceive('get_billing_state')->andReturn('State');
        $this->orderMock->shouldReceive('get_billing_country')->andReturn('Country');

        $expected = "Street 123 / Apt 4B - City - State - Country";
        $this->assertEquals($expected, $this->orderBilling->getFullAddress($this->orderMock));
    }
}
