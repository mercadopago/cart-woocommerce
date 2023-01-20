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
     * @var Country
     */
    private $country;

    /**
     * Links constructor
     */
    public function __construct(Country $country)
    {
        $this->country = $country;
    }

    /**
     * Get all links
     *
     * @return array
     */
    public function getLinks(): array
    {
        $country            = $this->country->getPluginDefaultCountry();
        $countryConfig      = $this->country->getCountryConfigs($country);

        $mercadoPagoLinks   = $this->getMercadoPagoLinks($countryConfig);
        $documentationLinks = $this->getDocumentationLinks($countryConfig);
        $adminLinks         = $this->getAdminLinks();
        $storeLinks         = $this->getStoreLinks();

        return array_merge_recursive($mercadoPagoLinks, $documentationLinks, $adminLinks, $storeLinks);
    }

    /**
     * Get documentation links on Mercado Pago Devsite page
     *
     * @param array $countryConfig
     *
     * @return array
     */
    private function getDocumentationLinks(array $countryConfig): array
    {
        $baseLink = self::MP_URL_PREFIX . $countryConfig['suffix_url'] . '/developers/' . $countryConfig['translate'];

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
     * @param array $countryConfig
     *
     * @return array
     */
    private function getMercadoPagoLinks(array $countryConfig): array
    {
        return [
            'mercadopago_home'                 => self::MP_URL_PREFIX . $countryConfig['suffix_url'] . '/home',
            'mercadopago_costs'                => self::MP_URL_PREFIX . $countryConfig['suffix_url'] . '/costs-section',
            'mercadopago_test_user'            => self::MP_URL . '/developers/panel/test-users',
            'mercadopago_credentials'          => self::MP_URL . '/developers/panel/credentials',
            'mercadopago_developers'           => self::MP_DEVELOPERS_URL,
            'mercadopago_pix'                  => self::MP_URL_PREFIX . '.com.br' . '/pix',
            'mercadopago_support'              => self::MP_URL_PREFIX . $countryConfig['suffix_url'] . '/developers/' . $countryConfig['translate'] . '/support/contact',
            'mercadopago_terms_and_conditions' => self::MP_URL_PREFIX . $countryConfig['suffix_url'] . $countryConfig['help'] . $countryConfig['terms_and_conditions'],
            'mercadopago_pix_config'            => self::MP_URL_PREFIX . '.com.br' . '/stop/pix?url=https%3A%2F%2Fwww.mercadopago.com.br%2Fadmin-pix-keys%2Fmy-keys&authentication_mode=required',
        ];
    }

    /**
     * Get admin links
     *
     * @return array
     */
    private function getAdminLinks(): array
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
    private function getStoreLinks(): array
    {
        return [
            'store_visit' => get_permalink(wc_get_page_id('shop')),
        ];
    }
}
