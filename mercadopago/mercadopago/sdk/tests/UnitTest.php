<?php

// You need configure absolute_path here
require_once(dirname(__FILE__).'/../lib/mercadopago.php');

class UnitTest extends PHPUnit_Framework_TestCase {

    public function setUp() {
        
    }

    public function tearDown() {
        
    }

    var $mp;
    var $credentials;

    public function UnitTest() {
        $this->credentials = parse_ini_file(dirname(__FILE__)."/credentials.ini");

        $this->mp = new MP($this->credentials["client_id"],$this->credentials["client_secret"]);
    }

    /* Test basic Exception */

    /**
     * @expectedException MercadoPagoException
     */
    public function testInstantiationException() {
        $mp = new MP("param 1", "param 2", "param 3");
    }

    /* Test LL Access Token */

    public function testLongLiveAccessToken() {
        $mp = new MP($this->credentials["access_token"]);

        $this->assertTrue($mp->get_access_token() == $this->credentials["access_token"]);
    }

    /* Create, Get and Update preference */

    public function testPreference() {

        $preference_data = array(
            "items" => array(
                array(
                    "title" => "test1",
                    "quantity" => 1,
                    "currency_id" => "ARS",
                    "unit_price" => 10.2
                )
            )
        );

        // CREATE
        $preference = $this->mp->create_preference($preference_data);

        $this->assertTrue($preference["status"] == 201);
        $this->assertTrue($preference["response"]["items"][0]["title"] == "test1"
                && (int) $preference["response"]["items"][0]["quantity"] == 1
                && (double) $preference["response"]["items"][0]["unit_price"] == 10.2
                && $preference["response"]["items"][0]["currency_id"] == "ARS");

        // GET
        $preference = $this->mp->get_preference($preference["response"]["id"]);

        $this->assertTrue($preference["status"] == 200);

        // UPDATE
        $preference_data = array(
            "items" => array(
                array(
                    "title" => "test2Modified",
                    "quantity" => 2,
                    "currency_id" => "USD",
                    "unit_price" => 100
                )
            )
        );

        $preferenceUpdatedResult = $this->mp->update_preference($preference["response"]["id"], $preference_data);

        $this->assertTrue($preferenceUpdatedResult["status"] == 200);

        $preferenceUpdatedResult = $this->mp->get_preference($preference["response"]["id"]);

        $this->assertTrue((double) $preferenceUpdatedResult["response"]["items"][0]["unit_price"] == 100
                && (double) $preferenceUpdatedResult["response"]["items"][0]["quantity"] == 2
                && $preferenceUpdatedResult["response"]["items"][0]["title"] == "test2Modified"
                && $preferenceUpdatedResult["response"]["items"][0]["currency_id"] == "USD");
    }

    public function testPreapproval() {

        $preference_data = array(
            "payer_email" => "my_customer@my_site.com",
            "back_url" => "https://www.testpreapproval.com/back_url",
            "reason" => "Preapproval preference",
            "external_reference" => "OP-1234",
            "auto_recurring" => array(
                "frequency" => 1,
                "frequency_type" => "months",
                "transaction_amount" => 60,
                "currency_id" => "ARS"
            )
        );

        // CREATE
        $preference = $this->mp->create_preapproval_payment($preference_data);

        $this->assertTrue($preference["status"] == 201);
        $this->assertTrue($preference["response"]["payer_email"] == "my_customer@my_site.com"
                && $preference["response"]["reason"] == "Preapproval preference"
                && $preference["response"]["external_reference"] == "OP-1234");

        // GET
        $preference = $this->mp->get_preapproval_payment($preference["response"]["id"]);

        $this->assertTrue($preference["status"] == 200);

        // UPDATE
        $preference_data = array(
            "reason" => "Preapproval preference updated",
            "external_reference" => "OP-5678"
        );

        $preferenceUpdatedResult = $this->mp->update_preapproval_payment($preference["response"]["id"], $preference_data);

        $this->assertTrue($preferenceUpdatedResult["status"] == 200);

        $preferenceUpdatedResult = $this->mp->get_preapproval_payment($preference["response"]["id"]);

        $this->assertTrue($preferenceUpdatedResult["response"]["payer_email"] == "my_customer@my_site.com"
                && $preferenceUpdatedResult["response"]["reason"] == "Preapproval preference updated"
                && $preferenceUpdatedResult["response"]["external_reference"] == "OP-5678");
    }


    // Basic request to Sites MLA
    public function testGenericGetWithoutAuthorization() {
        $request = array(
            "uri" => "/sites/MLA",
            "authenticate" => false
        );

        $response = $this->mp->get($request);

        $this->assertTrue($response["status"] == 200);
        $this->assertTrue($response["response"]["id"] == "MLA");
    }

