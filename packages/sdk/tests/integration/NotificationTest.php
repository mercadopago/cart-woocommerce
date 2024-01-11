<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class NotificationTest extends TestCase
{

    private function loadSdk()
    {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $accessToken = $envVars['ACCESS_TOKEN'] ?? null;
        $sdk = new Sdk(
            $accessToken,
            'ppcoreinternal',
            'ppcoreinternal',
            ''
        );

        return $sdk;
    }

    private function createPayment($cardHolder)
    {

        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $notificationUrl = $envVars['NOTIFICATION_URL'] ?? null;

        $sdk = $this->loadSdk();
        $payment = $sdk->getPaymentInstance();

        $payment->transaction_amount = 230;
        $payment->description = "Ergonomic Silk Shirt";
        $payment->payer->first_name = "Daniel";
        $payment->payer->last_name = "Lima";
        $payment->payer->identification->type = "CPF";
        $payment->payer->identification->number = "097.588.560-06";
        $payment->point_of_interaction->type = "CHECKOUT";
        $payment->payer->email = "test_user_98934401@testuser.com";
        $payment->notification_url = $notificationUrl;
        $cardToken = new CardToken();
        $idToken = $cardToken->generateCardTokenMaster($cardHolder);
        $payment->token = $idToken;
        $payment->installments = 1;
        $payment->payment_method_id = "master";
        return json_decode(json_encode($payment->save()));
    }

    public function testNotificationAprooved()
    {
        $sdk = $this->loadSdk();
        $notification = $sdk->getNotificationInstance();
        $payment = $this->createPayment("APRO");
        $response = json_decode(json_encode($notification->read(array("id" => "P-".$payment->id))));        
        $this->assertEquals($response->status, 'approved');
    }

    public function testNotificationRejected()
    {
        $sdk = $this->loadSdk();
        $notification = $sdk->getNotificationInstance();
        $payment = $this->createPayment("CALL");
        $response = json_decode(json_encode($notification->read(array("id" => "P-".$payment->id))));        
        $this->assertEquals($response->status, 'rejected');
    }

    public function testNotificationNotFound()
    {
        $sdk = $this->loadSdk();
        $notification = $sdk->getNotificationInstance();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('1003 - KVS getValue not found');
        $notification->read(array("id" => "P-123456"));
    }

}