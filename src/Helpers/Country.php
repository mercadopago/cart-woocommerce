<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\Gateways\BasicGateway;
use MercadoPago\Woocommerce\Gateways\CreditsGateway;
use MercadoPago\Woocommerce\Gateways\CustomGateway;
use MercadoPago\Woocommerce\Gateways\PixGateway;
use MercadoPago\Woocommerce\Gateways\PseGateway;
use MercadoPago\Woocommerce\Gateways\TicketGateway;
use MercadoPago\Woocommerce\Gateways\YapeGateway;

if (!defined('ABSPATH')) {
    exit;
}

class Country
{
    public const SITE_ID_MLA = 'MLA';

    public const SITE_ID_MLB = 'MLB';

    public const SITE_ID_MLM = 'MLM';

    public const SITE_ID_MLC = 'MLC';

    public const SITE_ID_MLU = 'MLU';

    public const SITE_ID_MCO = 'MCO';

    public const SITE_ID_MPE = 'MPE';

    public const COUNTRY_CODE_MLA = 'AR';

    public const COUNTRY_CODE_MLB = 'BR';

    public const COUNTRY_CODE_MLM = 'MX';

    public const COUNTRY_CODE_MLC = 'CL';

    public const COUNTRY_CODE_MLU = 'UY';

    public const COUNTRY_CODE_MCO = 'CO';

    public const COUNTRY_CODE_MPE = 'PE';

    private Seller $seller;

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
     */
    public static function siteIdToCountry(string $siteId): string
    {
        // TODO(PHP8.2): Use match
        return [
            self::SITE_ID_MLA => self::COUNTRY_CODE_MLA,
            self::SITE_ID_MLB => self::COUNTRY_CODE_MLB,
            self::SITE_ID_MLM => self::COUNTRY_CODE_MLM,
            self::SITE_ID_MLC => self::COUNTRY_CODE_MLC,
            self::SITE_ID_MLU => self::COUNTRY_CODE_MLU,
            self::SITE_ID_MCO => self::COUNTRY_CODE_MCO,
            self::SITE_ID_MPE => self::COUNTRY_CODE_MPE,
        ][$siteId] ?? self::COUNTRY_CODE_MLA;
    }

    /**
     * Convert Mercado Pago country to siteId
     */
    public static function countryToSiteId(string $country): string
    {
        // TODO(PHP8.2): Use match
        return [
            self::COUNTRY_CODE_MLA => self::SITE_ID_MLA,
            self::COUNTRY_CODE_MLB => self::SITE_ID_MLB,
            self::COUNTRY_CODE_MLM => self::SITE_ID_MLM,
            self::COUNTRY_CODE_MLC => self::SITE_ID_MLC,
            self::COUNTRY_CODE_MLU => self::SITE_ID_MLU,
            self::COUNTRY_CODE_MCO => self::SITE_ID_MCO,
            self::COUNTRY_CODE_MPE => self::SITE_ID_MPE,
        ][$country] ?? '';
    }

    /**
     * Get Wordpress default language configured.
     *
     * @return string
     */
    private function getWordpressLanguage(): string
    {
        return get_locale();
    }

    /**
     * Get languages supported by plugin.
     *
     * @return array
     */
    private function getLanguagesSupportedByPlugin(): array
    {
        return [
            'es_AR',
            'es_CL',
            'es_CO',
            'es_MX',
            'es_PE',
            'es_UY',
            'pt_BR',
            'en_US',
            'es_ES'
        ];
    }

    /**
     * Verify if WP selected lang is supported by plugin.
     *
     * @return bool
     */
    public function isLanguageSupportedByPlugin(): bool
    {
        $languages = $this->getLanguagesSupportedByPlugin();
        $language_code = $this->getWordpressLanguage();
        return in_array($language_code, $languages);
    }

    /**
     * Get Woocommerce default country configured
     *
     * @return string
     */
    public static function getWoocommerceDefaultCountry(): string
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

        if ($siteId) {
            return self::siteIdToCountry($siteId);
        }

        return self::getWoocommerceDefaultCountry();
    }

    /**
     * Country Configs
     *
     * @return array
     */
    public function getCountryConfigs(): array
    {
        // TODO(PHP8.2): Use match
        return [
            self::COUNTRY_CODE_MLB => [
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
            self::COUNTRY_CODE_MLC => [
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
            self::COUNTRY_CODE_MCO => [
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
            self::COUNTRY_CODE_MLM => [
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
            self::COUNTRY_CODE_MPE => [
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
            self::COUNTRY_CODE_MLU => [
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
        ][$this->getPluginDefaultCountry()] ?? [
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
        ];
    }

    public function getGatewayOrder(): array
    {
        // TODO(PHP8.2): Use match
        return [
            static::COUNTRY_CODE_MLB => [
                CustomGateway::class,
                PixGateway::class,
                TicketGateway::class,
                BasicGateway::class,
                CreditsGateway::class,
            ],
            static::COUNTRY_CODE_MLA => [
                BasicGateway::class,
                CustomGateway::class,
                TicketGateway::class,
                CreditsGateway::class,
            ],
            static::COUNTRY_CODE_MLU => [
                BasicGateway::class,
                CustomGateway::class,
                TicketGateway::class,
            ],
            static::COUNTRY_CODE_MLC => [
                BasicGateway::class,
                CustomGateway::class,
                TicketGateway::class,
            ],
            static::COUNTRY_CODE_MLM => [
                BasicGateway::class,
                CustomGateway::class,
                TicketGateway::class,
                CreditsGateway::class,
            ],
            static::COUNTRY_CODE_MCO => [
                BasicGateway::class,
                CustomGateway::class,
                TicketGateway::class,
                PseGateway::class,
            ],
            static::COUNTRY_CODE_MPE => [
                CustomGateway::class,
                YapeGateway::class,
                TicketGateway::class,
                BasicGateway::class,
            ],
        ][$this->getPluginDefaultCountry()] ?? [ //default gateways and orders
            BasicGateway::class,
            CustomGateway::class,
            CreditsGateway::class,
            PixGateway::class,
            PseGateway::class,
            TicketGateway::class,
        ];
    }
}
