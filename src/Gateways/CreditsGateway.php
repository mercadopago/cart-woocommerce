<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Transactions\CreditsTransaction;

if (!defined('ABSPATH')) {
    exit;
}

class CreditsGateway extends AbstractGateway
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

        $this->id    = self::ID;
        $this->icon  = $this->mercadopago->gateway->getGatewayIcon('icon-mp');
        $this->title = $this->mercadopago->store->getGatewayTitle($this, $this->adminTranslations['gateway_title']);

        $this->init_settings();
        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];
        $this->discount           = $this->getActionableValue('discount', 0);
        $this->commission         = $this->getActionableValue('commission', 0);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->gateway->registerGatewayTitle($this);

        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
    }

    /**
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'header' => [
                'type'        => 'mp_config_title',
                'title'       => $this->adminTranslations['header_title'],
                'description' => $this->adminTranslations['header_description'],
            ],
            'card_settings' => [
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => $this->adminTranslations['card_settings_title'],
                    'subtitle'    => $this->adminTranslations['card_settings_subtitle'],
                    'button_text' => $this->adminTranslations['card_settings_button_text'],
                    'button_url'  => $this->links['admin_settings_page'],
                    'icon'        => 'mp-icon-badge-info',
                    'color_card'  => 'mp-alert-color-success',
                    'size_card'   => 'mp-card-body-size',
                    'target'      => '_self',
                ],
            ],
            'enabled' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['enabled_title'],
                'subtitle'     => $this->adminTranslations['enabled_subtitle'],
                'default'      => 'no',
                'after_toggle' => $this->getCheckoutVisualization(),
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['enabled_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['enabled_descriptions_disabled'],
                ],
            ],
            'title' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['title_title'],
                'description' => $this->adminTranslations['title_description'],
                'default'     => $this->adminTranslations['title_default'],
                'desc_tip'    => $this->adminTranslations['title_desc_tip'],
                'class'       => 'limit-title-max-length',
            ],
            'currency_conversion' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['currency_conversion_title'],
                'subtitle'     => $this->adminTranslations['currency_conversion_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['currency_conversion_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['currency_conversion_descriptions_disabled'],
                ],
            ],
            'credits_banner' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['credits_banner_title'],
                'subtitle'     => $this->adminTranslations['credits_banner_subtitle'],
                'default'      => 'no',
                'after_toggle' => $this->getCreditsInfoTemplate(),
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['credits_banner_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['credits_banner_descriptions_disabled'],
                ],
            ],
            'advanced_configuration_title' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_title'],
                'class' => 'mp-subtitle-body',
            ],
            'advanced_configuration_description' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_description'],
                'class' => 'mp-small-text',
            ],
            'discount' => [
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
            'commission' => [
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
        $checkoutRedirectSrc   = $this->mercadopago->url->getPluginFileUrl(
            '/assets/images/checkouts/basic/cho-pro-redirect-v2',
            '.png',
            true
        );

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/checkouts/credits-checkout.php',
            [
                'test_mode'                        => $this->mercadopago->seller->isTestMode(),
                'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
                'test_mode_description'            => $this->storeTranslations['test_mode_description'],
                'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
                'test_mode_link_src'               => $this->links['docs_integration_test'],
                'checkout_benefits_title'          => $this->storeTranslations['checkout_benefits_title'],
                'checkout_benefits_items'          => wp_json_encode($checkoutBenefitsItems),
                'checkout_redirect_text'           => $this->storeTranslations['checkout_redirect_text'],
                'checkout_redirect_src'            => $checkoutRedirectSrc,
                'checkout_redirect_alt'            => $this->storeTranslations['checkout_redirect_alt'],
                'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
                'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
                'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
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
     * @param $order_id
     *
     * @return array
     * @throws \WC_Data_Exception
     */
    public function process_payment($order_id): array
    {
        parent::process_payment($order_id);

        $order              = wc_get_order($order_id);
        $this->transaction  = new CreditsTransaction($this, $order);

        $this->mercadopago->logs->file->info(
            'customer being redirected to Mercado Pago.',
            __FUNCTION__
        );

        return [
            'result'   => 'success',
            'redirect' => $this->transaction->createPreference(),
        ];
    }

    /**
     * Verify if the gateway is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $mercadopago;

        $paymentMethodsBySite = $mercadopago->seller->getSiteIdPaymentMethods();

        foreach ($paymentMethodsBySite as $paymentMethod) {
            if ('consumer_credits' === $paymentMethod['id']) {
                return true;
            }
        }

        return false;
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
                'image'     => $this->getCreditsPreviewImage($siteId),
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

        return $this->mercadopago->url->getPluginFileUrl(
            'assets/images/checkouts/credits/' . $prefix . 'checkout_preview',
            '.jpg',
            true
        );
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
                'computerBlueIcon'  => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/credits/desktop-blue-icon', '.png', true),
                'computerGrayIcon'  => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/credits/desktop-gray-icon', '.png', true),
                'cellphoneBlueIcon' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/credits/cellphone-blue-icon', '.png', true),
                'cellphoneGrayIcon' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/credits/cellphone-gray-icon', '.png', true),
                'viewMobile'        => $this->getCreditsGifPath($siteId, 'mobile'),
                'viewDesktop'       => $this->getCreditsGifPath($siteId, 'desktop'),
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
     * Get gif image path for mercado credits demonstration
     *
     * @param string $siteId
     * @param string $view
     *
     * @return string
     */
    private function getCreditsGifPath(string $siteId, string $view): string
    {
        $siteIds = [
            'mla' => 'MLA_',
            'mlb' => 'MLB_',
            'mlm' => 'MLM_',
        ];

        $prefix = $siteIds[$siteId] ?? '';

        return $this->mercadopago->url->getPluginFileUrl(
            'assets/images/checkouts/credits/' . $prefix . 'view_' . $view,
            '.gif',
            true
        );
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

    /**
     * Set credits banner
     */
    public function renderCreditsBanner(): void
    {
        $this->mercadopago->scripts->registerStoreStyle(
            'mp-credits-modal-style',
            $this->mercadopago->url->getPluginFileUrl('assets/css/products/credits-modal', '.css')
        );

        $this->mercadopago->scripts->registerStoreScript(
            'mp-credits-modal-js',
            $this->mercadopago->url->getPluginFileUrl('assets/js/products/credits-modal', '.js')
        );

        $this->mercadopago->scripts->registerMelidataStoreScript('/products');

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/products/credits-modal.php',
            [
                'banner_title'           => $this->storeTranslations['banner_title'],
                'banner_title_bold'      => $this->storeTranslations['banner_title_bold'],
                'banner_title_end'       => $this->storeTranslations['banner_title_end'],
                'banner_link'            => $this->storeTranslations['banner_link'],
                'modal_title'            => $this->storeTranslations['modal_title'],
                'modal_subtitle'         => $this->storeTranslations['modal_subtitle'],
                'modal_how_to'           => $this->storeTranslations['modal_how_to'],
                'modal_step_1'           => $this->storeTranslations['modal_step_1'],
                'modal_step_1_bold'      => $this->storeTranslations['modal_step_1_bold'],
                'modal_step_1_end'       => $this->storeTranslations['modal_step_1_end'],
                'modal_step_2'           => $this->storeTranslations['modal_step_2'],
                'modal_step_2_bold'      => $this->storeTranslations['modal_step_2_bold'],
                'modal_step_2_end'       => $this->storeTranslations['modal_step_2_end'],
                'modal_step_3'           => $this->storeTranslations['modal_step_3'],
                'modal_footer'           => $this->storeTranslations['modal_footer'],
                'modal_footer_link'      => $this->storeTranslations['modal_footer_link'],
                'modal_footer_end'       => $this->storeTranslations['modal_footer_end'],
                'modal_footer_help_link' => $this->links['credits_faq_link'],
            ]
        );
    }
}
