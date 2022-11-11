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
     * Get link settings from the country configured by default in Woocommerce
     *
     * @return array
     */
    public static function getLinkSettings(): array
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
        $documentationLinks = self::getDocumentationLinks($linkSettings);

        return [];
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
        $baseLink = self::MP_URL_PREFIX . $linkSettings['suffix_url'] . '/developers/' . $linkSettings['translate'];

        return array(
            'link_doc_integration_config' => $baseLink . '/docs/woocommerce/integration-configuration',
            'link_doc_integration_test'   => $baseLink . '/docs/woocommerce/integration-test',
            'link_doc_notifications_ipn'  => $baseLink . '/docs/woocommerce/additional-content/notifications/ipn',
            'link_doc_test_cards'         => $baseLink . '/docs/checkout-api/integration-test/test-cards',
            'link_doc_reasons_refusals'   => $baseLink . '/docs/woocommerce/reasons-refusals',
            'link_doc_dev_program'        => $baseLink . '/developer-program',
        );
    }

    /**
     * @return string
     */
    public static function getMercadoPagoHomeLink(): string
    {
        return self::MP_URL_PREFIX . self::getLinkSettings()['suffix_url'] . '/home';
    }

    /**
     * @return string
     */
    public static function getMercadoPagoCostsLink(): string
    {
        return self::MP_URL_PREFIX . self::getLinkSettings()['suffix_url'] . '/costs-section';
    }

    /**
     * @return string
     */
    public static function getMercadoPagoDevsiteCredentialsLink(): string
    {
        return self::MP_URL_PREFIX . self::getLinkSettings()['suffix_url'] . '/developers/panel/credentials';
    }

    /**
     * @return string
     */
    public static function getMercadoPagoDevsiteLink(): string
    {
        return self::MP_DEVELOPERS_URL;
    }
}
