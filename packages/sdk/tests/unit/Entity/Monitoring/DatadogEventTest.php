<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Entity\Monitoring;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Monitoring\DatadogEvent;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\DatadogEventMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class DatadogEventTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\Monitoring
 */
class DatadogEventTest extends TestCase
{
    /**
     * @var DatadogEvent
     */
    private $datadogEvent;

    /**
     * @var array
     */
    private $datadogEventMock;

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
        $this->datadogEventMock = DatadogEventMock::COMPLETE_DATADOG_EVENT;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->datadogEvent = new DatadogEvent($this->managerMock);
        $this->datadogEvent->setEntity($this->datadogEventMock);
    }

    function testSubclassesTypes()
    {
        $platform = $this->datadogEvent->__get('platform');
        $details = $this->datadogEvent->__get('details');

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Monitoring\Platform", $platform);
        $this->assertIsArray($details);
    }

    function testGetAndSetSuccess()
    {
        $this->datadogEvent->__set('value', 'success');

        $actual = $this->datadogEvent->__get('value');
        $expected = 'success';

        $this->assertEquals($expected, $actual);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->datadogEvent->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('read', $actual);
        $this->assertArrayHasKey('save', $actual);
        $this->assertTrue(is_array($actual['read']));
        $this->assertTrue(is_array($actual['save']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->datadogEvent->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testRegisterSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->datadogEventMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/ppcore/prod/monitor/v1/event/datadog/core/unit_test');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn(true);

        $actual = $this->datadogEvent->register(array("team" => "core", "event_type"=> "unit_test"));

        $this->assertTrue($actual);
    }

    function testJsonSerializeSuccess()
    {
        $actual = $this->datadogEvent->jsonSerialize();
        $expected = 'success';

        $this->assertTrue(is_array($actual));
        $this->assertEquals($expected, $actual['value']);
    }
}
