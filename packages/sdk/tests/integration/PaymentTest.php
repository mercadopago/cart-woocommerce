<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class PaymentTest extends TestCase
{

    private function loadPaymentSdk() {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $accessToken = $envVars['ACCESS_TOKEN'] ?? null;
        $publicKey = $envVars['PUBLIC_KEY'] ?? null;
        $sdk = new Sdk(
            $accessToken,
            'ppcoreinternal',
            'ppcoreinternal',
            '',
            $publicKey
        );
        $notificationUrl = $envVars['NOTIFICATION_URL'] ?? null;
        $payment = $sdk->getPaymentInstance(); 
        $payment->notification_url = $notificationUrl;
        return $payment;
    }

    private function loadPaymentSdkV21() {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $accessToken = $envVars['ACCESS_TOKEN'] ?? null;
        $publicKey = $envVars['PUBLIC_KEY'] ?? null;
        $sdk = new Sdk(
            $accessToken,
            'ppcoreinternal',
            'ppcoreinternal',
            '',
            $publicKey
        );
        $notificationUrl = $envVars['NOTIFICATION_URL'] ?? null;
        $payment = $sdk->getPaymentV21Instance(); 
        $payment->notification_url = $notificationUrl;
        return $payment;
    }

    private function loadPayment()
    {
        $payment = $this->loadPaymentSdk();
        $payment->transaction_amount = 230;
        $payment->description = "Ergonomic Silk Shirt";
        $payment->payer->first_name = "Daniel";
        $payment->payer->last_name = "Lima";
        $payment->payer->identification->type = "CPF";
        $payment->payer->identification->number = "097.588.560-06";
        $payment->point_of_interaction->type = "CHECKOUT";

        $payment->payer->email = "test_user_98934401@testuser.com";

        return $payment;
    }

    private function loadPaymentV21()
    {
        $payment = $this->loadPaymentSdkV21();
        $payment->transaction_amount = 230;
        $payment->description = "Ergonomic Silk Shirt";
        $payment->payer->first_name = "Daniel";
        $payment->payer->last_name = "Lima";
        $payment->payer->identification->type = "CPF";
        $payment->payer->identification->number = "097.588.560-06";
        $payment->point_of_interaction->type = "CHECKOUT";

        $payment->payer->email = "test_user_98934401@testuser.com";

        return $payment;
    }

    private function loadPayment3DS()
    {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $accessToken = $envVars['ACCESS_TOKEN_3DS'] ?? null;
        $publicKey = $envVars['PUBLIC_KEY'] ?? null;
        $sdk = new Sdk(
            $accessToken,
            'ppcoreinternal',
            'ppcoreinternal',
            '',
            $publicKey
        );
        $notificationUrl = $envVars['NOTIFICATION_URL'] ?? null;
        $payment = $sdk->getPaymentInstance(); 
        $payment->notification_url = $notificationUrl;

        $payment->transaction_amount = 230;
        $payment->description = "Ergonomic Silk Shirt";
        $payment->installments = 3;
        $payment->payment_method_id = "master";
        $payment->payer->email = "test_user_98934401@testuser.com";
        $payment->three_d_secure_mode = "optional";

        return $payment;
    }


    public function testPaymentSuccessCreditCard()
    {
        $payment = $this->loadPayment();

        $cardToken = new CardToken();
        $idToken = $cardToken->generateCardTokenMaster("APRO");

        $payment->token = $idToken;
        $payment->installments = 1;
        $payment->payment_method_id = "master";

        $response = json_decode(json_encode($payment->save()));

        $this->assertEquals($response->status, 'approved');
        $this->assertEquals($response->payment_method_id, 'master');
        $this->assertEquals($response->payment_type_id, 'credit_card');
        $this->assertEquals($response->installments, 1);
        $this->assertEquals($response->status_detail, "accredited");
    }

    public function testPaymentRejectedCreditCard()
    {
        $payment = $this->loadPayment();

        $cardToken = new CardToken();
        $idToken = $cardToken->generateCardTokenMaster("OTHE");

        $payment->token = $idToken;
        $payment->installments = 1;
        $payment->payment_method_id = "master";

        $response = json_decode(json_encode($payment->save()));

        $this->assertEquals($response->status, 'rejected');
        $this->assertEquals($response->payment_method_id, 'master');
        $this->assertEquals($response->payment_type_id, 'credit_card');
        $this->assertEquals($response->installments, 1);
        $this->assertEquals($response->status_detail, "cc_rejected_bad_filled_other");
    }

    public function testPaymentV21SuccessCreditCard()
    {
        $payment = $this->loadPaymentV21();

        $cardToken = new CardToken();
        $idToken = $cardToken->generateCardTokenMaster("APRO");

        $payment->token = $idToken;
        $payment->installments = 1;
        $payment->payment_method_id = "master";

        $response = json_decode(json_encode($payment->save()));

        $this->assertEquals($response->status, 'approved');
        $this->assertEquals($response->payment_method_id, 'master');
        $this->assertEquals($response->payment_type_id, 'credit_card');
        $this->assertEquals($response->installments, 1);
        $this->assertEquals($response->status_detail, "accredited");
    }

    public function testPaymentV21RejectedCreditCard()
    {
        $payment = $this->loadPaymentV21();

        $cardToken = new CardToken();
        $idToken = $cardToken->generateCardTokenMaster("OTHE");

        $payment->token = $idToken;
        $payment->installments = 1;
        $payment->payment_method_id = "master";

        $response = json_decode(json_encode($payment->save()));

        $this->assertEquals($response->status, 'rejected');
        $this->assertEquals($response->payment_method_id, 'master');
        $this->assertEquals($response->payment_type_id, 'credit_card');
        $this->assertEquals($response->installments, 1);
        $this->assertEquals($response->status_detail, "cc_rejected_bad_filled_other");
    }

    public function testPaymentSuccessBoleto()
    {
        $payment = $this->loadPayment();

        $payment->payment_method_id = "bolbradesco";
        $payment->payer->address->zip_code = "000";
        $payment->payer->address->street_name = "rua teste";
        $payment->payer->address->street_number = "123";
        $payment->payer->address->neighborhood = "neighborhood";
        $payment->payer->address->city = "city";
        $payment->payer->address->federal_unit = "federal_unit";

        $response = json_decode(json_encode($payment->save()));

        $this->assertEquals($response->status, 'pending');
        $this->assertEquals($response->payment_method_id, 'bolbradesco');
        $this->assertEquals($response->payment_type_id, 'ticket');
        $this->assertEquals($response->status_detail, "pending_waiting_payment");
    }

    public function testPaymentSuccessPix()
    {
        $payment = $this->loadPayment();

        $payment->payment_method_id = "pix";

        $response = json_decode(json_encode($payment->save()));

        $this->assertEquals($response->status, 'pending');
        $this->assertEquals($response->payment_method_id, 'pix');
        $this->assertEquals($response->payment_type_id, 'bank_transfer');
        $this->assertEquals($response->status_detail, "pending_waiting_transfer");
    }

    public function testPaymentSuccessCreditCard3DS()
    {
        $payment = $this->loadPayment3DS();

        $cardToken = new CardToken();
        $idToken = $cardToken->generateCardTokenMaster3DS("APRO");

        $payment->token = $idToken;
        $payment->installments = 3;
        $payment->payment_method_id = "master";

        $response = json_decode(json_encode($payment->save()));

        $this->assertEquals($response->status, 'approved');
        $this->assertEquals($response->payment_method_id, 'master');
        $this->assertEquals($response->payment_type_id, 'credit_card');
        $this->assertEquals($response->installments, 3);
        $this->assertEquals($response->status_detail, "accredited");
    }

    public function testPaymentRejectedCreditCard3DS()
    {
        $payment = $this->loadPayment3DS();

        $cardToken = new CardToken();
        $idToken = $cardToken->generateCardTokenMaster3DS("OTHE");

        $payment->token = $idToken;
        $payment->installments = 1;
        $payment->payment_method_id = "master";

        $response = json_decode(json_encode($payment->save()));

        $this->assertEquals($response->status, 'rejected');
        $this->assertEquals($response->payment_method_id, 'master');
        $this->assertEquals($response->payment_type_id, 'credit_card');
        $this->assertEquals($response->installments, 1);
        $this->assertEquals($response->status_detail, "cc_rejected_bad_filled_other");
    }

    public function testGetPaymentSuccess()
    {
        $payment = $this->loadPayment();
        $payment->payment_method_id = "pix";
        $response = json_decode(json_encode($payment->save()));

        $paymentInstance = $this->loadPaymentSdk();
        $responseRead = json_decode(json_encode($paymentInstance->read(array(
            "id" => $response->id,
        ))));

        $this->assertEquals($responseRead->id, $response->id);
        $this->assertEquals($responseRead->status, $response->status);
        $this->assertEquals($responseRead->payment_type_id, $response->payment_type_id);
        $this->assertEquals($responseRead->payment_method_id, $response->payment_method_id);
        $this->assertEquals($responseRead->transaction_details->total_paid_amount, $response->transaction_details->total_paid_amount);
        $this->assertEquals($responseRead->transaction_details->installment_amount, $response->transaction_details->installment_amount);
        $this->assertEquals($responseRead->point_of_interaction->transaction_data->qr_code_base64, $response->point_of_interaction->transaction_data->qr_code_base64);
        $this->assertEquals($responseRead->point_of_interaction->transaction_data->qr_code, $response->point_of_interaction->transaction_data->qr_code);
    }

    public function testGetPaymentNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment not found');
        $paymentInstance = $this->loadPaymentSdk();
        $responseRead = json_decode(json_encode($paymentInstance->read(array(
            "id" => "123",
        ))));
    }

    public function testGetPaymentSuccessV21()
    {
        $payment = $this->loadPayment();
        $payment->payment_method_id = "pix";
        $response = json_decode(json_encode($payment->save()));

        $paymentInstance = $this->loadPaymentSdkV21();
        $responseRead = json_decode(json_encode($paymentInstance->read(array(
            "id" => $response->id,
        ))));

        $this->assertEquals($responseRead->id, $response->id);
        $this->assertEquals($responseRead->status, $response->status);
        $this->assertEquals($responseRead->payment_type_id, $response->payment_type_id);
        $this->assertEquals($responseRead->payment_method_id, $response->payment_method_id);
        $this->assertEquals($responseRead->transaction_details->total_paid_amount, $response->transaction_details->total_paid_amount);
        $this->assertEquals($responseRead->transaction_details->installment_amount, $response->transaction_details->installment_amount);
    }

    public function testGetPaymentNotFoundV21()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment not found');
        $paymentInstance = $this->loadPaymentSdkV21();
        $responseRead = json_decode(json_encode($paymentInstance->read(array(
            "id" => "123",
        ))));
    }
}
