<?php

namespace MercadoPago\PP\Sdk\Tests\Unit\Mock;

/**
 * Class DatadogEventMock
 */
class DatadogEventMock
{

  const COMPLETE_DATADOG_EVENT = [
    "value" => "success",
    "message" => "error message",
    "plugin_version" => "1.2.3",
    "platform" => [
      "name" => "ppcoreinternal",
      "version" => "1.2.3",
      "uri" => "/platform_uri",
      "url" => "https://platform_url...",
    ],
    "details" => [
      "payment_id" => "123456",
    ],
  ];
}
