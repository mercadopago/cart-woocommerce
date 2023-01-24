<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsGateway extends AbstractGateway implements MercadoPagoGatewayInterface
{
    /**
     * ID
     *
     * @const
     */
    const ID = 'woo-mercado-pago-credits';

    /**
     * @const
     */
    public const CHECKOUT_NAME = 'checkout-credits';

    /**
     * CreditsGateway constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->translations = $this->mercadopago->storeTranslations->checkoutCredits;

        $this->id                 = self::ID;
        $this->icon               = $this->mercadopago->plugin->getGatewayIcon('icon-mp');
        $this->title              = $this->translations['gateway_title'];
        $this->description        = $this->translations['gateway_description'];
        $this->method_title       = $this->translations['method_title'];
        $this->method_description = $this->description;
        $this->has_fields         = true;
        $this->supports           = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();
        $this->payment_scripts($this->id);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
    }

    /**
     * Get checkout name
     *
     * @return string
     */
    public function getCheckoutName(): string
    {
        return self::CHECKOUT_NAME;
    }

    /**
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'config_header' => [
                'type'        => 'mp_config_title',
                'title'       => $this->translations['config_header_title'],
                'description' => $this->translations['config_header_desc'],
            ],
            'enabled'       => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->translations['config_enabled_title'],
                'subtitle'     => $this->translations['config_enabled_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->translations['config_enabled_enabled'],
                    'disabled' => $this->translations['config_enabled_disabled'],
                ],
                'after_toggle' => $this->get_ckeckout_visualization(),
            ],
            'title'         => [
                'type'        => 'text',
                'title'       => 'Title in the store Checkout',
                'description' => 'Change the display text in Checkout, maximum characters: 85',
                'default'     => $this->title,
                'desc_tip'    => 'The text inserted here will not be translated to other languages',
                'class'       => 'limit-title-max-length',
            ],
            'description'   => [
                'type'        => 'text',
                'title'       => 'Description',
                'description' => '',
                'default'     => $this->method_description,
                'class'       => 'mp-hidden-field-description',
            ],
            'currency_conversion'   => [
                'type'         => 'mp_toggle_switch',
                'title'        => 'Convert Currency',
                'subtitle'     => 'Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.',
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => 'Currency convertion is <b>enabled</b>.',
                    'disabled' => 'Currency convertion is <b>disabled</b>.',
                ],
            ],
            'credits_banner'   => [
                'type'     => 'mp_toggle_switch',
                'title'    => 'Inform your customers about the option of paying in installments without card',
                'subtitle' => sprintf(
                    /* translators: %s link to Mercado Credits blog */
                    '<b>By activating the installments without card component</b>, you increase your chances of selling. To learn more, please check the <a href="%s" target="blank">technical guideline</a>.',
                    'https://vendedores.mercadolibre.com.ar/nota/impulsa-tus-ventas-y-alcanza-mas-publico-con-mercado-credito'
                ),
                'default'  => 'no',
                'descriptions' => array(
                    'enabled'  => 'The installments without card component is <b>active</b>.',
                    'disabled' => 'The installments without card component is <b>inactive</b>.',
                ),
                'after_toggle' => $this->get_credits_info_template()
            ],
            'advanced_configuration_title'   => [
                'type'        => 'title',
                'title'       => 'Advanced settings',
                'class'       => 'mp-subtitle-body',
            ],
            'advanced_configuration_description'   => [
                'type'        => 'title',
                'title'       => 'Edit these advanced fields only when you want to modify the preset values.',
                'class'       => 'mp-small-text',
            ],
        ];
    }

    /**
     * Example Banner Credits Admin
     *
     * @param $siteId
     *
     * @return string
     */
    private function get_ckeckout_visualization()
    {
        $siteId = strtoupper($this->mercadopago->seller->getSiteId());
        return $this->mercadopago->template->getWoocommerceTemplateHtml(
            'admin/components/credits-checkout-example.php',
            array(
                'title'     => 'Checkout visualization',
                'subtitle'  => 'Check below how this feature will be displayed to your customers:',
                'footer'    => 'Checkout Preview',
                'pill_text' => 'PREVIEW',
                'image'     => plugins_url($this->get_mercado_credits_preview_image($siteId), plugin_dir_path(__FILE__)),
            ),
        );
    }

    /**
     * Get image path for mercado credits checkout preview
     *
     * @param $siteId
     *
     * @return string
     */
    private function get_mercado_credits_preview_image($siteId)
    {
        $siteIds = [
            'mla' => 'MLA_',
            'mlb' => 'MLB_',
            'mlm' => 'MLM_',
        ];

        $prefix = isset($siteIds[$siteId]) ? $siteIds[$siteId] : '';

        return sprintf('../assets/images/checkouts/credits/%scheckout_preview.jpg', $prefix);
    }

    /**
     * Example Banner Credits Admin
     *
     * @return string
     */
    private function get_credits_info_template()
    {
        $siteId = strtoupper($this->mercadopago->seller->getSiteId());
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        $this->mercadopago->scripts->registerAdminStyle(
            'mp_info_admin_credits_style',
            plugins_url('../assets/css/admin/credits/example-info' . $suffix . '.css', plugin_dir_path(__FILE__)),
        );

        $this->mercadopago->scripts->registerAdminScript(
            'mp_info_admin_credits_script',
            plugins_url('../assets/js/admin/credits/example-info' . $suffix . '.js', plugin_dir_path(__FILE__)),
            array(
                'computerBlueIcon'  => plugins_url('../assets/images/checkouts/credits/desktop-blue-icon.png', plugin_dir_path(__FILE__)),
                'computerGrayIcon'  => plugins_url('../assets/images/checkouts/credits/desktop-gray-icon.png', plugin_dir_path(__FILE__)),
                'cellphoneBlueIcon' => plugins_url('../assets/images/checkouts/credits/cellphone-blue-icon.png', plugin_dir_path(__FILE__)),
                'cellphoneGrayIcon' => plugins_url('../assets/images/checkouts/credits/cellphone-gray-icon.png', plugin_dir_path(__FILE__)),
                'viewMobile'        => plugins_url($this->get_mercado_credits_gif_path($siteId, 'mobile'), plugin_dir_path(__FILE__)),
                'viewDesktop'       => plugins_url($this->get_mercado_credits_gif_path($siteId, 'desktop'), plugin_dir_path(__FILE__)),
                'footerDesktop'     => 'Banner on the product page | Computer version',
                'footerCellphone'   => 'Banner on the product page | Cellphone version',
            ),
        );

        return $this->mercadopago->template->getWoocommerceTemplateHtml(
            'admin/components/credits-info-example.php',
            array(
                'desktop'   => 'Computer',
                'cellphone' => 'Mobile',
                'footer'    => 'Banner on the product page | Computer version',
                'title'     => 'Component visualization',
                'subtitle'  => 'Check below how this feature will be displayed to your customers:',
            ),
        );
    }

    /**
     * Get git image path for mercado credits demonstration
     *
     * @param $siteId
     * @param $view
     *
     * @return string
     */
    private function get_mercado_credits_gif_path($siteId, $view)
    {
        $siteIds = [
            'mla' => 'MLA_',
            'mlb' => 'MLB_',
            'mlm' => 'MLM_',
        ];

        $prefix = isset($siteIds[$siteId]) ? $siteIds[$siteId] : '';

        return sprintf('../assets/images/checkouts/credits/%sview_%s.gif', $prefix, $view);
    }

    /**
     * Added gateway scripts
     *
     * @param string $gatewaySection
     *
     * @return void
     */
    public function payment_scripts(string $gatewaySection): void
    {
        parent::payment_scripts($gatewaySection);
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->scripts->registerStoreStyle(
            'woocommerce-mercadopago-narciso-styles',
            plugins_url('../assets/css/checkout/mp-plugins-components.css', plugin_dir_path(__FILE__))
        );
        $this->mercadopago->scripts->registerStoreScript(
            'woocommerce-mercadopago-narciso-scripts',
            plugins_url('../assets/js/checkout/mp-plugins-components.js', plugin_dir_path(__FILE__))
        );

        $siteId         = strtoupper($this->mercadopago->seller->getSiteId());
        $test_mode_link = $this->get_mp_devsite_link($siteId);

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/checkouts/credits-checkout.php',
            [
                'test_mode'      => ! $this->mercadopago->store->getCheckboxCheckoutProductionMode(),
                'test_mode_link' => $test_mode_link,
                'redirect_image' => plugins_url('../assets/images/checkouts/credits/cho-pro-redirect-v2.png', plugin_dir_path(__FILE__)),
            ]
        );
    }

    /**
     * Validate gateway checkout form fields
     *
     * @return bool
     */
    public function validate_fields(): bool
    {
        return true;
    }

    /**
     * Process payment and create woocommerce order
     *
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);
        $order->payment_complete();
        $order->add_order_note('Hey, your order is paid! Thank you!', true);

        wc_reduce_stock_levels($order_id);

        $this->mercadopago->woocommerce->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        ];
    }

    /**
     * Receive gateway webhook notifications
     *
     * @return void
     */
    public function webhook(): void
    {
        $status   = 200;
        $response = [
            'status'  => $status,
            'message' => 'Webhook handled successful'
        ];

        wp_send_json_success($response, $status);
    }

    /**
     * Get Mercado Pago Devsite Page Link
     *
     * @param String $country Country Acronym
     *
     * @return String
     */
    private static function get_mp_devsite_link($country)
    {
        $country_links = [
            'mla' => 'https://www.mercadopago.com.ar/developers/es/guides/plugins/woocommerce/testing',
            'mlb' => 'https://www.mercadopago.com.br/developers/pt/guides/plugins/woocommerce/testing',
            'mlc' => 'https://www.mercadopago.cl/developers/es/guides/plugins/woocommerce/testing',
            'mco' => 'https://www.mercadopago.com.co/developers/es/guides/plugins/woocommerce/testing',
            'mlm' => 'https://www.mercadopago.com.mx/developers/es/guides/plugins/woocommerce/testing',
            'mpe' => 'https://www.mercadopago.com.pe/developers/es/guides/plugins/woocommerce/testing',
            'mlu' => 'https://www.mercadopago.com.uy/developers/es/guides/plugins/woocommerce/testing',
        ];

        $link = array_key_exists($country, $country_links) ? $country_links[$country] : $country_links['mla'];

        return $link;
    }
}
