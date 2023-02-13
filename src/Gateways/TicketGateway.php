<?php

namespace MercadoPago\Woocommerce\Gateways;

use MercadoPago\Woocommerce\Interfaces\MercadoPagoGatewayInterface;

if (!defined('ABSPATH')) {
    exit;
}

class TicketGateway extends AbstractGateway implements MercadoPagoGatewayInterface
{
    /**
     * ID
     *
     * @const
     */
    const ID = 'woo-mercado-pago-ticket';

    /**
     * @const
     */
    public const CHECKOUT_NAME = 'checkout-ticket';

    /**
     * TicketGateway constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->translations = $this->mercadopago->storeTranslations->checkoutTicket;

        $this->id                 = self::ID;
        $this->icon               = null;
        $this->title              = $this->translations['gateway_title'];
        $this->description        = $this->translations['gateway_dscription'];
        $this->method_title       = $this->translations['method_title'];
        $this->method_description = $this->description;
        $this->has_fields         = true;
        $this->supports           = ['products', 'refunds'];
        $this->icon               = $this->getCheckoutIcon();

        $this->init_form_fields();
        $this->init_settings();
        $this->payment_scripts($this->id);

        $this->mercadopago->gateway->registerUpdateOptions($this);
        $this->mercadopago->endpoints->registerApiEndpoint($this->id, [$this, 'webhook']);

        $this->mercadopago->gateway->registerThankYouPage($this->id, [$this, 'thankyou']);
    }

    /**
     * Get Mercado Pago Icon
     *
     * @return mixed
     */
    private function getCheckoutIcon()
    {
        $siteId = strtoupper($this->mercadopago->seller->getSiteId());
        $iconName = 'MLB' === $siteId ? 'icon-ticket-mlb' : 'icon-ticket';
        return $this->mercadopago->plugin->getGatewayIcon($iconName);
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
                'title'       => $this->translations['config_header_title'],
                'description' => $this->translations['config_header_desc'],
            ],
            'enabled'       => [
                'type'         => 'mp_toggle_switch',
                'title'        => $this->translations['config_enabled_title'],
                'subtitle'     => $this->translations['config_enabled_subtitle'],
                'default'      => 'no',
                'descriptions' => [
                    'enabled'  => $this->translations['config_enabled_enabled'],
                    'disabled' => $this->translations['config_enabled_disabled'],
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
            'type_payments' => $this->field_ticket_payments(),
            'date_expiration' => [
                'title'       => __('Payment Due', 'woocommerce-mercadopago'),
                'type'        => 'number',
                'description' => __('In how many days will cash payments expire.', 'woocommerce-mercadopago'),
                'default'     => MP_TICKET_DATE_EXPIRATION,
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
            'coupon_mode' => [
                'title'       => __('Discount coupons', 'woocommerce-mercadopago'),
                'type'        => 'mp_toggle_switch',
                'default'     => 'no',
                'subtitle' => __('Will you offer discount coupons to customers who buy with Mercado Pago?', 'woocommerce-mercadopago'),
                'descriptions' => array(
                    'enabled' => __('Discount coupons is <b>active</b>.', 'woocommerce-mercadopago'),
                    'disabled' => __('Discount coupons is <b>disabled</b>.', 'woocommerce-mercadopago'),
                ),
            ],
            'stock_reduce_mode' => [
                'title'       => __('Reduce inventory', 'woocommerce-mercadopago'),
                'type'        => 'mp_toggle_switch',
                'default'     => 'no',
                'subtitle'    => __('Activates inventory reduction during the creation of an order, whether or not the final payment is credited. Disable this option to reduce it only when payments are approved.', 'woocommerce-mercadopago'),
                'descriptions' => array(
                    'enabled'  => __('Reduce inventory is <b>enabled</b>.', 'woocommerce-mercadopago'),
                    'disabled' => __('Reduce inventory is <b>disabled</b>.', 'woocommerce-mercadopago'),
                ),
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

    //@TODO remove plugins_url, change plugin_version and fix link_terms_and_conditions
    /**
     * Render gateway checkout template
     *
     * @return void
     */
    public function payment_fields(): void
    {
        $this->mercadopago->scripts->registerStoreStyle(
            'woocommerce-mercadopago-narciso-styles',
            plugins_url('../assets/css/checkout/mp-plugins-components.css', plugin_dir_path(__FILE__))
        );
        $this->mercadopago->scripts->registerStoreScript(
            'woocommerce-mercadopago-narciso-scripts',
            plugins_url('../assets/js/checkout/mp-plugins-components.js', plugin_dir_path(__FILE__))
        );

		$amount    = $this->get_order_total();
		$discount  = $amount * ( $this->gateway_discount / 100 );
		$comission = $amount * ( $this->commission / 100 );
		$amount    = $amount - $discount + $comission;

		$logged_user_email = ( 0 !== wp_get_current_user()->ID ) ? wp_get_current_user()->user_email : null;
		$address           = get_user_meta( wp_get_current_user()->ID, 'billing_address_1', true );
		$address_2         = get_user_meta( wp_get_current_user()->ID, 'billing_address_2', true );
		$address          .= ( ! empty( $address_2 ) ? ' - ' . $address_2 : '' );
		$country           = get_user_meta( wp_get_current_user()->ID, 'billing_country', true );
		$address          .= ( ! empty( $country ) ? ' - ' . $country : '' );

        $siteId         = strtoupper($this->mercadopago->seller->getSiteId());
        $test_mode_link    = $this->get_mp_devsite_link($siteId);

        $parameters = array(
            'test_mode'            => ! $this->mercadopago->store->getCheckboxCheckoutProductionMode(),
            'test_mode_link'       => $test_mode_link,
            'amount'               => $amount,
            'payment_methods'      => $this->getTreatedPaymentMethods(),
            'site_id'              => $this->mercadopago->seller->getSiteId(),
            'coupon_mode'          => isset($logged_user_email) ? $this->settings['coupon_mode'] : 'no',
            'discount_action_url'  => $this->discount_action_url,
            'payer_email'          => esc_js($logged_user_email),
            'currency_ratio'       => $currency_ratio,
            'woocommerce_currency' => get_woocommerce_currency(),
            'account_currency'     => $this->site_data['currency'],
            'images_path'          => plugins_url('../assets/images/', plugin_dir_path(__FILE__)),
            'febraban'             => (0 !== wp_get_current_user()->ID) ?
                array(
                    'firstname' => esc_js(wp_get_current_user()->user_firstname),
                    'lastname'  => esc_js(wp_get_current_user()->user_lastname),
                    'docNumber' => '',
                    'address'   => esc_js($address),
                    'number'    => '',
                    'city'      => esc_js(get_user_meta(wp_get_current_user()->ID, 'billing_city', true)),
                    'state'     => esc_js(get_user_meta(wp_get_current_user()->ID, 'billing_state', true)),
                    'zipcode'   => esc_js(get_user_meta(wp_get_current_user()->ID, 'billing_postcode', true)),
                ) :
                array(
                    'firstname' => '',
                    'lastname'  => '',
                    'docNumber' => '',
                    'address'   => '',
                    'number'    => '',
                    'city'      => '',
                    'state'     => '',
                    'zipcode'   => '',
                ),
        );

        $this->mercadopago->template->getWoocommerceTemplate(
            'ticket-checkout.php',
            dirname(__FILE__) . '/../../templates/public/gateways/',
            $parameters
        );
    }

    /**
     * Field ticket payments
     *
     * @return array
     */
    private function field_ticket_payments()
    {
        $paymentMethods = $this->mercadopago->seller->getCheckoutTicketPaymentMethods();

        if (!is_array($paymentMethods)) {
            $paymentMethods = json_decode($paymentMethods, true);
        }

        $payment_list = array(
            'description'          => __('Enable the available payment methods', 'woocommerce-mercadopago'),
            'title'                => __('Payment methods', 'woocommerce-mercadopago'),
            'desc_tip'             => __('Choose the available payment methods in your store.', 'woocommerce-mercadopago'),
            'type'                 => 'mp_checkbox_list',
            'payment_method_types' => array(
                'ticket'           => array(
                    'label'        => __('All payment methods', 'woocommerce-mercadopago'),
                    'list'         => array(),
                ),
            ),
        );

        foreach ($paymentMethods as $paymentMethod) {
            $payment_list['payment_method_types']['ticket']['list'][] = array(
                'id'        => $paymentMethod['id'],
                'field_key' => $this->get_field_key($paymentMethod['id']),
                'label'     => $paymentMethod['name'], //@TODO array_key_exists('payment_places', $paymentMethod) ? $paymentMethod['name'] . ' (' . $this->build_paycash_payments_string() . ')' : $paymentMethod['name'],
                'value'     => $this->get_option($paymentMethod['id'], 'yes'),
                'type'      => 'checkbox',
            );
        }

        return $payment_list;
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

	/**
	 * Update settings script ticket
	 *
	 * @param string $order_id Order Id.
	 */
    public function thankyou($order_id): void
    {
		$order               = wc_get_order( $order_id );
		$transaction_details = ( method_exists( $order, 'get_meta' ) ) ? $order->get_meta( '_transaction_details_ticket' ) : get_post_meta( $order->get_id(), '_transaction_details_ticket', true );

		if ( empty( $transaction_details ) ) {
			return;
        }

        $this->mercadopago->template->getWoocommerceTemplate(
            'show-ticket.php',
            dirname(__FILE__) . '/../../templates/public/thankyou/',
            array( 'transaction_details' => $transaction_details )
        );
    }

	/**
	 * Get Mercado Pago Devsite Page Link
	 *
	 * @param String $country Country Acronym
	 *
	 * @return String
	 */
	private static function get_mp_devsite_link( $country ) {
		$country_links = [
			'mla' => 'https://www.mercadopago.com.ar/developers/es/guides/plugins/woocommerce/testing',
			'mlb' => 'https://www.mercadopago.com.br/developers/pt/guides/plugins/woocommerce/testing',
			'mlc' => 'https://www.mercadopago.cl/developers/es/guides/plugins/woocommerce/testing',
			'mco' => 'https://www.mercadopago.com.co/developers/es/guides/plugins/woocommerce/testing',
			'mlm' => 'https://www.mercadopago.com.mx/developers/es/guides/plugins/woocommerce/testing',
			'mpe' => 'https://www.mercadopago.com.pe/developers/es/guides/plugins/woocommerce/testing',
			'mlu' => 'https://www.mercadopago.com.uy/developers/es/guides/plugins/woocommerce/testing',
		];

		$link = array_key_exists($country, $country_links) ? $country_links[$country] : $country_links['mla'];

		return $link;
	}

	/**
	 * Get treated payment methods
	 *
	 * @return array
	 */
	public function getTreatedPaymentMethods() {
        $treatedPaymentMethods = [];
        $activePaymentMethods = [];
		$ticketPaymentMethods = $this->mercadopago->seller->getCheckoutTicketPaymentMethods();

		if ( ! empty( $ticketPaymentMethods ) ) {
			foreach ( $ticketPaymentMethods as $ticketPaymentMethod ) {
				if (!isset($this->settings[$ticketPaymentMethod['id']])
					|| 'yes' === $this->settings[$ticketPaymentMethod['id']]) {
					array_push( $activePaymentMethods, $ticketPaymentMethod );
				}
			}
		}
        sort($activePaymentMethods);

		foreach ( $activePaymentMethods as $paymentMethod ) {
			$treatedPaymentMethod = [];
			if ( isset($paymentMethod['payment_places']) ) {
				foreach ( $paymentMethod['payment_places'] as $place ) { //@TODO create CompositeIdHelper
					$paymentPlaceId                  = $paymentMethod['id']; //( new WC_WooMercadoPago_Composite_Id_Helper() )->generateIdFromPlace($paymentMethod['id'], $place['payment_option_id']);
					$treatedPaymentMethod['id']      = $paymentPlaceId;
					$treatedPaymentMethod['value']   = $paymentPlaceId;
					$treatedPaymentMethod['rowText'] = $place['name'];
					$treatedPaymentMethod['img']     = $place['thumbnail'];
					$treatedPaymentMethod['alt']     = $place['name'];
					array_push( $treatedPaymentMethods, $treatedPaymentMethod);
				}
			} else {
				$treatedPaymentMethod['id']      = $paymentMethod['id'];
				$treatedPaymentMethod['value']   = $paymentMethod['id'];
				$treatedPaymentMethod['rowText'] = $paymentMethod['name'];
				$treatedPaymentMethod['img']     = $paymentMethod['secure_thumbnail'];
				$treatedPaymentMethod['alt']     = $paymentMethod['name'];
				array_push( $treatedPaymentMethods, $treatedPaymentMethod);
			}
		}

		return $treatedPaymentMethods;
	}
}
