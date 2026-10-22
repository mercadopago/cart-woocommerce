<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class SdkLocale
{
    private const SPANISH_REGIONS_SUPPORTED_BY_SDK = ['AR', 'CL', 'CO', 'MX', 'PE', 'UY', 'VE'];

    /**
     * Spanish without a region the SDK supports (es_ES, plain es…) falls back to es-MX, the
     * closest the SDK has to neutral Spanish.
     */
    private const NEUTRAL_SPANISH = 'es-MX';

    private const DEFAULT_LOCALE = 'en-US';

    /**
     * Map a WordPress locale (e.g. es_CO) to a locale accepted by the Mercado Pago JS SDK.
     *
     * @param string $storeLocale
     *
     * @return string
     */
    public static function fromStoreLocale(string $storeLocale): string
    {
        $parts    = explode('_', $storeLocale);
        $language = strtolower($parts[0]);
        $region   = strtoupper($parts[1] ?? '');

        if ($language === 'pt') {
            return 'pt-BR';
        }

        if ($language === 'es') {
            return in_array($region, self::SPANISH_REGIONS_SUPPORTED_BY_SDK, true) ? "es-$region" : self::NEUTRAL_SPANISH;
        }

        return self::DEFAULT_LOCALE;
    }
}
