<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Device
{
    /**
     * Verify if device is mobile
     *
     * @return bool
     */
    public static function isMobile(): bool
    {
        return wp_is_mobile();
    }

    /**
     * Get device product id
     *
     * @return string
     */
    public static function getDeviceProductId(): string
    {
        return self::isMobile() ? MP_PRODUCT_ID_MOBILE : MP_PRODUCT_ID_DESKTOP;
    }
}
