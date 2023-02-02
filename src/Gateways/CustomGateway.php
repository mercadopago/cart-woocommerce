<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CustomGateway extends AbstractGateway implements MercadoPagoGatewayInterface
{
    /**
     * @const
     */
    public const ID = 'woo-mercado-pago-custom';

    /**
     * @const
     */
    public const CHECKOUT_NAME = 'checkout-custom';

    /**
     * CustomGateway constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->customGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->customCheckout;

        $this->id = self::ID;
        $this->icon = $this->mercadopago->plugin->getGatewayIcon('icon-gray-card');
        $this->title = $this->mercadopago->options->get('title', $this->adminTranslations['gateway_title']);
        $this->description = $this->adminTranslations['gateway_description'];
        $this->method_title = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];

        $this->init_form_fields();
        $this->init_settings();
        $this->payment_scripts($this->id);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->gateway->registerGatewayTitle($this);
        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
        //$this->mercadopago->checkout->registerReviewOrderBeforePayment([$this, '']); // @todo
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
        parent::init_form_fields();

        if (
            !empty($this->mercadopago->store->getCheckoutCountry()) &&
            !empty($this->mercadopago->seller->getCredentialsPublicKey()) &&
            !empty($this->mercadopago->seller->getCredentialsAccessToken())
        ) {
            $this->form_fields = [
                'header'                             => [
                    'type'        => 'mp_config_title',
                    'title'       => $this->adminTranslations['header_title'],
                    'description' => $this->adminTranslations['gateway_description'],
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
                'card_info_helper'                   => [
                    'type'  => 'title',
                    'value' => '',
                ],
                'card_info_fees'                     => [
                    'type'  => 'mp_card_info',
                    'value' => [
                        'title'       => $this->adminTranslations['card_info_fees_title'],
                        'subtitle'    => $this->adminTranslations['card_info_fees_subtitle'],
                        'button_text' => $this->adminTranslations['card_info_fees_button_url'],
                        'button_url'  => $this->mercadopago->links->getLinks()['mercadopago_costs'],
                        'icon'        => 'mp-icon-badge-info',
                        'color_card'  => 'mp-alert-color-success',
                        'size_card'   => 'mp-card-body-size',
                        'target'      => '_blank',
                    ],
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
                'wallet_button'                      => [
                    'type'         => 'mp_toggle_switch',
                    'title'        => $this->adminTranslations['wallet_button_title'],
                    'subtitle'     => $this->adminTranslations['wallet_button_subtitle'],
                    'default'      => 'yes',
                    'descriptions' => [
                        'enabled'  => $this->adminTranslations['wallet_button_descriptions_enabled'],
                        'disabled' => $this->adminTranslations['wallet_button_descriptions_disabled'],
                    ],
                ],
                'wallet_button_preview'              => [
                    'type'        => 'mp_preview',
                    'description' => $this->adminTranslations['wallet_button_preview_description'],
                    'url'         => $this->getWalletButtonPreviewUrl(),
                ],
                'advanced_configuration_title'       => [
                    'type'  => 'title',
                    'title' => $this->adminTranslations['advanced_configuration_title'],
                    'class' => 'mp-subtitle-body',
                ],
                'advanced_configuration_description' => [
                    'type'  => 'title',
                    'title' => $this->adminTranslations['advanced_configuration_subtitle'],
                    'class' => 'mp-small-text',
                ],
                'coupon_mode'                        => [
                    'type'         => 'mp_toggle_switch',
                    'title'        => $this->adminTranslations['coupon_mode_title'],
                    'default'      => 'no',
                    'subtitle'     => $this->adminTranslations['coupon_mode_subtitle'],
                    'descriptions' => [
                        'enabled'  => $this->adminTranslations['coupon_mode_descriptions_enabled'],
                        'disabled' => $this->adminTranslations['coupon_mode_descriptions_disabled'],
                    ],
                ],
                'binary_mode'                        => [
                    'type'         => 'mp_toggle_switch',
                    'title'        => $this->adminTranslations['binary_mode_title'],
                    'subtitle'     => $this->adminTranslations['binary_mode_subtitle'],
                    'default'      => 'no',
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

        global $woocommerce;
        $countrySuffix  = $this->mercadopago->country->getPluginDefaultCountry();
        $countryConfigs = $this->mercadopago->country->getCountryConfigs($countrySuffix);

        $this->mercadopago->scripts->registerStoreScript(
            'woocommerce-mercadopago-sdk',
            'https://sdk.mercadopago.com/js/v2'
        );

        wp_enqueue_scripts(
            'woocommerce-mercadopago-custom-checkout',
            $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/custom/mp-custom-checkout', '.js')
        );

        wp_localize_script(
            'woocommerce-mercadopago-checkout',
            'wc_mercadopago_params',
            [
                'site_id'              => $countryConfigs['site_id'],
                'currency'             => $countryConfigs['currency'],
                'intl'                 => $countryConfigs['intl'],
                'placeholders'         => [
                    'cardExpirationDate' => $this->storeTranslations['placeholders_card_expiration_date'],
                    'issuer'             => $this->storeTranslations['placeholders_issuer'],
                    'installments'       => $this->storeTranslations['placeholders_installments'],
                ],
                'cvvHint'              => [
                    'back'  => $this->storeTranslations['cvv_hint_back'],
                    'front' => $this->storeTranslations['cvv_hint_front'],
                ],
                'cvvText'              => $this->storeTranslations['cvv_text'],
                'installmentObsFee'    => $this->storeTranslations['installment_obs_fee'],
                'installmentButton'    => $this->storeTranslations['installment_button'],
                'bankInterestText'     => $this->storeTranslations['bank_interest_text'],
                'interestText'         => $this->storeTranslations['interest_text'],
                'input_helper_message' => [
                    'cardNumber'     => [
                        'invalid_type'   => $this->storeTranslations['input_helper_message_invalid_type'],
                        'invalid_length' => $this->storeTranslations['input_helper_message_invalid_length'],
                    ],
                    'cardholderName' => [
                        '221' => $this->storeTranslations['input_helper_message_card_holder_name_221'],
                        '316' => $this->storeTranslations['input_helper_message_card_holder_name_316'],
                    ],
                    'expirationDate' => [
                        'invalid_type'   => $this->storeTranslations['input_helper_message_expiration_date_invalid_type'],
                        'invalid_length' => $this->storeTranslations['input_helper_message_expiration_date_invalid_length'],
                        'invalid_value'  => $this->storeTranslations['input_helper_message_expiration_date_invalid_value'],
                    ],
                    'securityCode'   => [
                        'invalid_type'   => $this->storeTranslations['input_helper_message_security_code_invalid_type'],
                        'invalid_length' => $this->storeTranslations['input_helper_message_security_code_invalid_length'],
                    ]
                ],
                'theme'                => get_stylesheet(),
                'location'             => '/checkout',
                'plugin_version'       => MP_VERSION,
                'platform_version'     => $woocommerce->version,
            ]
        );

        $this->mercadopago->scripts->registerStoreScript(
            'woocommerce-mercadopago-custom-page',
            $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/custom/mp-custom-page', '.js')
        );

        $this->mercadopago->scripts->registerStoreScript(
            'woocommerce-mercadopago-custom-elements',
            $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/custom/mp-custom-elements', '.js')
        );
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->template->getWoocommerceTemplate(
            'public/checkouts/custom-checkout.php',
            [
                'test_mode'                        => $this->mercadopago->seller->isTestMode(),
                'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
                'test_mode_description'            => $this->storeTranslations['test_mode_description'],
                'test_mode_link_text'              => $this->storeTranslations['test_mode_link_text'],
                'test_mode_link_src'               => $this->mercadopago->links->getLinks()['docs_integration_test'],
                'wallet_button'                    => $this->mercadopago->options->get('wallet_button', 'yes'),
                'wallet_button_image'              => $this->mercadopago->url->getPluginFileUrl("/assets/images/icons/icon-logos", '.png'),
                'wallet_button_title'              => $this->storeTranslations['wallet_button_title'],
                'wallet_button_description'        => $this->storeTranslations['wallet_button_description'],
                'wallet_button_button_text'        => $this->storeTranslations['wallet_button_button_text'],
                'available_payments_title_icon'    => $this->mercadopago->url->getPluginFileUrl("/assets/images/icons/icon-purple-card", '.png'),
                'available_payments_title'         => $this->storeTranslations['available_payments_title'],
                'available_payments_image'         => $this->mercadopago->url->getPluginFileUrl("/assets/images/checkouts/custom/chevron-down", '.png'),
                'available_payments_chevron_up'    => $this->mercadopago->url->getPluginFileUrl("/assets/images/checkouts/custom/chevron-up", '.png'),
                'available_payments_chevron_down'  => $this->mercadopago->url->getPluginFileUrl("/assets/images/checkouts/custom/chevron-down", '.png'),
                'payment_methods_items'            => wp_json_encode($this->getPaymentMethodsContent()),
                'payment_methods_promotion_link'   => $this->mercadopago->links->getLinks()['mercadopago_debts'],
                'payment_methods_promotion_text'   => $this->storeTranslations['payment_methods_promotion_text'],
                'site_id'                          => $this->mercadopago->seller->getSiteId() ?: $this->mercadopago->country::SITE_ID_MLA,
                'card_form_title'                  => $this->storeTranslations['card_form_title'],
                'card_number_input_label'          => $this->storeTranslations['card_number_input_label'],
                'card_number_input_helper'         => $this->storeTranslations['card_number_input_helper'],
                'card_holder_name_input_label'     => $this->storeTranslations['card_holder_name_input_label'],
                'card_holder_name_input_helper'    => $this->storeTranslations['card_holder_name_input_helper'],
                'card_expiration_input_label'      => $this->storeTranslations['card_expiration_input_label'],
                'card_expiration_input_helper'     => $this->storeTranslations['card_expiration_input_helper'],
                'card_security_code_input_label'   => $this->storeTranslations['card_security_code_input_label'],
                'card_security_code_input_helper'  => $this->storeTranslations['card_security_code_input_helper'],
                'card_document_input_label'        => $this->storeTranslations['card_document_input_label'],
                'card_document_input_helper'       => $this->storeTranslations['card_document_input_helper'],
                'card_installments_title'          => $this->storeTranslations['card_installments_title'],
                'card_issuer_input_label'          => $this->storeTranslations['card_issuer_input_label'],
                'card_installments_input_helper'   => $this->storeTranslations['card_installments_input_helper'],
                'terms_and_conditions_description' => $this->storeTranslations['terms_and_conditions_description'],
                'terms_and_conditions_link_text'   => $this->storeTranslations['terms_and_conditions_link_text'],
                'terms_and_conditions_link_src'    => $this->mercadopago->links->getLinks()['mercadopago_terms_and_conditions'],
                'amount'                           => $this->getAmount(),
                'currency_ratio'                   => $this->mercadopago->currency->getRatio($this),
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
            'result' => 'success',
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
        $status = 200;
        $response = [
            'status' => $status,
            'message' => 'Webhook handled successful'
        ];

        wp_send_json_success($response, $status);
    }

    /**
     * Get wallet button preview url
     *
     * @return string
     */
    private function getWalletButtonPreviewUrl(): string
    {
        $locale = substr(strtolower(get_locale()), 0, 2);

        if ('pt' !== $locale && 'es' !== $locale) {
            $locale = 'en';
        }

        return $this->mercadopago->url->getPluginFileUrl("/assets/images/gateways/wallet-button/preview-{$locale}", '.png');
    }

    /**
     * Get payment methods to fill in the available payments content
     *
     * @return array
     */
    private function getPaymentMethodsContent(): array
    {
        $debitCard      = [];
        $creditCard     = [];
        $paymentMethods = [];
        $cards          = $this->mercadopago->seller->getCheckoutPaymentMethods();

        foreach ($cards as $card) {
            if ('credit_card' === $card['type']) {
                $creditCard[] = [
                    'src' => $card['image'],
                    'alt' => $card['name']
                ];
            } elseif ('debit_card' === $card['type'] || 'prepaid_card' === $card['type']) {
                $debitCard[] = [
                    'src' => $card['image'],
                    'alt' => $card['name']
                ];
            }
        }


        if (0 !== count($creditCard)) {
            $paymentMethods[] = [
                'title'           => $this->storeTranslations['available_payments_credit_card_title'],
                'label'           => $this->storeTranslations['available_payments_credit_card_label'],
                'payment_methods' => $creditCard,
            ];
        }

        if (0 !== count($debitCard)) {
            $paymentMethods[] = [
                'title'           => $this->storeTranslations['available_payments_debit_card_title'],
                'payment_methods' => $debitCard,
            ];
        }

        return $paymentMethods;
    }

    /**
     * Get amount
     *
     * @return float
     */
    private function getAmount(): float
    {
        $total      = $this->get_order_total();
        $subtotal   = (float) WC()->cart->subtotal;
        $tax        = $total - $subtotal;
        $discount   = $subtotal * ( $this->discount / 100 );
        $commission = $subtotal * ( $this->commission / 100 );
        $amount     = $subtotal - $discount + $commission;

        return $amount + $tax;
    }
}
