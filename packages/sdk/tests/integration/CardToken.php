<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

class CardToken
{
    public function generateCardTokenMaster($statusCardToken)
    {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $publicKey = $envVars['PUBLIC_KEY'] ?? null;
        $url = "https://api.mercadopago.com/v1/card_tokens?public_key=".$publicKey;

        $year = date("Y") + 1;

        $data = [
            "card_number" => "5031433215406351",
            "expiration_year" => $year,
            "expiration_month" => 11,
            "security_code" => "123",
            "site_id" => "MLB",
            "cardholder" => [
                "identification" => [
                    "type" => "CPF",
                    "number" => "12345678909"
                ],
                "name" => $statusCardToken
            ]
        ];
        $payload = json_encode($data);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $output = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->fail('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($output)->id;
    }

    public function generateCardTokenVisa($statusCardToken)
    {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $publicKey = $envVars['PUBLIC_KEY'] ?? null;
        $url = "https://api.mercadopago.com/v1/card_tokens?public_key=".$publicKey;

        $year = date("Y") + 1;

        $data = [
            "card_number" => "4235647728025682",
            "expiration_year" => $year,
            "expiration_month" => 11,
            "security_code" => "123",
            "site_id" => "MLB",
            "cardholder" => [
                "identification" => [
                    "type" => "CPF",
                    "number" => "12345678909"
                ],
                "name" => $statusCardToken
            ]
        ];
        $payload = json_encode($data);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $output = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->fail('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($output)->id;
    }

    public function generateCardTokenMaster3DS($statusCardToken)
    {
        $configKeys = new ConfigKeys();
        $envVars = $configKeys->loadConfigs();
        $publicKey = $envVars['PUBLIC_KEY_3DS'] ?? null;
        $url = "https://api.mercadopago.com/v1/card_tokens?public_key=".$publicKey;

        $year = date("Y") + 1;

        $data = [
            "card_number" => "5031433215406351",
            "expiration_year" => $year,
            "expiration_month" => 11,
            "security_code" => "123",
            "site_id" => "MLB",
            "cardholder" => [
                "identification" => [
                    "type" => "CPF",
                    "number" => "12345678909"
                ],
                "name" => $statusCardToken
            ]
        ];
        $payload = json_encode($data);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $output = curl_exec($ch);

        if (curl_errno($ch)) {
            $this->fail('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($output)->id;
    }
}
