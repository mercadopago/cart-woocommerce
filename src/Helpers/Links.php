<?php

namespace MercadoPago\Woocommerce\Helpers;

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
        $adminLinks         = self::getAdminLinks();
        $storeLinks         = self::getStoreLinks();

        return array_merge_recursive($mercadoPagoLinks, $documentationLinks, $adminLinks, $storeLinks);
    }

    /**
     * Get links settings from the country configured by default in Woocommerce
     *
     * @return array
     */
    private static function getLinksSettings(): array
    {
        $country = Country::getPluginDefaultCountry();

        $settings = [
            'AR' => [
                'translate'  => 'es',
                'suffix_url' => '.com.ar',
            ],
            'BR' => [
                'translate'  => 'pt',
                'suffix_url' => '.com.br',
            ],
            'CL' => [
                'translate'  => 'es',
                'suffix_url' => '.cl',
            ],
            'CO' => [
                'translate'  => 'es',
                'suffix_url' => '.com.co',
            ],
            'MX' => [
                'translate'  => 'es',
                'suffix_url' => '.com.mx',
            ],
            'PE' => [
                'translate'  => 'es',
                'suffix_url' => '.com.pe',
            ],
            'UY' => [
                'translate'  => 'es',
                'suffix_url' => '.com.uy',
            ],
        ];

        return array_key_exists($country, $settings) ? $settings[$country] : $settings['AR'];
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

    /**
     * Get admin links
     *
     * @return array
     */
    private static function getAdminLinks(): array
    {
        return [
            'admin_settings_page' => admin_url('admin.php?page=mercadopago-settings'),
            'admin_gateways_list' => admin_url('admin.php?page=wc-settings&tab=checkout'),
        ];
    }

    /**
     * Get store links
     *
     * @return array
     */
    private static function getStoreLinks(): array
    {
        return [
            'store_visit' => get_permalink(wc_get_page_id('shop')),
        ];
    }
}
