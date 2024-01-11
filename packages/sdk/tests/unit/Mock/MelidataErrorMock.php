<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class MelidataErrorMock
 */
class MelidataErrorMock
{

  const COMPLETE_MELIDATA_ERROR = [
    "name" => "error name",
    "message" => "error message",
    "target" => "error target",
    "plugin" => [
      "version" => "123",
    ],
    "platform" => [
      "name" => "ppcoreinternal",
      "version" => "1.2.3",
      "uri" => "/platform_uri",
      "location" => "platform_location",
    ],
    "details" => [
      "payment_id" => "123456",
    ],
  ];
}
