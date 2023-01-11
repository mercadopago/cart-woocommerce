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
 * Class WC_WooMercadoPago_Hook_Abstract
 */
abstract class WC_WooMercadoPago_Hook_Abstract {

	/**
	 * Payment class
	 *
	 * @var WC_WooMercadoPago_Payment_Abstract | WC_WooMercadoPago_Basic_Gateway | WC_WooMercadoPago_Custom_Gateway
	 */
	public $payment;

	/**
	 * Payment class
	 *
	 * @var WC_WooMercadoPago_Payment_Abstract
	 */
	public $class;

	/**
	 * Logger
	 *
	 * @var MP|null
	 */
	public $mp_instance;

	/**
	 * Public Key
	 *
	 * @var string
	 */
	public $public_key;

	/**
	 * Is test user?
	 *
	 * @var string
	 */
	public $test_user;

	/**
	 * Site Id
	 *
	 * @var string
	 */
	public $site_id;

	/**
	 * WC_WooMercadoPago_Hook_Abstract constructor.
	 *
	 * @param WC_WooMercadoPago_Payment_Abstract $payment Payment method.
	 */
	public function __construct( $payment ) {
		$this->payment     = $payment;
		$this->class       = get_class( $payment );
		$this->mp_instance = $payment->mp;
		$this->public_key  = $payment->get_public_key();
		$this->test_user   = get_option( '_test_user_v1' );
		$this->site_id     = strtolower(get_option( '_site_id_v1' ));

		$this->load_hooks();
	}

	/**
	 * Load Hooks
	 */
	public function load_hooks() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->payment->id, array( $this, 'custom_process_admin_options' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_discount' ), 10 );
		add_filter( 'woocommerce_gateway_title', array( $this, 'get_payment_method_title' ), 10, 2 );

		add_action(
			'admin_notices',
			function() {
				WC_WooMercadoPago_Helpers_CurrencyConverter::get_instance()->notices( $this->payment );
			}
		);

		if ( ! empty( $this->payment->settings['enabled'] ) && 'yes' === $this->payment->settings['enabled'] ) {
			add_action( 'woocommerce_after_checkout_form', array( $this, 'add_mp_settings_script' ) );
			add_action( 'woocommerce_thankyou', array( $this, 'update_mp_settings_script' ) );
		}
	}

	/**
	 * Add discount
	 *
	 * @param array $checkout Checkout information.
	 */
	public function add_discount_abst( $checkout ) {
		if (
			isset( $checkout['discount'] )
			&& ! empty( $checkout['discount'] )
			&& isset( $checkout['coupon_code'] )
			&& ! empty( $checkout['coupon_code'] )
			&& $checkout['discount'] > 0
			&& WC()->session->chosen_payment_method === $this->payment->id
		) {
			$this->payment->log->write_log( __FUNCTION__, $this->class . 'trying to apply discount...' );

			$value = ( 'COP' === $this->payment->site_data['currency'] || 'CLP' === $this->payment->site_data['currency'] )
				? floor( $checkout['discount'] / $checkout['currency_ratio'] )
				: floor( $checkout['discount'] / $checkout['currency_ratio'] * 100 ) / 100;

			global $woocommerce;

			/**
			 * Apply discount filter.
			 *
			 * @since 3.0.1
			 */
			if ( apply_filters( 'wc_mercadopago_custommodule_apply_discount', 0 < $value, $woocommerce->cart ) ) {
				$woocommerce->cart->add_fee(
					sprintf(
						/* translators: %s coupon  */
						__( 'Discount for coupon %s', 'woocommerce-mercadopago' ),
						esc_attr( $checkout['campaign'] )
					),
					( $value * -1 ),
					false
				);
			}
		}
	}

	/**
	 * Get payment method title
	 *
	 * @param string $title Title.
	 * @param string $id Id.
	 *
	 * @return string
	 */
	public function get_payment_method_title( $title, $id ) {
		if ( ! preg_match( '/woo-mercado-pago/', $id ) ) {
			return $title;
		}

		if ( $id !== $this->payment->id ) {
			return $title;
		}

		if ( ! is_checkout() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $title;
		}
		if ( $title !== $this->payment->title && ( 0 === $this->payment->commission && 0 === $this->payment->gateway_discount ) ) {
			return $title;
		}
		if ( ! is_numeric( $this->payment->gateway_discount ) || $this->payment->commission > 99 || $this->payment->gateway_discount > 99 ) {
			return $title;
		}

		$total            = (float) WC()->cart->subtotal;
		$price_discount   = $total * ( $this->payment->gateway_discount / 100 );
		$price_commission = $total * ( $this->payment->commission / 100 );

		if ( $this->payment->gateway_discount > 0 && $this->payment->commission > 0 ) {
			$title .= ' (' . __( 'discount of', 'woocommerce-mercadopago' ) . ' ' . wp_strip_all_tags( wc_price( $price_discount ) ) . __( ' and fee of', 'woocommerce-mercadopago' ) . ' ' . wp_strip_all_tags( wc_price( $price_commission ) ) . ')';
		} elseif ( $this->payment->gateway_discount > 0 ) {
			$title .= ' (' . __( 'discount of', 'woocommerce-mercadopago' ) . ' ' . wp_strip_all_tags( wc_price( $price_discount ) ) . ')';
		} elseif ( $this->payment->commission > 0 ) {
			$title .= ' (' . __( 'fee of', 'woocommerce-mercadopago' ) . ' ' . wp_strip_all_tags( wc_price( $price_commission ) ) . ')';
		}
		return $title;
	}

