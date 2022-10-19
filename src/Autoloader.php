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
        if (!is_readable($autoloader)) {
            self::missingAutoloadNotice();
            return false;
        }

        $autoloader_result = require $autoloader;
        if (!$autoloader_result) {
            return false;
        }

        return $autoloader_result;
    }

    protected static function missingAutoloadNotice()
    {
        add_action(
            'admin_notices',
            function () {
                ?>
                    <div class="notice notice-error">
                        <p>Unable to find composer autoloader</p>
                    </div>
                <?php
            }
        );
    }
}
