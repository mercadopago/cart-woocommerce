<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class MultipaymentTest extends TestCase
{
    private function loadMultipaymentV1()
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

        $multipayment = $sdk->getMultipaymentInstance();

        $multipayment->transaction_amount = 90;
        $multipayment->date_of_expiration = "2024-03-30T20:10:00.000+0000";
        $multipayment->description = "Ergonomic Silk Shirt";
        $multipayment->payer->email = "test_user_98934401@testuser.com";
        $multipayment->payment_method_id = "pp_multiple_payments";

        return $multipayment;
    }
    private function loadMultipaymentV2()
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

        $multipayment = $sdk->getMultipaymentV2Instance();

        $multipayment->transaction_amount = 90;
        $multipayment->date_of_expiration = "2024-03-30T20:10:00.000+0000";
        $multipayment->description = "Ergonomic Silk Shirt";
        $multipayment->payer->email = "test_user_98934401@testuser.com";
        $multipayment->payment_method_id = "pp_multiple_payments";

        return $multipayment;
    }
    private function loadMultipaymentV21()
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

        $multipayment = $sdk->getMultipaymentV21Instance();

        $multipayment->transaction_amount = 90;
        $multipayment->date_of_expiration = (date('Y') + 1)."-03-30T20:10:00.000+0000";
        $multipayment->description = "Ergonomic Silk Shirt";
        $multipayment->payer->email = "test_user_98934401@testuser.com";
        $multipayment->payment_method_id = "pp_multiple_payments";

        return $multipayment;
    }

    public function testMultipaymentV1Success()
    {
        $multipayment = $this->loadMultipaymentV1();

        $cardToken = new CardToken();
        $firstIdCardToken = $cardToken->generateCardTokenMaster("APRO");
        $secondIdCardToken = $cardToken->generateCardTokenVisa("APRO");

        $transaction_info = ["transaction_info" =>
            [
                "transaction_amount" => 50,
                "installments" => 1,
                "token" => $firstIdCardToken,
                "payment_method_id" => "master"
            ],
            [
                "transaction_amount" => 40,
                "installments" => 1,
                "token" => $secondIdCardToken,
                "payment_method_id" => "visa"
            ]
        ];
        $multipayment->transaction_info = $transaction_info;

        $response = json_decode(json_encode($multipayment->save()));

        $this->assertEquals($response->status, 'approved');
        $this->assertEquals($response->payment_method_id, 'pp_multiple_payments');
        $this->assertEquals($response->payment_type_id, 'pp_multiple_payments');
        $this->assertEquals($response->transaction_info[0]->payment_method_id, 'master');
        $this->assertEquals($response->transaction_info[1]->payment_method_id, 'visa');
        $this->assertEquals($response->transaction_info[0]->status, 'approved');
        $this->assertEquals($response->transaction_info[1]->status, 'approved');
        $this->assertEquals($response->transaction_info[0]->status_detail, "accredited");
        $this->assertEquals($response->transaction_info[1]->status_detail, "accredited");
        $this->assertNotEquals($response->transaction_info[0]->id, $response->transaction_info[1]->id);
    }

    public function testMultipaymentV1Rejected()
    {
        $multipayment = $this->loadMultipaymentV1();

        $cardToken = new CardToken();
        $firstIdCardToken = $cardToken->generateCardTokenMaster("APRO");
        $secondIdCardToken = $cardToken->generateCardTokenVisa("OTHE");

        $transaction_info = ["transaction_info" =>
            [
                "transaction_amount" => 50,
                "installments" => 1,
                "token" => $firstIdCardToken,
                "payment_method_id" => "master"
            ],
            [
                "transaction_amount" => 40,
                "installments" => 1,
                "token" => $secondIdCardToken,
                "payment_method_id" => "visa"
            ]
        ];
        $multipayment->transaction_info = $transaction_info;

        $response = null;
        try {
            $response = json_decode(json_encode($multipayment->save()));
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        $this->assertEquals($response, "No message for Multipayment scenario in v1!");
    }


    public function testMultipaymentV2Success()
    {
        $multipayment = $this->loadMultipaymentV2();

        $cardToken = new CardToken();
        $firstIdCardToken = $cardToken->generateCardTokenMaster("APRO");
        $secondIdCardToken = $cardToken->generateCardTokenVisa("APRO");

        $transaction_info = ["transaction_info" =>
            [
                "transaction_amount" => 50,
                "installments" => 1,
                "token" => $firstIdCardToken,
                "payment_method_id" => "master"
            ],
            [
                "transaction_amount" => 40,
                "installments" => 1,
                "token" => $secondIdCardToken,
                "payment_method_id" => "visa"
            ]
        ];
        $multipayment->transaction_info = $transaction_info;

        $response = json_decode(json_encode($multipayment->save()));

        $this->assertEquals($response->status, 'approved');
        $this->assertEquals($response->payment_method_id, 'pp_multiple_payments');
        $this->assertEquals($response->payment_type_id, 'pp_multiple_payments');
        $this->assertEquals($response->transaction_info[0]->payment_method_id, 'master');
        $this->assertEquals($response->transaction_info[1]->payment_method_id, 'visa');
        $this->assertEquals($response->transaction_info[0]->status, 'approved');
        $this->assertEquals($response->transaction_info[1]->status, 'approved');
        $this->assertEquals($response->transaction_info[0]->status_detail, "accredited");
        $this->assertEquals($response->transaction_info[1]->status_detail, "accredited");
        $this->assertNotEquals($response->transaction_info[0]->id, $response->transaction_info[1]->id);
    }

    public function testMultipaymentV2Rejected()
    {
        $multipayment = $this->loadMultipaymentV2();

        $cardToken = new CardToken();
        $firstIdCardToken = $cardToken->generateCardTokenMaster("APRO");
        $secondIdCardToken = $cardToken->generateCardTokenVisa("OTHE");

        $transaction_info = ["transaction_info" =>
            [
                "transaction_amount" => 50,
                "installments" => 1,
                "token" => $firstIdCardToken,
                "payment_method_id" => "master"
            ],
            [
                "transaction_amount" => 40,
                "installments" => 1,
                "token" => $secondIdCardToken,
                "payment_method_id" => "visa"
            ]
        ];
        $multipayment->transaction_info = $transaction_info;

        $response = null;
        try {
            $response = json_decode(json_encode($multipayment->save()));
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        $this->assertStringContainsString("Your payment was declined", $response);
    }

    public function testMultipaymentV21Success()
    {
        $multipayment = $this->loadMultipaymentV21();

        $cardToken = new CardToken();
        $firstIdCardToken = $cardToken->generateCardTokenMaster("APRO");
        $secondIdCardToken = $cardToken->generateCardTokenVisa("APRO");

        $transaction_info = ["transaction_info" =>
            [
                "transaction_amount" => 50,
                "installments" => 1,
                "token" => $firstIdCardToken,
                "payment_method_id" => "master"
            ],
            [
                "transaction_amount" => 40,
                "installments" => 1,
                "token" => $secondIdCardToken,
                "payment_method_id" => "visa"
            ]
        ];
        $multipayment->transaction_info = $transaction_info;

        $response = json_decode(json_encode($multipayment->save()));

        $this->assertEquals($response->status, 'approved');
        $this->assertEquals($response->payment_method_id, 'pp_multiple_payments');
        $this->assertEquals($response->payment_type_id, 'pp_multiple_payments');
        $this->assertEquals($response->transaction_info[0]->payment_method_id, 'master');
        $this->assertEquals($response->transaction_info[1]->payment_method_id, 'visa');
        $this->assertEquals($response->transaction_info[0]->status, 'approved');
        $this->assertEquals($response->transaction_info[1]->status, 'approved');
        $this->assertEquals($response->transaction_info[0]->status_detail, "accredited");
        $this->assertEquals($response->transaction_info[1]->status_detail, "accredited");
        $this->assertNotEquals($response->transaction_info[0]->id, $response->transaction_info[1]->id);
    }

    public function testMultipaymentV21Rejected()
    {
        $multipayment = $this->loadMultipaymentV21();

        $cardToken = new CardToken();
        $firstIdCardToken = $cardToken->generateCardTokenMaster("APRO");
        $secondIdCardToken = $cardToken->generateCardTokenVisa("OTHE");

        $transaction_info = ["transaction_info" =>
            [
                "transaction_amount" => 50,
                "installments" => 1,
                "token" => $firstIdCardToken,
                "payment_method_id" => "master"
            ],
            [
                "transaction_amount" => 40,
                "installments" => 1,
                "token" => $secondIdCardToken,
                "payment_method_id" => "visa"
            ]
        ];
        $multipayment->transaction_info = $transaction_info;

        try {
            $response = json_decode(json_encode($multipayment->save()));
        } catch (\Exception $e) {
            $response = $e->getMessage();
        }
        $this->assertStringContainsString("Payment with the second card was declined due to an error. We recommend paying with another card or contacting the bank. Do not worry if any fees were charged on the first payment method, they will be refunded automatically.", $response);
    }
}
