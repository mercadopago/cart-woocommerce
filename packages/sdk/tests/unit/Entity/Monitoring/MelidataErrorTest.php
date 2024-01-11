<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Entity\Monitoring;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Monitoring\MelidataError;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\MelidataErrorMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class MelidataErrorTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\Monitoring
 */
class MelidataErrorTest extends TestCase
{
    /**
     * @var MelidataError
     */
    private $melidataError;

    /**
     * @var array
     */
    private $melidataErrorMock;

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
        $this->melidataErrorMock = MelidataErrorMock::COMPLETE_MELIDATA_ERROR;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->melidataError = new MelidataError($this->managerMock);
        $this->melidataError->setEntity($this->melidataErrorMock);
    }

    function testSubclassesTypes()
    {
        $plugin = $this->melidataError->__get('plugin');
        $platform = $this->melidataError->__get('platform');
        $details = $this->melidataError->__get('details');

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Monitoring\Plugin", $plugin);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Monitoring\Platform", $platform);
        $this->assertIsArray($details);
    }

    function testGetAndSetSuccess()
    {
        $this->melidataError->__set('target', 'XXX');

        $actual = $this->melidataError->__get('target');
        $expected = 'XXX';

        $this->assertEquals($expected, $actual);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->melidataError->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('read', $actual);
        $this->assertArrayHasKey('save', $actual);
        $this->assertTrue(is_array($actual['read']));
        $this->assertTrue(is_array($actual['save']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->melidataError->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testSaveSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(201);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->melidataErrorMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/ppcore/prod/monitor/v1/melidata/errors');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn(true);

        $actual = $this->melidataError->registerError();

        $this->assertTrue($actual);
    }

    function testJsonSerializeSuccess()
    {
        $actual = $this->melidataError->jsonSerialize();
        $expected = 'error target';

        $this->assertTrue(is_array($actual));
        $this->assertEquals($expected, $actual['target']);
    }
}
