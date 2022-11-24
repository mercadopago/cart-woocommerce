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
    private function siteIdToCountry($siteId): string
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

        return array_key_exists($siteId, $siteIdToCountry) ? $siteIdToCountry[$siteId] : 'AR';
    }

    /**
     * Get Woocommerce default country configured
     *
     * @return string
     */
    public function getWoocommerceDefaultCountry(): string
    {
        $wcCountry = get_option('woocommerce_default_country', '');

        if ('' !== $wcCountry) {
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
}
