<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Transactions\CustomTransaction;
use MercadoPago\Woocommerce\Transactions\WalletButtonTransaction;

if (!defined('ABSPATH')) {
    exit;
}

class CustomGateway extends AbstractGateway
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

        $this->id                 = self::ID;
        $this->icon               = $this->mercadopago->plugin->getGatewayIcon('icon-gray-card');
        $this->title              = $this->getOption('title', $this->adminTranslations['gateway_title']);
        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];

        $this->init_form_fields();
        $this->init_settings();
        $this->payment_scripts($this->id);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->gateway->registerGatewayTitle($this);
        // @todo: call admin_notice hook to display currency notice
        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
        $this->mercadopago->gateway->registerThankYouPage($this->id, [$this, 'loadThankYouPage']);
        $this->mercadopago->checkout->registerReceipt($this->id, [$this, 'renderOrderForm']);
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
        $countryConfigs = $this->mercadopago->country->getCountryConfigs();

        $this->mercadopago->scripts->registerStoreScript(
            'wc_mercadopago_sdk',
            'https://sdk.mercadopago.com/js/v2'
        );

        $this->mercadopago->scripts->registerStoreScript(
            'wc_mercadopago_custom_checkout',
            $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/custom/mp-custom-checkout', '.js'),
            [
                'public_key'           => $this->mercadopago->seller->getCredentialsPublicKey(),
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
            'wc_mercadopago_custom_page',
            $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/custom/mp-custom-page', '.js')
        );

        $this->mercadopago->scripts->registerStoreScript(
            'wc_mercadopago_custom_elements',
            $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/custom/mp-custom-elements', '.js')
        );

        $this->mercadopago->checkout->registerReviewOrderBeforePayment(function () {
            $this->mercadopago->scripts->registerStoreScript(
                'wc_mercadopago_custom_update_checkout',
                $this->mercadopago->url->getPluginFileUrl('assets/js/checkouts/custom/mp-custom-update-checkout', '.js')
            );
        });
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
                'wallet_button'                    => $this->getOption('wallet_button', 'yes'),
                'wallet_button_image'              => $this->mercadopago->url->getPluginFileUrl("/assets/images/icons/icon-logos", '.png', true),
                'wallet_button_title'              => $this->storeTranslations['wallet_button_title'],
                'wallet_button_description'        => $this->storeTranslations['wallet_button_description'],
                'wallet_button_button_text'        => $this->storeTranslations['wallet_button_button_text'],
                'available_payments_title_icon'    => $this->mercadopago->url->getPluginFileUrl("/assets/images/icons/icon-purple-card", '.png', true),
                'available_payments_title'         => $this->storeTranslations['available_payments_title'],
                'available_payments_image'         => $this->mercadopago->url->getPluginFileUrl("/assets/images/checkouts/custom/chevron-down", '.png', true),
                'available_payments_chevron_up'    => $this->mercadopago->url->getPluginFileUrl("/assets/images/checkouts/custom/chevron-up", '.png', true),
                'available_payments_chevron_down'  => $this->mercadopago->url->getPluginFileUrl("/assets/images/checkouts/custom/chevron-down", '.png', true),
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
        parent::process_payment($order_id);

        $order = wc_get_order($order_id);

        // @todo: nonce validation

        // phpcs:ignore WordPress.Security.NonceVerification
        $checkout = map_deep($_POST['mercadopago_custom'], 'sanitize_text_field');

        if ('wallet_button' === $checkout['checkout_type']) {
            $this->mercadopago->logs->file->info(
                'preparing to render wallet button checkout.',
                __FUNCTION__
            );

            return [
                'result'   => 'success',
                'redirect' => add_query_arg(
                    [
                        'wallet_button' => 'open'
                    ],
                    $order->get_checkout_payment_url(true)
                ),
            ];
        } else {
            $this->mercadopago->logs->file->info(
                'preparing to get response of custom checkout.',
                __FUNCTION__
            );

            if (
                $checkout['amount'] &&
                $checkout['token'] &&
                $checkout['paymentMethodId'] &&
                $checkout['installments'] &&
                -1 !== $checkout['installments']
            ) {
                $this->transaction = new CustomTransaction($this, $order, $checkout);
                $response          = $this->transaction->createPayment();

                $this->mercadopago->order->setCustomMetadata($order, $response);
                $this->mercadopago->metaData->updatePaymentsOrderMetadata($order->get_id(), [$response['id']]);

                return $this->handleResponseStatus($order, $response, $checkout);
            }
        }

        return $this->processReturnFail(
            __FUNCTION__,
            $this->mercadopago->storeTranslations->commonMessages['cho_default_error']
        );
    }

    /**
     * Handle with response status
     *
     * @param $order
     * @param $response
     * @param $checkout
     *
     * @return array
     */
    public function handleResponseStatus($order, $response, $checkout): array
    {
        if (is_array($response) && array_key_exists('status', $response)) {
            switch ($response['status']) {
                case 'approved':
                    WC()->cart->empty_cart();

                    $orderStatusMessage = $this->mercadopago->order->getOrderStatusMessage('accredited');
                    $this->mercadopago->notices->storeApprovedStatusNotice($orderStatusMessage);
                    $this->mercadopago->order->setOrderStatus($order, 'failed', 'pending');

                    return [
                        'result'   => 'success',
                        'redirect' => $order->get_checkout_order_received_url(),
                    ];
                case 'pending':
                    // Order approved/pending, we just redirect to the congrats page.
                    return [
                        'result'   => 'success',
                        'redirect' => $order->get_checkout_order_received_url(),
                    ];
                case 'in_process':
                    // For pending, we don't know if the purchase will be made, so we must inform this status.
                    WC()->cart->empty_cart();

                    $orderStatus = $this->mercadopago->order->getOrderStatusMessage($response['status_detail']);
                    $urlReceived = esc_url($order->get_checkout_order_received_url());
                    $linkText    = $this->mercadopago->storeTranslations->commonMessages['cho_form_error'];

                    $this->mercadopago->notices->storeInProcessStatusNotice(
                        $orderStatus,
                        $urlReceived,
                        $checkout['checkout_type'],
                        $linkText
                    );

                    return [
                        'result'   => 'success',
                        'redirect' => $order->get_checkout_payment_url(true),
                    ];
                case 'rejected':
                    // If rejected is received, the order will not proceed until another payment try,
                    // so we must inform this status.

                    $noticeTitle = $this->mercadopago->storeTranslations->commonMessages['cho_payment_declined'];
                    $orderStatus = $this->mercadopago->order->getOrderStatusMessage($response['status_detail']);
                    $urlReceived = esc_url($order->get_checkout_payment_url());
                    $linkText    = $this->mercadopago->storeTranslations->commonMessages['cho_button_try_again'];

                    $this->mercadopago->notices->storeRejectedStatusNotice(
                        $noticeTitle,
                        $orderStatus,
                        $urlReceived,
                        $checkout['checkout_type'],
                        $linkText
                    );

                    return [
                        'result'   => 'success',
                        'redirect' => $order->get_checkout_payment_url(true),
                    ];
                case 'cancelled':
                case 'in_mediation':
                case 'charged_back':
                    // If we enter here (an order generating a direct cancelled, in_mediation,
                    // or charged_back status), then there must be something very wrong!
                    break;
                default:
                    break;
            }
        }

        return $this->processReturnFail(
            __FUNCTION__,
            $this->mercadopago->storeTranslations->commonMessages['cho_form_error']
        );
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

        return $this->mercadopago->url->getPluginFileUrl("/assets/images/gateways/wallet-button/preview-{$locale}", '.png', true);
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
        $cards          = $this->mercadopago->seller->getCheckoutBasicPaymentMethods();

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
     * Render order form
     *
     * @param $orderId
     *
     * @return void
     */
    public function renderOrderForm($orderId): void
    {
        $isWallet = get_query_var('wallet_button', false);

        if ($isWallet) {
            $order      = wc_get_order($orderId);
            $this->transaction = new WalletButtonTransaction();
            $preference        = $this->transaction->createPreference();

            $this->mercadopago->template->getWoocommerceTemplate(
                'public/receipt/custom-checkout.php',
                [
                    'public_key'          => $this->mercadopago->seller->getCredentialsPublicKey(),
                    'preference_id'       => $preference['id'],
                    'wallet_button_title' => $this->storeTranslations['wallet_button_title'],
                    'cancel_url'          => $order->get_cancel_order_url(),
                    'cancel_url_text'     => $this->storeTranslations['cancel_url_text'],
                ]
            );
        }
    }

    /**
     * Load thank you page
     *
     * @param $orderId
     *
     * @return void
     */
    public function loadThankYouPage($orderId): void
    {
        $order             = wc_get_order($orderId);
        $installments      = $order->get_meta('mp_installments');
        $installmentAmount = $order->get_meta('mp_transaction_details');
        $transactionAmount = $order->get_meta('mp_transaction_amount');
        $totalPaidAmount   = $order->get_meta('mp_total_paid_amount');
        $totalDiffCost     = (float) $totalPaidAmount - (float) $transactionAmount;

        $countryConfigs     = $this->mercadopago->country->getCountryConfigs();
        $currencySymbol    = $countryConfigs['currency_symbol'];

        if ($totalDiffCost > 0) {
            $this->mercadopago->gateway->registerOrderDetailsAfterOrderTable([$this, 'loadThankYouPage']);
            $this->mercadopago->template->getWoocommerceTemplate(
                'public/order/custom-order-received.php',
                [
                    'title_installment_cost'  => $this->storeTranslations['title_installment_cost'],
                    'title_installment_total' => $this->storeTranslations['title_installment_total'],
                    'text_installments'       => $this->storeTranslations['text_installments'],
                    'currency'                => $currencySymbol,
                    'total_paid_amount'       => number_format(floatval($totalPaidAmount), 2, ',', '.'),
                    'transaction_amount'      => number_format(floatval($transactionAmount), 2, ',', '.'),
                    'total_diff_cost'         => number_format(floatval($totalDiffCost), 2, ',', '.'),
                    'installment_amount'      => number_format(floatval($installmentAmount), 2, ',', '.'),
                    'installments'            => number_format(floatval($installments)),
                ]
            );
        }
    }
}
