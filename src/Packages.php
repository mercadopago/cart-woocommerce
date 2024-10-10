<?php

namespace MercadoPago\Woocommerce;

if (!defined('ABSPATH')) {
    exit;
}

class Packages
{
    /**
     * @var array
     */
    protected static $packages = [
        'sdk'
    ];

    /**
     * Init Packages
     *
     * @return bool
     */
    public static function init(): bool
    {
        return self::loadPackages();
    }

    /**
     * Check if package exists
     *
     * @param string $package
     *
     * @return bool
     */
    public static function packageExists(string $package): bool
    {
        return file_exists($package);
    }

    /**
     * Get package path
     *
     * @param string $packageName
     *
     * @return string
     */
    public static function getPackage(string $packageName): string
    {
        return dirname(__FILE__) . '/../packages/' . $packageName;
    }

    /**
     * Start loading packages
     *
     * @return bool
     */
    protected static function loadPackages(): bool
    {
        foreach (self::$packages as $packageName) {
            $package = self::getPackage($packageName);
            if (!self::packageExists($package)) {
                self::missingPackageNotice($packageName);
                return false;
            }

            $autoloader = $package . '/vendor/autoload.php';
            if (!Autoloader::loadAutoload($autoloader)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Show package missing notice
     *
     * @param string $package
     *
     * @return void
     */
    protected static function missingPackageNotice(string $package): void
    {
        add_action('admin_notices', function () use ($package) {
            include dirname(__FILE__) . '/../templates/admin/notices/miss-package.php';
        });
    }
}
