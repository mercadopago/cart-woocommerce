<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Entity\PaymentMethods;

use JsonSerializable;
use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\PaymentMethods\PaymentMethods;
use MercadoPago\PP\Sdk\Entity\PaymentMethods\PaymentMethodsList;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\PaymentMethodsMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class PaymentMethodsTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\PaymentMethods
 */
class PaymentMethodsTest extends TestCase
{
    /**
     * @var PaymentMethods
     */
    private $paymentMethods;

    /**
     * @var PaymentMethodsList
     */
    private $paymentMethodsMock;


    /**
     * @var array
     */
    private $groupedPaymentMethodsMock;


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
        $this->paymentMethodsMock = PaymentMethodsMock::PAYMENT_METHODS;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->paymentMethods = new PaymentMethods($this->managerMock);
        $this->paymentMethodsMock = PaymentMethodsMock::PAYMENT_METHODS; 
        $this->groupedPaymentMethodsMock = PaymentMethodsMock::GROUPED_PAYMENT_METHODS_BY_ID;
    }

    function testGetAndSetSuccess()
    {
        $this->paymentMethods->__set('payment_methods', $this->paymentMethodsMock);

        $actual = $this->paymentMethods->__get('payment_methods')->jsonSerialize()[0]->id;
        $expected = 'visa';

        $this->assertEquals($expected, $actual);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->paymentMethods->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('read', $actual);
        $this->assertArrayHasKey('save', $actual);
        $this->assertTrue(is_array($actual['read']));
        $this->assertTrue(is_array($actual['save']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->paymentMethods->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testGetPaymentMethodsSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->paymentMethodsMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/ppcore/prod/payment-methods/v1/payment-methods');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn($this->paymentMethodsMock);

        $actual = $this->paymentMethods->getPaymentMethods();
        $dataExpected = $this->responseMock->getData();

        $this->assertEquals(count($dataExpected), count($actual));
        $this->assertEquals(json_decode(json_encode($dataExpected)), json_decode(json_encode($actual)));
        $this->assertEquals($dataExpected[0]["id"] == "visa", $actual->jsonSerialize()[0]->id == "visa");
    }

    function testGetPaymentMethodsByGroupBySuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->groupedPaymentMethodsMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/ppcore/prod/payment-methods/v1/payment-methods');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn($this->groupedPaymentMethodsMock);

        $actual = $this->paymentMethods->getPaymentMethodsByGroupBy('id');
        $dataExpected = $this->responseMock->getData();

        $this->assertEquals(json_decode(json_encode($dataExpected)), json_decode(json_encode($actual)));
        $this->assertEquals($dataExpected["visa"], $actual['visa']);
    }

    function testSetCustomHeaderSuccess()
    {
        $this->paymentMethods->setCustomHeaders(['Authorization: ' . 'xxx']);
        $headers = $this->paymentMethods->getHeaders()['read'];
        $expectedHeader = 'Authorization: xxx';

        $this->assertContains($expectedHeader, $headers);
    }

    function testJsonSerializeSuccess()
    {
        $this->paymentMethods->__set('payment_methods', $this->paymentMethodsMock);
        $actual = $this->paymentMethods->jsonSerialize();
        $expected = 'visa';

        $this->assertTrue(is_array($actual));
        $this->assertEquals($expected, $actual['payment_methods'][0]['id']);
    }
}
