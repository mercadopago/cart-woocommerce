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
 * Class WC_WooMercadoPago_Custom_Gateway
 */
class WC_WooMercadoPago_Custom_Gateway extends WC_WooMercadoPago_Payment_Abstract {

	const ID = 'woo-mercado-pago-custom';

	/**
	 * WC_WooMercadoPago_CustomGateway constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception Exception load payment.
	 */
	public function __construct() {
		$this->id = self::ID;

		if ( ! $this->validate_section() ) {
			return;
		}

		$this->description        = __( 'Accept card payments on your website with the best possible financing and maximize the conversion of your business. With personalized checkout your customers pay without leaving your store!', 'woocommerce-mercadopago' );
		$this->form_fields        = array();
		$this->method_title       = __( 'Mercado Pago - Custom Checkout', 'woocommerce-mercadopago' );
		$this->title              = __( 'Pay with debit and credit cards', 'woocommerce-mercadopago' );
		$this->method_description = $this->description;
		$this->coupon_mode        = $this->get_option_mp( 'coupon_mode', 'no' );
		$this->field_forms_order  = $this->get_fields_sequence();
		parent::__construct();
		$this->form_fields         = $this->get_form_mp_fields( 'Custom' );
		$this->customer            = $this->get_or_create_customer();
		$this->hook                = new WC_WooMercadoPago_Hook_Custom( $this );
		$this->notification        = new WC_WooMercadoPago_Notification_Webhook( $this );
		$this->currency_convertion = true;
	}

