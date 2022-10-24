<?php

namespace MercadoPago\Woocommerce;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader
{
    public static function init()
    {
        $autoloader = dirname(__FILE__) . '/../vendor/autoload.php';
        return self::loadAutoload($autoloader);
    }

    public static function loadAutoload($autoloader)
    {
        if (!is_readable($autoloader)) {
            self::missingAutoloadNotice($autoloader);
            return false;
        }

        $autoloader_result = require $autoloader;
        if (!$autoloader_result) {
            return false;
        }

        return $autoloader_result;
    }

    protected static function missingAutoloadNotice($autoloader)
    {
        add_action('admin_notices', function () use ($autoloader) {
            include dirname(__FILE__) . '/../templates/admin/notices/miss-autoload.php';
        });
    }
}
