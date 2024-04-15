<?php

namespace MercadoPago\Woocommerce\Gateways;

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
    public const CHECKOUT_NAME = 'checkout-basic';

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
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->basicGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->basicCheckout;

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

        $this->mercadopago->hooks->checkout->registerReceipt($this->id, [$this, 'renderOrderForm']);
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

        $successUrl = $this->mercadopago->hooks->options->getGatewayOption($this, 'success_url');
        $failureUrl = $this->mercadopago->hooks->options->getGatewayOption($this, 'failure_url');
        $pendingUrl = $this->mercadopago->hooks->options->getGatewayOption($this, 'pending_url');

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

        if ($this->canCheckoutLoadScriptsAndStyles()) {
            $this->registerCheckoutScripts();
        }
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
        $checkoutBenefitsItems = $this->getBenefits();
        $paymentMethods        = $this->getPaymentMethods();
        $paymentMethodsTitle   = count($paymentMethods) != 0 ? $this->storeTranslations['payment_methods_title'] : '';

        return [
            'test_mode'                        => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
            'test_mode_description'            => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'               => $this->links['docs_integration_test'],
            'checkout_benefits_title'          => $this->storeTranslations['checkout_benefits_title'],
            'checkout_benefits_items'          => wp_json_encode($checkoutBenefitsItems),
            'payment_methods_title'            => $paymentMethodsTitle,
            'payment_methods_methods'          => wp_json_encode($paymentMethods),
            'method'                           => $this->mercadopago->hooks->options->getGatewayOption($this, 'method', 'redirect'),
            'checkout_redirect_text'           => $this->storeTranslations['checkout_redirect_text'],
            'checkout_redirect_src'            => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/cho-pro-redirect-v2', '.png', true),
            'checkout_redirect_alt'            => $this->storeTranslations['checkout_redirect_alt'],
            'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->links['mercadopago_terms_and_conditions'],
            'fee_title'                        => $this->getFeeTitle(),
        ];
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
        $order             = wc_get_order($order_id);
        try {
            parent::process_payment($order_id);

            if (isset($_POST['wc-woo-mercado-pago-basic-new-payment-method'])) {
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
            } else {
                $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
            }

            $this->transaction = new BasicTransaction($this, $order);
            $method            = $this->mercadopago->hooks->options->getGatewayOption($this, 'method', 'redirect');

            if ($method === 'modal') {
                $this->mercadopago->logs->file->info('Preparing to render Checkout Pro view.', self::LOG_SOURCE);
                return [
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_payment_url(true),
                ];
            }

            $this->mercadopago->logs->file->info('Customer being redirected to Mercado Pago.', self::LOG_SOURCE);
            $preference = $this->transaction->createPreference();
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
            $icon = $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/icons/icon-warning', '.png', true);
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
        $activePaymentMethods  = [];
        $paymentMethodsOptions = $this->mercadopago->sellerConfig->getCheckoutBasicPaymentMethods();

        foreach ($paymentMethodsOptions as $paymentMethodsOption) {
            if ($this->mercadopago->hooks->options->getGatewayOption($this, $paymentMethodsOption['config']) === 'yes') {
                $activePaymentMethods[] = [
                    'src' => $paymentMethodsOption['image'],
                    'alt' => $paymentMethodsOption['id']
                ];
            }
        }

        return $this->mercadopago->helpers->paymentMethods->treatBasicPaymentMethods($activePaymentMethods);
    }

    /**
     * Mount payment_methods fields
     *
     * @return array
     */
    private function generateExPaymentsFields(): array
    {
        $paymentList = [
            'type'                 => 'mp_checkbox_list',
            'title'                => $this->adminTranslations['ex_payments_title'],
            'description'          => $this->adminTranslations['ex_payments_description'],
            'payment_method_types' => [
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
            ],
        ];

        $allPayments = $this->mercadopago->hooks->options->get('_checkout_payments_methods');

        if (empty($allPayments)) {
            return $paymentList;
        }

        foreach ($allPayments as $paymentMethod) {
            switch ($paymentMethod['type']) {
                case 'credit_card':
                    $paymentList['payment_method_types']['credit_card']['list'][] = $this->serializePaymentMethod($paymentMethod);
                    break;

                case 'debit_card':
                case 'prepaid_card':
                    $paymentList['payment_method_types']['debit_card']['list'][] = $this->serializePaymentMethod($paymentMethod);
                    break;

                default:
                    $paymentList['payment_method_types']['other']['list'][] = $this->serializePaymentMethod($paymentMethod);
                    break;
            }
        }

        return $paymentList;
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
            'label'     => $paymentMethod['name'],
            'value'     => $this->mercadopago->hooks->options->getGatewayOption($this, 'ex_payments_' . $paymentMethod['id'], 'yes'),
            'field_key' => $this->get_field_key('ex_payments_' . $paymentMethod['id']),
        ];
    }

    /**
     * Get benefits items
     *
     * @return array
     */
    private function getBenefits(): array
    {
        $benefits = [
            'MLB' => [
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-phone', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone'],
                    ],
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_wallet'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_wallet'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-wallet', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_wallet'],
                    ],
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_protection'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_protection'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-protection', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_protection'],
                    ],
                ]
            ],
            'MLM' => [
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-phone', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_wallet'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_wallet_2'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-wallet', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_wallet'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_protection'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_protection'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-protection', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_protection'],
                    ]
                ]
            ],
            'MLA' => [
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_wallet'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_wallet_3'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-wallet', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_wallet'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone_installments'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone_installments'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-phone-installments', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone_installments'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_protection_2'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_protection_2'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-protection', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_protection'],
                    ]
                ]
            ],
            'ROLA' => [
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-phone', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_wallet'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_wallet_3'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-wallet', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_wallet'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone_installments'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone_installments_2'],
                    'image'    => [
                        'src' => $this->mercadopago->helpers->url->getPluginFileUrl('assets/images/checkouts/basic/blue-phone-installments', '.png', true),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone_installments'],
                    ]
                ]
            ],
        ];

        $site = $this->mercadopago->sellerConfig->getSiteId();

        return array_key_exists($site, $benefits) ? $benefits[$site] : $benefits['ROLA'];
    }

    /**
     * Render order form
     *
     * @param $order_id
     * @throws \Exception
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
