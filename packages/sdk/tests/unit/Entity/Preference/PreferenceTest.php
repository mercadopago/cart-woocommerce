<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Entity\Preference;

use MercadoPago\PP\Sdk\HttpClient\Response;
use MercadoPago\PP\Sdk\Common\Manager;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\PP\Sdk\Tests\Unit\Mock\PreferenceMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class PreferenceTest
 *
 * @package MercadoPago\PP\Sdk\Tests\Entity\Preference
 */
class PreferenceTest extends TestCase
{
    /**
     * @var Preference
     */
    private $preference;

    /**
     * @var array
     */
    private $preferenceMock;

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
        $this->preferenceMock = PreferenceMock::COMPLETE_PREFERENCE;

        $this->managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->responseMock = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->preference = new Preference($this->managerMock);
        $this->preference->setEntity($this->preferenceMock);
    }

    function testSubclassesTypes()
    {
        $backUrl = $this->preference->__get('back_urls');
        $items = $this->preference->__get('items');
        $item = $items->getIterator()[0];

        $payer = $this->preference->__get('payer');
        $address = $payer->__get('address');
        $payerIdentification = $payer->__get('identification');
        $phone = $payer->__get('phone');

        $tracks = $this->preference->__get('tracks');
        $track = $tracks->getIterator()[0];

        $paymentMethod = $this->preference->__get('payment_methods');
        $excludedPaymentMethods = $paymentMethod->__get('excluded_payment_methods');
        $excludedPaymentMethod = $excludedPaymentMethods->getIterator()[0];
        $excludedPaymentTypes = $paymentMethod->__get('excluded_payment_types');
        $excludedPaymentType = $excludedPaymentTypes->getIterator()[0];

        $shipment = $this->preference->__get('shipments');
        $freeMethods = $shipment->__get('free_methods');
        $freeMethod = $freeMethods->getIterator()[0];
        $receiverAddress = $shipment->__get('receiver_address');

        $additionalInfo = $this->preference->__get('additional_info');
        $additionalInfoPayer = $additionalInfo->__get('payer');
        $additionalInfoPayerAddress = $additionalInfoPayer->__get('address');
        $phone_additional_info = $additionalInfoPayer->__get('phone');

        $shipments = $additionalInfo->__get('shipments');
        $receiverAddress_additional_info = $shipments->__get('receiver_address');

        $items_additional_info = $additionalInfo->__get('items');
        $item_additional_info = $items_additional_info->getIterator()[0];

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\BackUrl", $backUrl);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\Item", $item);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\ItemList", $items);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\Payer", $payer);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\Address", $address);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\PayerIdentification", $payerIdentification);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\Phone", $phone);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\PaymentMethod", $paymentMethod);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\ExcludedPaymentMethod", $excludedPaymentMethod);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\ExcludedPaymentMethodList", $excludedPaymentMethods);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\ExcludedPaymentType", $excludedPaymentType);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\ExcludedPaymentTypeList", $excludedPaymentTypes);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\Shipment", $shipment);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\FreeMethod", $freeMethod);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\FreeMethodList", $freeMethods);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\ReceiverAddress", $receiverAddress);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\Track", $track);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Preference\TrackList", $tracks);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfo", $additionalInfo);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfoPayer", $additionalInfoPayer);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfoAddress", $additionalInfoPayerAddress);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Phone", $phone_additional_info);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Shipments", $shipments);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfoAddress", $receiverAddress_additional_info);

        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\ItemList", $items_additional_info);
        $this->assertInstanceOf("MercadoPago\PP\Sdk\Entity\Payment\Item", $item_additional_info);
    }

    function testGetAndSetSuccess()
    {
        $this->preference->__set('external_reference', 'XXX');

        $actual = $this->preference->__get('external_reference');
        $expected = 'XXX';

        $this->assertEquals($expected, $actual);
    }

    function testGetHeadersSuccess()
    {
        $actual = $this->preference->getHeaders();

        $this->assertTrue(is_array($actual));
        $this->assertArrayHasKey('read', $actual);
        $this->assertArrayHasKey('save', $actual);
        $this->assertTrue(is_array($actual['read']));
        $this->assertTrue(is_array($actual['save']));
    }

    function testGetUriSuccess()
    {
        $actual = $this->preference->getUris();

        $this->assertTrue(is_array($actual));
    }

    function testSaveSuccess()
    {
        $this->responseMock->expects(self::any())->method('getStatus')->willReturn(201);
        $this->responseMock->expects(self::any())->method('getData')->willReturn($this->preferenceMock);

        $this->managerMock->expects(self::any())->method('getEntityUri')->willReturn('/v1/asgard/preferences');
        $this->managerMock->expects(self::any())->method('getHeader')->willReturn([]);
        $this->managerMock->expects(self::any())->method('execute')->willReturn($this->responseMock);
        $this->managerMock->expects(self::any())->method('handleResponse')->willReturn(true);

        $actual = $this->preference->save();

        $this->assertTrue($actual);
    }

    function testJsonSerializeSuccess()
    {
        $actual = $this->preference->jsonSerialize();
        $expected = 'WC-XX';

        $this->assertTrue(is_array($actual));
        $this->assertEquals($expected, $actual['external_reference']);
    }
}
