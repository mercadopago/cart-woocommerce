<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Notification\NotificationFactory;

abstract class AbstractGateway extends \WC_Payment_Gateway implements MercadoPagoGatewayInterface
{
    /**
     * @const
     */
    public const ID = '';

    /**
     * @const
     */
    public const CHECKOUT_NAME = '';

    /**
     * @const
     */
    public const WEBHOOK_API_NAME = '';

    /**
     * @const
     */
    public const LOG_SOURCE = '';

    /**
     * @var WoocommerceMercadoPago
     */
    protected $mercadopago;

    /**
     * Transaction
     *
     * @var Payment|Preference
     */
    protected $transaction;

    /**
     * Commission
     *
     * @var int
     */
    public $commission;

    /**
     * Discount
     *
     * @var int
     */
    public $discount;

    /**
     * Expiration date
     *
     * @var int
     */
    public $expirationDate;

    /**
     * Checkout country
     *
     * @var string
     */
    public $checkoutCountry;

    /**
     * Translations
     *
     * @var array
     */
    protected $adminTranslations;

    /**
     * Translations
     *
     * @var array
     */
    protected $storeTranslations;

    /**
     * @var float
     */
    protected $ratio;

    /**
     * @var array
     */
    protected $countryConfigs;

    /**
     * @var array
     */
    protected $links;

    /**
     * Abstract Gateway constructor
     */
    public function __construct()
    {
        global $mercadopago;

        $this->mercadopago = $mercadopago;

        $this->checkoutCountry = $this->mercadopago->store->getCheckoutCountry();
        $this->countryConfigs  = $this->mercadopago->country->getCountryConfigs();
        $this->ratio           = $this->mercadopago->currency->getRatio($this);
        $this->links           = $this->mercadopago->links->getLinks();

        $this->has_fields = true;
        $this->supports   = ['products', 'refunds'];

        $this->loadResearchComponent();
        $this->loadMelidataStoreScripts();
    }

