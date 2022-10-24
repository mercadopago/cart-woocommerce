<?php

namespace MercadoPago\Woocommerce;

if (!defined('ABSPATH')) {
    exit;
}

class Packages
{
    protected static $packages = array(
        'sdk'
    );

    public static function init()
    {
        return add_action('plugins_loaded', array( __CLASS__, 'onInit' ));
    }

    public static function onInit()
    {
        self::loadPackages();
    }

    public static function packageExists($package): bool
    {
        return file_exists($package);
    }

    public static function getPackage($packageName): string
    {
        return dirname(__DIR__) . '/../packages/' . $packageName;
    }

    public static function loadAutoloadPackages(): bool
    {
        foreach (self::$packages as $packageName) {
            $package = self::getPackage($packageName);
            $autoloader = $package . '/vendor/autoload.php';
            if (! Autoloader::loadAutoload($autoloader)) {
                return false;
            }
        }
        return true;
    }

    protected static function loadPackages()
    {
        foreach (self::$packages as $packageName) {
            $package = self::getPackage($packageName);
            if (! self::packageExists($package)) {
                self::missingPackage($packageName);
                continue;
            }
        }
    }

    protected static function missingPackage($package)
    {
        add_action(
            'admin_notices',
            function () use ($package) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>
                            <?php
                            printf(
                                esc_html__('Missing the Mercado Pago %s package', 'woocommerce-mercadopago'),
                                '<code>' . esc_html($package) . '</code>'
                            );
                            ?>
                        </strong>
                        <br>
                        <?php
                        printf(
                            esc_html__('Your installation of Mercado Pago is incomplete.', 'woocommerce-mercadopago')
                        );
                        ?>
                    </p>
                </div>
                <?php
            }
        );
    }
}
