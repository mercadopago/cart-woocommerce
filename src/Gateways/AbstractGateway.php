<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;
use MercadoPago\Woocommerce\WoocommerceMercadoPago;
use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;
use MercadoPago\Woocommerce\Notification\NotificationFactory;

abstract class AbstractGateway extends \WC_Payment_Gateway implements MercadoPagoGatewayInterface
{
    /**
     * @const
     */
    public const CHECKOUT_NAME = '';

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

        $this->mercadopago     = $mercadopago;
        $this->discount        = $this->getActionableValue('discount', 0);
        $this->commission      = $this->getActionableValue('commission', 0);
        $this->checkoutCountry = $this->mercadopago->store->getCheckoutCountry();
        $this->countryConfigs  = $this->mercadopago->country->getCountryConfigs();
        $this->links           = $this->mercadopago->links->getLinks();
        $this->has_fields      = true;
        $this->supports        = ['products', 'refunds'];

        $this->loadResearchComponent();
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
     * @throws \WC_Data_Exception
     */
    public function process_payment($order_id): array
    {
        global $woocommerce;

        $order              = wc_get_order($order_id);
        $amount             = $woocommerce->cart->get_subtotal();
        $shipping           = floatval($order->get_shipping_total());
        $discount           = ($amount - $shipping) * $this->discount / 100;
        $commission         = $amount * ($this->commission / 100);
        $isTestModeSelected = 'no' === $this->mercadopago->store->getCheckboxCheckoutTestMode() ? 'yes' : 'no';

        if (method_exists($order, 'update_meta_data')) {
            $this->mercadopago->orderMetadata->setIsProductionModeData($order, $isTestModeSelected);
            $this->mercadopago->orderMetadata->setUsedGatewayData($order, get_class($this));

            if (!empty($this->discount)) {
                $feeTranslation = $this->mercadopago->storeTranslations->commonCheckout['discount_title'];
                $feeText = $this->getFeeText($feeTranslation, 'discount', $discount);
                $this->mercadopago->orderMetadata->setDiscountData($order, $feeText);

                $order->set_total($amount - $discount);
            }

            if (!empty($this->commission)) {
                $feeTranslation = $this->mercadopago->storeTranslations->commonCheckout['fee_title'];
                $feeText = $this->getFeeText($feeTranslation, 'commission', $commission);
                $this->mercadopago->orderMetadata->setCommissionData($order, $feeText);
            }

            $order->save();
        } else {
            $this->mercadopago->orderMetadata->setUsedGatewayPost($order_id, get_class($this));

            if (!empty($this->discount)) {
                $feeTranslation = $this->mercadopago->storeTranslations->commonCheckout['discount_title'];
                $feeText = $this->getFeeText($feeTranslation, 'discount', $discount);
                $this->mercadopago->orderMetadata->setDiscountPost($order_id, $feeText);

                $order->set_total($amount - $discount);
            }

            if (!empty($this->commission)) {
                $feeTranslation = $this->mercadopago->storeTranslations->commonCheckout['fee_title'];
                $feeText = $this->getFeeText($feeTranslation, 'commission', $commission);
                $this->mercadopago->orderMetadata->setCommissionPost($order_id, $feeText);
            }
        }

        return [];
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
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
    {
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
     * Check if admin scripts and styles can be loaded
     *
     * @param string $gatewaySection
     *
     * @return bool
     */
    public function canAdminLoadScriptsAndStyles(string $gatewaySection): bool
    {
        return $this->mercadopago->admin->isAdmin() && (
            $this->mercadopago->url->validatePage('wc-settings') &&
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
            !$this->mercadopago->url->validateQueryVar('order-received');
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
                'settings'    => $settings,
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
     * Get actionable component value
     *
     * @param $optionName
     * @param $default
     *
     * @return string
     */
    public function getActionableValue($optionName, $default): string
    {
        $active = $this->mercadopago->options->getGatewayOption($this, "{$optionName}_checkbox", false);
        return $active ? $this->mercadopago->options->getGatewayOption($this, $optionName, $default) : $default;
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
        return "$text $this->$feeName% / $text = $feeValue";
    }

    /**
     * Get amount
     *
     * @return float
     */
    protected function getAmount(): float
    {
        $total      = $this->get_order_total();
        $subtotal   = $this->mercadopago->woocommerce->cart->get_subtotal();
        $tax        = $total - $subtotal;
        $discount   = $subtotal * ($this->discount / 100);
        $commission = $subtotal * ($this->commission / 100);
        $amount     = $subtotal - $discount + $commission;

        return $amount + $tax;
    }

    /**
     * Process if result is fail
     *
     * @param $function
     * @param $logMessage
     * @param string $noticeMessage
     * @return array
     */
    public function processReturnFail($function, $logMessage, string $noticeMessage = ''): array
    {
        $this->mercadopago->logs->file->error($logMessage, $function);

        if ($noticeMessage == '') {
            $noticeMessage = $logMessage;
        }

        $this->mercadopago->notices->storeNotice($noticeMessage, 'error');

        return [
            'result'   => 'fail',
            'redirect' => '',
        ];
    }

    /**
     * Receive gateway webhook notifications
     *
     * @var string $gateway
     *
     * @return void
     */
    public function webhook(): void
    {
        $data    = $_GET;
        $gateway = get_class($this);

        $notificationFactory = new NotificationFactory();
        $notificationHandler = $notificationFactory->createNotificationHandler($gateway, $data);

        $notificationHandler->handleReceivedNotification();
    }
}
