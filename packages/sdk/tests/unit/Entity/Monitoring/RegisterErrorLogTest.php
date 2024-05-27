<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Entity\Monitoring;

use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Monitoring\RegisterErrorLog;
use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\RegisterErrorLogMock;
use PHPUnit\Framework\TestCase;

/**
 * Class RegisterErrorLogTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\Monitoring
 */
class RegisterErrorLogTest extends TestCase
{
    /**
     * @var RegisterErrorLog
     */
    private $registerErrorLog;

    /**
     * @var array
     */
    private $registerErrorLogMock;

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
        $this->registerErrorLogMock = RegisterErrorLogMock::COMPLETE_REGISTER_ERROR_LOG;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->registerErrorLog = new RegisterErrorLog($this->managerMock);
        $this->registerErrorLog->setEntity($this->registerErrorLogMock);
    }

    function testGetAndSetSuccess()
    {
        $this->registerErrorLog->__set('uri', 'XXX');

        $actual = $this->registerErrorLog->__get('uri');
        $expected = 'XXX';

        $this->assertEquals($expected, $actual);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->registerErrorLog->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('save', $actual);
        $this->assertTrue(is_array($actual['save']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->registerErrorLog->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testSaveSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(201);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->registerErrorLogMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/ppcore/prod/monitor/v1/event/error');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn(['x-flow:not identified']);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn(true);

        $actual = $this->registerErrorLog->save();

        $this->assertTrue($actual);
    }

    function testJsonSerializeSuccess()
    {
        $actual = $this->registerErrorLog->jsonSerialize();
        $expected = '/checkout';

        $this->assertTrue(is_array($actual));
        $this->assertEquals($expected, $actual['uri']);
    }
}
