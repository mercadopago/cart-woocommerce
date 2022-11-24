<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Hooks\Endpoints;

if (!defined('ABSPATH')) {
    exit;
}

class ExampleGateway extends \WC_Payment_Gateway implements MercadoPagoGatewayInterface
{
    /**
     * @var Endpoints
     */
    protected $endpoints;

    /**
     * ExampleGateway constructor
     */
    public function __construct()
    {
        $this->endpoints = new Endpoints();

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
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        $this->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);
    }

    /**
     * Init form fields for checkout configuration
     *
     * @return void
     */
    public function init_form_fields(): void
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

    /**
     * Added gateway scripts
     *
     * @return void
     */
    public function payment_scripts(): void
    {
    }

    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        wc_get_template(
            'checkout.php',
            array(),
            null,
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
     * @param $orderId
     *
     * @return array
     */
    public function process_payment($orderId): array
    {
        global $woocommerce;

        $order = wc_get_order($orderId);
        $order->payment_complete();
        $order->add_order_note('Hey, your order is paid! Thank you!', true);

        wc_reduce_stock_levels($orderId);

        $woocommerce->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order)
        );
    }

    /**
     * Receive gateway webhook notifications
     *
     * @return void
     */
    public function webhook(): void
    {
        $status   = 200;
        $response = array(
            'status'  => $status,
            'message' => 'Webhook handled successful'
        );

        wp_send_json_success($response, $status);
    }
}
