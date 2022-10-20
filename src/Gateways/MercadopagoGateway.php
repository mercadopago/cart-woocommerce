<?php

namespace MercadoPago\Woocommerce\Gateways;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @method init_settings()
 * @method get_option(string $string)
 */
class MercadopagoGateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'mercadopago';
        $this->icon = null;
        $this->has_fields = true;
        $this->method_title = 'Mercado Pago Gateway';
        $this->method_description = 'The best woocommerce gateway';
        $this->supports = array('products');

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable Misha Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'Credit Card',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => 'Pay with your credit card via our super-cool payment gateway.',
            )
        );
    }

    public function payment_fields()
    {
    }

    public function payment_scripts()
    {
    }

    public function validate_fields()
    {
    }

    public function process_payment($order_id)
    {
    }

    public function webhook()
    {
    }
}
