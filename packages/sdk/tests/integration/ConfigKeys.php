<?php

namespace MercadoPago\PP\Sdk\Tests\Integration;

class ConfigKeys
{
    public function loadConfigs()
    {
        return parse_ini_file(__DIR__ . '../../../.env');
    }
}
