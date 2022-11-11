<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Configs\Seller;

if (!defined('ABSPATH')) {
    exit;
}

final class Links
{
    /**
     * @const
     */
    private const MP_URL = 'https://www.mercadopago.com';

    /**
     * @const
     */
    private const MP_URL_PREFIX = 'https://www.mercadopago';

    /**
     * @const
     */
    private const MP_DEVELOPERS_URL = 'https://developers.mercadopago.com';

    /**
     * Get all links
     *
     * @return array
     */
    public static function getLinks(): array
    {
        $linksSettings      = self::getLinksSettings();
        $mercadoPagoLinks   = self::getMercadoPagoLinks($linksSettings);
        $documentationLinks = self::getDocumentationLinks($linksSettings);
        $storeLinks         = self::getStoreLinks();

        return array_merge_recursive($mercadoPagoLinks, $documentationLinks, $storeLinks);
    }

    /**
     * Get links settings from the country configured by default in Woocommerce
     *
     * @return array
     */
    private static function getLinksSettings(): array
    {
        $country = [
            'AR' => [
                'translate'  => 'es',
                'site_id_mp' => 'mla',
                'suffix_url' => '.com.ar',
            ],
            'BR' => [
                'translate'  => 'pt',
                'site_id_mp' => 'mlb',
                'suffix_url' => '.com.br',
            ],
            'CL' => [
                'translate'  => 'es',
                'site_id_mp' => 'mlc',
                'suffix_url' => '.cl',
            ],
            'CO' => [
                'translate'  => 'es',
                'site_id_mp' => 'mco',
                'suffix_url' => '.com.co',
            ],
            'MX' => [
                'translate'  => 'es',
                'site_id_mp' => 'mlm',
                'suffix_url' => '.com.mx',
            ],
            'PE' => [
                'translate'  => 'es',
                'site_id_mp' => 'mpe',
                'suffix_url' => '.com.pe',
            ],
            'UY' => [
                'translate'  => 'es',
                'site_id_mp' => 'mlu',
                'suffix_url' => '.com.uy',
            ],
        ];

        $siteId        = (Seller::getInstance())->getSiteId();
        $suffixCountry = self::siteIdToCountry($siteId);

        if ((Seller::getInstance())->getSiteId()) {
            $suffixCountry = self::siteIdToCountry($siteId);
        }

        return array_key_exists($suffixCountry, $country) ? $country[$suffixCountry] : $country['AR'];
    }

    /**
     * Get documentation links on Mercado Pago Devsite page
     *
     * @param array $linkSettings
     *
     * @return array
     */
    private static function getDocumentationLinks(array $linkSettings): array
    {
        $baseLink = self::MP_URL_PREFIX . $linkSettings['suffix_url'] . '/developers/' . $linkSettings['translate'];

        return [
            'docs_developers_program'       => $baseLink . '/developer-program',
            'docs_test_cards'               => $baseLink . '/docs/checkout-api/integration-test/test-cards',
            'docs_reasons_refusals'         => $baseLink . '/docs/woocommerce/reasons-refusals',
            'docs_ipn_notification'         => $baseLink . '/docs/woocommerce/additional-content/notifications/ipn',
            'docs_integration_test'         => $baseLink . '/docs/woocommerce/integration-test',
            'docs_integration_config'       => $baseLink . '/docs/woocommerce/integration-configuration',
            'docs_integration_introduction' => $baseLink . '/docs/woocommerce/introduction',
        ];
    }

    /**
     * Get documentation links on Mercado Pago Panel page
     *
     * @param array $linkSettings
     *
     * @return array
     */
    private static function getMercadoPagoLinks(array $linkSettings): array
    {
        return [
            'mercadopago_home'        => self::MP_URL_PREFIX . $linkSettings['suffix_url'] . '/home',
            'mercadopago_costs'       => self::MP_URL_PREFIX . $linkSettings['suffix_url'] . '/costs-section',
            'mercadopago_test_user'   => self::MP_URL . '/developers/panel/test-users',
            'mercadopago_credentials' => self::MP_URL . '/developers/panel/credentials',
            'mercadopago_developers'  => self::MP_DEVELOPERS_URL,
        ];
    }

    private static function getStoreLinks(): array
    {
        return [
            'store_visit' => get_permalink(wc_get_page_id('shop')),
        ];
    }

    /**
     * @param $siteId
     *
     * @return string
     */
    private static function siteIdToCountry($siteId): string
    {
        $siteIdToCountry = [
            'MLA' => 'AR',
            'MLB' => 'BR',
            'MLM' => 'MX',
            'MLC' => 'CL',
            'MLU' => 'UY',
            'MCO' => 'CO',
            'MPE' => 'PE',
        ];

        return array_key_exists($siteId, $siteIdToCountry) ? $siteIdToCountry[$siteId] : 'AR';
    }
}