     /**
     * @expectedException     Exception
     * @expectedExceptionCode 401
     */
    public function testGenericGetAuthorizationFail() {
        $request = array(
            "uri" => "/checkout/preferences/dummy",
            "authenticate" => false
        );

        $response = $this->mp->get($request);
    }

    // Generic post - IDEM testCreatePreference
    public function testGenericPost() {

        $preference_data = array(
            "items" => array(
                array(
                    "title" => "test1",
                    "quantity" => 1,
                    "currency_id" => "ARS",
                    "unit_price" => 10.2
                )
            )
        );


        $request = array(
            "uri" => "/checkout/preferences",
            "data" => $preference_data
        );

        $preference = $this->mp->post($request);

        $this->assertTrue($preference["status"] == 201);

        $this->assertTrue($preference["response"]["items"][0]["title"] == "test1"
                && (int) $preference["response"]["items"][0]["quantity"] == 1
                && (double) $preference["response"]["items"][0]["unit_price"] == 10.2
                && $preference["response"]["items"][0]["currency_id"] == "ARS");
    }

    // Generic put - IDEM testUpdatePreference
    public function testGenericPut() {

        $preference_data = array(
            "items" => array(
                array(
                    "title" => "test2",
                    "quantity" => 1,
                    "currency_id" => "ARS",
                    "unit_price" => 20.55
                )
            )
        );

        $request = array(
            "uri" => "/checkout/preferences",
            "data" => $preference_data
        );

        $preference = $this->mp->post($request);

        // Start updating

        $preference_data = array(
            "items" => array(
                array(
                    "title" => "test2Modified",
                    "quantity" => 2,
                    "currency_id" => "USD",
                    "unit_price" => 100
                )
            )
        );

        $request = array(
            "uri" => "/checkout/preferences/".$preference["response"]["id"],
            "data" => $preference_data
        );

        $preferenceUpdatedResult = $this->mp->put($request);

        $this->assertTrue($preferenceUpdatedResult["status"] == 200);

        $preferenceUpdatedResult = $this->mp->get_preference($preference["response"]["id"]);

        $this->assertTrue((double) $preferenceUpdatedResult["response"]["items"][0]["unit_price"] == 100
                && (double) $preferenceUpdatedResult["response"]["items"][0]["quantity"] == 2
                && $preferenceUpdatedResult["response"]["items"][0]["title"] == "test2Modified"
                && $preferenceUpdatedResult["response"]["items"][0]["currency_id"] == "USD");
    }


    public function testIdempotency() {
        // Card token
        $data = array(
            "card_number" => "5031755734530604",
            "security_code" => "123",
            "expiration_month" => 4,
            "expiration_year" => 2020,
            "cardholder" => array(
                "name" => "APRO",
                "identification" => array(
                    "subtype" => null,
                    "number" => "12345678",
                    "type" => "DNI"
                )
            )
        );

        $request = array(
            "uri" => "/v1/card_tokens",
            "params" => array(
                "public_key" => $this->credentials["public_key"]
            ),
            "data" => $data,
            "authenticate" => false
        );

        $card_token = $this->mp->post($request);

        //Payment
        $data = array(
            "token" => $card_token["response"]["id"],
            "description" => "Payment test",
            "transaction_amount" => 154.9,
            "payment_method_id" =>  "master",
            "payer" => array(
                "email" => "test@localsdk.com"
            ),
            "installments" => 9
        );

        $request = array(
            "uri" => "/v1/payments",
            "params" => array(
                "access_token" => $this->credentials["access_token"]
            ),
            "data" => $data,
            "headers" => array(
                "x-idempotency-key" => "sdk-test-idempotency-dummy-key"
            ),
            "authenticate" => false
        );

        $payment_1 = $this->mp->post($request);
        $payment_2 = $this->mp->post($request);

         $this->assertEquals($payment_1["response"]["id"], $payment_2["response"]["id"]);
    }

    // With customers api
    public function testDelete() {
        $request = array(
            "uri" => "/v1/customers",
            "params" => array(
                "access_token" => $this->credentials["access_token"]
            ),
            "data" => array(
                "email" => "test_".rand(10000, 99999)."@localsdk.com"
            ),
            "authenticate" => false
        );

        $customer = $this->mp->post($request);

        $this->assertEquals($customer["status"], 201);

        $request = array(
            "uri" => "/v1/customers/".$customer["response"]["id"],
            "params" => array(
                "access_token" => $this->credentials["access_token"]
            ),
            "authenticate" => false
        );

        $deleted_customer = $this->mp->delete($request);
        $this->assertEquals($deleted_customer["status"], 200);
    }
    // END TESTS
}
