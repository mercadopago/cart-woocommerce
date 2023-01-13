<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class BasicGateway extends AbstractGateway implements MercadoPagoGatewayInterface
{
	/**
	 * ID
	 *
	 * @const
	 */
	const ID = 'woo-mercado-pago-basic';

    /**
     * BasicGateway constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->id                 = self::ID;
        $this->icon               = null;
        $this->title              = 'Checkout Pro';
        $this->description        = 'Debit, Credit and invoice in Mercado Pago environment';
        $this->method_title       = 'Mercado Pago - Checkout Pro';
        $this->method_description = $this->description;
        $this->has_fields         = true;
        $this->supports           = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();
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
                'class'       => 'mp-hidden-field-description',
            ],
            'currency_conversion'   => [
                'type'         => 'mp_toggle_switch',
                'title'        => 'Convert Currency',
                'subtitle'     => 'Activate this option so that the value of the currency set in WooCommerce is compatible with the value of the currency you use in Mercado Pago.',
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => 'Currency convertion is <b>enabled</b>.',
                    'disabled' => 'Currency convertion is <b>disabled</b>.',
                ],
            ],
            'ex_payments'   => $this->field_ex_payments(),
            'installments'   => [
                'type'        => 'select',
                'title'       => 'Maximum number of installments',
                'description' => 'What is the maximum quota with which a customer can buy?',
                'default'     => '24',
                'options'     => [
                    '1'  => '1 installment',
                    '2'  => '2 installments',
                    '3'  => '3 installments',
                    '4'  => '4 installments',
                    '5'  => '5 installments',
                    '6'  => '6 installments',
                    '10' => '10 installments',
                    '12' => '12 installments',
                    '15' => '15 installments',
                    '18' => '18 installments',
                    '24' => '24 installments',
                ],
            ],
            'checkout_payments_advanced_title'   => [
                'type'        => 'title',
                'title'       => 'Advanced settings',
                'class'       => 'mp_subtitle_bd',
            ],
            'checkout_payments_advanced_description'   => [
                'type'        => 'title',
                'title'       => 'Edit these advanced fields only when you want to modify the preset values.',
                'class'       => 'mp_small_text',
            ],
            'method'   => [
                'type'        => 'select',
                'title'       => 'Payment experience',
                'description' => 'Define what payment experience your customers will have, whether inside or outside your store.',
                'default'     => 'redirect',
                'options'     => [
                    'redirect' => __('Redirect', 'woocommerce-mercadopago'),
				    'modal'    => __('Modal', 'woocommerce-mercadopago'),
                ],
            ],
            'auto_return'   => [
                'type'         => 'mp_toggle_switch',
                'title'        => 'Return to the store',
                'subtitle'     => 'Do you want your customer to automatically return to the store after payment?',
                'default'      => 'yes',
                'descriptions' => [
                    'enabled' => __('The buyer <b>will be automatically redirected to the store</b>.', 'woocommerce-mercadopago'),
				    'disabled' => __('The buyer <b>will not be automatically redirected to the store</b>.', 'woocommerce-mercadopago'),
                ],
            ],
            'success_url'   => [
                'type'        => 'text',
                'title'       => 'Payment URL success',
                'description' => $this->validateBackUrl($this->success_url, 'Choose the URL that we will show your customers when they finish their purchase.'),
            ],
            'failure_url'   => [
                'type'        => 'text',
                'title'       => 'Payment URL rejected',
                'description' => $this->validateBackUrl($this->failure_url, 'Choose the URL that we will show to your customers when we refuse their purchase. Make sure it includes a message appropriate to the situation and give them useful information so they can solve it.'),
            ],
            'pending_url'   => [
                'type'        => 'text',
                'title'       => 'Payment URL pending',
                'description' => $this->validateBackUrl($this->pending_url, 'Choose the URL that we will show to your customers when they have a payment pending approval.'),
            ],
            'binary_mode'   => [
                'type'         => 'mp_toggle_switch',
                'title'        => 'Automatic decline of payments without instant approval',
                'subtitle'     => 'Enable it if you want to automatically decline payments that are not instantly approved by banks or other institutions.',
                'descriptions' => [
                    'enabled'  => 'Pending payments <b>will be automatically declined</b>.',
                    'disabled' => 'Pending payments <b>will not be automatically declined</b>.',
                ],
                'default'      => 'Debit, Credit and Invoice in Mercado Pago environment.',
            ],
        ];
    }

    /**
	 * Validate Back URL and return error message or default string
	 *
	 * @return string
	 */
    private function validateBackUrl($url, $default) {
        if ( ! empty($url) && filter_var($url, FILTER_VALIDATE_URL) === false ) {
			return '<img width="14" height="14" src="' . plugins_url('assets/images/warning.png', plugin_dir_path(__FILE__)) . '"> ' .
				'This seems to be an invalid URL.';
		}
        return $default;
    }

    /**
	 * Field payments
	 *
	 * @return array
	 */
	private function field_ex_payments() {
		$payment_list = array(
			'description'          => __('Enable the payment methods available to your clients.', 'woocommerce-mercadopago'),
			'title'                => __('Choose the payment methods you accept in your store', 'woocommerce-mercadopago'),
			'type'                 => 'mp_checkbox_list',
			'payment_method_types' => array(
				'credit_card'      => array(
					'label'        => __('Credit Cards', 'woocommerce-mercadopago'),
					'list'         => array(),
				),
				'debit_card'       => array(
					'label'        => __('Debit Cards', 'woocommerce-mercadopago'),
					'list'         => array(),
				),
				'other'            => array(
					'label'        => __('Other Payment Methods', 'woocommerce-mercadopago'),
					'list'         => array(),
				),
			),
		);

		$all_payments = get_option('_checkout_payments_methods', '');

		if ( empty($all_payments) ) {
			return $payment_list;
		}

		foreach ( $all_payments as $payment_method ) {
			if ( 'credit_card' === $payment_method['type'] ) {
				$payment_list['payment_method_types']['credit_card']['list'][] = array(
				'id'        => 'ex_payments_' . $payment_method['id'],
				'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
				'label'     => $payment_method['name'],
				'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
				'type'      => 'checkbox',
				);
			} elseif ( 'debit_card' === $payment_method['type'] || 'prepaid_card' === $payment_method['type'] ) {
				$payment_list['payment_method_types']['debit_card']['list'][] = array(
				'id'        => 'ex_payments_' . $payment_method['id'],
				'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
				'label'     => $payment_method['name'],
				'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
				'type'      => 'checkbox',
				);
			} else {
				$payment_list['payment_method_types']['other']['list'][] = array(
				'id'        => 'ex_payments_' . $payment_method['id'],
				'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
				'label'     => $payment_method['name'],
				'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
				'type'      => 'checkbox',
				);
			}
		}

		return $payment_list;
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
