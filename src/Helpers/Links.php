<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Links
{
    /**
     * @var string
     */
    private static $mpUrl = 'https://www.mercadopago.com/';

    /**
     * @var string
     */
    private static $mpUrlPrefix = 'https://www.mercadopago.';

    /**
     * @var string
     */
    private static $mpDevelopersUrl = 'https://developers.mercadopago.com/';

    /**
     * Get link settings from the country configured by default in Woocommerce.
     *
     * @return array
     */
    public static function getLinkSettings(): array
    {
        $country = array(
            'AR' => array(
                'suffix_url' => 'com.ar/',
                'translate'  => 'es',
                'site_id_mp' => 'mla',
            ),
            'BR' => array(
                'suffix_url' => 'com.br/',
                'translate'  => 'pt',
                'site_id_mp' => 'mlb',
            ),
            'CL' => array(
                'suffix_url' => 'cl/',
                'translate'  => 'es',
                'site_id_mp' => 'mlc',
            ),
            'CO' => array(
                'suffix_url' => 'com.co/',
                'translate'  => 'es',
                'site_id_mp' => 'mco',
            ),
            'MX' => array(
                'suffix_url' => 'com.mx/',
                'translate'  => 'es',
                'site_id_mp' => 'mlm',
            ),
            'PE' => array(
                'suffix_url' => 'com.pe/',
                'translate'  => 'es',
                'site_id_mp' => 'mpe',
            ),
            'UY' => array(
                'suffix_url' => 'com.uy/',
                'translate'  => 'es',
                'site_id_mp' => 'mlu',
            ),
        );

        $suffixCountry = strtoupper(Plugin::getWoocommerceDefaultCountry());

        return array_key_exists($suffixCountry, $country) ? $country[ $suffixCountry ] : $country['AR'];
    }

    /**
     * Get all links
     *
     * @return array
     */
    public static function getLinks(): array
    {
        $linkSettings       = self::getLinkSettings();
        $panelLinks         = self::getMercadoPagoLinks($linkSettings);
        $documentationLinks = self::getDocumentationLinks($linkSettings);

        return array_merge($panelLinks, $documentationLinks);
    }

    /**
     * Get documentation links on Mercado Pago Devsite page
     *
     * @param array $linkSettings
     *
     * @return array
     */
    public static function getDocumentationLinks(array $linkSettings): array
    {
        $baseLink = self::$mpUrlPrefix . $linkSettings['suffix_url'] . 'developers/' . $linkSettings['translate'];

        return array(
            'link_doc_integration_config' => $baseLink . '/docs/woocommerce/integration-configuration',
            'link_doc_integration_test'   => $baseLink . '/docs/woocommerce/integration-test',
            'link_doc_dev_program'        => $baseLink . '/developer-program',
            'link_doc_notifications_ipn'  => $baseLink . '/docs/woocommerce/additional-content/notifications/ipn',
            'link_doc_test_cards'         => $baseLink . '/docs/checkout-api/integration-test/test-cards',
            'link_doc_reasons_refusals'   => $baseLink . '/docs/woocommerce/reasons-refusals',
        );
    }

    /**
     * Get documentation links on Mercado Pago Panel page
     *
     * @param array $linkSettings
     *
     * @return array
     */
    public static function getMercadoPagoLinks(array $linkSettings): array
    {
        return array(
            'link_mp_home'        => self::$mpUrlPrefix . $linkSettings['suffix_url'] . 'home',
            'link_mp_costs'       => self::$mpUrlPrefix . $linkSettings['suffix_url'] . 'costs-section',
            'link_mp_credentials' => self::$mpUrl . 'developers/panel/credentials',
            'link_mp_developers'  => self::$mpDevelopersUrl,
        );
    }
}
