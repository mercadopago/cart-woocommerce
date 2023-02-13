<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsGateway extends AbstractGateway implements MercadoPagoGatewayInterface
{
    /**
     * @const
     */
    public const ID = 'woo-mercado-pago-credits';

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

        $this->adminTranslations = $this->mercadopago->adminTranslations->creditsGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->creditsCheckout;

        $this->id                 = self::ID;
        $this->icon               = $this->mercadopago->plugin->getGatewayIcon('icon-mp');
        $this->title              = $this->adminTranslations['gateway_title'];
        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];

        $this->init_form_fields();
        $this->init_settings();
        $this->payment_scripts($this->id);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
    }

    public function isAvailable(): bool
    {
        $siteIdPaymentMethods = $this->mercadopago->seller->getSiteIdPaymentMethods();

        foreach ($siteIdPaymentMethods as $paymentMethod) {
            if ('consumer_credits' === $paymentMethod['id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'header'                             => [
                'type'        => 'mp_config_title',
                'title'       => $this->adminTranslations['header_title'],
                'description' => $this->adminTranslations['header_description'],
            ],
            'card_settings'                      => [
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => $this->adminTranslations['card_settings_title'],
                    'subtitle'    => $this->adminTranslations['card_settings_subtitle'],
                    'button_text' => $this->adminTranslations['card_settings_button_text'],
                    'button_url'  => $this->mercadopago->links->getLinks()['admin_settings_page'],
                    'icon'        => 'mp-icon-badge-info',
                    'color_card'  => 'mp-alert-color-success',
                    'size_card'   => 'mp-card-body-size',
                    'target'      => '_self',
                ],
            ],
            'enabled'                            => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['enabled_title'],
                'subtitle'     => $this->adminTranslations['enabled_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['enabled_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['enabled_descriptions_disabled'],
                ],
                'after_toggle' => $this->getCheckoutVisualization(),
            ],
            'title'                              => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['title_title'],
                'description' => $this->adminTranslations['title_description'],
                'default'     => $this->adminTranslations['title_default'],
                'desc_tip'    => $this->adminTranslations['title_desc_tip'],
                'class'       => 'limit-title-max-length',
            ],
            'currency_conversion'                => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['currency_conversion_title'],
                'subtitle'     => $this->adminTranslations['currency_conversion_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['currency_conversion_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['currency_conversion_descriptions_disabled'],
                ],
            ],
            'credits_banner'                     => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['credits_banner_title'],
                'subtitle'     => $this->adminTranslations['credits_banner_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['credits_banner_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['credits_banner_descriptions_disabled'],
                ],
                'after_toggle' => $this->getCreditsInfoTemplate()
            ],
            'advanced_configuration_title'       => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_title'],
                'class' => 'mp-subtitle-body',
            ],
            'advanced_configuration_description' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_description'],
                'class' => 'mp-small-text',
            ],
            'discount'                           => [
                'type'              => 'mp_actionable_input',
                'title'             => $this->adminTranslations['discount_title'],
                'input_type'        => 'number',
                'description'       => $this->adminTranslations['discount_description'],
                'checkbox_label'    => $this->adminTranslations['discount_checkbox_label'],
                'default'           => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0',
                    'max'  => '99',
                ],
            ],
            'commission'                         => [
                'type'              => 'mp_actionable_input',
                'title'             => $this->adminTranslations['commission_title'],
                'input_type'        => 'number',
                'description'       => $this->adminTranslations['commission_description'],
                'checkbox_label'    => $this->adminTranslations['commission_checkbox_label'],
                'default'           => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0',
                    'max'  => '99',
                ],
            ]
        ];
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
        $checkoutBenefitsItems = $this->getBenefits();

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/checkouts/credits-checkout.php',
            [
                'test_mode'                        => $this->mercadopago->seller->isTestMode(),
                'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
                'test_mode_description'            => $this->storeTranslations['test_mode_description'],
                'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
                'test_mode_link_src'               => $this->mercadopago->links->getLinks()['docs_integration_test'],
                'checkout_benefits_title'          => $this->storeTranslations['checkout_benefits_title'],
                'checkout_benefits_items'          => wp_json_encode($checkoutBenefitsItems),
                'checkout_redirect_text'           => $this->storeTranslations['checkout_redirect_text'],
                'checkout_redirect_src'            => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/cho-pro-redirect-v2', '.png'),
                'checkout_redirect_alt'            => $this->storeTranslations['checkout_redirect_alt'],
                'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
                'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
                'terms_and_conditions_link_src'    => $this->mercadopago->links->getLinks()['mercadopago_terms_and_conditions'],
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
     * Example Banner Credits Admin
     *
     * @return string
     */
    private function getCheckoutVisualization(): string
    {
        $siteId = strtoupper($this->mercadopago->seller->getSiteId());
        return $this->mercadopago->template->getWoocommerceTemplateHtml(
            'admin/components/credits-checkout-example.php',
            [
                'title'     => $this->adminTranslations['enabled_toggle_title'],
                'subtitle'  => $this->adminTranslations['enabled_toggle_subtitle'],
                'footer'    => $this->adminTranslations['enabled_toggle_footer'],
                'pill_text' => $this->adminTranslations['enabled_toggle_pill_text'],
                'image'     => plugins_url($this->getCreditsPreviewImage($siteId), plugin_dir_path(__FILE__)),
            ]
        );
    }

    /**
     * Get image path for mercado credits checkout preview
     *
     * @param $siteId
     *
     * @return string
     */
    private function getCreditsPreviewImage($siteId): string
    {
        $siteIds = [
            'mla' => 'MLA_',
            'mlb' => 'MLB_',
            'mlm' => 'MLM_',
        ];

        $prefix = $siteIds[$siteId] ?? '';

        return sprintf('../assets/images/checkouts/credits/%scheckout_preview.jpg', $prefix);
    }

    /**
     * Example Banner Credits Admin
     *
     * @return string
     */
    private function getCreditsInfoTemplate(): string
    {
        $siteId = strtoupper($this->mercadopago->seller->getSiteId());

        $this->mercadopago->scripts->registerAdminStyle(
            'mp_info_admin_credits_style',
            $this->mercadopago->url->getPluginFileUrl('/assets/css/admin/credits/example-info', '.css')
        );

        $this->mercadopago->scripts->registerAdminScript(
            'mp_info_admin_credits_script',
            $this->mercadopago->url->getPluginFileUrl('/assets/js/admin/credits/example-info', '.js'),
            [
                'computerBlueIcon'  => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/credits/desktop-blue-icon', '.png'),
                'computerGrayIcon'  => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/credits/desktop-gray-icon', '.png'),
                'cellphoneBlueIcon' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/credits/cellphone-blue-icon', '.png'),
                'cellphoneGrayIcon' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/credits/cellphone-gray-icon', '.png'),
                'viewMobile'        => plugins_url($this->getCreditsGifPath($siteId, 'mobile'), plugin_dir_path(__FILE__)),
                'viewDesktop'       => plugins_url($this->getCreditsGifPath($siteId, 'desktop'), plugin_dir_path(__FILE__)),
                'footerDesktop'     => $this->adminTranslations['credits_banner_desktop'],
                'footerCellphone'   => $this->adminTranslations['credits_banner_cellphone'],
            ]
        );

        return $this->mercadopago->template->getWoocommerceTemplateHtml(
            'admin/components/credits-info-example.php',
            [
                'desktop'   => $this->adminTranslations['credits_banner_toggle_computer'],
                'cellphone' => $this->adminTranslations['credits_banner_toggle_mobile'],
                'footer'    => $this->adminTranslations['credits_banner_desktop'],
                'title'     => $this->adminTranslations['credits_banner_toggle_title'],
                'subtitle'  => $this->adminTranslations['credits_banner_toggle_subtitle'],
            ]
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
    private function getCreditsGifPath($siteId, $view): string
    {
        $siteIds = [
            'mla' => 'MLA_',
            'mlb' => 'MLB_',
            'mlm' => 'MLM_',
        ];

        $prefix = $siteIds[$siteId] ?? '';

        return sprintf('../assets/images/checkouts/credits/%sview_%s.gif', $prefix, $view);
    }

    /**
     * Get benefits items
     *
     * @return array
     */
    private function getBenefits(): array
    {
        return [
            $this->storeTranslations['checkout_benefits_1'],
            $this->storeTranslations['checkout_benefits_2'],
            $this->storeTranslations['checkout_benefits_3'],
        ];
    }
}
