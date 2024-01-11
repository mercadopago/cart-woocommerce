<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Common;

use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Common\Config;
use MercadoPago\PP\Sdk\Entity\Payment\Payer;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\PP\Sdk\HttpClient\HttpClient;
use MercadoPago\PP\Sdk\HttpClient\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class ManagerTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Common\Manager
 */
class ManagerTest extends TestCase
{
    /**
     * @var Manager
     */
    private $manager;

    /**
     * @var MockObject
     */
    protected $clientMock;

    /**
     * @var MockObject
     */
    protected $configMock;

    /**
     * @var MockObject
     */
    protected $preferenceMock;

    /**
     * @var MockObject
     */
    protected $responseMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->clientMock = $this->getMockBuilder(HttpClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->preferenceMock = $this->getMockBuilder(Preference::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->manager = new Manager($this->clientMock, $this->configMock);
    }

    function testExecuteWithGetMethodSuccess()
    {
        $header = array('x-product-id' => 'product_id', 'x-integrator-id' => 'integrator_id');

        $this->clientMock->expects(self::any())->method('get')->willReturn($this->responseMock);

        $actual = $this->manager->execute($this->preferenceMock, '/preference/123', 'get', $header);

        $this->assertEquals($this->responseMock, $actual);
    }

    function testExecuteWithPostMethodSuccess()
    {
        $header = array('x-product-id' => 'product_id', 'x-integrator-id' => 'integrator_id');

        $this->clientMock->expects(self::any())->method('post')->willReturn($this->responseMock);

        $actual = $this->manager->execute($this->preferenceMock, '/preference', 'post', $header);

        $this->assertEquals($this->responseMock, $actual);
    }

	function testGetEntityUriWithoutParamsSuccess()
    {
        $uris = array('post' => '/preference');

        $this->preferenceMock->expects(self::any())->method('getUris')->willReturn($uris);

        $actual = $this->manager->getEntityUri($this->preferenceMock, 'post');
        $expected = '/preference';

        $this->assertEquals($expected, $actual);
    }

    function testGetEntityUriWithParamsSuccess()
    {
        $uris = array('get' => '/preference/:id');
        $params = array('id' => '123');

        $this->preferenceMock->expects(self::any())->method('getUris')->willReturn($uris);

        $actual = $this->manager->getEntityUri($this->preferenceMock, 'get', $params);
        $expected = '/preference/123';

        $this->assertEquals($expected, $actual);
    }

    function testGetEntityUriWithParamsFailure()
    {
        $uris = array('get' => '/preference/:id');
        $params = array('client_id' => '123');

        $this->preferenceMock->expects(self::any())->method('getUris')->willReturn($uris);

        $actual = $this->manager->getEntityUri($this->preferenceMock, 'get', $params);
        $expected = '/preference/';

        $this->assertEquals($expected, $actual);
    }

    function testGetEntityUriFailure()
    {
        $entity = new Payer(null);

        $this->expectExceptionMessage('Method not available for MercadoPago\PP\Sdk\Entity\Payment\Payer entity');
        $this->manager->getEntityUri($entity, 'post');
    }

    function testGetDefaultHeaderSuccess()
    {
        $this->configMock->expects(self::exactly(4))->method('__get')->willReturn('XXX');

        $actual = $this->manager->getDefaultHeader();
        $this->assertTrue(is_array($actual));
    }

    function testGetHeaderSuccess()
    {
        $this->configMock->expects(self::exactly(4))->method('__get')->willReturn('XXX');

        $actual = $this->manager->getHeader();
        $this->assertTrue(is_array($actual));
    }

    function testHandleResponseGetMethodSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(200);
        $this->responseMock->expects(self::any())->method('getData')->willReturn(new stdClass());

        $actual = $this->manager->handleResponse($this->responseMock, 'get');
        $expected = new stdClass();

        $this->assertEquals($expected, $actual);
    }

    function testHandleResponseFailure()
    {
        $data = array('message' => 'Error');

        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(400);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($data);

        $this->expectExceptionMessage('Error');
        $this->manager->handleResponse($this->responseMock, 'get');
    }

    function testHandleResponseFailureInternalApiError()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(500);
        $this->responseMock->expects(self::any())->method('getData')->willReturn(null);

        $this->expectExceptionMessage('Internal API Error');
        $this->manager->handleResponse($this->responseMock, 'get');
    }
}