    public function saveOrderPaymentsId(string $orderId)
    {
        $order = wc_get_order($orderId);
        $paymentIds = Form::sanitizeTextFromGet('payment_id');

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
        if (empty($this->mercadopago->seller->getCredentialsPublicKey()) || empty($this->mercadopago->seller->getCredentialsAccessToken())) {
            $this->form_fields = [
                'card_info_validate' => [
                    'type'  => 'mp_card_info',
                    'value' => [
                        'title'       => $this->mercadopago->adminTranslations->credentialsSettings['card_info_title'],
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
        if ($this->mercadopago->seller->getHomologValidate()) {
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
            $this->mercadopago->scripts->registerAdminScript(
                'wc_mercadopago_admin_components',
                $this->mercadopago->url->getPluginFileUrl('assets/js/admin/mp-admin-configs', '.js')
            );

            $this->mercadopago->scripts->registerAdminStyle(
                'wc_mercadopago_admin_components',
                $this->mercadopago->url->getPluginFileUrl('assets/css/admin/mp-admin-configs', '.css')
            );
        }

        if ($this->canCheckoutLoadScriptsAndStyles()) {
            $this->mercadopago->scripts->registerCheckoutScript(
                'wc_mercadopago_checkout_components',
                $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/mp-plugins-components', '.js')
            );

            $this->mercadopago->scripts->registerCheckoutStyle(
                'wc_mercadopago_checkout_components',
                $this->mercadopago->url->getPluginFileUrl('assets/css/checkouts/mp-plugins-components', '.css')
            );
        }
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
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        $ratio            = $this->mercadopago->currency->getRatio($this);
        $currency         = $this->mercadopago->country->getCountryConfigs()['currency'];
        $isProductionMode = $this->mercadopago->store->getProductionMode();

        $cartSubtotal    = $this->mercadopago->woocommerce->cart->get_cart_contents_total();
        $cartSubtotalTax = $this->mercadopago->woocommerce->cart->get_cart_contents_tax();
        $subtotal        = $cartSubtotal + $cartSubtotalTax;

        $discount = $subtotal * $this->discount / 100;
        $discount = Numbers::calculateByCurrency($currency, $discount, $ratio);

        $commission = $subtotal * ($this->commission / 100);
        $commission = Numbers::calculateByCurrency($currency, $commission, $ratio);

        $this->mercadopago->orderMetadata->setIsProductionModeData($order, $isProductionMode);
        $this->mercadopago->orderMetadata->setUsedGatewayData($order, get_class($this)::ID);

        if ($this->discount != 0) {
            $translation = $this->mercadopago->storeTranslations->commonCheckout['discount_title'];
            $feeText     = $this->getFeeText($translation, 'discount', $discount);

            $this->mercadopago->orderMetadata->setDiscountData($order, $feeText);
        }

        if ($this->commission != 0) {
            $translation = $this->mercadopago->storeTranslations->commonCheckout['fee_title'];
            $feeText     = $this->getFeeText($translation, 'commission', $commission);

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
        $data = Form::sanitizeFromData($_GET);

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
        return $this->mercadopago->admin->isAdmin() && ($this->mercadopago->url->validatePage('wc-settings') &&
            $this->mercadopago->url->validateSection($gatewaySection)
        );
    }

    /**
     * Check if admin scripts and styles can be loaded
     *
     * @return bool
     */
    public function canCheckoutLoadScriptsAndStyles(): bool
    {
        return $this->mercadopago->checkout->isCheckout() &&
            $this->mercadopago->gateway->isEnabled($this) &&
            !$this->mercadopago->url->validateQueryVar('order-received');
    }

    /**
     * Load research component
     *
     * @return void
     */
    public function loadResearchComponent(): void
    {
        $this->mercadopago->gateway->registerAfterSettingsCheckout(
            'admin/components/research-fields.php',
            [
                [
                    'field_key'   => 'mp-public-key-prod',
                    'field_value' => $this->mercadopago->seller->getCredentialsPublicKey(),
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
        $this->mercadopago->checkout->registerBeforePay(function () {
            $this->mercadopago->scripts->registerMelidataStoreScript('/woocommerce_pay');
        });

        $this->mercadopago->checkout->registerBeforeCheckoutForm(function () {
            $this->mercadopago->scripts->registerMelidataStoreScript('/checkout');
        });

        $this->mercadopago->checkout->registerPayOrderBeforeSubmit(function () {
            $this->mercadopago->scripts->registerMelidataStoreScript('/pay_order');
        });

        $this->mercadopago->gateway->registerBeforeThankYou(function ($orderId) {
            $order         = wc_get_order($orderId);
            $paymentMethod = $order->get_payment_method();

            foreach ($this->mercadopago->store->getAvailablePaymentGateways() as $gateway) {
                if ($gateway::ID === $paymentMethod) {
                    $this->mercadopago->scripts->registerMelidataStoreScript('/thankyou', $paymentMethod);
                }
            }
        });
    }

    /**
     * Process if result is fail
     *
     * @param string $message
     * @param string $source
     * @param array $context
     * @param bool $notice
     *
     * @return array
     */
    public function processReturnFail(\Exception $e, string $message, string $source, array $context = [], bool $notice = false): array
    {
        $this->mercadopago->logs->file->error($e->getMessage(), $source, $context);

        if ($notice) {
            $this->mercadopago->notices->storeNotice($message, 'error');
        }

        return [
            'result'   => 'fail',
            'redirect' => '',
        ];
    }

    /**
     * Register commission and discount on admin order totals
     *
     * @param AbstractGateway $gateway
     * @param int $orderId
     *
     * @return void
     */
    public function registerCommissionAndDiscount(AbstractGateway $gateway, int $orderId): void
    {
        $order       = wc_get_order($orderId);
        $usedGateway = $this->mercadopago->orderMetadata->getUsedGatewayData($order);

        if ($gateway::ID === $usedGateway) {
            $discount   = explode('=', $this->mercadopago->orderMetadata->getDiscountData($order))[0];
            $commission = explode('=', $this->mercadopago->orderMetadata->getCommissionData($order))[0];

            if ($commission) {
                $this->mercadopago->template->getWoocommerceTemplate(
                    'admin/order/generic-note.php',
                    [
                        'tip'   => $this->mercadopago->adminTranslations->order['order_note_commission_tip'],
                        'title' => $this->mercadopago->adminTranslations->order['order_note_commission_title'],
                        'value' => $commission,
                    ]
                );
            }

            if ($discount) {
                $this->mercadopago->template->getWoocommerceTemplate(
                    'admin/order/generic-note.php',
                    [
                        'tip'   => $this->mercadopago->adminTranslations->order['order_note_discount_tip'],
                        'title' => $this->mercadopago->adminTranslations->order['order_note_discount_title'],
                        'value' => $discount,
                    ]
                );
            }
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
     * Get actionable component value
     *
     * @param string $optionName
     * @param mixed $default
     *
     * @return string
     */
    public function getActionableValue(string $optionName, $default): string
    {
        $active = $this->mercadopago->options->getGatewayOption($this, "{$optionName}_checkbox");

        if ($active === 'yes') {
            return $this->mercadopago->options->getGatewayOption($this, $optionName, $default);
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
        $total = Numbers::formatWithCurrencySymbol($this->mercadopago->currency->getCurrencySymbol(), $feeValue);
        return "$text {$this->$feeName}% = $total";
    }

    /**
     * Get amount
     *
     * @return float
     */
    protected function getAmount(): float
    {
        $cartTotal       = $this->mercadopago->woocommerce->cart->__get('total');
        $cartSubtotal    = $this->mercadopago->woocommerce->cart->get_subtotal();
        $cartSubtotalTax = $this->mercadopago->woocommerce->cart->get_subtotal_tax();

        $subtotal = $cartSubtotal + $cartSubtotalTax;
        $total    = $cartTotal - $subtotal;

        $discount   = $subtotal * ($this->discount / 100);
        $commission = $subtotal * ($this->commission / 100);
        $amount     = $subtotal - $discount + $commission;

        $calculatedTotal = $total + $amount;

        return Numbers::calculateByCurrency($this->countryConfigs['currency'], $calculatedTotal, $this->ratio);
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
        return $this->mercadopago->template->getWoocommerceTemplateHtml(
            'admin/components/toggle-switch.php',
            [
                'field_key'   => $this->get_field_key($key),
                'field_value' => $this->mercadopago->options->getGatewayOption($this, $key, $settings['default']),
                'settings'    => $settings,
            ]
        );
    }

    /**
     * Generate custom toggle switch component
     *
     * @param string $key
     * @param array  $settings
     *
     * @return string
     */
    public function generate_mp_checkbox_list_html(string $key, array $settings): string
    {
        return $this->mercadopago->template->getWoocommerceTemplateHtml(
            'admin/components/checkbox-list.php',
            [
                'settings' => $settings,
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
        return $this->mercadopago->template->getWoocommerceTemplateHtml(
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
        return $this->mercadopago->template->getWoocommerceTemplateHtml(
            'admin/components/actionable-input.php',
            [
                'field_key'          => $this->get_field_key($key),
                'field_key_checkbox' => $this->get_field_key($key . '_checkbox'),
                'field_value'        => $this->mercadopago->options->getGatewayOption($this, $key),
                'enabled'            => $this->mercadopago->options->getGatewayOption($this, $key . '_checkbox'),
                'custom_attributes'  => $this->get_custom_attribute_html($settings),
                'settings'           => $settings,
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
        return $this->mercadopago->template->getWoocommerceTemplateHtml(
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
        return $this->mercadopago->template->getWoocommerceTemplateHtml(
            'admin/components/preview.php',
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
            $publicKey   = $this->mercadopago->seller->getCredentialsPublicKey();
            $accessToken = $this->mercadopago->seller->getCredentialsAccessToken();

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
}