	/**
	 * MP Settings Script
	 */
	public function add_mp_settings_script() {
		if ( ! empty( $this->public_key ) && ! $this->test_user && isset( WC()->payment_gateways ) ) {
			$woo      = WC_WooMercadoPago_Module::woocommerce_instance();
			$gateways = $woo->payment_gateways->get_available_payment_gateways();

			$available_payments = array();
			foreach ( $gateways as $gateway ) {
				$available_payments[] = $gateway->id;
			}

			$available_payments = str_replace( '-', '_', implode( ', ', $available_payments ) );
			$logged_user_email  = null;
			if ( 0 !== wp_get_current_user()->ID ) {
				$logged_user_email = wp_get_current_user()->user_email;
			}
		}
	}

	/**
	 * Settings script
	 *
	 * @param int $order_id
	 *
	 * @return void
	 */
	public function update_mp_settings_script( $order_id ) {
		// Do nothing
	}

	/**
	 * Update order payments metadata
	 *
	 * @param string $order_id
	 * @param array $payments_id
	 *
	 * @return void
	 */
	public function update_mp_order_payments_metadata( $order_id, $payments_id ) {
		$payments_id_meta_key = '_Mercado_Pago_Payment_IDs';
		$payments_id_metadata = count( get_post_meta( $order_id, $payments_id_meta_key ) );

		if ( count( $payments_id ) > 0 ) {
			if ( 0 === $payments_id_metadata ) {
				update_post_meta( $order_id, $payments_id_meta_key, implode( ', ', $payments_id ) );
			}

			foreach ( $payments_id as $payment_id ) {
				$payment_detail_meta_key = 'Mercado Pago - Payment ' . $payment_id;
				$payment_detail_metadata = count ( get_post_meta( $order_id, $payment_detail_meta_key ) );

				if ( 0 === $payment_detail_metadata ) {
					update_post_meta(
						$order_id,
						$payment_detail_meta_key,
						'[Date ' . gmdate('Y-m-d H:i:s') . ']'
					);
				}
			}
		}
	}

	/**
	 * Sort By Checkout Mode First
	 *
	 * @param array $form_fields Form fields
	 *
	 * @return array $sorted_array Sorted array
	 */
	public function sort_by_checkout_mode_first( $form_fields ) {
		$sort_credentials_first = array(
			'checkout_subtitle_checkout_mode',
			'checkbox_checkout_test_mode',
			'checkbox_checkout_production_mode',
			'_mp_public_key_prod',
			'_mp_access_token_prod',
			'_mp_public_key_test',
			'_mp_access_token_test',
		);

		return $this->payment->sort_form_fields( $form_fields, $sort_credentials_first );
	}

