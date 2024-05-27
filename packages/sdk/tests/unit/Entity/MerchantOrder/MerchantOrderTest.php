<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Entity\MerchantOrder;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\MerchantOrder\MerchantOrder;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\MerchantOrderMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class MerchantOrderTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Unit\Entity\MerchantOrder
 */
class MerchantOrderTest extends TestCase
{
    /**
     * @var MerchantOrder
    */
    private $merchantOrder;

    /**
     * @var MockObject
    */
    protected $managerMock;

    /**
     * @var MockObject
    */
    protected $responseMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->merchantOrder = new MerchantOrder($this->managerMock);
    }

    function testGetAndSetSuccess()
    {
        $this->merchantOrder->__set('elements', MerchantOrderMock::MERCHANT_ORDERS["elements"]);

        $actual = $this->merchantOrder->__get('elements')["0"]["id"];
        $expected = 1234567891;

        $this->assertEquals($expected, $actual);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->merchantOrder->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('read', $actual);
        $this->assertTrue(is_array($actual['read']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->merchantOrder->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testGetMerchantOrdersSuccess()
    {
        $this->merchantOrder->elements = MerchantOrderMock::MERCHANT_ORDERS["elements"];

        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->merchantOrder);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/merchant_orders/');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn($this->merchantOrder);

        $actual = $this->merchantOrder->getMerchantOrders();
        $expectedData = $this->responseMock->getData();

        $this->assertEquals($expectedData->elements[0]["id"], $actual[0]["id"]);
    }

    function testGetMerchantOrderSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn(MerchantOrderMock::MERCHANT_ORDER);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/merchant_orders/123');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn(MerchantOrderMock::MERCHANT_ORDER);

        $actual = $this->merchantOrder->getMerchantOrder('123');
        $dataExpected = $this->responseMock->getData();

        $this->assertEquals($dataExpected["id"], $actual->id);
    }
}
