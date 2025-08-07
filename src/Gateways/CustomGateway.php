<?php

namespace MercadoPago\Woocommerce\Gateways;

use Exception;
use MercadoPago\Woocommerce\Exceptions\InvalidCheckoutDataException;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Numbers;
use MercadoPago\Woocommerce\Transactions\CustomTransaction;
use MercadoPago\Woocommerce\Transactions\SupertokenTransaction;
use MercadoPago\Woocommerce\Transactions\WalletButtonTransaction;
use MercadoPago\Woocommerce\Exceptions\ResponseStatusException;

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
     * @const
     */
    public const WEBHOOK_API_NAME = 'WC_WooMercadoPago_Custom_Gateway';

    /**
     * @const
     */
    public const LOG_SOURCE = 'MercadoPago_CustomGateway';

    /**
     * CustomGateway constructor
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->customGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->customCheckout;

        $this->id        = self::ID;
        $this->icon      = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-custom');
        $this->iconAdmin = $this->mercadopago->hooks->gateway->getGatewayIcon('icon-custom-admin');

        $gatewayTitle    = $this->mercadopago->sellerConfig->getSiteId() === 'MLB' ? $this->adminTranslations['gateway_title_MLB'] : $this->adminTranslations['gateway_title_ALL'];
        $this->title     = $this->mercadopago->storeConfig->getGatewayTitle($this, $gatewayTitle);

        $this->init_form_fields();
        $this->payment_scripts($this->id);

        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];
        $this->discount           = (int) $this->getActionableValue('gateway_discount', 0);
        $this->commission         = (int) $this->getActionableValue('commission', 0);

        $this->mercadopago->hooks->gateway->registerUpdateOptions($this);
        $this->mercadopago->hooks->gateway->registerGatewayTitle($this);
        $this->mercadopago->hooks->gateway->registerThankYouPage($this->id, [$this, 'renderInstallmentsRateDetails']);

        $this->mercadopago->hooks->order->registerOrderDetailsAfterOrderTable([$this, 'renderInstallmentsRateDetails']);
        $this->mercadopago->hooks->order->registerAdminOrderTotalsAfterTotal([$this, 'registerInstallmentsFeeOnAdminOrder']);

        $this->mercadopago->hooks->endpoints->registerApiEndpoint(self::WEBHOOK_API_NAME, [$this, 'webhook']);
        $this->mercadopago->hooks->checkout->registerReceipt($this->id, [$this, 'renderOrderForm']);

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

    public function formFieldsHeaderSection(): array
    {
        return array_replace_recursive(parent::formFieldsHeaderSection(), [
            'header' => [
                'title' => $this->mercadopago->sellerConfig->getSiteId() === 'MLB' ? $this->adminTranslations['header_title_MLB'] : $this->adminTranslations['header_title_ALL'],
            ],
            'enabled' => [
                'descriptions' => [
                    'enabled'  => $this->mercadopago->sellerConfig->getSiteId() === 'MLB' ? $this->adminTranslations['enabled_descriptions_enabled_MLB'] : $this->adminTranslations['enabled_descriptions_enabled_ALL'],
                    'disabled' => $this->mercadopago->sellerConfig->getSiteId() === 'MLB' ? $this->adminTranslations['enabled_descriptions_disabled_MLB'] : $this->adminTranslations['enabled_descriptions_disabled_ALL'],
                ],
            ],
            'title' => [
                'default' => $this->title,
            ],
        ]);
    }

    public function formFieldsMainSection(): array
    {
        return [
            'card_info_helper' => [
                'type'  => 'title',
                'value' => '',
            ],
            'card_info_fees' => [
                'type'  => 'mp_card_info',
                'value' => [
                    'title'       => $this->adminTranslations['card_info_fees_title'],
                    'subtitle'    => $this->adminTranslations['card_info_fees_subtitle'],
                    'button_text' => $this->adminTranslations['card_info_fees_button_url'],
                    'button_url'  => $this->links['mercadopago_costs'],
                    'icon'        => 'mp-icon-badge-info',
                    'color_card'  => 'mp-alert-color-success',
                    'size_card'   => 'mp-card-body-size',
                    'target'      => '_blank',
                ],
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
            'wallet_button' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['wallet_button_title'],
                'subtitle'     => $this->adminTranslations['wallet_button_subtitle'],
                'default'      => 'yes',
                'after_toggle' => $this->getWalletButtonPreview(),
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['wallet_button_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['wallet_button_descriptions_disabled'],
                ],
            ],
            'advanced_configuration_title' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_title'],
                'class' => 'mp-subtitle-body',
            ],
            'advanced_configuration_description' => [
                'type'  => 'title',
                'title' => $this->adminTranslations['advanced_configuration_subtitle'],
                'class' => 'mp-small-text',
            ],
            'binary_mode' => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations['binary_mode_title'],
                'subtitle'     => $this->adminTranslations['binary_mode_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations['binary_mode_descriptions_enabled'],
                    'disabled' => $this->adminTranslations['binary_mode_descriptions_disabled'],
                ],
            ],
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

        if ($this->canCheckoutLoadScriptsAndStyles()) {
            $this->registerCheckoutScripts();
        }
    }

    public function registerCheckoutStyle()
    {
        $this->mercadopago->hooks->scripts->registerCheckoutStyle(
            'wc_mercadopago_supertoken_payment_methods',
            $this->mercadopago->helpers->url->getCssAsset('checkouts/super-token/super-token-payment-methods'),
        );
    }

    /**
     * Register checkout scripts
     *
     * @return void
     */
    public function registerCheckoutScripts(): void
    {
        parent::registerCheckoutScripts();

        $this->registerCheckoutStyle();

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_security_session',
            $this->mercadopago->helpers->url->getJsAsset('session')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_sdk',
            'https://sdk.mercadopago.com/js/v2'
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_custom_card_form',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/custom/entities/card-form'),
            [
                'security_code_placeholder_text_3_digits' => $this->storeTranslations['security_code_placeholder_text_3_digits'],
            ]
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_custom_three_ds_handler',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/custom/entities/three-ds-handler')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_custom_event_handler',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/custom/entities/event-handler')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_custom_page',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/custom/mp-custom-page'),
            [
                'security_code_placeholder_text_3_digits' => $this->storeTranslations['security_code_placeholder_text_3_digits'],
                'security_code_placeholder_text_4_digits' => $this->storeTranslations['security_code_placeholder_text_4_digits'],
                'security_code_tooltip_text_3_digits' => $this->storeTranslations['security_code_tooltip_text_3_digits'],
                'security_code_tooltip_text_4_digits' => $this->storeTranslations['security_code_tooltip_text_4_digits'],
            ]
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_custom_elements',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/custom/mp-custom-elements')
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_custom_checkout',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/custom/mp-custom-checkout'),
            [
                'public_key'        => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
                'locale'            => $this->storeTranslations['locale'],
                'intl'              => $this->countryConfigs['intl'],
                'site_id'           => $this->countryConfigs['site_id'],
                'currency'          => $this->countryConfigs['currency'],
                'theme'             => get_stylesheet(),
                'location'          => '/checkout',
                'plugin_version'    => MP_VERSION,
                'platform_version'  => $this->mercadopago->woocommerce->version,
                'placeholders' => [
                    'issuer'             => $this->storeTranslations['placeholders_issuer'],
                    'installments'       => $this->storeTranslations['placeholders_installments'],
                    'cardExpirationDate' => $this->storeTranslations['placeholders_card_expiration_date'],
                    'cardholderName'     => $this->storeTranslations['placeholders_cardholder_name'],
                ],
                'input_title' => [
                    'installments' => $this->storeTranslations['card_installments_label'],
                ],
                'input_helper_message' => [
                    'cardNumber' => [
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
                    'securityCode' => [
                        'invalid_type'   => $this->storeTranslations['input_helper_message_security_code_invalid_type'],
                        'invalid_length' => $this->storeTranslations['input_helper_message_security_code_invalid_length'],
                    ],
                    'installments' => [
                        'required' => $this->storeTranslations['installments_required'],
                        'interest_free_option_text' => $this->storeTranslations['interest_free_part_two_text'],
                        'bank_interest_hint_text' => $this->storeTranslations['card_installments_interest_text'],
                        'bank_interest_option_text' => $this->storeTranslations['installments_text']
                    ],
                ],
                'threeDsText' => [
                    'title_loading'          => $this->mercadopago->storeTranslations->threeDsTranslations['title_loading_3ds_frame'],
                    'title_loading2'         => $this->mercadopago->storeTranslations->threeDsTranslations['title_loading_3ds_frame2'],
                    'text_loading'           => $this->mercadopago->storeTranslations->threeDsTranslations['text_loading_3ds_frame'],
                    'title_loading_response' => $this->mercadopago->storeTranslations->threeDsTranslations['title_loading_3ds_response'],
                    'title_frame'            => $this->mercadopago->storeTranslations->threeDsTranslations['title_3ds_frame'],
                    'tooltip_frame'          => $this->mercadopago->storeTranslations->threeDsTranslations['tooltip_3ds_frame'],
                    'message_close'          => $this->mercadopago->storeTranslations->threeDsTranslations['message_3ds_declined'],
                ],
                'error_messages' => [
                    'default' => $this->storeTranslations['default_error_message'],
                    'installments' => [
                        'invalid amount' => $this->storeTranslations['installments_error_invalid_amount'],
                    ],
                ],
            ]
        );

        $this->registerSuperTokenScripts();
    }

    /**
     * Register all super token scripts
     *
     * @return void
     */
    public function registerSuperTokenScripts()
    {
        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_supertoken_debounce',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/super-token/entities/debounce'),
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_supertoken_email_listener',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/super-token/entities/email-listener'),
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_supertoken_metrics',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/super-token/entities/super-token-metrics'),
            [
                'plugin_version'    => MP_VERSION,
                'platform_version'  => $this->mercadopago->woocommerce->version,
                'site_id'           => $this->countryConfigs['site_id'],
                'location'          => '/checkout',
                'theme'             => get_stylesheet(),
                'cust_id'           => $this->mercadopago->sellerConfig->getCustIdFromAT(),
            ]
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_supertoken_trigger_handler',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/super-token/entities/super-token-trigger-handler'),
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_supertoken_payment_methods',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/super-token/entities/super-token-payment-methods'),
            [
                'yellow_wallet_path' => $this->mercadopago->helpers->url->getImageAsset('icons/icon-yellow-wallet'),
                'white_card_path' => $this->mercadopago->helpers->url->getImageAsset('icons/icon-white-card'),
                'payment_methods_order' => $this->mercadopago->hooks->options->getGatewayOption($this, 'payment_methods_order', 'cards_first'),
                'payment_methods_thumbnails' => $this->mercadopago->sellerConfig->getPaymentMethodsThumbnails(),
                'intl'              => $this->countryConfigs['intl'],
                'site_id'           => $this->countryConfigs['site_id'],
                'currency'          => $this->countryConfigs['currency'],
                'payment_methods_list_text' => $this->storeTranslations['payment_methods_list_text'],
                'last_digits_text' => $this->storeTranslations['last_digits_text'],
                'new_card_text' => $this->storeTranslations['new_card_text'],
                'account_money_text' => $this->storeTranslations['account_money_text'],
                'account_money_invested_text' => $this->storeTranslations['account_money_invested_text'],
                'interest_free_part_one_text' => $this->storeTranslations['interest_free_part_one_text'],
                'interest_free_part_two_text' => $this->storeTranslations['interest_free_part_two_text'],
                'security_code_input_title_text' => $this->storeTranslations['security_code_input_title_text'],
                'security_code_placeholder_text_3_digits' => $this->storeTranslations['security_code_placeholder_text_3_digits'],
                'security_code_placeholder_text_4_digits' => $this->storeTranslations['security_code_placeholder_text_4_digits'],
                'security_code_tooltip_text_3_digits' => $this->storeTranslations['security_code_tooltip_text_3_digits'],
                'security_code_tooltip_text_4_digits' => $this->storeTranslations['security_code_tooltip_text_4_digits'],
                'security_code_error_message_text' => $this->storeTranslations['security_code_error_message_text'],
                'input_title' => [
                    'installments' => $this->storeTranslations['card_installments_label'],
                ],
                'placeholders' => [
                    'issuer'             => $this->storeTranslations['placeholders_issuer'],
                    'installments'       => $this->storeTranslations['placeholders_installments'],
                    'cardExpirationDate' => $this->storeTranslations['placeholders_card_expiration_date'],
                ],
                'input_helper_message' => [
                    'installments' => [
                        'required' => $this->storeTranslations['installments_required'],
                        'interest_free_option_text' => $this->storeTranslations['interest_free_part_two_text'],
                        'bank_interest_hint_text' => $this->storeTranslations['card_installments_interest_text'],
                        'bank_interest_option_text' => $this->storeTranslations['installments_text']
                    ],
                    'securityCode' => [
                        'invalid_type'   => $this->storeTranslations['input_helper_message_security_code_invalid_type'],
                        'invalid_length' => $this->storeTranslations['input_helper_message_security_code_invalid_length'],
                    ],
                ],
                'mercado_pago_card_name' => $this->storeTranslations['mercado_pago_card_name'],
            ]
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_supertoken_authenticator',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/super-token/entities/super-token-authenticator'),
            [
                'platform_id' => MP_PLATFORM_ID,
            ]
        );

        $this->mercadopago->hooks->scripts->registerCheckoutScript(
            'wc_mercadopago_supertoken',
            $this->mercadopago->helpers->url->getJsAsset('checkouts/super-token/mp-super-token'),
            [
                'public_key' => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
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
        $this->mercadopago->hooks->template->getWoocommerceTemplate(
            'public/checkouts/custom-checkout.php',
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
        $amountAndCurrencyRatio = $this->getAmountAndCurrency();
        return [
            'test_mode'                               => $this->mercadopago->storeConfig->isTestMode(),
            'test_mode_title'                         => $this->storeTranslations['test_mode_title'],
            'test_mode_description'                   => $this->storeTranslations['test_mode_description'],
            'test_mode_link_text'                     => $this->storeTranslations['test_mode_link_text'],
            'test_mode_link_src'                      => $this->links['docs_integration_test'],
            'wallet_button'                           => $this->mercadopago->hooks->options->getGatewayOption($this, 'wallet_button', 'yes'),
            'wallet_button_image'                     => $this->mercadopago->helpers->url->getImageAsset('gateways/wallet-button/logo.svg'),
            'wallet_button_title'                     => $this->storeTranslations['wallet_button_title'],
            'wallet_button_description'               => $this->storeTranslations['wallet_button_description'],
            'site_id'                                 => $this->mercadopago->sellerConfig->getSiteId() ?: $this->mercadopago->helpers->country::SITE_ID_MLA,
            'card_number_input_label'                 => $this->storeTranslations['card_number_input_label'],
            'card_number_input_helper'                => $this->storeTranslations['card_number_input_helper'],
            'card_holder_name_input_label'            => $this->storeTranslations['card_holder_name_input_label'],
            'card_holder_name_input_helper'           => $this->storeTranslations['card_holder_name_input_helper'],
            'card_expiration_input_label'             => $this->storeTranslations['card_expiration_input_label'],
            'card_expiration_input_helper'            => $this->storeTranslations['card_expiration_input_helper'],
            'card_security_code_input_label'          => $this->storeTranslations['card_security_code_input_label'],
            'card_security_code_input_helper'         => $this->storeTranslations['card_security_code_input_helper'],
            'card_document_input_label'               => $this->storeTranslations['card_document_input_label'],
            'card_input_document_helper_empty'        => $this->storeTranslations['card_document_input_helper_empty'],
            'card_input_document_helper_invalid'      => $this->storeTranslations['card_document_input_helper_invalid'],
            'card_input_document_helper_wrong'        => $this->storeTranslations['card_document_input_helper_wrong'],
            'card_issuer_input_label'                 => $this->storeTranslations['card_issuer_input_label'],
            'amount'                                  => $amountAndCurrencyRatio['amount'],
            'currency_ratio'                          => $amountAndCurrencyRatio['currencyRatio'],
            'message_error_amount'                    => $this->storeTranslations['message_error_amount'],
            'security_code_tooltip_text_3_digits'     => $this->storeTranslations['security_code_tooltip_text_3_digits'],
            'placeholders_cardholder_name'            => $this->storeTranslations['placeholders_cardholder_name'],
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
        $order = wc_get_order($order_id);

        try {
            $checkout = $this->getCheckoutFormData($order);

            parent::process_payment($order_id);

            switch ($checkout['checkout_type']) {
                case 'wallet_button':
                    $this->mercadopago->logs->file->info('Preparing to render wallet button checkout', self::LOG_SOURCE);

                    return [
                        'result'   => 'success',
                        'redirect' => $this->mercadopago->helpers->url->setQueryVar(
                            'wallet_button',
                            'autoOpen',
                            $order->get_checkout_payment_url(true)
                        ),
                    ];

                case 'super_token':
                    $this->mercadopago->logs->file->info('Preparing to get response of custom super token checkout', self::LOG_SOURCE);
                    if (
                        !empty($checkout['token']) &&
                        !empty($checkout['amount']) &&
                        !empty($checkout['payment_method_id']) &&
                        !empty($checkout['payment_type_id']) &&
                        ($checkout['payment_type_id'] != 'credit_card' || (!empty($checkout['installments']) && $checkout['installments'] > 0))
                    ) {
                        $this->transaction = new SupertokenTransaction($this, $order, $checkout);
                        $response          = $this->transaction->createPayment();

                        $this->mercadopago->orderMetadata->setSupertokenMetadata($order, $response, $this->transaction->getInternalMetadata());
                        return $this->handleResponseStatus($order, $response);
                    }

                    throw new InvalidCheckoutDataException('exception : Unable to process payment on ' . __METHOD__);

                default:
                    $this->mercadopago->logs->file->info('Preparing to get response of custom checkout', self::LOG_SOURCE);

                    if (
                        !empty($checkout['token']) &&
                        !empty($checkout['amount']) &&
                        !empty($checkout['payment_method_id']) &&
                        !empty($checkout['installments']) && $checkout['installments'] !== -1
                    ) {
                        $this->transaction = new CustomTransaction($this, $order, $checkout);
                        $response          = $this->transaction->createPayment();

                        $this->mercadopago->orderMetadata->setCustomMetadata($order, $response);
                        return $this->handleResponseStatus($order, $response);
                    }

                    throw new InvalidCheckoutDataException('exception : Unable to process payment on ' . __METHOD__);
            }
        } catch (Exception $e) {
            return $this->processReturnFail(
                $e,
                $e->getMessage(),
                self::LOG_SOURCE,
                (array) $order,
                true
            );
        }
    }

    /**
     * Get checkout mercadopago custom
     *
     * @param $order
     *
     * @return array
     */
    private function getCheckoutFormData($order): array
    {
        if (isset($_POST['mercadopago_custom'])) {
            $checkout = Form::sanitizedPostData('mercadopago_custom');
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "no");
        } else {
            $checkout = $this->processBlocksCheckoutData('mercadopago_custom', Form::sanitizedPostData());
            $this->mercadopago->orderMetadata->markPaymentAsBlocks($order, "yes");
        }

        return $checkout;
    }

    /**
     * Generating Wallet Button preview component
     *
     * @return string
     */
    public function getWalletButtonPreview(): string
    {
        return $this->mercadopago->hooks->template->getWoocommerceTemplateHtml(
            'admin/components/preview.php',
            [
                'settings' => [
                    'url'         => $this->getWalletButtonPreviewUrl(),
                    'description' => $this->adminTranslations['wallet_button_preview_description'],
                ],
            ]
        );
    }

    /**
     * Get wallet button preview url
     *
     * @return string
     */
    private function getWalletButtonPreviewUrl(): string
    {
        $locale = substr(strtolower(get_locale()), 0, 2);

        if ($locale !== 'pt' && $locale !== 'es') {
            $locale = 'en';
        }

        return $this->mercadopago->helpers->url->getImageAsset(
            'gateways/wallet-button/preview-' . $locale,
        );
    }

    /**
     * Render order form
     *
     * @param $orderId
     *
     * @return void
     * @throws Exception
     */
    public function renderOrderForm($orderId): void
    {
        if ($this->mercadopago->helpers->url->validateQueryVar('wallet_button')) {
            $order             = wc_get_order($orderId);
            $this->transaction = new WalletButtonTransaction($this, $order);
            $preference        = $this->transaction->createPreference();

            $this->mercadopago->hooks->template->getWoocommerceTemplate(
                'public/receipt/preference-modal.php',
                [
                    'public_key'        => $this->mercadopago->sellerConfig->getCredentialsPublicKey(),
                    'preference_id'     => $preference['id'],
                    'pay_with_mp_title' => $this->storeTranslations['wallet_button_order_receipt_title'],
                    'cancel_url'        => $order->get_cancel_order_url(),
                    'cancel_url_text'   => $this->storeTranslations['cancel_url_text'],
                ]
            );
        }
    }

    /**
     * Render thank you page
     *
     * @param $order_id
     */
    public function renderInstallmentsRateDetails($order_id): void
    {
        $order             = wc_get_order($order_id);
        $currency          = $this->countryConfigs['currency_symbol'];
        $installments      = (float) $this->mercadopago->orderMetadata->getInstallmentsMeta($order);
        $installmentAmount = $this->mercadopago->orderMetadata->getTransactionDetailsMeta($order);
        $transactionAmount = Numbers::makesValueSafe($this->mercadopago->orderMetadata->getTransactionAmountMeta($order));
        $totalPaidAmount   = Numbers::makesValueSafe($this->mercadopago->orderMetadata->getTotalPaidAmountMeta($order));
        $totalDiffCost     = $totalPaidAmount - $transactionAmount;

        if ($totalDiffCost > 0) {
            $this->mercadopago->hooks->template->getWoocommerceTemplate(
                'public/order/custom-order-received.php',
                [
                    'title_installment_cost'  => $this->storeTranslations['title_installment_cost'],
                    'title_installment_total' => $this->storeTranslations['title_installment_total'],
                    'text_installments'       => $this->storeTranslations['text_installments'],
                    'total_paid_amount'       => Numbers::formatWithCurrencySymbol($currency, $totalPaidAmount),
                    'transaction_amount'      => Numbers::formatWithCurrencySymbol($currency, $transactionAmount),
                    'total_diff_cost'         => Numbers::formatWithCurrencySymbol($currency, $totalDiffCost),
                    'installment_amount'      => Numbers::formatWithCurrencySymbol($currency, $installmentAmount),
                    'installments'            => Numbers::format($installments),
                ]
            );
        }
    }

    /**
     * Handle with response status
     * The order_pay page always redirect the requester, so we must stop the current execution to return a JSON.
     * See mp-custom-checkout.js to understand how to handle the return.
     *
     * @param $return
     */
    private function handlePayForOrderRequest($return)
    {
        if (!headers_sent()) {
            header('Content-Type: application/json;');
        }

        echo wp_json_encode($return);

        if (!$_ENV['PHPUNIT_TEST'] ?? false) {
            die();
        }
    }

    /**
     * Check if there is a pay_for_order query param.
     * This indicates that the user is on the Order Pay Checkout page.
     *
     * @return bool
     */
    private function isOrderPayPage(): bool
    {
        return $this->mercadopago->helpers->url->validateGetVar('pay_for_order');
    }

    /**
     * Handle with response status
     *
     * @param $order
     * @param $response
     *
     * @return array
     */
    private function handleResponseStatus($order, array $response): array
    {
        try {
            if (array_key_exists('status', $response)) {
                switch ($response['status']) {
                    case 'approved':
                        $this->mercadopago->helpers->cart->emptyCart();

                        $urlReceived = $order->get_checkout_order_received_url();
                        $orderStatus = $this->mercadopago->orderStatus->getOrderStatusMessage('accredited');

                        $this->mercadopago->helpers->notices->storeApprovedStatusNotice($orderStatus);
                        $this->mercadopago->orderStatus->setOrderStatus($order, 'failed', 'pending');

                        $return = [
                            'result'   => 'success',
                            'redirect' => $urlReceived,
                        ];

                        if ($this->isOrderPayPage()) {
                            $this->handlePayForOrderRequest($return);
                        }

                        return $return;

                    case 'pending':
                    case 'in_process':
                        if ($response['status_detail'] === 'pending_challenge') {
                            $this->mercadopago->helpers->session->setSession('mp_3ds_url', $response['three_ds_info']['external_resource_url']);
                            $this->mercadopago->helpers->session->setSession('mp_3ds_creq', $response['three_ds_info']['creq']);
                            $this->mercadopago->helpers->session->setSession('mp_order_id', $order->ID);
                            $this->mercadopago->helpers->session->setSession('mp_payment_id', $response['id']);
                            $lastFourDigits = (empty($response['card']['last_four_digits'])) ? '****' : $response['card']['last_four_digits'];

                            $return = [
                                'result'           => 'success',
                                'three_ds_flow'    => true,
                                'last_four_digits' =>  $lastFourDigits,
                                'redirect'         => false,
                                'messages'         => '<script>window.mpCustomCheckoutHandler.threeDSHandler.load3DSFlow(' . $lastFourDigits . ')</script>',
                            ];

                            if ($this->isOrderPayPage()) {
                                $this->handlePayForOrderRequest($return);
                            }

                            return $return;
                        }

                        $this->mercadopago->helpers->cart->emptyCart();

                        $urlReceived = $order->get_checkout_order_received_url();

                        $return = [
                            'result'   => 'success',
                            'redirect' => $urlReceived,
                        ];

                        if ($this->isOrderPayPage()) {
                            $this->handlePayForOrderRequest($return);
                        }

                        return $return;

                    case 'rejected':
                        if ($this->isOrderPayPage()) {
                            $this->handlePayForOrderRequest([
                                'result'   => 'fail',
                                'messages' => $this->getRejectedPaymentErrorMessage($response['status_detail'])
                            ]);
                            return []; // Case $_ENV['PHPUNIT_TEST'] == true
                        }

                        $this->handleWithRejectPayment($response);
                        break;
                    // Fall-through intentional - throw RejectedPaymentException for 'rejected' case.

                    default:
                        break;
                }
            }
            throw new ResponseStatusException('exception: Response status not mapped on ' . __METHOD__);
        } catch (Exception $e) {
            return $this->processReturnFail(
                $e,
                $e->getMessage(),
                self::LOG_SOURCE,
                (array) $response,
                true
            );
        }
    }

    /**
     * Register installments fee on admin order totals
     *
     * @param int $orderId
     *
     * @return void
     */
    public function registerInstallmentsFeeOnAdminOrder(int $orderId): void
    {
        $order = wc_get_order($orderId);

        $currency    = $this->mercadopago->helpers->currency->getCurrencySymbol();
        $usedGateway = $this->mercadopago->orderMetadata->getUsedGatewayData($order);

        if ($this::ID === $usedGateway) {
            $totalPaidAmount       = Numbers::format(Numbers::makesValueSafe($this->mercadopago->orderMetadata->getTotalPaidAmountMeta($order)));
            $transactionAmount     = Numbers::format(Numbers::makesValueSafe($this->mercadopago->orderMetadata->getTransactionAmountMeta($order)));
            $installmentsFeeAmount = $totalPaidAmount - $transactionAmount;

            if ($installmentsFeeAmount > 0) {
                $this->mercadopago->hooks->template->getWoocommerceTemplate(
                    'admin/order/generic-note.php',
                    [
                        'tip'   => $this->mercadopago->adminTranslations->order['order_note_installments_fee_tip'],
                        'title' => $this->mercadopago->adminTranslations->order['order_note_installments_fee_title'],
                        'value' => Numbers::formatWithCurrencySymbol($currency, $installmentsFeeAmount),
                    ]
                );

                $this->mercadopago->hooks->template->getWoocommerceTemplate(
                    'admin/order/generic-note.php',
                    [
                        'tip'   => $this->mercadopago->adminTranslations->order['order_note_total_paid_amount_tip'],
                        'title' => $this->mercadopago->adminTranslations->order['order_note_total_paid_amount_title'],
                        'value' => Numbers::formatWithCurrencySymbol($currency, $totalPaidAmount),
                    ]
                );
            }
        }
    }
}
