<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Translations\AdminTranslations;
use MercadoPago\Woocommerce\Translations\StoreTranslations;

if (!defined('ABSPATH')) {
    exit;
}

class I18n
{
    private static AdminTranslations $admin;
    private static StoreTranslations $store;

    public static function boot(AdminTranslations $admin, StoreTranslations $store)
    {
        static::$admin = $admin;
        static::$store = $store;
    }

    /**
     * Get a translations value using dot notation
     *
     * Example:
     * ```php
     *  I18n::get('store');
     *  // Equals:
     *  WoocommerceMercadoPago->storeTranslations;
     *
     *  I18n::get('admin');
     *  // Equals:
     *  WoocommerceMercadoPago->adminTranslations;
     *
     *  I18n::get('admin.credentialsLinkComponents');
     *  // Equals:
     *  WoocommerceMercadoPago->adminTranslations->credentialsLinkComponents;
     *
     *  I18n::get('admin.credentialsLinkComponents.select_country');
     *  // Equals:
     *  WoocommerceMercadoPago->adminTranslations->credentialsLinkComponents['select_country'];
     * ```
     */
    public static function get(string $path)
    {
        $keys  = explode('.', $path);
        $first = array_shift($keys);
        $value = static::$$first;

        foreach ($keys as $key) {
            $value = is_array($value) ? $value[$key] : $value->$key;
        }

        return $value;
    }
}
