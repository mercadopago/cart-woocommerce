<?php
/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * @package MercadoPago
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_WooMercadoPago_Ticket_Gateway
 */
class WC_WooMercadoPago_Ticket_Gateway extends WC_WooMercadoPago_Payment_Abstract {

	const ID = 'woo-mercado-pago-ticket';

	/**
	 * WC_WooMercadoPago_TicketGateway constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception Load payment exception.
	 */
	public function __construct() {
		$this->id          = self::ID;
		$this->description = __( "Offer boleto and lotérica payments within your store's website.", 'woocommerce-mercadopago' );
		$this->title       = __( 'Payments via invoice', 'woocommerce-mercadopago' );
		$this->mp_options  = $this->get_mp_options();

		if ( ! $this->validate_section() ) {
			return;
		}

		$this->form_fields        = array();
		$this->method_title       = __( 'Mercado pago - Customized Checkout', 'woocommerce-mercadopago' );
		$this->title              = $this->get_option( 'title', __( 'Payments via invoice', 'woocommerce-mercadopago' ) );
		$this->method_description = $this->description;
		$this->coupon_mode        = $this->get_option( 'coupon_mode', 'no' );
		$this->stock_reduce_mode  = $this->get_option( 'stock_reduce_mode', 'no' );
		$this->date_expiration    = (int) $this->get_option( 'date_expiration', 3 );
		$this->type_payments      = $this->get_option( 'type_payments', 'no' );
		$this->payment_type       = 'ticket';
		$this->checkout_type      = 'custom';
		$this->activated_payment  = $this->get_activated_payment();
		$this->field_forms_order  = $this->get_fields_sequence();
		parent::__construct();
		$this->form_fields         = $this->get_form_mp_fields( 'Ticket' );
		$this->hook                = new WC_WooMercadoPago_Hook_Ticket( $this );
		$this->notification        = new WC_WooMercadoPago_Notification_Webhook( $this );
		$this->currency_convertion = true;
	}

