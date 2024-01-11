<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Entity\Notification;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Notification\Notification;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\NotificationMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class NotificationTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\Notification
 */
class NotificationTest extends TestCase
{
    /**
     * @var Notification
     */
    private $notification;

    /**
     * @var array
     */
    private $notificationMock;

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
        $this->notificationMock = NotificationMock::COMPLETE_NOTIFICATION;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->notification = new Notification($this->managerMock);
        $this->notification->setEntity($this->notificationMock);
    }

    function testGetAndSetSuccess()
    {
        $this->notification->__set('notification_id', 'XXX');

        $actual = $this->notification->__get('notification_id');
        $expected = 'XXX';

        $this->assertEquals($expected, $actual);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->notification->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('read', $actual);
        $this->assertArrayHasKey('save', $actual);
        $this->assertTrue(is_array($actual['read']));
        $this->assertTrue(is_array($actual['save']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->notification->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testReadSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->notificationMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/v1/asgard/notification/:id');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn($this->notificationMock);

        $actual = $this->notification->read(array("id" => "P-25604645467"));

        $this->assertEquals(json_encode($this->responseMock->getData()), json_encode($actual));
    }

    function testJsonSerializeSuccess()
    {
        $actual = $this->notification->jsonSerialize();
        $expected = 'P-25604645467';

        $this->assertTrue(is_array($actual));
        $this->assertEquals($expected, $actual['notification_id']);
    }
}
