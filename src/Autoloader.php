<?php

namespace MercadoPago\Woocommerce;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader
{
    /**
     * Init Autoloader
     *
     * @return mixed
     */
    public static function init()
    {
        $autoloader = dirname(__FILE__) . '/../vendor/autoload.php';
        return self::loadAutoload($autoloader);
    }

    /**
     * Start loading autoload
     *
     * @param string $autoloader
     *
     * @return mixed
     */
    public static function loadAutoload(string $autoloader)
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

    /**
     * Show autoload missing notice
     *
     * @param string $autoloader
     *
     * @return void
     */
    protected static function missingAutoloadNotice(string $autoloader): void
    {
        add_action('admin_notices', function () use ($autoloader) {
            include dirname(__FILE__) . '/../templates/admin/notices/miss-autoload.php';
        });
    }
}
