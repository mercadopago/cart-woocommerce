<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Entity\Payment;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\PaymentMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class PaymentTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\Notification
 */
class PaymentTest extends TestCase
{
    /**
     * @var Payment
     */
    private $payment;

    /**
     * @var array
     */
    private $paymentMock;

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
        $this->paymentMock = PaymentMock::COMPLETE_PAYMENT;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->payment = new Payment($this->managerMock);
        $this->payment->setEntity($this->paymentMock);
    }

    function testSubclassesTypes()
    {
        $additionalInfo = $this->payment->__get('additional_info');
        $additionalInfoPayer = $additionalInfo->__get('payer');
        $additionalInfoPayerAddress = $additionalInfoPayer->__get('address');
        $phone = $additionalInfoPayer->__get('phone');

        $shipments = $additionalInfo->__get('shipments');
        $receiverAddress = $shipments->__get('receiver_address');

        $items = $additionalInfo->__get('items');
        $item = $items->getIterator()[0];

        $payer = $this->payment->__get('payer');
        $identification = $payer->__get('identification');
        $payerAddress = $payer->__get('address');
        $phoneAddress = $payer->__get('phone');

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfo", $additionalInfo);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfoPayer", $additionalInfoPayer);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfoAddress", $additionalInfoPayerAddress);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Phone", $phone);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Shipments", $shipments);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfoAddress", $receiverAddress);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\ItemList", $items);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Item", $item);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Payer", $payer);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Identification", $identification);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Address", $payerAddress);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Phone", $phoneAddress);
    }

    function testGetAndSetSuccess()
    {
        $this->payment->__set('external_reference', 'XXX');

        $actual = $this->payment->__get('external_reference');
        $expected = 'XXX';

        $this->assertEquals($expected, $actual);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->payment->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('read', $actual);
        $this->assertArrayHasKey('save', $actual);
        $this->assertTrue(is_array($actual['read']));
        $this->assertTrue(is_array($actual['save']));
    }

    function testSetCustomHeaderSuccess()
    {
        $this->payment->setCustomHeaders(['x-customer-id: ' . '102030']);
        $headers = $this->payment->getHeaders()['save'];
        $expectedHeader = 'x-customer-id: 102030';

        $this->assertContains($expectedHeader, $headers);
    }

    function testGetAndSetSessionIdHeaderSuccess()
    {
        $this->payment->__set('session_id', 'armor.hash.123456');

        $headers = $this->payment->getHeaders()['save'];
        $expectedHeader = 'x-meli-session-id: armor.hash.123456';

        $property = $this->payment->__get('session_id');
        $expectedProperty = 'armor.hash.123456';

        $this->assertContains($expectedHeader, $headers);
        $this->assertEquals($expectedProperty, $property);
    }

    function testGetUriSuccess()
    {
        $actual = $this->payment->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testReadSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->paymentMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/v1/asgard/payments');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn($this->paymentMock);

        $actual = $this->payment->read(array("external_reference" => "WC-105"));

        $this->assertEquals(json_encode($this->responseMock->getData()), json_encode($actual));
    }

    function testRead3DSSuccess()
    {
        $this->paymentMock = PaymentMock::COMPLETE_PAYMENT_WITH_3DS;
        $this->payment->setEntity($this->paymentMock);

        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->paymentMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/v1/asgard/payments');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn($this->paymentMock);

        $actual = $this->payment->read(array("external_reference" => "WC-105"));

        $this->assertEquals(json_encode($this->responseMock->getData()), json_encode($actual));
    }

    function testJsonSerializeSuccess()
    {
        $actual = $this->payment->jsonSerialize();
        $expected = 'WC-105';

        $this->assertTrue(is_array($actual));
        $this->assertEquals($expected, $actual['external_reference']);
    }
}
