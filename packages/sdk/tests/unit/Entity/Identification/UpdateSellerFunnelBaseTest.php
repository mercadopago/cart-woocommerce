<?php

namespace Entity\Identification;

use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Identification\UpdateSellerFunnelBase;
use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Tests\Unit\Entity\Monitoring\MockObject;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\SellerFunnelBaseMock;
use PHPUnit\Framework\TestCase;

/**
 * Class UpdateSellerFunnelBaseTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\Identification
 */
class UpdateSellerFunnelBaseTest extends TestCase
{
    /**
     * @var UpdateSellerFunnelBase
     */
    private $updateSellerFunnelBase;

    /**
     * @var array
     */
    private $updateSellerFunnelBaseMock;

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
        $this->updateSellerFunnelBaseMock = SellerFunnelBaseMock::COMPLETE_UPDATE_SELLER_BASE;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->updateSellerFunnelBase = new UpdateSellerFunnelBase($this->managerMock);
        $this->updateSellerFunnelBase->setEntity($this->updateSellerFunnelBaseMock);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->updateSellerFunnelBase->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('update', $actual);
        $this->assertTrue(is_array($actual['update']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->updateSellerFunnelBase->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testSaveSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(201);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->updateSellerFunnelBaseMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/v1/eplatforms/core/identification/seller-configuration/update-integration');
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn(true);

        $actual = $this->updateSellerFunnelBase->update();

        $this->assertTrue($actual);
    }
}
