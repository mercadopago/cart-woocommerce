<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

use PHPUnit\Framework\TestCase;
use MercadoPago\PP\Sdk\Sdk;

class RegisterErrorLogTest extends TestCase
{
    private function loadRegisterErrorLog()
    {
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

        $registerErrorLog = $sdk->getRegisterErrorLogInstance();

        $registerErrorLog->message = 'Sample error message';
        $registerErrorLog->stacktrace = 'monitoring_regiter_log.php';
        $registerErrorLog->location = 'save';
        $registerErrorLog->platform_version = phpversion();
        $registerErrorLog->module_version = "1.0.0";
        $registerErrorLog->user_agent = 'PHP SDK';
        $registerErrorLog->flow = 'sample-php-sdk';
        $registerErrorLog->runtime_version = phpversion();
        $registerErrorLog->os_version = "10";
        $registerErrorLog->browser_version = "Chrome";
        $registerErrorLog->uri = 'http://localhost';
        $registerErrorLog->url = 'http://localhost';
        $registerErrorLog->details = [
            'payment_id' => '123456789',
        ];

        return $registerErrorLog;
    }


    public function testRegisterErrorLogWithSuccess()
    {
        $registerErrorLog = $this->loadRegisterErrorLog();

        $response = json_decode(json_encode($registerErrorLog->save()));

        $this->assertEquals($response->code, 'created');
        $this->assertEquals($response->message, 'created');
        $this->assertEquals($response->status, '201');
    }
}