	/**
	 * Custom process admin options
	 *
	 * @return bool
	 * @throws WC_WooMercadoPago_Exception Admin Options Exception.
	 */
	public function custom_process_admin_options() {
		$old_data                    = array();
		$value_credential_production = null;

		$this->payment->init_settings();

		$post_data   = $this->payment->get_post_data();
		$form_fields = $this->payment->get_form_fields();

		$form_fields        = $this->handle_mp_components($form_fields);
		$sorted_form_fields = $this->sort_by_checkout_mode_first( $form_fields );

		foreach ( $sorted_form_fields as $key => $field ) {
			if ( 'title' !== $this->payment->get_field_type( $field ) ) {
				$value            = $this->payment->get_field_value( $key, $field, $post_data );
				$old_data[ $key ] = isset( $this->payment->settings[ $key ] ) ? $this->payment->settings[ $key ] : null;

				if ( 'checkbox_checkout_test_mode' === $key ) {
					$value_credential_production = 'yes' === $value ? 'no' : 'yes';
				}

				$common_configs = $this->payment->get_common_configs();

				if ( in_array( $key, $common_configs, true ) ) {
					if ( $this->validate_credentials( $key, $value, $value_credential_production ) ) {
						continue;
					}

					update_option( $key, $value, true );
				}

				$value                           = $this->payment->get_field_value( $key, $field, $post_data );
				$this->payment->settings[ $key ] = $value;
			}
		}

		WC_WooMercadoPago_Helpers_CurrencyConverter::get_instance()->schedule_notice(
			$this->payment,
			$old_data,
			$this->payment->settings
		);

		/**
		 * Update if options were changed.
		 *
		 * @since 3.0.1
		 */
		return update_option( $this->payment->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->payment->id, $this->payment->settings ) );
	}

	/**
	 * Handles custom components for better integration with native hooks
	 *
	 * @param array $form_fields all the form fields
	 *
	 * @return array
	 */
	public function handle_mp_components( $form_fields ) {
		foreach ( $form_fields as $key => $form_field ) {
			//separating payment methods
			if ( 'mp_checkbox_list' === $form_field['type'] ) {
				$form_fields += $this->separate_checkboxes($form_fields[$key]);
				unset($form_fields[$key]);
			}

			//separating checkboxes from activable inputs
			if ( 'mp_activable_input' === $form_field['type'] && ! isset( $form_fields[$key . '_checkbox'] ) ) {
				$form_fields[$key . '_checkbox'] = array(
					'type'      => 'checkbox',
				);
			}

			//setting toggle as checkbox
			if ( 'mp_toggle_switch' === $form_field['type'] ) {
				$form_fields[$key]['type'] = 'checkbox';
			}
		}

		return $form_fields;
	}

	/**
	 * Separates multiple ex_payments checkbox into an array
	 *
	 * @param array $ex_payments ex_payments form field
	 *
	 * @return array
	 */
	public function separate_checkboxes( $ex_payments ) {
		$payment_methods = array();
		foreach ( $ex_payments['payment_method_types'] as $payment_method_type ) {
			$payment_methods += $this->separate_checkboxes_list($payment_method_type['list']);
		}
		return $payment_methods;
	}

	/**
	 * Separates multiple ex_payments checkbox into an array
	 *
	 * @param array $ex_payments list of payment_methods
	 *
	 * @return array
	 */
	public function separate_checkboxes_list( $ex_payments_list ) {
		$payment_methods = array();
		foreach ( $ex_payments_list as $payment ) {
			$payment_methods[$payment['id']] = $payment;
		}
		return $payment_methods;
	}

	/**
	 * Build Woocommerce settings key
	 *
	 * @param String $gateway_id Constant ID
	 *
	 * @return String
	 */
	private function build_woocommerce_settings_key( $gateway_id ) {
		return 'woocommerce_' . $gateway_id . '_settings';
	}

	/**
	 * Validate credentials
	 *
	 * @param string      $key                         Key.
	 * @param string      $value                       Value.
	 * @param string|null $value_credential_production Production credentials.
	 *
	 * @return bool
	 * @throws WC_WooMercadoPago_Exception Invalid credentials exception.
	 */
	private function validate_credentials( $key, $value, $value_credential_production = null ) {
		if ( $this->validate_public_key( $key, $value ) ) {
			return true;
		}

		if ( $this->validate_access_token( $key, $value, $value_credential_production ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate Public Key
	 *
	 * @param string $key key.
	 * @param string $value value.
	 *
	 * @return bool
	 */
	private function validate_public_key( $key, $value ) {
		if ( '_mp_public_key_test' !== $key && '_mp_public_key_prod' !== $key ) {
			return false;
		}

		if ( '_mp_public_key_prod' === $key ) {
			if ( null === $value || '' === $value ) {
				add_action( 'admin_notices', array( $this, 'notice_blank_public_key_prod' ) );
				return true;
			}

			if ( false === WC_WooMercadoPago_Credentials::validate_credentials_prod( $this->mp_instance, null, $value ) ) {
				update_option( $key, '', true );
				add_action( 'admin_notices', array( $this, 'notice_invalid_public_key_prod' ) );
				return true;
			}
		}

		if ( '_mp_public_key_test' === $key ) {
			if ( null === $value || '' === $value ) {
				add_action( 'admin_notices', array( $this, 'notice_blank_public_key_test' ) );
				return true;
			}

			if ( false === WC_WooMercadoPago_Credentials::validate_credentials_test( $this->mp_instance, null, $value ) ) {
				update_option( $key, '', true );
				add_action( 'admin_notices', array( $this, 'notice_invalid_public_key_test' ) );
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate Access Token
	 *
	 * @param string      $key Key.
	 * @param string      $value Value.
	 * @param string|null $is_production Is Production.
	 *
	 * @return bool
	 * @throws WC_WooMercadoPago_Exception Invalid Access Token Exception.
	 */
	private function validate_access_token( $key, $value, $is_production = null ) {
		if ( '_mp_access_token_prod' !== $key && '_mp_access_token_test' !== $key ) {
			return false;
		}

		if ( '_mp_access_token_prod' === $key ) {
			if ( null === $value || '' === $value ) {
				add_action( 'admin_notices', array( $this, 'notice_blank_prod_credentials' ) );
				return true;
			}

			if ( false === WC_WooMercadoPago_Credentials::validate_credentials_prod( $this->mp_instance, $value, null ) ) {
				add_action( 'admin_notices', array( $this, 'notice_invalid_prod_credentials' ) );
				update_option( $key, '', true );
				return true;
			}
		}

		if ( '_mp_access_token_test' === $key ) {
			if ( null === $value || '' === $value ) {
				add_action( 'admin_notices', array( $this, 'notice_blank_test_credentials' ) );
				return true;
			}

			if ( false === WC_WooMercadoPago_Credentials::validate_credentials_test( $this->mp_instance, $value, null ) ) {
				add_action( 'admin_notices', array( $this, 'notice_invalid_test_credentials' ) );
				update_option( $key, '', true );
				return true;
			}
		}

		if ( empty( $is_production ) ) {
			$is_production = $this->payment->is_production_mode();
		}

		if ( WC_WooMercadoPago_Credentials::access_token_is_valid( $value ) ) {
			update_option( $key, $value, true );

			if ( '_mp_access_token_prod' === $key ) {
				$homolog_validate = $this->mp_instance->get_credentials_wrapper( $value );
				$homolog_validate = isset( $homolog_validate['homologated'] ) && true === $homolog_validate['homologated'] ? 1 : 0;
				update_option( 'homolog_validate', $homolog_validate, true );
				if ( 'yes' === $is_production && 0 === $homolog_validate ) {
					add_action( 'admin_notices', array( $this, 'enable_payment_notice' ) );
				}
			}

			if (
				( '_mp_access_token_prod' === $key && 'yes' === $is_production ) || ( '_mp_access_token_test' === $key && 'no' === $is_production )
			) {
				WC_WooMercadoPago_Credentials::update_payment_methods( $this->mp_instance, $value );
				WC_WooMercadoPago_Credentials::update_ticket_method( $this->mp_instance, $value );
				$wc_country = WC_WooMercadoPago_Module::get_woocommerce_default_country();
				$site_id    = strtolower(get_option( '_site_id_v1', '' ));
				if ( ( 'BR' === $wc_country && '' === $site_id ) || ( 'mlb' === $site_id ) ) {
					WC_WooMercadoPago_Credentials::update_pix_method( $this->mp_instance, $value );
				}
			}
			return true;
		}

		if ( '_mp_access_token_prod' === $key ) {
			update_option( '_mp_public_key_prod', '', true );
			WC_WooMercadoPago_Credentials::set_no_credentials();
			add_action( 'admin_notices', array( $this, 'notice_invalid_prod_credentials' ) );
		} else {
			update_option( '_mp_public_key_test', '', true );
			add_action( 'admin_notices', array( $this, 'notice_invalid_test_credentials' ) );
		}

		update_option( $key, '', true );
		return true;
	}

	/**
	 *  ADMIN NOTICE
	 */
	public function notice_invalid_public_key_prod() {
		$type    = 'error';
		$message = __( '<b>Public Key</b> production credential is invalid. Review the field to receive real payments.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 *  ADMIN NOTICE
	 */
	public function notice_invalid_public_key_test() {
		$type    = 'error';
		$message = __( '<b>Public Key</b> test credential is invalid. Review the field to perform tests in your store.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 *  ADMIN NOTICE
	 */
	public function notice_invalid_prod_credentials() {
		$type    = 'error';
		$message = __( '<b>Access Token</b> production credential is invalid. Remember that it must be complete to receive real payments.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 *  ADMIN NOTICE
	 */
	public function notice_invalid_test_credentials() {
		$type    = 'error';
		$message = __( '<b>Access Token</b> test credential is invalid. Review the field to perform tests in your store.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 * Enable payment notice
	 */
	public function enable_payment_notice() {
		$type    = 'notice-warning';
		$message = __( 'Fill in your credentials to enable payment methods.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 *  ADMIN NOTICE
	 */
	public function notice_blank_public_key_test() {
		$type    = 'error';
		$message = __( '<b>Public Key</b> test credential is blank. Review the field to perform tests in your store.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 *  ADMIN NOTICE
	 */
	public function notice_blank_public_key_prod() {
		$type    = 'error';
		$message = __( '<b>Public Key</b> production credential is blank. Review the field to receive real payments.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 *  ADMIN NOTICE
	 */
	public function notice_blank_test_credentials() {
		$type    = 'error';
		$message = __( '<b>Access Token</b> test credential is blank. Review the field to perform tests in your store.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 *  ADMIN NOTICE
	 */
	public function notice_blank_prod_credentials() {
		$type    = 'error';
		$message = __( '<b>Access Token</b> production credential is blank. Remember that it must be complete to receive real payments.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}
}