	/**
	 * Get Form Mercado Pago fields
	 *
	 * @param string $label Label.
	 * @return array
	 */
	public function get_form_mp_fields( $label ) {
		if ( is_admin() && $this->is_manage_section() ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script(
				'woocommerce-mercadopago-custom-config-script',
				plugins_url( '../assets/js/custom_config_mercadopago' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				false
			);
		}

		if ( empty( $this->checkout_country ) ) {
			$this->field_forms_order = array_slice( $this->field_forms_order, 0, 7 );
		}

		if ( ! empty( $this->checkout_country ) && empty( $this->get_access_token() ) && empty( $this->get_public_key() ) ) {
			$this->field_forms_order = array_slice( $this->field_forms_order, 0, 22 );
		}

		$form_fields                           = array();
		$form_fields['checkout_custom_header'] = $this->field_checkout_custom_header();
		if ( ! empty( $this->checkout_country ) && ! empty( $this->get_access_token() ) && ! empty( $this->get_public_key() ) ) {
			$form_fields['checkout_custom_options_title']           = $this->field_checkout_custom_options_title();
			$form_fields['checkout_custom_payments_title']          = $this->field_checkout_custom_payments_title();
			$form_fields['checkout_payments_subtitle']              = $this->field_checkout_payments_subtitle();
			$form_fields['binary_mode']                             = $this->field_binary_mode();
			$form_fields['checkout_custom_payments_advanced_title'] = $this->field_checkout_custom_payments_advanced_title();
			$form_fields['coupon_mode']                             = $this->field_coupon_mode();
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
			'title',
			'description',
			// Checkout de pagos con tarjetas de débito y crédito<br> Aceptá pagos al instante y maximizá la conversión de tu negocio.
			'checkout_custom_header',
			'checkout_steps',
			// ¿En qué país vas a activar tu tienda?
			'checkout_country_title',
			'checkout_country',
			'checkout_btn_save',
			// Carga tus credenciales.
			'checkout_credential_title',
			'checkout_credential_mod_test_title',
			'checkout_credential_mod_test_description',
			'checkout_credential_mod_prod_title',
			'checkout_credential_mod_prod_description',
			'checkout_credential_prod',
			'checkout_credential_link',
			'checkout_credential_title_test',
			'checkout_credential_description_test',
			'_mp_public_key_test',
			'_mp_access_token_test',
			'checkout_credential_title_prod',
			'checkout_credential_description_prod',
			'_mp_public_key_prod',
			'_mp_access_token_prod',
			// No olvides de homologar tu cuenta.
			'checkout_homolog_title',
			'checkout_homolog_subtitle',
			'checkout_homolog_link',
			// Set up the payment experience in your store.
			'checkout_custom_options_title',
			'mp_statement_descriptor',
			'_mp_category_id',
			'_mp_store_identificator',
			'_mp_integrator_id',
			// Advanced settings.
			'checkout_advanced_settings',
			'_mp_debug_mode',
			'_mp_custom_domain',
			// Configure the personalized payment experience in your store.
			'checkout_custom_payments_title',
			'checkout_payments_subtitle',
			'enabled',
			WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY,
			// Advanced configuration of the personalized payment experience.
			'checkout_custom_payments_advanced_title',
			'checkout_payments_advanced_description',
			'coupon_mode',
			'binary_mode',
			'gateway_discount',
			'commission',
			// Support session.
			'checkout_support_title',
			'checkout_support_description',
			'checkout_support_description_link',
			'checkout_support_problem',
			// Everything ready for the takeoff of your sales?
			'checkout_ready_title',
			'checkout_ready_description',
			'checkout_ready_description_link',
		);
	}

	/**
	 * Field checkout custom header
	 *
	 * @return array
	 */
	public function field_checkout_custom_header() {
		$checkout_custom_header = array(
			'title' => sprintf(
				/* translators: %s card */
				__( 'Checkout of payments with debit and credit cards %s', 'woocommerce-mercadopago' ),
				'<div class="mp-row">
                <div class="mp-col-md-12 mp_subtitle_header">
                ' . __( 'Accept payments instantly and maximize the conversion of your business', 'woocommerce-mercadopago' ) . '
                 </div>
              <div class="mp-col-md-12">
                <p class="mp-text-checkout-body mp-mb-0">
                  ' . __( 'Turn your online store into a secure and easy-to-use payment gateway for your customers. With personalized checkout your customers pay without leaving your store!', 'woocommerce-mercadopago' ) . '
                </p>
              </div>
            </div>'
			),
			'type'  => 'title',
			'class' => 'mp_title_header',
		);
		return $checkout_custom_header;
	}

	/**
	 * Filed checkout custom options title
	 *
	 * @return array
	 */
	public function field_checkout_custom_options_title() {
		$checkout_custom_options_title = array(
			'title' => __( 'Set up the payment experience in your store', 'woocommerce-mercadopago' ),
			'type'  => 'title',
			'class' => 'mp_title_bd',
		);
		return $checkout_custom_options_title;
	}

	/**
	 * Field checkout custom payments title
	 *
	 * @return array
	 */
	public function field_checkout_custom_payments_title() {
		$checkout_custom_payments_title = array(
			'title' => __( 'Configure the personalized payment experience in your store', 'woocommerce-mercadopago' ),
			'type'  => 'title',
			'class' => 'mp_title_bd',
		);
		return $checkout_custom_payments_title;
	}

	/**
	 * Field checkout custom payment advanced title
	 *
	 * @return array
	 */
	public function field_checkout_custom_payments_advanced_title() {
		$checkout_custom_payments_advanced_title = array(
			'title' => __( 'Advanced configuration of the personalized payment experience"', 'woocommerce-mercadopago' ),
			'type'  => 'title',
			'class' => 'mp_subtitle_bd',
		);
		return $checkout_custom_payments_advanced_title;
	}

	/**
	 * Get Order Status
	 *
	 * @param string $status_detail Status.
	 * @return string|void
	 */
	public function get_order_status( $status_detail ) {
		switch ( $status_detail ) {
			case 'accredited':
				return __( 'That’s it, payment accepted!', 'woocommerce-mercadopago' );
			case 'pending_contingency':
				return __( 'We are processing your payment. In less than an hour we will send you the result by email.', 'woocommerce-mercadopago' );
			case 'pending_review_manual':
				return __( 'We are processing your payment. In less than 2 days we will send you by email if the payment has been approved or if additional information is needed.', 'woocommerce-mercadopago' );
			case 'cc_rejected_bad_filled_card_number':
				return __( 'Check the card number.', 'woocommerce-mercadopago' );
			case 'cc_rejected_bad_filled_date':
				return __( 'Check the expiration date.', 'woocommerce-mercadopago' );
			case 'cc_rejected_bad_filled_other':
				return __( 'Check the information provided.', 'woocommerce-mercadopago' );
			case 'cc_rejected_bad_filled_security_code':
				return __( 'Check the informed security code.', 'woocommerce-mercadopago' );
			case 'cc_rejected_blacklist':
				return __( 'Your payment cannot be processed.', 'woocommerce-mercadopago' );
			case 'cc_rejected_call_for_authorize':
				return __( 'You must authorize payments for your orders.', 'woocommerce-mercadopago' );
			case 'cc_rejected_card_disabled':
				return __( 'Contact your card issuer to activate it. The phone is on the back of your card.', 'woocommerce-mercadopago' );
			case 'cc_rejected_card_error':
				return __( 'Your payment cannot be processed.', 'woocommerce-mercadopago' );
			case 'cc_rejected_duplicated_payment':
				return __( 'You have already made a payment of this amount. If you have to pay again, use another card or other method of payment.', 'woocommerce-mercadopago' );
			case 'cc_rejected_high_risk':
				return __( 'Your payment was declined. Please select another payment method. It is recommended in cash.', 'woocommerce-mercadopago' );
			case 'cc_rejected_insufficient_amount':
				return __( 'Your payment does not have sufficient funds.', 'woocommerce-mercadopago' );
			case 'cc_rejected_invalid_installments':
				return __( 'Payment cannot process the selected fee.', 'woocommerce-mercadopago' );
			case 'cc_rejected_max_attempts':
				return __( 'You have reached the limit of allowed attempts. Choose another card or other payment method.', 'woocommerce-mercadopago' );
			case 'cc_rejected_other_reason':
				return __( 'This payment method cannot process your payment.', 'woocommerce-mercadopago' );
			default:
				return __( 'This payment method cannot process your payment.', 'woocommerce-mercadopago' );
		}
	}

	/**
	 * Payment Fields
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

		$amount     = $this->get_order_total();
		$discount   = $amount * ( $this->gateway_discount / 100 );
		$comission  = $amount * ( $this->commission / 100 );
		$amount     = $amount - $discount + $comission;
		$banner_url = $this->get_option_mp( '_mp_custom_banner' );
		if ( ! isset( $banner_url ) || empty( $banner_url ) ) {
			$banner_url = $this->site_data['checkout_banner_custom'];
		}

		// credit or debit card.
		$debit_card  = array();
		$credit_card = array();
		$tarjetas    = get_option( '_checkout_payments_methods', '' );

		foreach ( $tarjetas as $tarjeta ) {
			if ( 'credit_card' === $tarjeta['type'] ) {
				$credit_card[] = $tarjeta['image'];
			} elseif ( 'debit_card' === $tarjeta['type'] || 'prepaid_card' === $tarjeta['type'] ) {
				$debit_card[] = $tarjeta['image'];
			}
		}

		try {
			$currency_ratio = WC_WooMercadoPago_Helpers_CurrencyConverter::get_instance()->ratio( $this );
		} catch ( Exception $e ) {
			$currency_ratio = WC_WooMercadoPago_Helpers_CurrencyConverter::DEFAULT_RATIO;
		}

		$parameters = array(
			'amount'               => $amount,
			'site_id'              => $this->get_option_mp( '_site_id_v1' ),
			'public_key'           => $this->get_public_key(),
			'coupon_mode'          => isset( $this->logged_user_email ) ? $this->coupon_mode : 'no',
			'discount_action_url'  => $this->discount_action_url,
			'payer_email'          => esc_js( $this->logged_user_email ),
			'images_path'          => plugins_url( '../assets/images/', plugin_dir_path( __FILE__ ) ),
			'currency_ratio'       => $currency_ratio,
			'woocommerce_currency' => get_woocommerce_currency(),
			'account_currency'     => $this->site_data['currency'],
			'debit_card'           => $debit_card,
			'credit_card'          => $credit_card,
		);

		wc_get_template( 'checkout/custom-checkout.php', $parameters, 'woo/mercado/pago/module/', WC_WooMercadoPago_Module::get_templates_path() );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order Id.
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		// @todo need fix Processing form data without nonce verification
		// @codingStandardsIgnoreLine
		if ( ! isset( $_POST['mercadopago_custom'] ) ) {
			$this->log->write_log( __FUNCTION__, 'A problem was occurred when processing your payment. Please, try again.' );
			wc_add_notice( '<p>' . __( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago' ) . '</p>', 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
		// @todo need fix Processing form data without nonce verification
		// @codingStandardsIgnoreLine
		$custom_checkout = $_POST['mercadopago_custom'];
		$this->log->write_log( __FUNCTION__, 'POST Custom: ' . wp_json_encode( $custom_checkout, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		$order  = wc_get_order( $order_id );
		$amount = $this->get_order_total();
		if ( method_exists( $order, 'update_meta_data' ) ) {
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
		// Mexico country case.
		if ( ! isset( $custom_checkout['paymentMethodId'] ) || empty( $custom_checkout['paymentMethodId'] ) ) {
			$custom_checkout['paymentMethodId'] = $custom_checkout['paymentMethodSelector'];
		}
		if (
			isset( $custom_checkout['amount'] ) && ! empty( $custom_checkout['amount'] ) &&
			isset( $custom_checkout['token'] ) && ! empty( $custom_checkout['token'] ) &&
			isset( $custom_checkout['paymentMethodId'] ) && ! empty( $custom_checkout['paymentMethodId'] ) &&
			isset( $custom_checkout['installments'] ) && ! empty( $custom_checkout['installments'] ) &&
			-1 !== $custom_checkout['installments']
		) {
			$response = $this->create_preference( $order, $custom_checkout );

			if ( ! is_array( $response ) ) {
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
			// Switch on response.
			if ( array_key_exists( 'status', $response ) ) {
				switch ( $response['status'] ) {
					case 'approved':
						WC()->cart->empty_cart();
						wc_add_notice( '<p>' . $this->get_order_status( 'accredited' ) . '</p>', 'notice' );
						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_order_received_url(),
						);
					case 'pending':
						// Order approved/pending, we just redirect to the thankyou page.
						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_order_received_url(),
						);
					case 'in_process':
						// For pending, we don't know if the purchase will be made, so we must inform this status.
						WC()->cart->empty_cart();
						wc_add_notice(
							'<p>' . $this->get_order_status( $response['status_detail'] ) . '</p>' .
							'<p><a class="button" href="' . esc_url( $order->get_checkout_order_received_url() ) . '">' .
							__( 'See your order form', 'woocommerce-mercadopago' ) .
							'</a></p>',
							'notice'
						);
						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_payment_url( true ),
						);
					case 'rejected':
						// If rejected is received, the order will not proceed until another payment try, so we must inform this status.
						wc_add_notice(
							'<p>' . __(
								'Your payment was declined. You can try again.',
								'woocommerce-mercadopago'
							) . '<br>' .
							$this->get_order_status( $response['status_detail'] ) .
							'</p>' .
							'<p><a class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' .
							__( 'Click to try again', 'woocommerce-mercadopago' ) .
							'</a></p>',
							'error'
						);
						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_payment_url( true ),
						);
					case 'cancelled':
					case 'in_mediation':
					case 'charged_back':
						// If we enter here (an order generating a direct [cancelled, in_mediation, or charged_back] status),
						// them there must be something very wrong!
						break;
					default:
						break;
				}
			} else {
				// Process when fields are imcomplete.
				$this->log->write_log( __FUNCTION__, 'A problem was occurred when processing your payment. Are you sure you have correctly filled all information in the checkout form? ' );

				wc_add_notice(
					'<p>' . __( 'A problem was occurred when processing your payment. Are you sure you have correctly filled all information in the checkout form?', 'woocommerce-mercadopago' ) . ' MERCADO PAGO: ' .
					WC_WooMercadoPago_Module::get_common_error_messages( $response ) . '</p>',
					'error'
				);
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		} else {
			$this->log->write_log( __FUNCTION__, 'A problem was occurred when processing your payment. Please, try again.' );
			wc_add_notice( '<p>' . __( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago' ) . '</p>', 'error' );
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Create Preference
	 *
	 * @param object $order Order.
	 * @param mixed  $custom_checkout Checkout info.
	 * @return string|array
	 */
	protected function create_preference( $order, $custom_checkout ) {
		$preferences_custom = new WC_WooMercadoPago_Preference_Custom( $this, $order, $custom_checkout );
		$preferences        = $preferences_custom->get_preference();
		try {
			$checkout_info = $this->mp->post( '/v1/payments', wp_json_encode( $preferences ) );
			$this->log->write_log( __FUNCTION__, 'Preference created: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
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
	 * Check and save customer card
	 *
	 * @param array $checkout_info Checkout info.
	 */
	public function check_and_save_customer_card( $checkout_info ) {
		$this->log->write_log( __FUNCTION__, 'checking info to create card: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		$cust_id           = null;
		$token             = null;
		$issuer_id         = null;
		$payment_method_id = null;
		if ( isset( $checkout_info['payer']['id'] ) && ! empty( $checkout_info['payer']['id'] ) ) {
			$cust_id = $checkout_info['payer']['id'];
		} else {
			return;
		}
		if ( isset( $checkout_info['metadata']['token'] ) && ! empty( $checkout_info['metadata']['token'] ) ) {
			$token = $checkout_info['metadata']['token'];
		} else {
			return;
		}
		if ( isset( $checkout_info['issuer_id'] ) && ! empty( $checkout_info['issuer_id'] ) ) {
			$issuer_id = (int) ( $checkout_info['issuer_id'] );
		}
		if ( isset( $checkout_info['payment_method_id'] ) && ! empty( $checkout_info['payment_method_id'] ) ) {
			$payment_method_id = $checkout_info['payment_method_id'];
		}
		try {
			$this->mp->create_card_in_customer( $cust_id, $token, $payment_method_id, $issuer_id );
		} catch ( WC_WooMercadoPago_Exception $ex ) {
			$this->log->write_log( __FUNCTION__, 'card creation failed: ' . wp_json_encode( $ex, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
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

		$_mp_access_token    = get_option( '_mp_access_token_prod' );
		$is_prod_credentials = false === WC_WooMercadoPago_Credentials::validate_credentials_test( $this->mp, $_mp_access_token, null );

		if ( ( empty( $_SERVER['HTTPS'] ) || 'off' === $_SERVER['HTTPS'] ) && $is_prod_credentials ) {
			$this->log->write_log( __FUNCTION__, 'NO HTTPS, Custom unavailable.' );
			return false;
		}

		return true;
	}

	/**
	 * Get or create customer
	 *
	 * @return array|mixed|null
	 * @throws WC_WooMercadoPago_Exception Get or create user exception.
	 */
	public function get_or_create_customer() {
		if ( empty( $this->mp ) ) {
			return null;
		}
		return isset( $this->logged_user_email ) ? $this->mp->get_or_create_customer( $this->logged_user_email ) : null;
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
	 * Get the Mercado Pago registration URL.
	 *
	 * @return string URL.
	 */
	public function get_registration_url() {
		$url = 'https://www.mercadopago.com';
		$country_code = WC_Countries::get_base_country();

		if ( in_array( $country_code, array( 'AR', 'BR', 'CL', 'CO', 'MX', 'PE', 'UY' ) ) ) {
			$country_code = strtolower( $country_code );
			return "${url}.${country_code}/registration-company?confirmation_url=${url}.${country_code}%2Fcomo-cobrar";
		}

		return $url;
	}

	/**
	 * Get help text to display during quick setup.
	 */
	public function get_setup_help_text() {
		return sprintf(
			__( 'Mercado Pago can be configured under your <a href="%s">store settings.</a> Create your Mercado Pago account <a href="%s">here.</a>', 'wc_mercado' ),
			$this->get_registration_url(),
			admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( $this->id ) )
		);
	}
}
