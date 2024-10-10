<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Notification\NotificationFactory;
use MercadoPago\Woocommerce\Exceptions\RejectedPaymentException;
use WC_Payment_Gateway;

abstract class AbstractGateway extends WC_Payment_Gateway implements MercadoPagoGatewayInterface
{
    public const ID = '';

    public const CHECKOUT_NAME = '';

    public const WEBHOOK_API_NAME = '';

    public const LOG_SOURCE = '';

    public string $iconAdmin;

    protected WoocommerceMercadoPago $mercadopago;

    /**
     * Transaction
     *
     * @var Payment|Preference
     */
    protected $transaction;

    public int $commission;

    public int $discount;

    public int $expirationDate;

    public string $checkoutCountry;

    protected array $adminTranslations;

    protected array $storeTranslations;

    protected float $ratio;

    protected array $countryConfigs;

    protected array $links;

    /**
     * Abstract Gateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;

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
            $this->mercadopago->orderMetadata->updatePaymentsOrderMetadata($order, explode(',', $paymentIds));

            return;
        }
        $this->mercadopago->logs->file->info("no payment ids to update", "MercadoPago_AbstractGateway");
    }

    /**
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [];
    }

    /**
     * Add a "missing credentials" notice into the $form_fields array if there ir no credentials configured.
     * Returns true when the notice is added to the array, and false otherwise.
     *
     * @return bool
     */
    protected function addMissingCredentialsNoticeAsFormField(): bool
    {
        if (empty($this->mercadopago->sellerConfig->getCredentialsPublicKey()) || empty($this->mercadopago->sellerConfig->getCredentialsAccessToken())) {
            $this->form_fields = [
                'card_info_validate' => [
                    'type'  => 'mp_card_info',
                    'value' => [
                        'title'       => '',
                        'subtitle'    => $this->mercadopago->adminTranslations->credentialsSettings['card_info_subtitle'],
                        'button_text' => $this->mercadopago->adminTranslations->credentialsSettings['card_info_button_text'],
                        'button_url'  => $this->links['admin_settings_page'],
                        'icon'        => 'mp-icon-badge-warning',
                        'color_card'  => 'mp-alert-color-error',
                        'size_card'   => 'mp-card-body-size',
                        'target'      => '_self',
                    ]
                ]
            ];

            return true;
        }

        return false;
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
            'wc_mercadopago_admin_components',
            $this->mercadopago->helpers->url->getCssAsset('admin/mp-admin-configs')
        );
    }

    /**
     * Register checkout scripts
     *
     * @return void
     */
    public function registerCheckoutScripts(): void
    {
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
        $order = wc_get_order($order_id);

        $discount   = $this->mercadopago->helpers->cart->calculateSubtotalWithDiscount($this);
        $commission = $this->mercadopago->helpers->cart->calculateSubtotalWithCommission($this);

        $isProductionMode = $this->mercadopago->storeConfig->getProductionMode();

        $this->mercadopago->orderMetadata->setIsProductionModeData($order, $isProductionMode);
        $this->mercadopago->orderMetadata->setUsedGatewayData($order, get_class($this)::ID);

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

        return [];
    }

    /**
     * Receive gateway webhook notifications
     *
     * @return void
     */
    public function webhook(): void
    {
        $data = Form::sanitizedGetData();

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
        return $this->mercadopago->hooks->checkout->isCheckout() &&
               $this->mercadopago->hooks->gateway->isEnabled($this) &&
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
        $this->mercadopago->logs->file->error('Message: ' . $e->getMessage() . ' \n\n\n' . 'Stackstrace: ' . $e->getTraceAsString() . ' \n\n\n', $source, $context);

        $errorMessages = [
            "Invalid test user email"          => $this->mercadopago->storeTranslations->commonMessages['invalid_users'],
            "Invalid users involved"           => $this->mercadopago->storeTranslations->commonMessages['invalid_users'],
            "Invalid operators users involved" => $this->mercadopago->storeTranslations->commonMessages['invalid_operators'],
            "exception"                        => $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'],
            "400"                              => $this->mercadopago->storeTranslations->buyerRefusedMessages['buyer_default'],
        ];

        foreach ($errorMessages as $keyword => $replacement) {
            if (strpos($message, $keyword) !== false) {
                $message = $replacement;
                break;
            }
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
     * Get checkout name
     *
     * @return string
     */
    public function getCheckoutName(): string
    {
        return self::CHECKOUT_NAME;
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

        return $this->mercadopago->helpers->cart->calculateTotalWithDiscountAndCommission($this);
    }

    /**
     * Get discount config field
     *
     * @return array
     */
    public function getDiscountField(): array
    {
        return [
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
        ];
    }

    /**
     * Get commission config field
     *
     * @return array
     */
    public function getCommissionField(): array
    {
        return [
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
        ];
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
     * Generating credits checkout example component
     *
     * @param string $key
     * @param array $settings
     *
     * @return string
     */
    public function generate_mp_credits_checkout_example_html(string $key, array $settings): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/credits-checkout-example.php',
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
     * Get url setting
     *
     * @return string
     */
    public function get_settings_url(): string
    {
        return $this->links['admin_settings_page'];
    }

    protected function getAmountAndCurrency(): array
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

        return [
            'currencyRatio' => $currencyRatio,
            'amount'        => $amount,
        ];
    }
}
