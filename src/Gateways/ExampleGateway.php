<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class ExampleGateway extends AbstractGateway implements MercadoPagoGatewayInterface
{
    /**
     * ExampleGateway constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->id                 = 'mercadopago';
        $this->icon               = null;
        $this->has_fields         = true;
        $this->method_title       = 'Mercado Pago Gateway';
        $this->method_description = 'The best woocommerce gateway';
        $this->supports           = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->enabled     = $this->get_option('enabled');
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
            'config_header' => [
                'type'        => 'mp_config_title',
                'title'       => 'Checkout Pro',
                'description' => 'With Checkout Pro you sell with all the safety inside Mercado Pago environment.',
            ],
            'enabled'       => [
                'type'         => 'mp_toggle_switch',
                'title'        => 'Enable the checkout',
                'subtitle'     => 'By disabling it, you will disable all payment methods of this checkout.',
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => 'The checkout is <b>enabled</b>.',
                    'disabled' => 'The checkout is <b>disabled</b>.',
                ],
            ],
            'title'         => [
                'type'        => 'text',
                'title'       => 'Title in the store Checkout',
                'description' => 'Change the display text in Checkout, maximum characters: 85',
                'default'     => 'Credit Card',
                'desc_tip'    => 'The text inserted here will not be translated to other languages',
                'class'       => 'limit-title-max-length',
            ],
            'description'   => [
                'type'        => 'text',
                'title'       => 'Description',
                'description' => '',
                'default'     => 'Debit, Credit and Invoice in Mercado Pago environment.',
                'class'       => 'mp-hidden-field-description'
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
}
