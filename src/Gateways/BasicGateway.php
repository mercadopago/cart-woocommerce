<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Transactions\BasicTransaction;

if (!defined('ABSPATH')) {
    exit;
}

class BasicGateway extends AbstractGateway
{
    /**
     * @const
     */
    public const ID = 'woo-mercado-pago-basic';

    /**
     * @const
     */
    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Basic_Gateway';

    /**
     * @const
     */
    public const LOG_SOURCE = 'MercadoPago_BasicGateway';

    /**
     * BasicGateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->basicGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->basicCheckout;

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

        $this->mercadopago->hooks->checkout->registerReceipt($this->id, [$this, 'renderOrderForm']);
        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);

        $this->mercadopago->hooks->cart->registerCartCalculateFees([$this, 'registerDiscountAndCommissionFeesOnCart']);

        $this->mercadopago->helpers->currency->handleCurrencyNotices($this);
    }

    public function getCheckoutName(): string
    {
        return 'checkout-basic';
    }

    public function formFieldsMainSection(): array
    {
        $successUrl = $this->mercadopago->hooks->options->getGatewayOption($this, 'success_url');
        $failureUrl = $this->mercadopago->hooks->options->getGatewayOption($this, 'failure_url');
        $pendingUrl = $this->mercadopago->hooks->options->getGatewayOption($this, 'pending_url');

        return [
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
            'ex_payments'  => $this->generateExPaymentsFields(),
            'installments' => [
                'type'        => 'select',
                'title'       => $this->adminTranslations['installments_title'],
                'description' => $this->adminTranslations['installments_description'],
                'default'     => '24',
                'options'     => [
                    '1'  => $this->adminTranslations['installments_options_1'],
                    '2'  => $this->adminTranslations['installments_options_2'],
                    '3'  => $this->adminTranslations['installments_options_3'],
                    '4'  => $this->adminTranslations['installments_options_4'],
                    '5'  => $this->adminTranslations['installments_options_5'],
                    '6'  => $this->adminTranslations['installments_options_6'],
                    '10' => $this->adminTranslations['installments_options_10'],
                    '12' => $this->adminTranslations['installments_options_12'],
                    '15' => $this->adminTranslations['installments_options_15'],
                    '18' => $this->adminTranslations['installments_options_18'],
                    '24' => $this->adminTranslations['installments_options_24'],
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
            'method' => [
                'type'        => 'select',
                'title'       => $this->adminTranslations['method_title'],
                'description' => $this->adminTranslations['method_description'],
                'default'     => 'redirect',
                'options'     => [
                    'redirect' => $this->adminTranslations['method_options_redirect'],
                    'modal'    => $this->adminTranslations['method_options_modal'],
                ],
            ],
            'auto_return' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['auto_return_title'],
                'subtitle'     => $this->adminTranslations['auto_return_subtitle'],
                'default'      => 'yes',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['auto_return_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['auto_return_descriptions_disabled'],
                ],
            ],
            'success_url' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['success_url_title'],
                'description' => $this->validateBackUrl($successUrl, $this->adminTranslations['success_url_description']),
            ],
            'failure_url' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['failure_url_title'],
                'description' => $this->validateBackUrl($failureUrl, $this->adminTranslations['failure_url_description']),
            ],
            'pending_url' => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['pending_url_title'],
                'description' => $this->validateBackUrl($pendingUrl, $this->adminTranslations['pending_url_description']),
            ],
            'binary_mode' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['binary_mode_title'],
                'subtitle'     => $this->adminTranslations['binary_mode_subtitle'],
                'default'      => $this->adminTranslations['binary_mode_default'],
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['binary_mode_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['binary_mode_descriptions_disabled'],
                ],
            ],
        ];
    }

    /**
     * Register checkout scripts
     *
     * @return void
     */
    public function registerCheckoutScripts(): void
    {
        parent::registerCheckoutScripts();

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_sdk',
            'https://sdk.mercadopago.com/js/v2'
        );
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/checkouts/basic-checkout.php',
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
            'url'             => $this->mercadopago->helpers->url,
            'i18n'            => array_merge($this->mercadopago->storeTranslations->commonCheckout, $this->storeTranslations),
            'links'           => $this->links,
            'method'          => $this->mercadopago->hooks->options->getGatewayOption($this, 'method', 'redirect'),
            'amount'          => $this->getAmountAndCurrency('amount'),
            'site_id'         => $this->countryConfigs['site_id'],
            'test_mode'       => $this->mercadopago->storeConfig->isTestMode(),
            'payment_methods' => $this->getPaymentMethods(),
        ];
    }

    public function proccessPaymentInternal($order): array
    {
        try {
            $this->mercadopago->orderMetadata->markPaymentAsBlocks(
                $order,
                isset($_POST['wc-woo-mercado-pago-basic-new-payment-method']) ? "yes" : "no"
            );

            $method = $this->mercadopago->hooks->options->getGatewayOption($this, 'method', 'redirect');
            if ($method === 'modal') {
                $this->mercadopago->logs->file->info('Preparing to render Checkout Pro view.', self::LOG_SOURCE);
                return [
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_payment_url(true),
                ];
            }

            $this->transaction = new BasicTransaction($this, $order);
            $preference = $this->transaction->createPreference();
            $this->mercadopago->logs->file->info('Customer being redirected to Mercado Pago.', self::LOG_SOURCE);
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
     * Validate Back URL and return error message or default string
     *
     * @param $url
     * @param $default
     *
     * @return string
     */
    private function validateBackUrl($url, $default): string
    {
        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) === false) {
            $icon = $this->mercadopago->helpers->url->getImageAsset('icons/icon-warning');
            return "<img width='14' height='14' style='vertical-align: middle' src='$icon' /> " . $this->adminTranslations['invalid_back_url'];
        }

        return $default;
    }

    /**
     * Get payment methods
     *
     * @return array
     */
    private function getPaymentMethods(): array
    {
        $options = [
            'MLM' => [
                'visa',
                'master',
                'amex',
                'oxxo',
                'clabe',
                'bancomer',
                'account-money',
            ],
            'MLU' => [
                'visa',
                'oca',
                'master',
                'amex',
                'lider',
                'account-money',
            ],
            'MLB' => [
                'pix',
                'master',
                'visa',
                'elo',
                'amex',
                'hipercard',
                'account-money',
            ],
            'ROLA' => [
                'visa',
                'master',
                'amex',
                'naranja',
                'maestro',
                'cabal',
                'account-money',
            ],
        ];

        return array_filter(
            $options[$this->countryConfigs['site_id']] ?? $options['ROLA'],
            fn($method): bool => in_array($method, ['pix', 'account-money']) || $this->mercadopago->hooks->options->getGatewayOption($this, "ex_payments_$method") === 'yes'
        );
    }

    /**
     * Mount payment_methods fields
     *
     * @return array
     */
    private function generateExPaymentsFields(): array
    {
        $exPaymentsFields = [
            'type'                 => 'mp_checkbox_list',
            'title'                => $this->adminTranslations['ex_payments_title'],
            'description'          => $this->adminTranslations['ex_payments_description'],
            'payment_method_types' => $this->setupPaymentMethodTypesList(),
        ];

        return $exPaymentsFields;
    }

    /**
     * Mounts the payment method types list for CHO-PRO config page
     *
     * @return array
     */
    private function setupPaymentMethodTypesList(): array
    {
        $sellerPaymentMethods = $this->mercadopago->hooks->options->get('_checkout_payments_methods');
        if (empty($sellerPaymentMethods)) {
            return [];
        }

        $paymentMethodTypesList = [
            'credit_card' => [
                'list'  => [],
                'label' => $this->adminTranslations['ex_payments_type_credit_card_label'],
            ],
            'debit_card' => [
                'list'  => [],
                'label' => $this->adminTranslations['ex_payments_type_debit_card_label'],
            ],
            'other' => [
                'list'  => [],
                'label' => $this->adminTranslations['ex_payments_type_other_label'],
            ],
        ];

        foreach ($sellerPaymentMethods as $paymentMethod) {
            // We use it to put yape in other because it is not a card payment method,
            // and at the beginning of the list because UX said so.
            if (in_array($paymentMethod['name'], ["yape"])) {
                array_unshift($paymentMethodTypesList['other']['list'], $this->serializePaymentMethod($paymentMethod));
                break;
            }

            switch ($paymentMethod['type']) {
                case 'credit_card':
                    $paymentMethodTypesList['credit_card']['list'][] = $this->serializePaymentMethod($paymentMethod);
                    break;
                case 'debit_card':
                case 'prepaid_card':
                    $paymentMethodTypesList['debit_card']['list'][] = $this->serializePaymentMethod($paymentMethod);
                    break;
                default:
                    $paymentMethodTypesList['other']['list'][] = $this->serializePaymentMethod($paymentMethod);
                    break;
            }
        }

        return $paymentMethodTypesList;
    }

    /**
     * Serialize payment_methods to mount settings fields
     *
     * @param mixed $paymentMethod
     *
     * @return array
     */
    private function serializePaymentMethod($paymentMethod): array
    {
        return [
            'id'        => 'ex_payments_' . $paymentMethod['id'],
            'type'      => 'checkbox',
            'label'     => ucfirst($paymentMethod['name']),
            'value'     => $this->mercadopago->hooks->options->getGatewayOption($this, 'ex_payments_' . $paymentMethod['id'], 'yes'),
            'field_key' => $this->get_field_key('ex_payments_' . $paymentMethod['id']),
        ];
    }

    /**
     * Render order form
     *
     * @param $order_id
     * @throws Exception
     */
    public function renderOrderForm($order_id): void
    {
        $order             = wc_get_order($order_id);
        $this->transaction = new BasicTransaction($this, $order);
        $preference        = $this->transaction->createPreference();

        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/receipt/preference-modal.php',
            [
                'public_key'          => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
                'preference_id'       => $preference['id'],
                'pay_with_mp_title'   => $this->storeTranslations['pay_with_mp_title'],
                'cancel_url'          => $order->get_cancel_order_url(),
                'cancel_url_text'     => $this->storeTranslations['cancel_url_text'],
            ]
        );
    }
}
