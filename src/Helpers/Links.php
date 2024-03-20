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
     * @var Url
     */
    private $url;

    /**
     * Links constructor
     *
     * @param Country $country
     * @param Url $url
     */
    public function __construct(Country $country, Url $url)
    {
        $this->country = $country;
        $this->url     = $url;
    }

    /**
     * Get all links
     *
     * @return array
     */
    public function getLinks(): array
    {
        $countryConfig = $this->country->getCountryConfigs();

        return array_merge_recursive(
            $this->getDocumentationLinks($countryConfig),
            $this->getMercadoPagoLinks($countryConfig),
            $this->getCreditsLinks($countryConfig),
            $this->getAdminLinks(),
            $this->getStoreLinks(),
            $this->getWordpressLinks()
        );
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
            'docs_test_cards'               => $baseLink . '/docs/checkout-api/additional-content/your-integrations/test/cards',
            'docs_integration_credentials'  => $baseLink . '/docs/checkout-api/additional-content/your-integrations/credentials',
            'docs_reasons_refusals'         => $baseLink . '/docs/woocommerce/reasons-refusals',
            'docs_ipn_notification'         => $baseLink . '/docs/woocommerce/integration-configuration/notifications',
            'docs_integration_test'         => $baseLink . '/docs/woocommerce/integration-test',
            'docs_integration_config'       => $baseLink . '/docs/woocommerce/integration-configuration',
            'docs_integration_introduction' => $baseLink . '/docs/woocommerce/introduction',
            'reasons_refusals'              => $baseLink . '/docs/woocommerce/reasons-refusals',
            'docs_support_faq'              => $baseLink . '/support/26097',
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
            'mercadopago_credentials'          => self::MP_URL_PREFIX . $countryConfig['suffix_url'] .  '/settings/account/credentials',
            'mercadopago_developers'           => self::MP_DEVELOPERS_URL,
            'mercadopago_pix'                  => self::MP_URL_PREFIX . '.com.br/ferramentas-para-vender/aceitar-pix',
            'mercadopago_debts'                => self::MP_URL_PREFIX . '.com.ar/cuotas',
            'mercadopago_support'              => self::MP_URL_PREFIX . $countryConfig['suffix_url'] . '/developers/' . $countryConfig['translate'] . '/support/contact',
            'mercadopago_terms_and_conditions' => self::MP_URL_PREFIX . $countryConfig['suffix_url'] . $countryConfig['help'] . $countryConfig['terms_and_conditions'],
            'mercadopago_pix_config'           => self::MP_URL_PREFIX . '.com.br/stop/pix?url=https://www.mercadopago.com.br/admin-pix-keys/my-keys?authentication_mode=required',
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
            'store_visit' => $this->url->getBaseUrl(),
        ];
    }

    /**
     * Get store links
     *
     * @param array $countryConfig
     *
     * @return array
     */
    private function getCreditsLinks(array $countryConfig): array
    {
        $siteId = $countryConfig['site_id'];

        $country_links = [
            'MLA' => [
                'credits_blog_link' => 'https://vendedores.mercadolibre.com.ar/nota/impulsa-tus-ventas-y-alcanza-mas-publico-con-mercado-credito',
                'credits_faq_link'  => 'https://www.mercadopago.com.ar/help/19040'
            ],
            'MLM' => [
                'credits_blog_link' => 'https://vendedores.mercadolibre.com.mx/nota/impulsa-tus-ventas-y-alcanza-a-mas-clientes-con-mercado-credito',
                'credits_faq_link'  => 'https://www.mercadopago.com.mx/help/19040'
            ],
            'MLB' => [
                'credits_blog_link' => 'https://conteudo.mercadopago.com.br/parcelamento-via-boleto-bancario-no-mercado-pago-seus-clientes-ja-podem-solicitar',
                'credits_faq_link'  => 'https://www.mercadopago.com.br/help/19040'
            ],
        ];

        return array_key_exists($siteId, $country_links) ? $country_links[$siteId] : $country_links['MLA'];
    }

    /**
     * Get wordpress links
     *
     * @return array
     */
    private function getWordpressLinks(): array
    {
        return [
            'wordpress_review_link' => 'https://wordpress.org/support/plugin/woocommerce-mercadopago/reviews/?filter=5#new-post',
        ];
    }
}
