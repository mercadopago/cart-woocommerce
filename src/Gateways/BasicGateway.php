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
    public const ID = 'woo-mercado-pago-pix';

    public function __construct()
    {
        parent::__construct();

        $this->id = self::ID;
        $this->icon = $this->getIcon();
        $this->has_fields = true;

        $this->method_title = 'Mercado pago - Customized Checkout';
        $this->method_description = 'Transparent Checkout in your store environment';

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->description = $this->get_option('description');

        $this->payment_scripts($this->id);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
    }

    /**
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'header'                 => [
                'type'        => 'title',
                'title'       => $this->adminTranslations->pixSettings['header_title'],
                'description' => $this->adminTranslations->pixSettings['header_description'],
                'class'       => 'mp_title_header',
            ],
            'card_settings'          => [
                'type'        => 'mp_card_info',
                'title'       => $this->adminTranslations->pixSettings['card_settings_title'],
                'subtitle'    => $this->adminTranslations->pixSettings['card_settings_subtitle'],
                'button_text' => $this->adminTranslations->pixSettings['card_settings_button_text'],
                'button_url'  => $this->links->getLinks()['admin_settings_page'],
                'icon'        => 'mp-icon-badge-info',
                'color_card'  => 'mp-alert-color-sucess',
                'size_card'   => 'mp-card-body-size',
                'target'      => '_self',
            ],
            'enabled'                => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->adminTranslations->pixSettings['enabled_title'],
                'subtitle'     => $this->adminTranslations->pixSettings['enabled_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations->pixSettings['enabled_descriptions_enabled'],
                    'disabled' => $this->adminTranslations->pixSettings['enabled_descriptions_disabled'],
                ],
            ],
            'title'                  => [
                'type'            => 'text',
                'title'           => $this->adminTranslations->pixSettings['title_title'],
                'description'     => $this->adminTranslations->pixSettings['title_description'],
                'description_tip' => $this->adminTranslations->pixSettings['title_description_tip'],
                'default'         => 'Pix',
                'maxlength'       => 100,
                'class'           => 'limit-title-max-length',
            ],
            'expiration_date'        => [
                'type'        => 'select',
                'title'       => $this->adminTranslations->pixSettings['expiration_date_title'],
                'description' => $this->adminTranslations->pixSettings['expiration_date_description'],
                'default'     => '30 minutes',
                'options'     => [
                    '15 minutes' => $this->adminTranslations->pixSettings['expiration_date_options_fifteen_minutes'],
                    '30 minutes' => $this->adminTranslations->pixSettings['expiration_date_options_thirty_minutes'],
                    '60 minutes' => $this->adminTranslations->pixSettings['expiration_date_options_sixty_minutes'],
                    '12 hours'   => $this->adminTranslations->pixSettings['expiration_date_options_twelve_hours'],
                    '24 hours'   => $this->adminTranslations->pixSettings['expiration_date_options_twenty_four_hours'],
                    '2 days'     => $this->adminTranslations->pixSettings['expiration_date_options_two_days'],
                    '3 days'     => $this->adminTranslations->pixSettings['expiration_date_options_three_days'],
                    '4 days'     => $this->adminTranslations->pixSettings['expiration_date_options_four_days'],
                    '5 days'     => $this->adminTranslations->pixSettings['expiration_date_options_five_days'],
                    '6 days'     => $this->adminTranslations->pixSettings['expiration_date_options_six_days'],
                    '7 days'     => $this->adminTranslations->pixSettings['expiration_date_options_seven_days'],
                ]
            ],
            'currency_conversion'    => [
                'type'     => 'mp_toggle_switch',
                'title'    => $this->adminTranslations->pixSettings['currency_conversion_title'],
                'subtitle' => $this->adminTranslations->pixSettings['currency_conversion_subtitle'],
                'default'  => 'no',
                'descriptions' => [
                    'enabled'  => $this->adminTranslations->pixSettings['currency_conversion_descriptions_enabled'],
                    'disabled' => $this->adminTranslations->pixSettings['currency_conversion_descriptions_disabled'],
                ],
            ],
            'card_info'              => [
                'type'        => 'mp_card_info',
                'title'       => $this->adminTranslations->pixSettings['card_info_title'],
                'subtitle'    => $this->adminTranslations->pixSettings['card_info_subtitle'],
                'button_text' => $this->adminTranslations->pixSettings['card_info_button_text'],
                'button_url'  => $this->links->getLinks()['mercadopago_pix'],
                'icon'        => 'mp-icon-badge-info',
                'color_card'  => 'mp-alert-color-sucess',
                'size_card'   => 'mp-card-body-size',
                'target'      => '_blank'
            ],
            'advanced_configuration' => [
                'type'        => 'title',
                'title'       => $this->adminTranslations->pixSettings['advanced_configuration_title'],
                'description' => $this->adminTranslations->pixSettings['advanced_configuration_subtitle'],
                'class'       => 'mp_subtitle_bd',
            ],
            'discount'               => [
                'type'              => 'mp_activable_input',
                'title'             => $this->adminTranslations->pixSettings['discount_title'],
                'input_type'        => 'number',
                'description'       => $this->adminTranslations->pixSettings['discount_description'],
                'checkbox_label'    => $this->adminTranslations->pixSettings['discount_checkbox_label'],
                'default'           => '0',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0',
                    'max'  => '99',
                ],
            ],
            'commission'             => [
                'type'              => 'mp_activable_input',
                'title'             => $this->adminTranslations->pixSettings['commission_title'],
                'input_type'        => 'number',
                'description'       => $this->adminTranslations->pixSettings['commission_description'],
                'checkbox_label'    => $this->adminTranslations->pixSettings['commission_checkbox_label'],
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
        $this->mercadopago->template->getWoocommerceTemplate(
            'checkout.php',
            dirname(__FILE__) . '/../../templates/public/gateways/'
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

    private function getIcon()
    {
        return apply_filters('woo_mercado_pago_icon', plugins_url('../assets/images/icons/icon-pix.png', plugin_dir_path(__FILE__)));
    }
}
