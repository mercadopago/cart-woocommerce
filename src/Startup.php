<?php

namespace MercadoPago\Woocommerce;

if (!defined('ABSPATH')) {
    exit;
}

class Startup
{
    /**
     * Defines package missing notice type
     */
    protected const PACKAGE_TYPE = "package";

    /**
     * Defines autoload missing notice type
     */
    protected const AUTOLOAD_TYPE = "autoload";

    /**
     * Defines needed project packages
     */
    protected static array $packages = [
        'sdk/'
    ];

    /**
     * Verify if plugin has it's packages and autoloader file
     *
     * @return bool
     */
    public static function available(): bool
    {
        return self::haveAutoload() && self::havePackages();
    }

    /**
     * Check's if autoload file is present and is readable
     *
     * @return bool
     */
    protected static function haveAutoload(): bool
    {
        $file = dirname(__FILE__) . '/../vendor/autoload.php';

        if (!is_file($file) && !is_readable($file)) {
            self::missingNotice(self::AUTOLOAD_TYPE, $file);
            return false;
        }

        return true;
    }

    /**
     * Check's if packages folder exists
     *
     * @return bool
     */
    protected static function havePackages(): bool
    {
        foreach (self::$packages as $package) {
            $package = dirname(__FILE__) . '/../packages/' . $package;

            if (!is_dir($package)) {
                self::missingNotice(self::PACKAGE_TYPE, $package);
                return false;
            }
        }

        return true;
    }

    /**
     * Show missing notice for package's and autoload
     *
     * @param string $type
     * @param string $path
     *
     * @return void
     */
    protected static function missingNotice(string $type, $path): void
    {
        add_action('admin_notices', function () use ($type, $path) {
            switch ($type) {
                case self::PACKAGE_TYPE:
                    include dirname(__FILE__) . '/../templates/admin/notices/miss-package.php';
                    break;
                case self::AUTOLOAD_TYPE:
                    include dirname(__FILE__) . '/../templates/admin/notices/miss-autoload.php';
                    break;
            }
        });
    }
}
