<?php

namespace MercadoPago\Woocommerce\Tests\Mocks;

use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\PP\Sdk\Entity\Preference\PaymentMethod;
use MercadoPago\PP\Sdk\Entity\Preference\ItemList;
use MercadoPago\PP\Sdk\Entity\Preference\BackUrl;
use MercadoPago\PP\Sdk\Entity\Preference\Payer as PreferencePayer;
use MercadoPago\PP\Sdk\Entity\Preference\Shipment;
use MercadoPago\PP\Sdk\Entity\Preference\TrackList;
use MercadoPao\PP\Sdk\Entity\Preference\ExcludedPaymentMethodList;
use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Payment\Payer as PaymentPayer;
use MercadoPago\PP\Sdk\Entity\Payment\TransactionDetails;
use MercadoPago\PP\Sdk\Entity\Payment\PointOfInteraction;
use MercadoPago\PP\Sdk\Entity\Payment\AdditionalInfo;
use Mockery;

class SdkMock
{
    static function getPreferenceEntityMock()
    {
        $mock = Mockery::mock(Preference::class)->makePartial();
        $mock->payment_methods = Mockery::mock(PaymentMethod::class);
        $mock->payment_methods->excluded_payment_methods = Mockery::mock(ExcludedPaymentMethodList::class);
        $mock->additional_info = Mockery::mock(AdditionalInfo::class);
        $mock->items = Mockery::mock(ItemList::class);
        $mock->back_urls = Mockery::mock(BackUrl::class);
        $mock->payer = Mockery::mock(PreferencePayer::class);
        $mock->shipments = Mockery::mock(Shipment::class);
        $mock->tracks = Mockery::mock(TrackList::class);
        return $mock;        
    }

    static function getPaymentEntityMock()
    {
        $mock = Mockery::mock(Payment::class)->makePartial();
        $mock->payer = Mockery::mock(PaymentPayer::class);
        $mock->additional_info = Mockery::mock(AdditionalInfo::class);
        $mock->transaction_details = Mockery::mock(TransactionDetails::class);
        $mock->point_of_interaction = Mockery::mock(PointOfInteraction::class);
        return $mock;
    }
}
