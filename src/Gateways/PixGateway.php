<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class PixGateway extends AbstractGateway implements MercadoPagoGatewayInterface
{
    /**
     * @const
     */
    public const ID = 'woo-mercado-pago-pix';

    /**
     * @const
     */
    public const CHECKOUT_NAME = 'checkout-pix';

    public function __construct()
    {
        parent::__construct();

        $this->id                 = self::ID;
        $this->icon               = $this->mercadopago->plugin->getGatewayIcon('icon-pix');
        $this->title              = $this->mercadopago->seller->getGatewayTitle($this->mercadopago->adminTranslations->pixSettings['gateway_title']);
        $this->description        = $this->mercadopago->adminTranslations->pixSettings['gateway_description'];
        $this->method_title       = $this->mercadopago->adminTranslations->pixSettings['gateway_method_title'];
        $this->method_description = $this->mercadopago->adminTranslations->pixSettings['gateway_method_description'];
        $this->has_fields         = true;
        $this->supports           = ['products', 'refunds'];
        $this->activatedGateway   = $this->mercadopago->seller->getCheckoutPaymentMethodPix();

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
        parent::init_form_fields();

        if (
            !empty($this->mercadopago->store->getCheckoutCountry()) &&
            !empty($this->mercadopago->seller->getCredentialsPublicKey()) &&
            !empty($this->mercadopago->seller->getCredentialsAccessToken())
        ) {
            $paymentMethodPix = $this->mercadopago->seller->getCheckoutPaymentMethodPix();

            if (empty($paymentMethodPix) || !in_array('pix', $paymentMethodPix['pix'], true)) {
                $this->mercadopago->notices->adminNoticeMissPix();

                $stepsContent = $this->mercadopago->template->getWoocommerceTemplateHtml(
                    'admin/settings/steps.php',
                    [
                        'title'                       => $this->mercadopago->adminTranslations->pixSettings['steps_title'],
                        'step_one_text'               => $this->mercadopago->adminTranslations->pixSettings['steps_step_one_text' ],
                        'step_two_text'               => $this->mercadopago->adminTranslations->pixSettings['steps_step_two_text'],
                        'step_three_text'             => $this->mercadopago->adminTranslations->pixSettings['steps_step_three_text'],
                        'observation_one'             => $this->mercadopago->adminTranslations->pixSettings['steps_observation_one'],
                        'observation_two'             => $this->mercadopago->adminTranslations->pixSettings['steps_observation_two'],
                        'button_about_pix'            => $this->mercadopago->adminTranslations->pixSettings['steps_button_about_pix'],
                        'observation_three'           => $this->mercadopago->adminTranslations->pixSettings['steps_observation_three'],
                        'link_title_one'              => $this->mercadopago->adminTranslations->pixSettings['steps_link_title_one'],
                        'link_url_one'                => $this->mercadopago->links->getLinks()['mercadopago_pix'],
                        'link_url_two'                => $this->mercadopago->links->getLinks()['mercadopago_support'],
                    ]
                );

                $this->form_fields = [
                    'header'        => [
                        'type'        => 'mp_config_title',
                        'title'       => $this->mercadopago->adminTranslations->pixSettings['header_title'],
                        'description' => $this->mercadopago->adminTranslations->pixSettings['header_description'],
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
                        'title'       => $this->mercadopago->adminTranslations->pixSettings['header_title'],
                        'description' => $this->mercadopago->adminTranslations->pixSettings['header_description'],
                    ],
                    'card_settings'                      => [
                        'type'        => 'mp_card_info',
                        'value'       => [
                            'title'       => $this->mercadopago->adminTranslations->pixSettings['card_settings_title'],
                            'subtitle'    => $this->mercadopago->adminTranslations->pixSettings['card_settings_subtitle'],
                            'button_text' => $this->mercadopago->adminTranslations->pixSettings['card_settings_button_text'],
                            'button_url'  => $this->mercadopago->links->getLinks()['admin_settings_page'],
                            'icon'        => 'mp-icon-badge-info',
                            'color_card'  => 'mp-alert-color-success',
                            'size_card'   => 'mp-card-body-size',
                            'target'      => '_self',
                        ],
                    ],
                    'enabled'                            => [
                        'type'         => 'mp_toggle_switch',
                        'title'        => $this->mercadopago->adminTranslations->pixSettings['enabled_title'],
                        'subtitle'     => $this->mercadopago->adminTranslations->pixSettings['enabled_subtitle'],
                        'default'      => 'no',
                        'descriptions' => [
                            'enabled'  => $this->mercadopago->adminTranslations->pixSettings['enabled_descriptions_enabled'],
                            'disabled' => $this->mercadopago->adminTranslations->pixSettings['enabled_descriptions_disabled'],
                        ],
                    ],
                    'title'                              => [
                        'type'            => 'text',
                        'title'           => $this->mercadopago->adminTranslations->pixSettings['title_title'],
                        'description'     => $this->mercadopago->adminTranslations->pixSettings['title_description'],
                        'default'         => $this->mercadopago->adminTranslations->pixSettings['title_default'],
                        'desc_tip'        => $this->mercadopago->adminTranslations->pixSettings['title_desc_tip'],
                        'class'           => 'limit-title-max-length',
                    ],
                    'expiration_date'                    => [
                        'type'        => 'select',
                        'title'       => $this->mercadopago->adminTranslations->pixSettings['expiration_date_title'],
                        'description' => $this->mercadopago->adminTranslations->pixSettings['expiration_date_description'],
                        'default'     => '30 minutes',
                        'options'     => [
                            '15 minutes' => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_fifteen_minutes'],
                            '30 minutes' => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_thirty_minutes'],
                            '60 minutes' => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_sixty_minutes'],
                            '12 hours'   => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_twelve_hours'],
                            '24 hours'   => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_twenty_four_hours'],
                            '2 days'     => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_two_days'],
                            '3 days'     => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_three_days'],
                            '4 days'     => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_four_days'],
                            '5 days'     => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_five_days'],
                            '6 days'     => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_six_days'],
                            '7 days'     => $this->mercadopago->adminTranslations->pixSettings['expiration_date_options_seven_days'],
                        ]
                    ],
                    'currency_conversion'                => [
                        'type'         => 'mp_toggle_switch',
                        'title'        => $this->mercadopago->adminTranslations->pixSettings['currency_conversion_title'],
                        'subtitle'     => $this->mercadopago->adminTranslations->pixSettings['currency_conversion_subtitle'],
                        'default'      => 'no',
                        'descriptions' => [
                            'enabled'  => $this->mercadopago->adminTranslations->pixSettings['currency_conversion_descriptions_enabled'],
                            'disabled' => $this->mercadopago->adminTranslations->pixSettings['currency_conversion_descriptions_disabled'],
                        ],
                    ],
                    'card_info_helper'                   => [
                        'type'  => 'title',
                        'value' => '',
                    ],
                    'card_info'                          => [
                        'type'        => 'mp_card_info',
                        'value'       => [
                            'title'       => $this->mercadopago->adminTranslations->pixSettings['card_info_title'],
                            'subtitle'    => $this->mercadopago->adminTranslations->pixSettings['card_info_subtitle'],
                            'button_text' => $this->mercadopago->adminTranslations->pixSettings['card_info_button_text'],
                            'button_url'  => $this->mercadopago->links->getLinks()['mercadopago_pix'],
                            'icon'        => 'mp-icon-badge-info',
                            'color_card'  => 'mp-alert-color-success',
                            'size_card'   => 'mp-card-body-size',
                            'target'      => '_blank',
                        ]
                    ],
                    'advanced_configuration_title'       => [
                        'type'  => 'title',
                        'title' => $this->mercadopago->adminTranslations->pixSettings['advanced_configuration_title'],
                        'class' => 'mp-subtitle-body',
                    ],
                    'advanced_configuration_description' => [
                        'type'  => 'title',
                        'title' => $this->mercadopago->adminTranslations->pixSettings['advanced_configuration_subtitle'],
                        'class' => 'mp-small-text',
                    ],
                    'discount'               => [
                        'type'              => 'mp_actionable_input',
                        'title'             => $this->mercadopago->adminTranslations->pixSettings['discount_title'],
                        'input_type'        => 'number',
                        'description'       => $this->mercadopago->adminTranslations->pixSettings['discount_description'],
                        'checkbox_label'    => $this->mercadopago->adminTranslations->pixSettings['discount_checkbox_label'],
                        'default'           => '0',
                        'custom_attributes' => [
                            'step' => '0.01',
                            'min'  => '0',
                            'max'  => '99',
                        ],
                    ],
                    'commission'             => [
                        'type'              => 'mp_actionable_input',
                        'title'             => $this->mercadopago->adminTranslations->pixSettings['commission_title'],
                        'input_type'        => 'number',
                        'description'       => $this->mercadopago->adminTranslations->pixSettings['commission_description'],
                        'checkbox_label'    => $this->mercadopago->adminTranslations->pixSettings['commission_checkbox_label'],
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
        $parameters = [
            'test_mode'                        => $this->mercadopago->seller->isTestMode(),
            'test_mode_title'                  => $this->mercadopago->checkoutTranslations->pixCheckout['test_mode_title'],
            'test_mode_description'            => $this->mercadopago->checkoutTranslations->pixCheckout['test_mode_description'],
            'pix_template_title'               => $this->mercadopago->checkoutTranslations->pixCheckout['pix_template_title'],
            'pix_template_subtitle'            => $this->mercadopago->checkoutTranslations->pixCheckout['pix_template_subtitle'],
            'pix_template_alt'                 => $this->mercadopago->checkoutTranslations->pixCheckout['pix_template_alt'],
            'pix_template_src'                 => plugins_url('../assets/images/pix.png', plugin_dir_path(__FILE__)),
            'terms_and_conditions_description' => $this->mercadopago->checkoutTranslations->pixCheckout['terms_and_conditions_description'],
            'terms_and_conditions_link_text'   => $this->mercadopago->checkoutTranslations->pixCheckout['terms_and_conditions_link_text'],
            'terms_and_conditions_link_src'    => $this->mercadopago->links->getLinks()['mercadopago_terms_and_conditions'],
        ];

        $this->mercadopago->template->getWoocommerceTemplate(
            'public/gateways/pix-checkout.php',
            $parameters
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
}
