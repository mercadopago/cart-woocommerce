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

    public static function init(): bool
    {
        return self::loadPackages();
    }

    public static function packageExists($package): bool
    {
        return file_exists($package);
    }

    public static function getPackage($packageName): string
    {
        return dirname(__FILE__) . '/../packages/' . $packageName;
    }

    protected static function loadPackages(): bool
    {
        foreach (self::$packages as $packageName) {
            $package = self::getPackage($packageName);
            if (!self::packageExists($package)) {
                self::missingPackage($packageName);
                return false;
            }

            $autoloader = $package . '/vendor/autoload.php';
            if (!Autoloader::loadAutoload($autoloader)) {
                return false;
            }
        }

        return true;
    }

    protected static function missingPackage($package): void
    {
        add_action('admin_notices', function () use ($package) {
            include dirname(__FILE__) . '/../templates/admin/notices/miss-package.php';
        });
    }
}