	/**
	 * Get form mp fields
	 *
	 * @param string $label Label.
	 * @return array
	 */
	public function get_form_mp_fields( $label ) {
		if ( is_admin() && $this->is_manage_section() ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script(
				'woocommerce-mercadopago-ticket-config-script',
				plugins_url( '../assets/js/ticket_config_mercadopago' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				false
			);
			wp_enqueue_script(
				'woocommerce-mercadopago-credentials',
				plugins_url( '../assets/js/validate-credentials' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				true
			);
		}

		if ( empty( $this->checkout_country ) ) {
			$this->field_forms_order = array_slice( $this->field_forms_order, 0, 7 );
		}

		if ( ! empty( $this->checkout_country ) && empty( $this->get_access_token() ) && empty( $this->get_public_key() ) ) {
			$this->field_forms_order = array_slice( $this->field_forms_order, 0, 22 );
		}

		$form_fields = array();
		if ( ! empty( $this->checkout_country ) && ! empty( $this->get_access_token() ) && ! empty( $this->get_public_key() ) ) {
			$form_fields['checkout_ticket_header']                  = $this->field_checkout_ticket_header();
			$form_fields['checkout_ticket_payments_advanced_title'] = $this->field_checkout_ticket_payments_advanced_title();
			$form_fields['coupon_mode']                             = $this->field_coupon_mode();
			$form_fields['stock_reduce_mode']                       = $this->field_stock_reduce_mode();
			$form_fields['date_expiration']                         = $this->field_date_expiration();
			$form_fields['field_ticket_payments']                   = $this->field_ticket_payments();
		}

		$form_fields_abs = parent::get_form_mp_fields( $label );
		if ( 1 === count( $form_fields_abs ) ) {
			return $form_fields_abs;
		}
		$form_fields_merge = array_merge( $form_fields_abs, $form_fields );
		$fields            = $this->sort_form_fields( $form_fields_merge, $this->field_forms_order );

		return $fields;
	}

	/**
	 * Get fields sequence
	 *
	 * @return array
	 */
	public function get_fields_sequence() {
		return array(
			// Necessary to run.
			'description',
			// Checkout de pagos con dinero en efectivo<br> Aceptá pagos al instante y maximizá la conversión de tu negocio.
			'checkout_ticket_header',
			// No olvides de homologar tu cuenta.
			'checkout_card_homolog',
			// Configure the personalized payment experience in your store.
			'checkout_card_validate',
			'enabled',
			'title',
			WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY,
			'field_ticket_payments',
			'date_expiration',
			// Advanced configuration of the personalized payment experience.
			'checkout_ticket_payments_advanced_title',
			'checkout_payments_advanced_description',
			'coupon_mode',
			'stock_reduce_mode',
			'gateway_discount',
			'commission',
		);
	}

	/**
	 * Get activated payment
	 *
	 * @return array
	 */
	public static function get_activated_payment() {
		$activated_payment          = array();
		$get_payment_methods_ticket = get_option( '_all_payment_methods_ticket', '' );

		if ( ! empty( $get_payment_methods_ticket ) ) {
			$saved_options = get_option( 'woocommerce_woo-mercado-pago-ticket_settings', '' );

			if ( ! is_array( $get_payment_methods_ticket ) ) {
				$get_payment_methods_ticket = json_decode( $get_payment_methods_ticket, true );
			}

			foreach ( $get_payment_methods_ticket as $payment_methods_ticket ) {
				if ( ! isset( $saved_options[ 'ticket_payment_' . $payment_methods_ticket['id'] ] )
					|| 'yes' === $saved_options[ 'ticket_payment_' . $payment_methods_ticket['id'] ] ) {
					array_push( $activated_payment, $payment_methods_ticket );
					sort($activated_payment);
				}
			}
		}
		return $activated_payment;
	}

	/**
	 * Field checkout ticket header
	 *
	 * @return array
	 */
	public function field_checkout_ticket_header() {
		return array(
			'title' => sprintf(
				'<div class="mp-row">
                <div class="mp-col-md-12 mp_subtitle_header">
                ' . __( 'Transparent Checkout | Invoice or Loterica', 'woocommerce-mercadopago' ) . '
                 </div>
              <div class="mp-col-md-12">
                <p class="mp-text-checkout-body mp-mb-0">
                  ' . __( 'With the Transparent Checkout, you can sell inside your store environment, without redirection and all the safety from Mercado Pago.', 'woocommerce-mercadopago' ) . '
                </p>
              </div>
            </div>'
			),
			'type'  => 'title',
			'class' => 'mp_title_header',
		);
	}

	/**
	 * Field checkout ticket payments advanced title
	 *
	 * @return array
	 */
	public function field_checkout_ticket_payments_advanced_title() {
		return array(
			'title' => __( 'Advanced configuration of the cash payment experience', 'woocommerce-mercadopago' ),
			'type'  => 'title',
			'class' => 'mp_subtitle_bd',
		);
	}

	/**
	 * Field sotck reduce mode
	 *
	 * @return array
	 */
	public function field_stock_reduce_mode() {
		return array(
			'title'       => __( 'Reduce inventory', 'woocommerce-mercadopago' ),
			'type'        => 'mp_toggle_switch',
			'default'     => 'no',
			'subtitle' => __( 'Activates inventory reduction during the creation of an order, whether or not the final payment is credited. Disable this option to reduce it only when payments are approved.', 'woocommerce-mercadopago' ),
			'descriptions' => array(
				'enabled' => __( 'Reduce inventory is <b>enabled</b>.', 'woocommerce-mercadopago' ),
				'disabled' => __( 'Reduce inventory is <b>disabled</b>.', 'woocommerce-mercadopago' ),
			),
		);
	}

	/**
	 * Field date expiration
	 *
	 * @return array
	 */
	public function field_date_expiration() {
		return array(
			'title'       => __( 'Payment Due', 'woocommerce-mercadopago' ),
			'type'        => 'number',
			'description' => __( 'In how many days will cash payments expire.', 'woocommerce-mercadopago' ),
			'default'     => '',
		);
	}

	/**
	 * Field ticket payments
	 *
	 * @return array
	 */
	public function field_ticket_payments() {
		$get_payment_methods_ticket = get_option( '_all_payment_methods_ticket', '[]' );

		$count_payment = 0;

		if ( ! is_array( $get_payment_methods_ticket ) ) {
			$get_payment_methods_ticket = json_decode( $get_payment_methods_ticket, true );
		}

		$payment_list = array(
			'description'          => __( 'Enable the available payment methods', 'woocommerce-mercadopago' ),
			'title'                => __( 'Payment methods', 'woocommerce-mercadopago' ),
			'desc_tip'             => __( 'Choose the available payment methods in your store.', 'woocommerce-mercadopago' ),
			'type'                 => 'mp_checkbox_list',
			'payment_method_types' => array(
				'ticket'           => array(
					'label'        => __('All payment methods', 'woocommerce-mercadopago'),
					'list'         => array(),
				),
			),
		);

		foreach ( $get_payment_methods_ticket as $payment_method_ticket ) {
			$payment_list['payment_method_types']['ticket']['list'][] = array(
				'id'        => $payment_method_ticket['id'],
				'field_key' => $this->get_field_key($payment_method_ticket['id']),
				'label'     => array_key_exists('payment_places', $payment_method_ticket) ? $payment_method_ticket['name'] . ' (' . $this->build_paycash_payments_string() . ')' : $payment_method_ticket['name'],
				'value'     => $this->get_option($payment_method_ticket['id'], 'yes'),
				'type'      => 'checkbox',
			);
		}

		return $payment_list;
	}

	/**
	 * Payment fields
	 */
	public function payment_fields() {
		// add css.
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'woocommerce-mercadopago-basic-checkout-styles',
			plugins_url( '../assets/css/basic_checkout_mercadopago' . $suffix . '.css', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION
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

		try {
			$currency_ratio = WC_WooMercadoPago_Helpers_CurrencyConverter::get_instance()->ratio( $this );
		} catch ( Exception $e ) {
			$currency_ratio = WC_WooMercadoPago_Helpers_CurrencyConverter::DEFAULT_RATIO;
		}

		$parameters = array(
			'checkout_alert_test_mode' => $this->is_production_mode()
			? ''
			: $this->checkout_alert_test_mode_template(
				__( 'Offline Methods in Test Mode', 'woocommerce-mercadopago' ),
				__( 'You can test the flow to generate an invoice, but you cannot finalize the payment.', 'woocommerce-mercadopago' )
			),
			'amount'               => $amount,
			'payment_methods'      => $this->activated_payment,
			'site_id'              => $this->mp_options->get_site_id(),
			'coupon_mode'          => isset( $logged_user_email ) ? $this->coupon_mode : 'no',
			'discount_action_url'  => $this->discount_action_url,
			'payer_email'          => esc_js( $logged_user_email ),
			'currency_ratio'       => $currency_ratio,
			'woocommerce_currency' => get_woocommerce_currency(),
			'account_currency'     => $this->site_data['currency'],
			'febraban'             => ( 0 !== wp_get_current_user()->ID ) ?
				array(
					'firstname' => esc_js( wp_get_current_user()->user_firstname ),
					'lastname'  => esc_js( wp_get_current_user()->user_lastname ),
					'docNumber' => '',
					'address'   => esc_js( $address ),
					'number'    => '',
					'city'      => esc_js( get_user_meta( wp_get_current_user()->ID, 'billing_city', true ) ),
					'state'     => esc_js( get_user_meta( wp_get_current_user()->ID, 'billing_state', true ) ),
					'zipcode'   => esc_js( get_user_meta( wp_get_current_user()->ID, 'billing_postcode', true ) ),
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
			'images_path'          => plugins_url( '../assets/images/', plugin_dir_path( __FILE__ ) ),
		);

		$parameters = array_merge($parameters, WC_WooMercadoPago_Helper_Links::mp_define_terms_and_conditions());
		wc_get_template( 'checkout/ticket-checkout.php', $parameters, 'woo/mercado/pago/module/', WC_WooMercadoPago_Module::get_templates_path() );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order Id.
	 * @return array|string[]
	 */
	public function process_payment( $order_id ) {
		// @todo need fix Processing form data without nonce verification
		// @codingStandardsIgnoreLine
		$ticket_checkout = $_POST['mercadopago_ticket'];
		$this->log->write_log( __FUNCTION__, 'Ticket POST: ' . wp_json_encode( $ticket_checkout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		$order  = wc_get_order( $order_id );
		$amount = $this->get_order_total();
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( 'is_production_mode', $this->mp_options->get_checkbox_checkout_production_mode() );
			$order->update_meta_data( '_used_gateway', get_class( $this ) );

			if ( ! empty( $this->gateway_discount ) ) {
				$discount = $amount * ( $this->gateway_discount / 100 );
				$order->update_meta_data( 'Mercado Pago: discount', __( 'discount of', 'woocommerce-mercadopago' ) . ' ' . $this->gateway_discount . '% / ' . __( 'discount of', 'woocommerce-mercadopago' ) . ' = ' . $discount );
			}

			if ( ! empty( $this->commission ) ) {
				$comission = $amount * ( $this->commission / 100 );
				$order->update_meta_data( 'Mercado Pago: comission', __( 'fee of', 'woocommerce-mercadopago' ) . ' ' . $this->commission . '% / ' . __( 'fee of', 'woocommerce-mercadopago' ) . ' = ' . $comission );
			}
			$order->save();
		} else {
			update_post_meta( $order_id, '_used_gateway', get_class( $this ) );

			if ( ! empty( $this->gateway_discount ) ) {
				$discount = $amount * ( $this->gateway_discount / 100 );
				update_post_meta( $order_id, 'Mercado Pago: discount', __( 'discount of', 'woocommerce-mercadopago' ) . ' ' . $this->gateway_discount . '% / ' . __( 'discount of', 'woocommerce-mercadopago' ) . ' = ' . $discount );
			}

			if ( ! empty( $this->commission ) ) {
				$comission = $amount * ( $this->commission / 100 );
				update_post_meta( $order_id, 'Mercado Pago: comission', __( 'fee of', 'woocommerce-mercadopago' ) . ' ' . $this->commission . '% / ' . __( 'fee of', 'woocommerce-mercadopago' ) . ' = ' . $comission );
			}
		}

		// Check for brazilian FEBRABAN rules.
		if ( 'MLB' === $this->mp_options->get_site_id() ) {
			if ( ! isset( $ticket_checkout['docNumber'] ) || empty( $ticket_checkout['docNumber'] ) ||
				( 14 !== strlen( $ticket_checkout['docNumber'] ) && 18 !== strlen( $ticket_checkout['docNumber'] ) ) ) {
				wc_add_notice(
					'<p>' .
					__( 'There was a problem processing your payment. Are you sure you have correctly filled out all the information on the payment form?', 'woocommerce-mercadopago' ) .
					'</p>',
					'error'
				);
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		}

		if ( 'MLU' === $this->mp_options->get_site_id() ) {
			if (
				! isset( $ticket_checkout['docNumber'] ) || empty( $ticket_checkout['docNumber'] ) ||
				! isset( $ticket_checkout['docType'] ) || empty( $ticket_checkout['docType'] )
			) {
				wc_add_notice(
					'<p>' .
					__( 'There was a problem processing your payment. Are you sure you have correctly filled out all the information on the payment form?', 'woocommerce-mercadopago' ) .
					'</p>',
					'error'
				);
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		}

		if ( isset( $ticket_checkout['amount'] ) && ! empty( $ticket_checkout['amount'] ) &&
			isset( $ticket_checkout['paymentMethodId'] ) && ! empty( $ticket_checkout['paymentMethodId'] ) ) {
			$response = $this->create_preference( $order, $ticket_checkout );

			if ( is_array( $response ) && array_key_exists( 'status', $response ) ) {
				if ( 'pending' === $response['status'] ) {
					if ( 'pending_waiting_payment' === $response['status_detail'] || 'pending_waiting_transfer' === $response['status_detail'] ) {
						WC()->cart->empty_cart();
						if ( 'yes' === $this->stock_reduce_mode ) {
							wc_reduce_stock_levels( $order_id );
						}
						// WooCommerce 3.0 or later.
						if ( method_exists( $order, 'update_meta_data' ) ) {
							$order->update_meta_data( '_transaction_details_ticket', $response['transaction_details']['external_resource_url'] );
							$order->save();
						} else {
							update_post_meta( $order->get_id(), '_transaction_details_ticket', $response['transaction_details']['external_resource_url'] );
						}
						// Shows some info in checkout page.
						$order->add_order_note(
							'Mercado Pago: ' .
							__( 'The customer has not paid yet.', 'woocommerce-mercadopago' )
						);
						if ( 'bank_transfer' !== $response['payment_type_id'] ) {
							$order->add_order_note(
								'Mercado Pago: ' .
								__( 'To print the ticket again click', 'woocommerce-mercadopago' ) .
								' <a target="_blank" href="' .
								$response['transaction_details']['external_resource_url'] . '">' .
								__( 'here', 'woocommerce-mercadopago' ) .
								'</a>',
								1,
								false
							);
						}

						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_order_received_url(),
						);
					}
				}
			} else {
				// Process when fields are imcomplete.
				wc_add_notice(
					'<p>' .
					__( 'A problem occurred when processing your payment. Are you sure you have correctly filled in all the information on the checkout form?', 'woocommerce-mercadopago' ) . ' MERCADO PAGO: ' .
					WC_WooMercadoPago_Module::get_common_error_messages( $response ) .
					'</p>',
					'error'
				);
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		} else {
			// Process when fields are incomplete.
			wc_add_notice(
				'<p>' .
				__( 'A problem occurred when processing your payment. Please try again.', 'woocommerce-mercadopago' ) .
				'</p>',
				'error'
			);
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Create preference
	 *
	 * @param object $order Order.
	 * @param array  $ticket_checkout Ticket checkout.
	 * @return string|array
	 */
	public function create_preference( $order, $ticket_checkout ) {
		$preferences_ticket = new WC_WooMercadoPago_Preference_Ticket( $this, $order, $ticket_checkout );
		$preferences        = $preferences_ticket->get_preference();
		try {
			$checkout_info = $this->mp->post( '/v1/payments', wp_json_encode( $preferences ) );
			$this->log->write_log( __FUNCTION__, 'Created Preference: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			if ( $checkout_info['status'] < 200 || $checkout_info['status'] >= 300 ) {
				$this->log->write_log( __FUNCTION__, 'mercado pago gave error, payment creation failed with error: ' . $checkout_info['response']['message'] );
				return $checkout_info['response']['message'];
			} elseif ( is_wp_error( $checkout_info ) ) {
				$this->log->write_log( __FUNCTION__, 'WordPress gave error, payment creation failed with error: ' . $checkout_info['response']['message'] );
				return $checkout_info['response']['message'];
			} else {
				$this->log->write_log( __FUNCTION__, 'payment link generated with success from mercado pago, with structure as follow: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
				return $checkout_info['response'];
			}
		} catch ( WC_WooMercadoPago_Exception $ex ) {
			$this->log->write_log( __FUNCTION__, 'payment creation failed with exception: ' . wp_json_encode( $ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			return $ex->getMessage();
		}
	}

	/**
	 * Is available?
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		$payment_methods = $this->activated_payment;
		if ( 0 === count( $payment_methods ) ) {
			$this->log->write_log( __FUNCTION__, 'Ticket unavailable, no active payment methods. ' );
			return false;
		}

		return true;
	}

	/**
	 * Get Id
	 *
	 * @return string
	 */
	public static function get_id() {
		return self::ID;
	}

	/**
	 * Build Paycash Payments String
	 *
	 * @return string
	 */
	public static function build_paycash_payments_string() {

		$get_payment_methods_ticket = get_option( '_all_payment_methods_ticket', '[]' );

		foreach ( $get_payment_methods_ticket as $payment ) {

			if ( 'paycash' === $payment['id'] ) {
				$payments = array_column( $payment['payment_places'] , 'name');
			}
		}

		$last_element     = array_pop( $payments );
		$paycash_payments = implode (', ', $payments);

		return implode( __(' and ', 'woocommerce-mercadopago') , array( $paycash_payments, $last_element ));
	}
}
