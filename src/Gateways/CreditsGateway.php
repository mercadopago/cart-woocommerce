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
     * @const
     */
    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Credits_Gateway';

    /**
     * @const
     */
    public const LOG_SOURCE = 'MercadoPago_CreditsGateway';

    /**
     * CreditsGateway constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->creditsGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->creditsCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-mp');
        $this->iconAdmin = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-mp-admin');
        $this->title     = $this->mercadopago->storeConfig->getGatewayTitle($this, $this->adminTranslations['gateway_title']);

        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];
        $this->discount           = $this->getActionableValue('gateway_discount', 0);
        $this->commission         = $this->getActionableValue('commission', 0);

        $this->mercadopago->hooks->gateway->registerUpdateOptions($this);
        $this->mercadopago->hooks->gateway->registerGatewayTitle($this);
        $this->mercadopago->hooks->gateway->registerThankyouPage($this->id, [$this, 'saveOrderPaymentsId']);

        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);
        $this->mercadopago->hooks->cart->registerCartCalculateFees([$this, 'registerDiscountAndCommissionFeesOnCart']);

        $this->mercadopago->helpers->currency->handleCurrencyNotices($this);
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
        if ($this->addMissingCredentialsNoticeAsFormField()) {
            return;
        }

        parent::init_form_fields();

        $this->form_fields = array_merge($this->form_fields, [
            'header' => [
                'type'        => 'mp_config_title',
                'title'       => $this->adminTranslations['header_title'],
                'description' => $this->adminTranslations['header_description'],
            ],
            'card_homolog_validate' => $this->getHomologValidateNoticeOrHidden(),
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
            'gateway_discount' => $this->getDiscountField(),
            'commission'       => $this->getCommissionField(),
            'split_section' => [
                'type'  => 'title',
                'title' => "",
            ],
            'support_link' => [
                'type'  => 'mp_support_link',
                'bold_text'    => $this->adminTranslations['support_link_bold_text'],
                'text_before_link'    => $this->adminTranslations['support_link_text_before_link'],
                'text_with_link' => $this->adminTranslations['support_link_text_with_link'],
                'text_after_link'    => $this->adminTranslations['support_link_text_after_link'],
                'support_link'    => $this->links['docs_support_faq'],
            ],
        ]);
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
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/checkouts/credits-checkout.php',
            $this->getPaymentFieldsParams()
        );
    }

    /**
     * Get Payment Fields params
     *
     * @return array
     */
    public function getPaymentFieldsParams(): array
    {
        $checkoutBenefitsItems = $this->getBenefits();
        $checkoutRedirectSrc   = $this->mercadopago->helpers->url->getPluginFileUrl(
            'assets/images/checkouts/basic/cho-pro-redirect-v2',
            '.png',
            true
        );
        return [
            'test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
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
            'fee_title'                        => $this->getFeeTitle(),
        ];
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
     */
    public function process_payment($order_id): array
    {
        $order              = wc_get_order($order_id);
        try {
            parent::process_payment($order_id);

            if (isset($_POST['wc-woo-mercado-pago-credits-new-payment-method'])) {
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
            } else {
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
            }

            $this->transaction  = new CreditsTransaction($this, $order);

            $this->mercadopago->logs->file->info('Customer being redirected to Mercado Pago.', self::LOG_SOURCE);
            $preference        = $this->transaction->createPreference();

            return [
                'result'   => 'success',
                'redirect' => $this->mercadopago->storeConfig->isTestMode() ? $preference['sandbox_init_point'] : $preference['init_point'],
            ];
        } catch (\Exception $e) {
            return $this->processReturnFail(
                $e,
                $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'],
                self::LOG_SOURCE,
                (array) $order,
                true
            );
        }
    }

    /**
     * Verify if the gateway is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $mercadopago;

        $paymentMethodsBySite = $mercadopago->sellerConfig->getSiteIdPaymentMethods();

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
        $siteId = strtoupper($this->mercadopago->sellerConfig->getSiteId());

        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
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
            'MLA' => 'MLA_',
            'MLB' => 'MLB_',
            'MLM' => 'MLM_',
        ];

        $prefix = $siteIds[$siteId] ?? '';

        return $this->mercadopago->helpers->url->getPluginFileUrl(
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
        $siteId = strtoupper($this->mercadopago->sellerConfig->getSiteId());

        $this->mercadopago->hooks->scripts->registerCreditsAdminStyle(
            'mp_info_admin_credits_style',
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/css/admin/credits/example-info', '.css')
        );

        $this->mercadopago->hooks->scripts->registerCreditsAdminScript(
            'mp_info_admin_credits_script',
            $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/admin/credits/example-info', '.js'),
            [
                'computerBlueIcon'  => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/credits/desktop-blue-icon', '.png', true),
                'computerGrayIcon'  => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/credits/desktop-gray-icon', '.png', true),
                'cellphoneBlueIcon' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/credits/cellphone-blue-icon', '.png', true),
                'cellphoneGrayIcon' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/credits/cellphone-gray-icon', '.png', true),
                'viewMobile'        => $this->getCreditsGifPath($siteId, 'mobile'),
                'viewDesktop'       => $this->getCreditsGifPath($siteId, 'desktop'),
                'footerDesktop'     => $this->adminTranslations['credits_banner_desktop'],
                'footerCellphone'   => $this->adminTranslations['credits_banner_cellphone'],
            ]
        );

        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
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
            'MLA' => 'MLA_',
            'MLB' => 'MLB_',
            'MLM' => 'MLM_',
        ];

        $prefix = $siteIds[$siteId] ?? '';

        return $this->mercadopago->helpers->url->getPluginFileUrl(
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
        $gatewayAvailable = $this->isAvailable();
        $gatewayEnabled   = $this->mercadopago->hooks->gateway->isEnabled($this);
        $bannerEnabled    = $this->mercadopago->hooks->options->getGatewayOption($this, 'credits_banner', 'no') === 'yes';

        if ($gatewayAvailable && $gatewayEnabled && $bannerEnabled) {
            $this->mercadopago->hooks->scripts->registerStoreStyle(
                'mp-credits-modal-style',
                $this->mercadopago->helpers->url->getPluginFileUrl('assets/css/products/credits-modal', '.css')
            );

            $this->mercadopago->hooks->scripts->registerStoreScript(
                'mp-credits-modal-js',
                $this->mercadopago->helpers->url->getPluginFileUrl('assets/js/products/credits-modal', '.js')
            );

            $this->mercadopago->hooks->scripts->registerMelidataStoreScript('/products');

            $this->mercadopago->hooks->template->getWoocommerceTemplate(
                'public/products/credits-modal.php',
                [
                    'banner_title'           => $this->storeTranslations['banner_title'],
                    'banner_title_bold'      => $this->storeTranslations['banner_title_bold'],
                    'banner_title_end'       => $this->storeTranslations['banner_title_end'],
                    'banner_link'            => $this->storeTranslations['banner_link'],
                    'modal_title'            => $this->storeTranslations['modal_title'],
                    'modal_subtitle'         => $this->storeTranslations['modal_subtitle'],
                    'modal_step_1'           => $this->storeTranslations['modal_step_1'],
                    'modal_step_2'           => $this->storeTranslations['modal_step_2'],
                    'modal_step_3'           => $this->storeTranslations['modal_step_3'],
                    'modal_footer'           => $this->storeTranslations['modal_footer'],
                    'modal_footer_link'      => $this->storeTranslations['modal_footer_link'],
                    'modal_footer_end'       => $this->storeTranslations['modal_footer_end'],
                    'modal_footer_help_link' => $this->links['credits_faq_link'],
                ]
            );
        }
    }

    /**
     * Enable Credits by default
     */
    public function activeByDefault(): void
    {
        $this->mercadopago->hooks->options->setGatewayOption($this, 'enabled', 'yes');
        $this->mercadopago->hooks->options->setGatewayOption($this, 'credits_banner', 'yes');
    }
}
