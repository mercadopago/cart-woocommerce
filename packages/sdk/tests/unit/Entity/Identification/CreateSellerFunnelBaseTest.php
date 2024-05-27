<?php

namespace Entity\Identification;

use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Identification\CreateSellerFunnelBase;
use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Tests\Unit\Entity\Monitoring\MockObject;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\CreateSellerFunnelBaseMock;
use PHPUnit\Framework\TestCase;

/**
 * Class SellerFunnelBaseTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\Identifiation
 */
class CreateSellerFunnelBaseTest extends TestCase
{
    /**
     * @var CreateSellerFunnelBase
     */
    private $createSellerFunnelBase;

    /**
     * @var array
     */
    private $createSellerFunnelBaseMock;

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
        $this->createSellerFunnelBaseMock = CreateSellerFunnelBaseMock::COMPLETE_CREATE_SELLER_BASE;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->createSellerFunnelBase = new CreateSellerFunnelBase($this->managerMock);
        $this->createSellerFunnelBase->setEntity($this->createSellerFunnelBaseMock);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->createSellerFunnelBase->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('save', $actual);
        $this->assertTrue(is_array($actual['save']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->createSellerFunnelBase->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testSaveSuccess()
    {
        $response = new Response();
        $response->setData(json_decode('{"id": "123"}'));
        $response->setHeaders(['x-cpp-token' => '1235']);
        $response->setStatus(201);

        $this->responseMock->expects(self::any())->method('getStatus')->willReturn($response->getStatus());
        $this->responseMock->expects(self::any())->method('getData')->willReturn($response->getData());
        $this->responseMock->expects(self::any())->method('getHeaders')->willReturn($response->getHeaders());

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/v1/eplatforms/core/identification/seller-configuration/start-integration');
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponseWithHeaders')->willReturn($response);

        $actual = $this->createSellerFunnelBase->save();

        $this->assertNotNull($actual);
    }
}
