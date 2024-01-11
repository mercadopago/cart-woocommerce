<?php

namespace MercadoPago\PP\Sdk\Tests\Unit;

use MercadoPago\PP\Sdk\Common\Config;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\HttpClient\HttpClientInterface;
use MercadoPago\PP\Sdk\HttpClient\Requester\RequesterInterface;
use MercadoPago\PP\Sdk\Sdk;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class SdkTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Sdk
 */
class SdkTest extends TestCase
{
    /**
     * @var Sdk
     */
    private $sdk;

    /**
     * @var MockObject
     */
    protected $configMock;

    /**
     * @var MockObject
     */
    protected $requesterMock;

    /**
     * @var MockObject
     */
    protected $clientMock;

    /**
     * @var MockObject
     */
    protected $managerMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->requesterMock = $this->getMockBuilder(RequesterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->clientMock = $this->getMockBuilder(HttpClientInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->sdk = new Sdk('access_token', 'platform_id', 'product_id', 'integrator_id');
    }

    function testGetPreferenceSuccess()
    {
        $actual = $this->sdk->getPreferenceInstance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Preference\Preference', $actual);
    }

    function testGetNotificationSuccess()
    {
        $actual = $this->sdk->getNotificationInstance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Notification\Notification', $actual);
    }

    function testGetPaymentSuccess()
    {
        $actual = $this->sdk->getPaymentInstance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Payment\Payment', $actual);
    }

    function testGetPaymentV2Success()
    {
        $actual = $this->sdk->getPaymentV2Instance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Payment\PaymentV2', $actual);
    }

    function testGetPaymentV21Success()
    {
        $actual = $this->sdk->getPaymentV21Instance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Payment\PaymentV21', $actual);
    }

    function testGetMultipaymentSuccess()
    {
        $actual = $this->sdk->getMultipaymentInstance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Payment\Multipayment', $actual);
    }

    function testGetMultipaymentV2Success()
    {
        $actual = $this->sdk->getMultipaymentV2Instance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Payment\MultipaymentV2', $actual);
    }

    function testGetMultipaymentV21Success()
    {
        $actual = $this->sdk->getMultipaymentV21Instance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Payment\MultipaymentV21', $actual);
    }

    function testGetMelidataErrorSuccess()
    {
        $actual = $this->sdk->getMelidataErrorInstance();

        $this->assertInstanceOf('MercadoPago\PP\Sdk\Entity\Monitoring\MelidataError', $actual);
    }
}
