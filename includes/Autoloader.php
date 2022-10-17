<?php

namespace MercadoPago\CartWooCommerce\Autoloader;

defined('ABSPATH') || exit;

class Autoloader
{
    public static function init()
    {
        $autoloader = dirname(__DIR__) . '../vendor/autoload.php';

        if (! is_readable($autoloader)) {
            self::missingAutoload();
            return false;
        }

        $autoloader_result = require $autoloader;

        if (!$autoloader_result) {
            return false;
        }

        return $autoloader_result;
    }

    protected static function missingAutoload()
    {
    }
}
