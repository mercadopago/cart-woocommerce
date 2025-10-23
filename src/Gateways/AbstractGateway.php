<?php

namespace MercadoPago\Woocommerce\Gateways;

use ArrayAccess;
use Exception;
use MercadoPago\Woocommerce\Helpers\Arrays;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Notification\NotificationFactory;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use Mockery\Exception\MockeryExceptionInterface;
use WC_Payment_Gateway;
use MercadoPago\Woocommerce\Helpers\RefundStatusCodes;
use MercadoPago\Woocommerce\Exceptions\RefundException;
use WP_Error;

abstract class AbstractGateway extends WC_Payment_Gateway implements MercadoPagoGatewayInterface
{
    public const ID = '';

    public const WEBHOOK_API_NAME = '';

    public const LOG_SOURCE = '';

    protected const ENABLED_OPTION = 'enabled';

    protected const ENABLED_DEFAULT = 'no';

    public string $iconAdmin;

    public int $commission;

    public int $discount;

    public int $expirationDate;

    public string $checkoutCountry;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var array|ArrayAccess
     */
    public $adminTranslations;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var array|ArrayAccess
     */
    public $storeTranslations;

    protected float $ratio;

    protected array $countryConfigs;

    // TODO(PHP8.2): Change type hint from phpdoc to native
    /**
     * @var array|ArrayAccess
     */
    protected $links;

    public WoocommerceMercadoPago $mercadopago;

    public $transaction;

    /**
     * Abstract Gateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;

        if (!$this->mercadopago->booted()) {
            return;
        }

        $this->checkoutCountry = $this->mercadopago->storeConfig->getCheckoutCountry();
        $this->countryConfigs  = $this->mercadopago->helpers->country->getCountryConfigs();
        $this->ratio           = $this->mercadopago->helpers->currency->getRatio($this);
        $this->links           = $this->mercadopago->helpers->links->getLinks();

        $this->has_fields = true;
        $this->supports   = [ 'products', 'refunds' ];

        $this->init_settings();
        $this->loadResearchComponent();
        $this->loadMelidataStoreScripts();
    }

    abstract public function getCheckoutName(): string;

    /**
     * Process blocks checkout data
     *
     * @param $prefix
     * @param $postData
     *
     * @return array
     */
    public function processBlocksCheckoutData($prefix, $postData): array
    {
        $checkoutData = [];

        foreach ($postData as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $newKey                  = substr($key, strlen($prefix));
                $checkoutData[ $newKey ] = $value;
            }
        }

