<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;

if (!defined('ABSPATH')) {
    exit;
}

final class Country
{
    /**
     * @const
     */
    public const SITE_ID_MLA = 'MLA';

    /**
     * @const
     */
    public const SITE_ID_MLB = 'MLB';

    /**
     * @const
     */
    public const SITE_ID_MLM = 'MLM';

    /**
     * @const
     */
    public const SITE_ID_MLC = 'MLC';

    /**
     * @const
     */
    public const SITE_ID_MLU = 'MLU';

    /**
     * @const
     */
    public const SITE_ID_MCO = 'MCO';

    /**
     * @const
     */
    public const SITE_ID_MPE = 'MPE';

    /**
     * @const
     */
    public const COUNTRY_SUFFIX_MLA = 'AR';

    /**
     * @const
     */
    public const COUNTRY_SUFFIX_MLB = 'BR';

    /**
     * @const
     */
    public const COUNTRY_SUFFIX_MLM = 'MX';

    /**
     * @const
     */
    public const COUNTRY_SUFFIX_MLC = 'CL';

    /**
     * @const
     */
    public const COUNTRY_SUFFIX_MLU = 'UY';

    /**
     * @const
     */
    public const COUNTRY_SUFFIX_MCO = 'CO';

    /**
     * @const
     */
    public const COUNTRY_SUFFIX_MPE = 'PE';

    /**
     * @var Seller
     */
    private $seller;

    /**
     * Country constructor
     *
     * @param Seller $seller
     */
    public function __construct(Seller $seller)
    {
        $this->seller = $seller;
    }

    /**
     * Convert Mercado Pago site_id to Woocommerce country
     *
     * @param $siteId
     *
     * @return string
     */
    public function siteIdToCountry($siteId): string
    {
        $siteIdToCountry = [
            self::SITE_ID_MLA => self::COUNTRY_SUFFIX_MLA,
            self::SITE_ID_MLB => self::COUNTRY_SUFFIX_MLB,
            self::SITE_ID_MLM => self::COUNTRY_SUFFIX_MLM,
            self::SITE_ID_MLC => self::COUNTRY_SUFFIX_MLC,
            self::SITE_ID_MLU => self::COUNTRY_SUFFIX_MLU,
            self::SITE_ID_MCO => self::COUNTRY_SUFFIX_MCO,
            self::SITE_ID_MPE => self::COUNTRY_SUFFIX_MPE,
        ];

        return array_key_exists($siteId, $siteIdToCountry)
            ? $siteIdToCountry[$siteId]
            : $siteIdToCountry[self::SITE_ID_MLA];
    }

    /**
     * Get Woocommerce default country configured
     *
     * @return string
     */
    public function getWoocommerceDefaultCountry(): string
    {
        $wcCountry = get_option('woocommerce_default_country', '');

        if ($wcCountry !== '') {
            $wcCountry = strlen($wcCountry) > 2 ? substr($wcCountry, 0, 2) : $wcCountry;
        }

        return $wcCountry;
    }

    /**
     * Get Plugin default country
     *
     * @return string
     */
    public function getPluginDefaultCountry(): string
    {
        $siteId  = $this->seller->getSiteId();
        $country = $this->getWoocommerceDefaultCountry();

        if ($siteId) {
            $country = $this->siteIdToCountry($siteId);
        }

        return $country;
    }

    /**
     * Country Configs
     *
     * @return array
     */
    public function getCountryConfigs(): array
    {
        $countrySuffix = $this->getPluginDefaultCountry();

        $configs = [
            self::COUNTRY_SUFFIX_MLA => [
                'site_id'              => self::SITE_ID_MLA,
                'sponsor_id'           => 208682286,
                'currency'             => 'ARS',
                'zip_code'             => '3039',
                'currency_symbol'      => '$',
                'intl'                 => 'es-AR',
                'translate'            => 'es',
                'suffix_url'           => '.com.ar',
                'help'                 => '/ayuda',
                'terms_and_conditions' => '/terminos-y-politicas_194',
            ],
            self::COUNTRY_SUFFIX_MLB => [
                'site_id'              => self::SITE_ID_MLB,
                'sponsor_id'           => 208686191,
                'currency'             => 'BRL',
                'zip_code'             => '01310924',
                'currency_symbol'      => 'R$',
                'intl'                 => 'pt-BR',
                'translate'            => 'pt',
                'suffix_url'           => '.com.br',
                'help'                 => '/ajuda',
                'terms_and_conditions' => '/termos-e-politicas_194',
            ],
            self::COUNTRY_SUFFIX_MLC => [
                'site_id'              => self::SITE_ID_MLC,
                'sponsor_id'           => 208690789,
                'currency'             => 'CLP',
                'zip_code'             => '7591538',
                'currency_symbol'      => '$',
                'intl'                 => 'es-CL',
                'translate'            => 'es',
                'suffix_url'           => '.cl',
                'help'                 => '/ayuda',
                'terms_and_conditions' => '/terminos-y-politicas_194',
            ],
            self::COUNTRY_SUFFIX_MCO => [
                'site_id'              => self::SITE_ID_MCO,
                'sponsor_id'           => 208687643,
                'currency'             => 'COP',
                'zip_code'             => '110111',
                'currency_symbol'      => '$',
                'intl'                 => 'es-CO',
                'translate'            => 'es',
                'suffix_url'           => '.com.co',
                'help'                 => '/ayuda',
                'terms_and_conditions' => '/terminos-y-politicas_194',
            ],
            self::COUNTRY_SUFFIX_MLM => [
                'site_id'              => self::SITE_ID_MLM,
                'sponsor_id'           => 208692380,
                'currency'             => 'MXN',
                'zip_code'             => '11250',
                'currency_symbol'      => '$',
                'intl'                 => 'es-MX',
                'translate'            => 'es',
                'suffix_url'           => '.com.mx',
                'help'                 => '/ayuda',
                'terms_and_conditions' => '/terminos-y-politicas_194',
            ],
            self::COUNTRY_SUFFIX_MPE => [
                'site_id'              => self::SITE_ID_MPE,
                'sponsor_id'           => 216998692,
                'currency'             => 'PEN',
                'zip_code'             => '15074',
                'currency_symbol'      => '$',
                'intl'                 => 'es-PE',
                'translate'            => 'es',
                'suffix_url'           => '.com.pe',
                'help'                 => '/ayuda',
                'terms_and_conditions' => '/terminos-y-politicas_194',
            ],
            self::COUNTRY_SUFFIX_MLU => [
                'site_id'              => self::SITE_ID_MLU,
                'sponsor_id'           => 243692679,
                'currency'             => 'UYU',
                'zip_code'             => '11800',
                'currency_symbol'      => '$',
                'intl'                 => 'es-UY',
                'translate'            => 'es',
                'suffix_url'           => '.com.uy',
                'help'                 => '/ayuda',
                'terms_and_conditions' => '/terminos-y-politicas_194',
            ]
        ];

        return array_key_exists($countrySuffix, $configs)
            ? $configs[$countrySuffix]
            : $configs[self::COUNTRY_SUFFIX_MLA];
    }
}
