<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Helpers\Template;
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
    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Credits_Gateway';

    /**
     * @const
     */
    public const LOG_SOURCE = 'MercadoPago_CreditsGateway';

    /**
     * CreditsGateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->creditsGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->creditsCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-mp');
        $this->iconAdmin = $this->icon;
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
        $this->setDefaultTooltip();
    }

    public function getCheckoutName(): string
    {
        return 'checkout-credits';
    }

    public function formFieldsMainSection(): array
    {
        return [
            'checkout_visualization' => [
                'type'      => 'mp_credits_checkout_example',
                'title'     => $this->adminTranslations['enabled_toggle_title'],
                'subtitle'  => $this->adminTranslations['enabled_toggle_subtitle'],
                'footer'    => $this->adminTranslations['enabled_toggle_footer'],
                'pill_text' => $this->adminTranslations['enabled_toggle_pill_text'],
                'image'     => $this->getCreditsPreviewImage($this->getSiteId()),
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
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['credits_banner_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['credits_banner_descriptions_disabled'],
                ],
            ],
            'tooltip_section' => [
                'type'  => 'title',
                'title' => "",
            ],
            'tooltip_selection' => [
                'type'               => 'mp_tooltip_selection',
                'icon'               => $this->icon,
                'i18n'               => $this->adminTranslations,
                'after_toggle'       => $this->getCreditsInfoTemplate(),
                'current_tooltip_id' => $this->mercadopago->hooks->options->getGatewayOption($this, 'tooltip_selection', 1)
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
        ];
    }

    /**
     * Generate credits checkout example component
     */
    public function generate_mp_credits_checkout_example_html(string $key, array $settings): string
    {
        return Template::html('admin/components/credits-checkout-example', [
            'field_key' => $this->get_field_key($key),
            'field_value' => null,
            'settings' => $settings,
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
        return [
            'test_mode'               => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_link_src'      => $this->links['docs_integration_test'],
            'i18n'                    => array_merge($this->mercadopago->storeTranslations->commonCheckout, $this->storeTranslations),
            'checkout_redirect_src'   => $this->mercadopago->helpers->url->getImageAsset('icons/icon-mp-nobg'),
            'amount'                  => $this->getAmountAndCurrency('amount'),
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

    public function proccessPaymentInternal($order): array
    {
        try {
            $this->mercadopago->orderMetadata->markPaymentAsBlocks(
                $order,
                isset($_POST['wc-woo-mercado-pago-credits-new-payment-method']) ? "yes" : "no"
            );

            $this->transaction  = new CreditsTransaction($this, $order);

            $this->mercadopago->logs->file->info('Customer being redirected to Mercado Pago.', self::LOG_SOURCE);
            $preference        = $this->transaction->createPreference();

            return [
                'result'   => 'success',
                'redirect' => $this->mercadopago->storeConfig->isTestMode() ? $preference['sandbox_init_point'] : $preference['init_point'],
            ];
        } catch (Exception $e) {
            return $this->processReturnFail(
                $e,
                'buyer_default',
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
     * Get image path for mercado credits checkout preview
     */
    private function getCreditsPreviewImage(string $siteId): string
    {
        $lang = $siteId === 'MLB' ? 'pt' : 'es';
        return $this->mercadopago->helpers->url->getImageAsset("checkouts/credits/{$lang}_checkout_preview");
    }

    /**
     * Example Banner Credits Admin
     *
     * @return string
     */
    private function getCreditsInfoTemplate(): string
    {
        $this->mercadopago->hooks->scripts->registerCreditsAdminStyle(
            'mp_info_admin_credits_style',
            $this->mercadopago->helpers->url->getCssAsset('admin/credits/example-info')
        );

        $this->mercadopago->hooks->scripts->registerCreditsAdminScript(
            'mp_info_admin_credits_script',
            $this->mercadopago->helpers->url->getJsAsset('admin/credits/example-info'),
            [
                'computerBlueIcon'  => $this->mercadopago->helpers->url->getImageAsset('checkouts/credits/desktop-blue-icon'),
                'computerGrayIcon'  => $this->mercadopago->helpers->url->getImageAsset('checkouts/credits/desktop-gray-icon'),
                'cellphoneBlueIcon' => $this->mercadopago->helpers->url->getImageAsset('checkouts/credits/cellphone-blue-icon'),
                'cellphoneGrayIcon' => $this->mercadopago->helpers->url->getImageAsset('checkouts/credits/cellphone-gray-icon'),
                'viewMobile'        => $this->getCreditsGifMobilePath($this->getSiteId()),
                'viewDesktop'       => $this->getCreditsGifDesktopPath($this->getSiteId()),
                'footerDesktop'     => $this->adminTranslations['credits_banner_desktop'],
                'footerCellphone'   => $this->adminTranslations['credits_banner_cellphone'],
            ]
        );

        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/credits-info-example.php',
            [
                'desktop'     => $this->adminTranslations['credits_banner_toggle_computer'],
                'cellphone'   => $this->adminTranslations['credits_banner_toggle_mobile'],
                'footer'      => $this->adminTranslations['credits_banner_desktop'],
                'title'       => $this->adminTranslations['credits_banner_toggle_title'],
                'subtitle'    => $this->adminTranslations['credits_banner_toggle_subtitle'],
                'viewDesktop' => $this->getCreditsGifDesktopPath($this->getSiteId()),
            ]
        );
    }

    /**
     * Get desktop gif image url for mercado credits demonstration
     */
    public function getCreditsGifDesktopPath(string $siteId): string
    {
        $paths = [
            'MLB' => 'https://http2.mlstatic.com/storage/cpp/static-files/8afbe775-e8c3-4fa1-b013-ab7f079872b7.gif',
        ];

        return $paths[$siteId] ?? 'https://http2.mlstatic.com/storage/cpp/static-files/e6af4c4b-bede-4a6a-8711-b3d19fe423e3.gif';
    }

    public function getCreditsGifMobilePath(string $siteId): string
    {
        $paths = [
            'MLB' => 'https://http2.mlstatic.com/storage/cpp/static-files/8bcbd873-6ec3-45eb-bccf-47bdcd9af255.gif',
        ];

        return $paths[$siteId] ?? 'https://http2.mlstatic.com/storage/cpp/static-files/a91b365a-73dc-461a-9f3f-f8b3329ae5d2.gif';
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
                $this->mercadopago->helpers->url->getCssAsset('products/credits-modal')
            );

            $this->mercadopago->hooks->scripts->registerStoreScript(
                'mp-credits-modal-js',
                $this->mercadopago->helpers->url->getJsAsset('products/credits-modal')
            );

            $this->mercadopago->hooks->scripts->registerMelidataStoreScript('/products');

            $tooltipId = $this->mercadopago->hooks->options->getGatewayOption($this, 'tooltip_selection');
            $this->mercadopago->hooks->template->getWoocommerceTemplate(
                'public/products/credits-modal.php',
                [
                    'tooltip_html'           => $this->storeTranslations["tooltip_component_option$tooltipId"],
                    'tooltip_link'           => $this->storeTranslations['tooltip_link'],
                    'modal_title'            => $this->storeTranslations['modal_title'],
                    'modal_step_1'           => $this->storeTranslations['modal_step_1'],
                    'modal_step_2'           => $this->storeTranslations['modal_step_2'],
                    'modal_step_3'           => $this->storeTranslations['modal_step_3'],
                    'modal_footer'           => $this->storeTranslations['modal_footer'],
                    'modal_footer_link'      => $this->storeTranslations['modal_footer_link'],
                    'modal_footer_init'      => $this->storeTranslations['modal_footer_init'],
                    'modal_footer_help_link' => $this->links['credits_faq_link'],
                    'icon'                   => $this->icon
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

    /**
     * Set a default option for Tooltip
     */
    private function setDefaultTooltip(): void
    {
        if (empty($this->mercadopago->hooks->options->getGatewayOption($this, 'tooltip_selection'))) {
            $this->mercadopago->hooks->options->setGatewayOption($this, 'tooltip_selection', 1);
        }
    }

    protected function getSiteId(): string
    {
        return strtoupper($this->mercadopago->sellerConfig->getSiteId());
    }
}
