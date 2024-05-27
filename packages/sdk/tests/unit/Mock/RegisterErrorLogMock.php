<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class RegisterErrorLogMock
 */
class RegisterErrorLogMock
{

  const COMPLETE_REGISTER_ERROR_LOG = [
      "message" => "Error to send payment",
      "stacktrace" => "test.go",
      "uri" => "/checkout",
      "url" => "https=>//",
      "location" => "/main=>83",
      "platform_version" => "1.2.3",
      "module_version" => "9.5.0",
      "runtime_version" => "PHP 7.0",
      "os_version" => "ubuntu bionic",
      "browser_version" => "50.80.3",
      "details" => [
          "payment_id" => "123"
      ]
  ];
}