        return $checkoutData;
    }

    public function saveOrderPaymentsId(string $orderId)
    {
        $order      = wc_get_order($orderId);
        $paymentIds = Form::sanitizedGetData('payment_id');

        if ($paymentIds) {
            $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, ['id' => $paymentIds]);

            return;
        }
        $this->mercadopago->logs->file->info("no payment ids to update", "MercadoPago_AbstractGateway");
    }

    /**
     * Init form fields for checkout configuration
     */
    public function init_form_fields(): void
    {
        $this->form_fields = $this->isMissingCredentials()
            ? $this->missingCredentialsFormFieldNotice()
            : $this->formFields();
    }

    public function formFields(): array
    {
        return array_merge(
            $this->formFieldsHeaderSection(),
            $this->formFieldsMainSection(),
            $this->formFieldsFooterSection()
        );
    }

    public function formFieldsHeaderSection(): array
    {
        return [
            'header' => [
                'type'        => 'mp_config_title',
                'title'       => $this->adminTranslations['header_title'] ?? null,
                'description' => $this->adminTranslations['header_description'] ?? null,
            ],
            'card_homolog_validate' => $this->getHomologValidateNoticeOrHidden(),
            'card_invalid_credentials' => $this->getCredentialExpiredNotice(),
            'card_settings' => [
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => $this->adminTranslations['card_settings_title'] ?? null,
                    'subtitle'    => $this->adminTranslations['card_settings_subtitle'] ?? null,
                    'button_text' => $this->adminTranslations['card_settings_button_text'] ?? null,
                    'button_url'  => $this->links['admin_settings_page'],
                    'icon'        => 'mp-icon-badge-info',
                    'color_card'  => 'mp-alert-color-success',
                    'size_card'   => 'mp-card-body-size',
                    'target'      => '_self',
                ],
            ],
            static::ENABLED_OPTION => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['enabled_title'] ?? null,
                'subtitle'     => $this->adminTranslations['enabled_subtitle'] ?? null,
                'default'      => static::ENABLED_DEFAULT,
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['enabled_descriptions_enabled'] ?? null,
                    'disabled' => $this->adminTranslations['enabled_descriptions_disabled'] ?? null,
                ],
            ],
            'title' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['title_title'] ?? null,
                'description' => $this->adminTranslations['title_description'] ?? null,
                'default'     => $this->adminTranslations['title_default'] ?? null,
                'desc_tip'    => $this->adminTranslations['title_desc_tip'] ?? null,
                'class'       => 'limit-title-max-length',
            ],
        ];
    }

    abstract public function formFieldsMainSection(): array;

    public function formFieldsFooterSection(): array
    {
        return [
            'gateway_discount' => [
                'type' => 'mp_actionable_input',
                'title' => $this->adminTranslations['discount_title'] ?? null,
                'input_type' => 'number',
                'description' => $this->adminTranslations['discount_description'] ?? null,
                'checkbox_label' => $this->adminTranslations['discount_checkbox_label'] ?? null,
                'default' => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '99',
                ],
            ],
            'commission' => [
                'type' => 'mp_actionable_input',
                'title' => $this->adminTranslations['commission_title'] ?? null,
                'input_type' => 'number',
                'description' => $this->adminTranslations['commission_description'] ?? null,
                'checkbox_label' => $this->adminTranslations['commission_checkbox_label'] ?? null,
                'default' => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '99',
                ],
            ],
            'split_section' => [
                'type' => 'title',
                'title' => '',
            ],
            'support_link' => [
                'type' => 'mp_support_link',
                'bold_text' => $this->adminTranslations['support_link_bold_text'] ?? null,
                'text_before_link' => $this->adminTranslations['support_link_text_before_link'] ?? null,
                'text_with_link' => $this->adminTranslations['support_link_text_with_link'] ?? null,
                'text_after_link' => $this->adminTranslations['support_link_text_after_link'] ?? null,
                'support_link' => $this->links['docs_support_faq'],
            ],
        ];
    }

    protected function isMissingCredentials(): bool
    {
        return Arrays::anyEmpty([
            $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
            $this->mercadopago->sellerConfig->getCredentialsAccessToken()
        ]);
    }

    protected function missingCredentialsFormFieldNotice(): array
    {
        return [
            'card_info_validate' => [
                'type' => 'mp_card_info',
                'value' => [
                    'title' => '',
                    'subtitle' => $this->mercadopago->adminTranslations->credentialsSettings['card_info_subtitle'],
                    'button_text' => $this->mercadopago->adminTranslations->credentialsSettings['card_info_button_text'],
                    'button_url' => $this->links['admin_settings_page'],
                    'icon' => 'mp-icon-badge-warning',
                    'color_card' => 'mp-alert-color-error',
                    'size_card' => 'mp-card-body-size',
                    'target' => '_self',
                ]
            ]
        ];
    }

    /**
     * If the seller is homologated, it returns an array of an empty $form_fields field.
     * If not, then return a notice to inform that the seller must be homologated to be able to sell.
     *
     * @return array
     */
    protected function getHomologValidateNoticeOrHidden(): array
    {
        if ($this->mercadopago->sellerConfig->getHomologValidate()) {
            return [
                'type'  => 'title',
                'value' => '',
            ];
        }

        return [
            'type'  => 'mp_card_info',
            'value' => [
                'title'       => $this->mercadopago->adminTranslations->credentialsSettings['card_homolog_title'],
                'subtitle'    => $this->mercadopago->adminTranslations->credentialsSettings['card_homolog_subtitle'],
                'button_text' => $this->mercadopago->adminTranslations->credentialsSettings['card_homolog_button_text'],
                'button_url'  => $this->links['admin_settings_page'],
                'icon'        => 'mp-icon-badge-warning',
                'color_card'  => 'mp-alert-color-alert',
                'size_card'   => 'mp-card-body-size-homolog',
                'target'      => '_blank',
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
        if ($this->canAdminLoadScriptsAndStyles($gatewaySection)) {
            $this->registerAdminScripts();
        }

        if ($this->canCheckoutLoadScriptsAndStyles()) {
            $this->registerCheckoutScripts();
        }
    }

    /**
     * Register admin scripts
     *
     * @return void
     */
    public function registerAdminScripts()
    {
        $this->mercadopago->hooks->scripts->registerAdminScript(
            'wc_mercadopago_admin_components',
            $this->mercadopago->helpers->url->getJsAsset('admin/mp-admin-configs')
        );

        $this->mercadopago->hooks->scripts->registerAdminStyle(
            'mercadopago_admin_configs_css',
            $this->mercadopago->helpers->url->getCssAsset('admin/mp-admin-configs')
        );
    }

    /**
     * @codeCoverageIgnore
     *
     * Register checkout scripts
     *
     * @return void
     */
    public function registerCheckoutScripts(): void
    {
        $this->mercadopago->hooks->scripts->registerCheckoutStyle(
            'mercadopago_vars_css',
            $this->mercadopago->helpers->url->getCssAsset('public/mp-vars')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_checkout_error_dispatcher',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/mp-checkout-error-dispatcher')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_checkout_fields_dispatcher',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/mp-checkout-fields-dispatcher')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_checkout_session_data_register',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/mp-checkout-session-data-register'),
            [
                'public_key' => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
                'locale' => $this->mercadopago->storeTranslations->customCheckout['locale'],
            ]
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_checkout_components',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/mp-plugins-components')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutStyle(
            'wc_mercadopago_checkout_components',
            $this->mercadopago->helpers->url->getCssAsset('checkouts/mp-plugins-components')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_checkout_update',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/mp-checkout-update')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_checkout_metrics',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/mp-checkout-metrics'),
            [
                'theme'             => get_stylesheet(),
                'location'          => '/checkout',
                'plugin_version'    => MP_VERSION,
                'platform_version'  => $this->mercadopago->woocommerce->version,
                'site_id'           => $this->countryConfigs['site_id'],
                'currency'          => $this->countryConfigs['currency'],
            ]
        );
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
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

    public function setCheckoutSessionDataOnSessionHelperByOrderId(string $orderId)
    {
        $checkoutSessionData = [];
        if (isset($_POST['mercadopago_checkout_session'])) {
            // Classic Checkout
            $checkoutSessionData = Form::sanitizedPostData('mercadopago_checkout_session');
        } else {
            // Blocks Checkout
            $checkoutSessionData = $this->processBlocksCheckoutData('mercadopago_checkout_session', Form::sanitizedPostData());
        }

        if (!empty($checkoutSessionData)) {
            $this->mercadopago->helpers->session->setSession('mp_checkout_session_' . $orderId, $checkoutSessionData);
        }
    }

    /**
     * Process payment and create woocommerce order
     *
     * @param $order_id
     *
     * @return array
     * @throws Exception
     */
    public function process_payment($order_id): array
    {
        try {
            $order = wc_get_order($order_id);

            $discount   = $this->mercadopago->helpers->cart->calculateSubtotalWithDiscount($this);
            $commission = $this->mercadopago->helpers->cart->calculateSubtotalWithCommission($this);

            $isProductionMode = $this->mercadopago->storeConfig->getProductionMode();

            $this->mercadopago->orderMetadata->setIsProductionModeData($order, $isProductionMode);
            $this->mercadopago->orderMetadata->setUsedGatewayData($order, static::ID);

            if ($this->settings['currency_conversion'] === 'yes') {
                $ratio = $this->mercadopago->helpers->currency->getRatio($this);

                // Validate ratio is positive and not zero
                if ($ratio > 0) {
                    $this->mercadopago->orderMetadata->setCurrencyRatioData($order, $ratio);
                } else {
                    throw new Exception("Invalid currency ratio received: {$ratio}. Ratio must be positive and greater than zero.");
                }
            }

            if ($this->discount != 0) {
                $percentage  = Numbers::getPercentageFromParcialValue($discount, $order->get_total());
                $translation = $this->mercadopago->storeTranslations->commonCheckout['discount_title'];
                $feeText     = $this->getFeeText($translation, $percentage, $discount);

                $this->mercadopago->orderMetadata->setDiscountData($order, $feeText);
            }

            if ($this->commission != 0) {
                $percentage  = Numbers::getPercentageFromParcialValue($commission, $order->get_total());
                $translation = $this->mercadopago->storeTranslations->commonCheckout['fee_title'];
                $feeText     = $this->getFeeText($translation, $percentage, $commission);

                $this->mercadopago->orderMetadata->setCommissionData($order, $feeText);
            }

            $this->setCheckoutSessionDataOnSessionHelperByOrderId($order->get_id());

            return $this->proccessPaymentInternal($order);
        } catch (Exception $e) {
            return $this->processReturnFail(
                $e,
                $e->getMessage(),
                static::LOG_SOURCE,
                (array) $order ?? [],
                true
            );
        }
    }

    /**
     * Process order payment
     *
     * @param \WC_Order|\WC_Order_Refund $order
     */
    abstract public function proccessPaymentInternal($order): array;

    /**
     * Receive gateway webhook notifications
     *
     * @return void
     */
    public function webhook(): void
    {
        $data = Form::sanitizedGetData();

        if (!is_array($data)) {
            $data = [$data];
        }

        $notificationFactory = new NotificationFactory();
        $notificationHandler = $notificationFactory->createNotificationHandler($this, $data);

        $notificationHandler->handleReceivedNotification($data);
    }

    /**
     * Verify if the gateway is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        return true;
    }

    /**
     * Check if admin scripts and styles can be loaded
     *
     * @param string $gatewaySection
     *
     * @return bool
     */
    public function canAdminLoadScriptsAndStyles(string $gatewaySection): bool
    {
        return $this->mercadopago->hooks->admin->isAdmin() && ( $this->mercadopago->helpers->url->validatePage('wc-settings') &&
                                                                $this->mercadopago->helpers->url->validateSection($gatewaySection)
            );
    }

    /**
     * Check if admin scripts and styles can be loaded
     *
     * @return bool
     */
    public function canCheckoutLoadScriptsAndStyles(): bool
    {
        return $this->mercadopago->hooks->gateway->isEnabled($this) &&
               ! $this->mercadopago->helpers->url->validateQueryVar('order-received');
    }

    /**
     * Load research component
     *
     * @return void
     */
    public function loadResearchComponent(): void
    {
        $this->mercadopago->hooks->gateway->registerAfterSettingsCheckout(
            'admin/components/research-fields.php',
            [
                [
                    'field_key'   => 'mp-public-key-prod',
                    'field_value' => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
                ],
                [
                    'field_key'   => 'reference',
                    'field_value' => '{"mp-screen-name":"' . $this->getCheckoutName() . '"}',
                ]
            ]
        );
    }

    /**
     * Load melidata script on store
     *
     * @return void
     */
    public function loadMelidataStoreScripts(): void
    {
        $this->mercadopago->hooks->checkout->registerBeforePay(function () {
            $this->mercadopago->hooks->scripts->registerMelidataStoreScript('/woocommerce_pay');
        });

        $this->mercadopago->hooks->checkout->registerBeforeCheckoutForm(function () {
            $this->mercadopago->hooks->scripts->registerMelidataStoreScript('/checkout');
        });

        $this->mercadopago->hooks->checkout->registerPayOrderBeforeSubmit(function () {
            $this->mercadopago->hooks->scripts->registerMelidataStoreScript('/pay_order');
        });

        $this->mercadopago->hooks->gateway->registerBeforeThankYou(function ($orderId) {
            $order         = wc_get_order($orderId);
            $paymentMethod = $order->get_payment_method();

            foreach ($this->mercadopago->storeConfig->getAvailablePaymentGateways() as $gateway) {
                if ($gateway::ID === $paymentMethod) {
                    $this->mercadopago->hooks->scripts->registerMelidataStoreScript('/thankyou', $paymentMethod);
                }
            }
        });
    }

    /**
     * Process if result is fail
     *
     * @param Exception $e
     * @param string $message
     * @param string $source
     * @param array $context
     * @param bool $notice
     *
     * @return array
     */
    public function processReturnFail(Exception $e, string $message, string $source, array $context = [], bool $notice = false): array
    {
        if ($e instanceof MockeryExceptionInterface) {
            throw $e;
        }

        $this->mercadopago->logs->file->error("Message: {$e->getMessage()} \n\n\nStacktrace: {$e->getTraceAsString()} \n\n\n", $source, $context);

        $errorMessages = [
            "400"                              => $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'],
            "exception"                        => $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'],
            "Invalid test user email"          => $this->mercadopago->storeTranslations->commonMessages['invalid_users'],
            "Invalid users involved"           => $this->mercadopago->storeTranslations->commonMessages['invalid_users'],
            "Invalid operators users involved" => $this->mercadopago->storeTranslations->commonMessages['invalid_operators'],
            "Invalid card_number_validation"   => $this->mercadopago->storeTranslations->customCheckout['card_number_validation_error'],
            "cho_form_error"                   => $this->mercadopago->storeTranslations->commonMessages['cho_form_error'],
            'buyer_default'                    => $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default']
        ];

        $messageFound = false;
        foreach ($errorMessages as $keyword => $replacement) {
            if (strpos($message, $keyword) !== false) {
                $message = $replacement;
                $messageFound = true;
                break;
            }
        }

        if (!$messageFound) {
            $message = $errorMessages['exception'];
        }

        if ($notice) {
            $this->mercadopago->helpers->notices->storeNotice($message, 'error');
        }

        return [
            'result'   => 'fail',
            'redirect' => '',
            'message'  => $message,
        ];
    }

    /**
     * Register plugin and commission to WC_Cart fees
     *
     * @return void
     * @throws Exception
     */
    public function registerDiscountAndCommissionFeesOnCart()
    {
        if ($this->mercadopago->hooks->checkout->isCheckout()) {
            $this->mercadopago->helpers->cart->addDiscountAndCommissionOnFees($this);
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getFeeTitle(): string
    {
        if ($this->mercadopago->helpers->cart->isAvailable()) {
            $discount   = $this->mercadopago->helpers->cart->calculateSubtotalWithDiscount($this);
            $commission = $this->mercadopago->helpers->cart->calculateSubtotalWithCommission($this);

            return $this->mercadopago->hooks->gateway->buildTitleWithDiscountAndCommission(
                $discount,
                $commission,
                $this->mercadopago->storeTranslations->commonCheckout['discount_title'],
                $this->mercadopago->storeTranslations->commonCheckout['fee_title']
            );
        }

        return '';
    }

    /**
     * Get actionable component value
     *
     * @param string $optionName
     * @param mixed $default
     *
     * @return string
     */
    public function getActionableValue(string $optionName, $default): string
    {
        $active = $this->mercadopago->hooks->options->getGatewayOption($this, "{$optionName}_checkbox");

        if ($active === 'yes') {
            return $this->mercadopago->hooks->options->getGatewayOption($this, $optionName, $default);
        }

        return $default;
    }

    /**
     * Get fee text
     *
     * @param string $text
     * @param string $feeName
     * @param float $feeValue
     *
     * @return string
     */
    public function getFeeText(string $text, string $feeName, float $feeValue): string
    {
        $total = Numbers::formatWithCurrencySymbol($this->mercadopago->helpers->currency->getCurrencySymbol(), $feeValue);

        return "$text $feeName% = $total";
    }

    /**
     * Get amount
     *
     * @return float
     * @throws Exception
     */
    protected function getAmount(): float
    {
        // WC_Cart is null when blocks is loaded on the admin
        if (! $this->mercadopago->helpers->cart->isAvailable()) {
            return 0.00;
        }

        $total = $this->mercadopago->helpers->cart->calculateTotalWithDiscountAndCommission($this);

        if ($this->mercadopago->helpers->url->validateGetVar('pay_for_order')) {
            $orderId = sanitize_key(get_query_var('order-pay'));
            $currentOrder = wc_get_order($orderId);
            $total = (float) $currentOrder->get_total();
        }

        return $total;
    }

    /**
     * Generate custom toggle switch component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_toggle_switch_html(string $key, array $settings): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/toggle-switch.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => $this->mercadopago->hooks->options->getGatewayOption($this, $key, $settings['default']),
                'settings'    => $settings,
            ]
        );
    }

    /**
     * Generate custom toggle switch component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_checkbox_list_html(string $key, array $settings): string
    {

        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/checkbox-list.php',
            [
                'field_key' => $this->get_field_key($key),
                'settings'  => $settings,
            ]
        );
    }

    /**
     * Generate custom header component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_config_title_html(string $key, array $settings): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/config-title.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => null,
                'settings'    => $settings,
            ]
        );
    }

    /**
     * Generating custom actionable input component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_actionable_input_html(string $key, array $settings): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/actionable-input.php',
            [
                'field_key'          => $this->get_field_key($key),
                'field_key_checkbox' => $this->get_field_key($key . '_checkbox'),
                'field_value'        => $this->mercadopago->hooks->options->getGatewayOption($this, $key),
                'enabled'            => $this->mercadopago->hooks->options->getGatewayOption($this, $key . '_checkbox'),
                'custom_attributes'  => $this->get_custom_attribute_html($settings),
                'settings'           => $settings,
                'allowedHtmlTags'    => $this->mercadopago->helpers->strings->getAllowedHtmlTags(),
            ]
        );
    }

    /**
     * Generating custom card info component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_card_info_html(string $key, array $settings): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/card-info.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => null,
                'settings'    => $settings,
            ]
        );
    }

    /**
     * Generating custom preview component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_preview_html(string $key, array $settings): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/preview.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => null,
                'settings'    => $settings,
            ]
        );
    }

    /**
     * Generating support link component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_support_link_html(string $key, array $settings): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/support-link.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => null,
                'settings'    => $settings,
            ]
        );
    }

    /**
     * Generating tooltip selection component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_tooltip_selection_html(string $key, array $settings): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/tooltip-selection.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => null,
                'settings'    => $settings,
            ]
        );
    }

    /**
     * Update Option
     *
     * @param string $key key.
     * @param string $value value.
     *
     * @return bool
     */
    public function update_option($key, $value = ''): bool
    {
        if ($key === 'enabled' && $value === 'yes') {
            $publicKey   = $this->mercadopago->sellerConfig->getCredentialsPublicKey();
            $accessToken = $this->mercadopago->sellerConfig->getCredentialsAccessToken();

            if (empty($publicKey) || empty($accessToken)) {
                $this->mercadopago->logs->file->error(
                    "No credentials to enable payment method",
                    "MercadoPago_AbstractGateway"
                );

                echo wp_json_encode(
                    array(
                        'data'    => $this->mercadopago->adminTranslations->gatewaysSettings['empty_credentials'],
                        'success' => false,
                    )
                );

                die();
            }
        }

        return parent::update_option($key, $value);
    }

    /**
     * Handle With Rejectec Payment Status
     *
     * @param $response
     *
     * @throws RejectedPaymentException
     */
    public function handleWithRejectPayment($response)
    {
        if ($response['status'] === 'rejected') {
            $statusDetail = $response['status_detail'];

            $errorMessage = $this->getRejectedPaymentErrorMessage($statusDetail);

            throw new RejectedPaymentException($errorMessage);
        }
    }

    /**
     * Get payment rejected error message
     *
     * @param string $statusDetail statusDetail.
     *
     * @return string
     */
    public function getRejectedPaymentErrorMessage(string $statusDetail): string
    {
        return $this->mercadopago->storeTranslations->buyerRefusedMessages[ 'buyer_' . $statusDetail ] ??
               $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'];
    }

    /**
     * Get url admin settings page
     *
     * @return string
     */
    public function get_connection_url(): string
    {
        return $this->links['admin_settings_page'];
    }

    /**
     * Get url payment method settings page
     *
     * @return string
     */
    public function get_settings_url(): string
    {
        return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($this->id));
    }

    /**
     * Get amount and currency
     *
     * @param string $key 'amount' or 'currency' to get just one value
     *
     * @return array|float|null
     */
    protected function getAmountAndCurrency(?string $key = null)
    {
        $currencyRatio = 0;
        $amount        = null;
        try {
            $currencyRatio = $this->mercadopago->helpers->currency->getRatio($this);
            $amount        = $this->getAmount();
        } catch (Exception $e) {
            $this->mercadopago->logs->file->warning(
                "Mercado pago gave error to call getRatio: {$e->getMessage()}",
                self::LOG_SOURCE
            );
        }

        return $key ? $$key : compact('currencyRatio', 'amount');
    }

    /**
     * If the seller has valid credentials, it returns an array of an empty $form_fields field.
     * If not, then it returns a warning to inform the seller must update their credentials to be able to sell.
     *
     * @return array
     */
    protected function getCredentialValidationNoticeOrHidden(): array
    {
        if ($this->mercadopago->sellerConfig->isValidCredential()) {
            return [
                'type'  => 'title',
                'value' => '',
            ];
        }

        return [
            'type'  => 'mp_card_info',
            'value' => [
                'title'       => $this->mercadopago->adminTranslations->credentialsSettings['title_invalid_credentials'],
                'subtitle'    => $this->mercadopago->adminTranslations->credentialsSettings['subtitle_invalid_credentials'],
                'button_text' => $this->mercadopago->adminTranslations->credentialsSettings['button_invalid_credentials'],
                'button_url'  => $this->links['admin_settings_page'],
                'icon'        => 'mp-icon-badge-warning',
                'color_card'  => 'mp-alert-color-error',
                'size_card'   => 'mp-card-body-size',
                'target'      => '_blank',
            ]
        ];
    }

    /**
     * If the seller has valid credentials, it returns an array of an empty $form_fields field.
     * If not, then it returns a warning to inform the seller must update their credentials to be able to sell.
     *
     * @return array
     */
    protected function getCredentialExpiredNotice(): array
    {
        $result = [
            'type'  => 'title',
            'value' => '',
        ];

        if (
            !$this->mercadopago->hooks->admin->isAdmin() ||
            !$this->mercadopago->helpers->url->validatePage('wc-settings') ||
            !$this->mercadopago->helpers->url->validateSection($this->id)
        ) {
            return $result;
        }

        $cached_result = get_transient('mp_credentials_expired_result');
        if ($cached_result !== false && !empty($cached_result)) {
            return $cached_result;
        }

        $publicKeyProd = $this->mercadopago->sellerConfig->getCredentialsPublicKeyProd();

        if ($this->mercadopago->sellerConfig->isExpiredPublicKey($publicKeyProd)) {
            $result = [
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => $this->mercadopago->adminTranslations->credentialsSettings['title_invalid_credentials'],
                    'subtitle'    => $this->mercadopago->adminTranslations->credentialsSettings['subtitle_invalid_credentials'],
                    'button_text' => $this->mercadopago->adminTranslations->credentialsSettings['button_invalid_credentials'],
                    'button_url'  => $this->links['admin_settings_page'],
                    'icon'        => 'mp-icon-badge-warning',
                    'color_card'  => 'mp-alert-color-error',
                    'size_card'   => 'mp-card-body-size',
                    'target'      => '_blank',
                ]
            ];
        }

        set_transient('mp_credentials_expired_result', $result, 3600);
        return $result;
    }

    /**
     * Process refund
     *
     * @param int $order_id
     * @param float|null $amount
     * @param string $reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        try {
            $order = wc_get_order($order_id);

            $refundHandler = new \MercadoPago\Woocommerce\Refund\RefundHandler(
                $this->mercadopago->helpers->requester,
                $order,
                $this->mercadopago
            );

            $refundHandler->processRefund($amount, $reason);

            return true;
        } catch (RefundException $e) {
            $responseData = $e->getResponseData();
            $refundStatusCodes = new RefundStatusCodes($this->mercadopago->adminTranslations);
            $userMessage = $refundStatusCodes->getUserMessage($e->getHttpStatusCode() ?? 0, $responseData);

            return new WP_Error('refund_error', $userMessage);
        } catch (Exception $e) {
            return new WP_Error(
                'refund_error',
                $this->mercadopago->adminTranslations->refund[$e->getMessage()] ??
                $this->mercadopago->adminTranslations->refund['unknown_error']
            );
        }
    }

    public function getEnabled(): bool
    {
        return $this->get_option(static::ENABLED_OPTION, static::ENABLED_DEFAULT) === "yes";
    }
}
