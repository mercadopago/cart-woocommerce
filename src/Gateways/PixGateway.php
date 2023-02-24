<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Transactions\PixTransaction;

if (!defined('ABSPATH')) {
    exit;
}

class PixGateway extends AbstractGateway
{
    /**
     * @const
     */
    public const ID = 'woo-mercado-pago-pix';

    /**
     * @const
     */
    public const CHECKOUT_NAME = 'checkout-pix';

    /**
     * PixGateway constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->adminTranslations = $this->mercadopago->adminTranslations->pixGatewaySettings;
        $this->storeTranslations = $this->mercadopago->storeTranslations->pixCheckout;

        $this->id                 = self::ID;
        $this->icon               = $this->mercadopago->plugin->getGatewayIcon('icon-pix');
        $this->title              = $this->getOption('title', $this->adminTranslations['gateway_title']);
        $this->description        = $this->adminTranslations['gateway_description'];
        $this->method_title       = $this->adminTranslations['gateway_method_title'];
        $this->method_description = $this->adminTranslations['gateway_method_description'];
        $this->expirationDate     = (int) $this->mercadopago->seller->getCheckoutDateExpirationPix($this, '1');

        $this->init_form_fields();
        $this->init_settings();
        $this->payment_scripts($this->id);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->gateway->registerGatewayTitle($this);
        // @todo: register the endpoint to woocommerce_api_wc_mp_pix_image
        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
        $this->mercadopago->order->registerEmailBeforeOrderTable([$this, 'getTemplate']);
        $this->mercadopago->order->registerOrderDetailsAfterOrderTable([$this, 'getTemplate']);
        $this->mercadopago->gateway->registerThankYouPage($this->id, [$this, 'loadThankYouPage']);
    }

    /**
     * Verify if the gateway is available
     *
     * @return bool
     */
    public static function isAvailable(): bool
    {
        global $mercadopago;
        $siteId  = $mercadopago->seller->getSiteId();
        $country = $mercadopago->country->getWoocommerceDefaultCountry();

        if ('MLB' === $siteId || ('' === $siteId && 'BR' === $country)) {
            return true;
        }

        return false;
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
            $paymentMethodPix = $this->mercadopago->seller->getCheckoutPixPaymentMethods();

            if (empty($paymentMethodPix) || !in_array('pix', $paymentMethodPix['pix'], true)) {
                if (isset($_GET['section']) && $_GET['section'] == $this->id) {
                    $this->mercadopago->notices->adminNoticeMissPix();
                }

                $stepsContent = $this->mercadopago->template->getWoocommerceTemplateHtml(
                    'admin/settings/steps.php',
                    [
                        'title'             => $this->adminTranslations['steps_title'],
                        'step_one_text'     => $this->adminTranslations['steps_step_one_text' ],
                        'step_two_text'     => $this->adminTranslations['steps_step_two_text'],
                        'step_three_text'   => $this->adminTranslations['steps_step_three_text'],
                        'observation_one'   => $this->adminTranslations['steps_observation_one'],
                        'observation_two'   => $this->adminTranslations['steps_observation_two'],
                        'button_about_pix'  => $this->adminTranslations['steps_button_about_pix'],
                        'observation_three' => $this->adminTranslations['steps_observation_three'],
                        'link_title_one'    => $this->adminTranslations['steps_link_title_one'],
                        'link_url_one'      => $this->mercadopago->links->getLinks()['mercadopago_pix'],
                        'link_url_two'      => $this->mercadopago->links->getLinks()['mercadopago_support'],
                    ]
                );

                $this->form_fields = [
                    'header'        => [
                        'type'        => 'mp_config_title',
                        'title'       => $this->adminTranslations['header_title'],
                        'description' => $this->adminTranslations['header_description'],
                    ],
                    'steps_content' => [
                        'title' => $stepsContent,
                        'type'  => 'title',
                        'class' => 'mp_title_checkout',
                    ],
                ];
            } else {
                $this->form_fields = [
                    'header'                             => [
                        'type'        => 'mp_config_title',
                        'title'       => $this->adminTranslations['header_title'],
                        'description' => $this->adminTranslations['header_description'],
                    ],
                    'card_settings'                      => [
                        'type'        => 'mp_card_info',
                        'value'       => [
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
                        'type'            => 'text',
                        'title'           => $this->adminTranslations['title_title'],
                        'description'     => $this->adminTranslations['title_description'],
                        'default'         => $this->adminTranslations['title_default'],
                        'desc_tip'        => $this->adminTranslations['title_desc_tip'],
                        'class'           => 'limit-title-max-length',
                    ],
                    'expiration_date'                    => [
                        'type'        => 'select',
                        'title'       => $this->adminTranslations['expiration_date_title'],
                        'description' => $this->adminTranslations['expiration_date_description'],
                        'default'     => '30 minutes',
                        'options'     => [
                            '15 minutes' => $this->adminTranslations['expiration_date_options_15_minutes'],
                            '30 minutes' => $this->adminTranslations['expiration_date_options_30_minutes'],
                            '60 minutes' => $this->adminTranslations['expiration_date_options_60_minutes'],
                            '12 hours'   => $this->adminTranslations['expiration_date_options_12_hours'],
                            '24 hours'   => $this->adminTranslations['expiration_date_options_24_hours'],
                            '2 days'     => $this->adminTranslations['expiration_date_options_2_days'],
                            '3 days'     => $this->adminTranslations['expiration_date_options_3_days'],
                            '4 days'     => $this->adminTranslations['expiration_date_options_4_days'],
                            '5 days'     => $this->adminTranslations['expiration_date_options_5_days'],
                            '6 days'     => $this->adminTranslations['expiration_date_options_6_days'],
                            '7 days'     => $this->adminTranslations['expiration_date_options_7_days'],
                        ]
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
                    'card_info_helper'                   => [
                        'type'  => 'title',
                        'value' => '',
                    ],
                    'card_info'                          => [
                        'type'        => 'mp_card_info',
                        'value'       => [
                            'title'       => $this->adminTranslations['card_info_title'],
                            'subtitle'    => $this->adminTranslations['card_info_subtitle'],
                            'button_text' => $this->adminTranslations['card_info_button_text'],
                            'button_url'  => $this->mercadopago->links->getLinks()['mercadopago_pix'],
                            'icon'        => 'mp-icon-badge-info',
                            'color_card'  => 'mp-alert-color-success',
                            'size_card'   => 'mp-card-body-size',
                            'target'      => '_blank',
                        ]
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
                    'discount'               => [
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
                    'commission'             => [
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
        $this->mercadopago->template->getWoocommerceTemplate(
            'public/checkouts/pix-checkout.php',
            [
                'test_mode'                        => $this->mercadopago->seller->isTestMode(),
                'test_mode_title'                  => $this->storeTranslations['test_mode_title'],
                'test_mode_description'            => $this->storeTranslations['test_mode_description'],
                'pix_template_title'               => $this->storeTranslations['pix_template_title'],
                'pix_template_subtitle'            => $this->storeTranslations['pix_template_subtitle'],
                'pix_template_alt'                 => $this->storeTranslations['pix_template_alt'],
                'pix_template_src'                 => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/pix/pix', '.png', true),
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
        parent::process_payment($order_id);

        // @todo: nonce validation

        // phpcs:ignore WordPress.Security.NonceVerification
        $checkout = map_deep($_POST, 'sanitize_text_field');
        $order = wc_get_order($order_id);

        if (filter_var($order->get_billing_email(), FILTER_VALIDATE_EMAIL)) {
            $this->transaction = new PixTransaction($this, $order, $checkout);
            $response = $this->transaction->createPayment();

            if (is_array($response) && array_key_exists('status', $response)) {
                $this->mercadopago->metaData->updatePaymentsOrderMetadata($order->get_id(), [$response['id']]);

                if ('pending' === $response['status']) {
                    if (
                        'pending_waiting_payment' === $response['status_detail'] ||
                        'pending_waiting_transfer' === $response['status_detail']
                    ) {
                        WC()->cart->empty_cart();

                        $this->mercadopago->order->setPixMetadata($this, $order, $response);

                        $description = $this->storeTranslations['customer_not_paid'];
                        $this->mercadopago->order->addOrderNote($order, $description);

                        if ('pix' === $response['payment_method_id']) {
                            $description = "<div style=\"text-align: justify;\"><p>{$this->storeTranslations['congrats_title']} {$this->storeTranslations['congrats_subtitle']}</small></p>";
                            $this->mercadopago->order->addOrderNote($order, $description, 1);
                        }

                        return array(
                            'result'   => 'success',
                            'redirect' => $order->get_checkout_order_received_url(),
                        );
                    }
                }
            }

            return $this->processReturnFail(
                __FUNCTION__,
                $this->mercadopago->storeTranslations->commonMessages['cho_form_error']
            );
        }

        return $this->processReturnFail(
            __FUNCTION__,
            $this->mercadopago->storeTranslations->commonMessages['cho_default_error']
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
            'status'  => $status,
            'message' => 'Webhook handled successful'
        ];

        wp_send_json_success($response, $status);
    }

    /**
     * Get pix template
     *
     * @param $order
     *
     * @return string
     */
    public function getTemplate($order): string
    {
        $orderId = $order->get_id();
        $pixOn   = get_post_meta($orderId, 'pix_on');
        $pixOn   = (int) array_pop($pixOn);

        if (1 === $pixOn && 'pending' === $order->get_status()) {
            $qrCode         = get_post_meta($orderId, 'mp_pix_qr_code');
            $qrCode         = array_pop($qrCode);

            $qrCodeBase64   = get_post_meta($orderId, 'mp_pix_qr_base64');
            $qrCodeBase64   = array_pop($qrCodeBase64);

            $expirationDate = get_post_meta($orderId, 'checkout_pix_date_expiration');
            $expirationDate = array_pop($expirationDate);

            $siteUrl        = $this->mercadopago->options->get('siteurl');
            $hasGd          = !in_array('gd', get_loaded_extensions(), true);
            $qrCodeImage    = $hasGd ? "data:image/jpeg;base64,{$qrCode}" : "{$siteUrl}/?wc-api=wc_mp_pix_image&id={$orderId}";

            return $this->mercadopago->template->getWoocommerceTemplateHtml(
                'public/congrats/pix-image.php',
                [
                    'qr_code'              => $qrCode,
                    'expiration_date'      => $expirationDate,
                    'expiration_date_text' => $this->storeTranslations['expiration_date_text'],
                    'qr_code_image'        => $qrCodeImage,
                ]
            );
        }

        return '';
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
        $methodExists      = method_exists($order, 'get_meta');
        $qrCodeBase64      = $methodExists ? $order->get_meta('mp_pix_qr_base64') : get_post_meta($order->get_id(), 'mp_pix_qr_base64', true);
        $qrCode            = $methodExists ? $order->get_meta('mp_pix_qr_code') : get_post_meta($order->get_id(), 'mp_pix_qr_code', true);
        $transactionAmount = $methodExists ? $order->get_meta('mp_transaction_amount') : get_post_meta($order->get_id(), 'mp_transaction_amount', true);
        $transactionAmount = number_format($transactionAmount, 2, ',', '.');

        $expirationOption  = $this->mercadopago->options->get('checkout_pix_date_expiration', '30 minutes');
        $countryConfigs    = $this->mercadopago->country->getCountryConfigs();
        $currencySymbol    = $countryConfigs['currency_symbol'];

        if (empty($qr_base64) && empty($qr_code)) {
            return;
        }

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/order/pix-order-received.php',
            [
                'img_pix'             => $this->mercadopago->url->getPluginFileUrl('/assets/images/checkouts/pix', '.png', true),
                'amount'              => $transactionAmount,
                'qr_base64'           => $qrCodeBase64,
                'title_purchase_pix'  => $this->storeTranslations['title_purchase_pix'],
                'title_how_to_pay'    => $this->storeTranslations['title_how_to_pay'],
                'step_one'            => $this->storeTranslations['step_one'],
                'step_two'            => $this->storeTranslations['step_two'],
                'step_three'          => $this->storeTranslations['step_three'],
                'step_four'           => $this->storeTranslations['step_four'],
                'text_amount'         => $this->storeTranslations['text_amount'],
                'currency'            => $currencySymbol,
                'text_scan_qr'        => $this->storeTranslations['text_scan_qr'],
                'text_time_qr_one'    => $this->storeTranslations['qr_date_expiration'],
                'qr_date_expiration'  => $expirationOption,
                'text_description_qr' => $this->storeTranslations['text_description_qr'],
                'qr_code'             => $qrCode,
                'text_button'         => $this->storeTranslations['text_button'],
            ]
        );
    }
}
