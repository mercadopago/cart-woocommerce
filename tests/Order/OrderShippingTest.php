<?php

namespace MercadoPago\Woocommerce\Tests\Gateways;

use PHPUnit\Framework\TestCase;
use MercadoPago\Woocommerce\Tests\Mocks\WoocommerceMock;
use MercadoPago\Woocommerce\Order\OrderShipping;
use Mockery;

class OrderShippingTest extends TestCase
{
    use WoocommerceMock;

    private \WC_Order $orderMock;

    private OrderShipping $orderShipping;

    public function setUp(): void
    {
        $this->orderMock = Mockery::mock('WC_Order');
        $this->orderShipping = new OrderShipping();
    }

    public function testGetFirstName()
    {
        $this->orderMock->shouldReceive('get_shipping_first_name')->andReturn('Jane');
        $this->assertEquals('Jane', $this->orderShipping->getFirstName($this->orderMock));
    }

    public function testGetLastName()
    {
        $this->orderMock->shouldReceive('get_shipping_last_name')->andReturn('Smith');
        $this->assertEquals('Smith', $this->orderShipping->getLastName($this->orderMock));
    }

    public function testGetPhone()
    {
        $this->orderMock->shouldReceive('get_shipping_phone')->andReturn('1234567890');
        $this->assertEquals('1234567890', $this->orderShipping->getPhone($this->orderMock));
    }

    public function testGetZipcode()
    {
        $this->orderMock->shouldReceive('get_shipping_postcode')->andReturn('12345');
        $this->assertEquals('12345', $this->orderShipping->getZipcode($this->orderMock));
    }

    public function testGetAddress1()
    {
        $this->orderMock->shouldReceive('get_shipping_address_1')->andReturn('Street 123');
        $this->assertEquals('Street 123', $this->orderShipping->getAddress1($this->orderMock));
    }

    public function testGetAddress2()
    {
        $this->orderMock->shouldReceive('get_shipping_address_2')->andReturn('Apt 4B');
        $this->assertEquals('Apt 4B', $this->orderShipping->getAddress2($this->orderMock));
    }

    public function testGetCity()
    {
        $this->orderMock->shouldReceive('get_shipping_city')->andReturn('City');
        $this->assertEquals('City', $this->orderShipping->getCity($this->orderMock));
    }

    public function testGetState()
    {
        $this->orderMock->shouldReceive('get_shipping_state')->andReturn('State');
        $this->assertEquals('State', $this->orderShipping->getState($this->orderMock));
    }

    public function testGetCountry()
    {
        $this->orderMock->shouldReceive('get_shipping_country')->andReturn('Country');
        $this->assertEquals('Country', $this->orderShipping->getCountry($this->orderMock));
    }

    public function testGetFullAddress()
    {
        $this->orderMock->shouldReceive('get_shipping_address_1')->andReturn('Street 123');
        $this->orderMock->shouldReceive('get_shipping_address_2')->andReturn('Apt 4B');
        $this->orderMock->shouldReceive('get_shipping_city')->andReturn('City');
        $this->orderMock->shouldReceive('get_shipping_state')->andReturn('State');
        $this->orderMock->shouldReceive('get_shipping_country')->andReturn('Country');

        $expected = "Street 123 / Apt 4B - City - State - Country";
        $this->assertEquals($expected, $this->orderShipping->getFullAddress($this->orderMock));
    }

    public function testGetShippingMethod()
    {
        $this->orderMock->shouldReceive('get_shipping_method')->andReturn('Standard Shipping');
        $this->assertEquals('Standard Shipping', $this->orderShipping->getShippingMethod($this->orderMock));
    }

    public function testGetTotal()
    {
        $this->orderMock->shouldReceive('get_shipping_total')->andReturn(9.99);
        $this->assertEquals(9.99, $this->orderShipping->getTotal($this->orderMock));
    }
}
