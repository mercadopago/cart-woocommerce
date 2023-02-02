<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BasicGateway extends AbstractGateway implements MercadoPagoGatewayInterface
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
     * BasicGateway constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->basicGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->basicCheckout;

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
            'ex_payments'                        => $this->fieldExPayments(),
            'installments'                       => [
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
            'method'                             => [
                'type'        => 'select',
                'title'       => $this->adminTranslations['method_title'],
                'description' => $this->adminTranslations['method_description'],
                'default'     => 'redirect',
                'options'     => [
                    'redirect' => $this->adminTranslations['method_options_redirect'],
                    'modal'    => $this->adminTranslations['method_options_modal'],
                ],
            ],
            'auto_return'                        => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['auto_return_title'],
                'subtitle'     => $this->adminTranslations['auto_return_subtitle'],
                'default'      => 'yes',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['auto_return_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['auto_return_descriptions_disabled'],
                ],
            ],
            'success_url'                        => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['success_url_title'],
                'description' => $this->validateBackUrl($this->settings['success_url'], 'Choose the URL that we will show your customers when they finish their purchase.'),
            ],
            'failure_url'                        => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['failure_url_title'],
                'description' => $this->validateBackUrl($this->settings['failure_url'], 'Choose the URL that we will show to your customers when we refuse their purchase. Make sure it includes a message appropriate to the situation and give them useful information so they can solve it.'),
            ],
            'pending_url'                        => [
                'type'        => 'text',
                'title'       => $this->adminTranslations['pending_url_title'],
                'description' => $this->validateBackUrl($this->settings['pending_url'], 'Choose the URL that we will show to your customers when they have a payment pending approval.'),
            ],
            'binary_mode'                        => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['binary_mode_title'],
                'subtitle'     => $this->adminTranslations['binary_mode_subtitle'],
                'default'      => $this->adminTranslations['binary_mode_default'],
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['binary_mode_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['binary_mode_descriptions_disabled'],
                ],
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
        $paymentMethods        = $this->getPaymentMethods();
        $paymentMethodsTitle   = count($paymentMethods) !== 0 ? $this->storeTranslations['payment_methods_title'] : '';

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/checkouts/basic-checkout.php',
            [
                'test_mode'                        => $this->mercadopago->seller->isTestMode(),
                'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
                'test_mode_description'            => $this->storeTranslations['test_mode_description'],
                'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
                'test_mode_link_src'               => $this->mercadopago->links->getLinks()['docs_integration_test'],
                'checkout_benefits_title'          => $this->storeTranslations['checkout_benefits_title'],
                'checkout_benefits_items'          => wp_json_encode($checkoutBenefitsItems),
                'payment_methods_title'            => $paymentMethodsTitle,
                'payment_methods_methods'          => wp_json_encode($paymentMethods),
                'method'                           => $this->settings['method'],
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
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-phone', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone'],
                    ],
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_wallet'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_wallet'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-wallet', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_wallet'],
                    ],
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_protection'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_protection'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-protection', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_protection'],
                    ],
                ]
            ],
            'MLM' => [
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-phone', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_wallet'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_wallet_2'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-wallet', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_wallet'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_protection'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_protection'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-protection', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_protection'],
                    ]
                ]
            ],
            'MLA' => [
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_wallet'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_wallet_3'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-wallet', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_wallet'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone_installments'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone_installments'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-phone-installments', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone_installments'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_protection_2'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_protection_2'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-protection', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_protection'],
                    ]
                ]
            ],
            'ROLA' => [
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-phone', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_wallet'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_wallet_3'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-wallet', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_wallet'],
                    ]
                ],
                [
                    'title'    => $this->storeTranslations['checkout_benefits_title_phone_installments'],
                    'subtitle' => $this->storeTranslations['checkout_benefits_subtitle_phone_installments_2'],
                    'image'    => [
                        'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/basic/blue-phone-installments', '.png'),
                        'alt' => $this->storeTranslations['checkout_benefits_alt_phone_installments'],
                    ]
                ]
            ],
        ];

        $site = $this->mercadopago->seller->getSiteId();
        return array_key_exists($site, $benefits) ? $benefits[$site] : $benefits['ROLA'];
    }

    /**
     * Get payment methods
     *
     * @return array
     */
    private function getPaymentMethods(): array
    {
        //@TODO change getOption
        $paymentMethodsOptions = get_option('_checkout_payments_methods', '');
        $paymentMethods = [];

        //@TODO add credits helper
        /*
        if ( $this->credits_helper->is_credits() ) {
            $paymentMethods[] = [
                'src' => $this->mercadopago->url->getPluginFileUrl('/assets/images/mercado-credito', '.png'),
                'alt' => 'Credits image'
            ];
        }
        */

        foreach ($paymentMethodsOptions as $paymentMethodsOption) {
            //@TODO change getOption
            if ('yes' === $this->get_option($paymentMethodsOption['config'], '')) {
                $paymentMethods[] = [
                    'src' => $paymentMethodsOption['image'],
                    'alt' => $paymentMethodsOption['id']
                ];
            }
        }

        return $paymentMethods;
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
            return '<img width="14" height="14" src="' . $this->mercadopago->url->getPluginFileUrl('/assets/images/icons/icon-warning', '.png') . '"> ' .
                'This seems to be an invalid URL.';
        }
        return $default;
    }

    //@TODO change getOption

    /**
     * Field payments
     *
     * @return array
     */
    private function fieldExPayments(): array
    {
        $paymentList = [
            'type'                 => 'mp_checkbox_list',
            'title'                => $this->adminTranslations['ex_payments_title'],
            'description'          => $this->adminTranslations['ex_payments_description'],
            'payment_method_types' => [
                'credit_card' => [
                    'label' => $this->adminTranslations['ex_payments_type_credit_card_label'],
                    'list'  => [],
                ],
                'debit_card'  => [
                    'label' => $this->adminTranslations['ex_payments_type_debit_card_label'],
                    'list'  => [],
                ],
                'other'       => [
                    'label' => $this->adminTranslations['ex_payments_type_other_label'],
                    'list'  => [],
                ],
            ],
        ];

        $allPayments = get_option('_checkout_payments_methods', '');

        if (empty($allPayments)) {
            return $paymentList;
        }

        foreach ($allPayments as $paymentMethod) {
            if ('credit_card' === $paymentMethod['type']) {
                $paymentList['payment_method_types']['credit_card']['list'][] = [
                    'id'        => 'ex_payments_' . $paymentMethod['id'],
                    'field_key' => $this->get_field_key('ex_payments_' . $paymentMethod['id']),
                    'label'     => $paymentMethod['name'],
                    'value'     => $this->get_option('ex_payments_' . $paymentMethod['id'], 'yes'),
                    'type'      => 'checkbox',
                ];
            } elseif ('debit_card' === $paymentMethod['type'] || 'prepaid_card' === $paymentMethod['type']) {
                $paymentList['payment_method_types']['debit_card']['list'][] = [
                    'id'        => 'ex_payments_' . $paymentMethod['id'],
                    'field_key' => $this->get_field_key('ex_payments_' . $paymentMethod['id']),
                    'label'     => $paymentMethod['name'],
                    'value'     => $this->get_option('ex_payments_' . $paymentMethod['id'], 'yes'),
                    'type'      => 'checkbox',
                ];
            } else {
                $paymentList['payment_method_types']['other']['list'][] = [
                    'id'        => 'ex_payments_' . $paymentMethod['id'],
                    'field_key' => $this->get_field_key('ex_payments_' . $paymentMethod['id']),
                    'label'     => $paymentMethod['name'],
                    'value'     => $this->get_option('ex_payments_' . $paymentMethod['id'], 'yes'),
                    'type'      => 'checkbox',
                ];
            }
        }

        return $paymentList;
    }
}
