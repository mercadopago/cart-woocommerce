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
 * Class WC_WooMercadoPago_Credentials
 */
class WC_WooMercadoPago_Credentials {

	const TYPE_ACCESS_CLIENT = 'client';
	const TYPE_ACCESS_TOKEN  = 'token';

	/**
	 * Payment variable
	 *
	 * @var [mixed|null]
	 */
	public $payment;

	/**
	 * Public key variable
	 *
	 * @var [string]
	 */
	public $public_key;

	/**
	 * Access token variable
	 *
	 * @var [string]
	 */
	public $access_token;

	/**
	 * Client id variable
	 *
	 * @var [string]
	 */
	public $client_id;

	/**
	 * Client Secret variable
	 *
	 * @var [string]
	 */
	public $client_secret;

	/**
	 * Sandbox variable
	 *
	 * @var [bool]
	 */
	public $sandbox;

	/**
	 * Log variable
	 *
	 * @var WC_WooMercadoPago_Log
	 */
	public $log;

	/**
	 * WC_WooMercadoPago_Credentials constructor.
	 *
	 * @param mixed|null $payment payment.
	 */
	public function __construct( $payment = null ) {
		$this->payment = $payment;
		$public_key    = get_option( '_mp_public_key_prod', '' );
		$access_token  = get_option( '_mp_access_token_prod', '' );

		if ( ! is_null( $this->payment ) ) {
			$this->sandbox = $payment->is_test_user();
			if ( 'yes' === get_option( 'checkbox_checkout_test_mode', '' ) || empty( get_option( 'checkbox_checkout_test_mode', '' ) ) ) {
				$public_key   = get_option( '_mp_public_key_test', '' );
				$access_token = get_option( '_mp_access_token_test', '' );
			}
		}

		if ( is_null( $this->payment ) && empty( $public_key ) && empty( $access_token ) ) {
			$public_key   = get_option( '_mp_public_key_test', '' );
			$access_token = get_option( '_mp_access_token_test', '' );
		}

		$this->public_key    = $public_key;
		$this->access_token  = $access_token;
		$this->client_id     = get_option( '_mp_client_id' );
		$this->client_secret = get_option( '_mp_client_secret' );
	}

	/**
	 * Mercadopago payment update function
	 *
	 * @return void
	 * @throws WC_WooMercadoPago_Exception Error.
	 */
	public static function mercadopago_payment_update() {
		try {
			$mp_v1 = WC_WooMercadoPago_Module::get_mp_instance_singleton();
			if ( false === $mp_v1 instanceof MP ) {
				self::set_no_credentials();
				return;
			}

			$access_token = $mp_v1->get_access_token();
			if ( ! empty( $access_token ) ) {
				$payments_response = self::get_payment_response( $mp_v1, $access_token );
				self::update_payment_methods( $mp_v1, $access_token, $payments_response );
				self::update_ticket_method( $mp_v1, $access_token, $payments_response );
			}
		} catch ( WC_WooMercadoPago_Exception $e ) {
			$log = WC_WooMercadoPago_Log::init_mercado_pago_log( 'WC_WooMercadoPago_Credentials' );
			$log->write_log( 'mercadopago_payment_update', 'Exception ERROR' . $e->getMessage() );
		}
	}

	/**
	 * Validate Credentials Type function
	 *
	 * @return string
	 */
	public function validate_credentials_type() {
		$basic_is_enabled = self::basic_is_enabled();
		if ( ! $this->token_is_valid() && ( $this->payment instanceof WC_WooMercadoPago_Basic_Gateway || 'yes' === $basic_is_enabled ) ) {
			if ( ! $this->client_is_valid() ) {
				return self::TYPE_ACCESS_TOKEN;
			}
			return self::TYPE_ACCESS_CLIENT;
		}

		return self::TYPE_ACCESS_TOKEN;
	}

	/**
	 *
	 * Client Is Valid function
	 *
	 * @return bool
	 */
	public function client_is_valid() {
		if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
			return false;
		}
		return true;
	}

	/**
	 *
	 * Token Is Valid function
	 *
	 * @return bool
	 */
	public function token_is_valid() {
		if ( empty( $this->public_key ) || empty( $this->access_token ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Set No Credentials function
	 *
	 * @return void
	 */
	public static function set_no_credentials() {
		update_option( '_test_user_v1', '', true );
		update_option( '_site_id_v1', '', true );
		update_option( '_collector_id_v1', '', true );
		update_option( '_all_payment_methods_v0', array(), true );
		update_option( '_all_payment_methods_ticket', '[]', true );
		update_option( '_mp_payment_methods_pix', '', true );
		update_option( '_can_do_currency_conversion_v1', false, true );
	}

	/**
	 *
	 * Access Token Is Valid function
	 *
	 * @param string $access_token access token.
	 * @return bool
	 * @throws WC_WooMercadoPago_Exception Error.
	 */
	public static function access_token_is_valid( $access_token ) {
		$mp_v1 = WC_WooMercadoPago_Module::get_mp_instance_singleton();
		if ( empty( $mp_v1 ) ) {
			return false;
		}
		$get_request = $mp_v1->get( '/users/me', array( 'Authorization' => 'Bearer ' . $access_token ), false );
		if ( $get_request['status'] > 202 ) {
			$log = WC_WooMercadoPago_Log::init_mercado_pago_log( 'WC_WooMercadoPago_Credentials' );
			$log->write_log( 'API valid_access_token error:', $get_request['response']['message'] );
			return false;
		}

		if ( isset( $get_request['response']['site_id'] ) ) {
			update_option( '_site_id_v1', strtolower($get_request['response']['site_id']), true );
			update_option( '_test_user_v1', in_array( 'test_user', $get_request['response']['tags'], true ), true );
		}

		if ( isset( $get_request['response']['id'] ) ) {
			update_option( '_collector_id_v1', $get_request['response']['id'], true );
		}

		return true;
	}

	/**
	 *
	 * Validate Credentials v1 function
	 *
	 * @return bool
	 */
	public static function validate_credentials_v1() {
		$credentials      = new self();
		$basic_is_enabled = 'no';
		if ( ! $credentials->token_is_valid() ) {
			$basic_is_enabled = self::basic_is_enabled();
			if ( 'yes' !== $basic_is_enabled ) {
				self::set_no_credentials();
				return false;
			}
		}

		try {
			$mp_v1 = WC_WooMercadoPago_Module::get_mp_instance_singleton();
			if ( false === $mp_v1 instanceof MP ) {
				self::set_no_credentials();
				return false;
			}
			$access_token = $mp_v1->get_access_token();
			$get_request  = $mp_v1->get( '/users/me', array( 'Authorization' => 'Bearer ' . $access_token ) );

			if ( isset( $get_request['response']['site_id'] ) && ( ! empty( $credentials->public_key ) || 'yes' === $basic_is_enabled ) ) {

				update_option( '_test_user_v1', in_array( 'test_user', $get_request['response']['tags'], true ), true );
				update_option( '_site_id_v1', strtolower($get_request['response']['site_id']), true );
				update_option( '_collector_id_v1', $get_request['response']['id'], true );

				self::mercadopago_payment_update();

				$currency_ratio = WC_WooMercadoPago_Module::get_conversion_rate(
					WC_WooMercadoPago_Module::$country_configs[ strtolower($get_request['response']['site_id']) ]['currency']
				);

				if ( $currency_ratio > 0 ) {
					update_option( '_can_do_currency_conversion_v1', true, true );
				} else {
					update_option( '_can_do_currency_conversion_v1', false, true );
				}
				return true;
			}
		} catch ( WC_WooMercadoPago_Exception $e ) {
			$log = WC_WooMercadoPago_Log::init_mercado_pago_log( 'WC_WooMercadoPago_Credentials' );
			$log->write_log( 'validate_credentials_v1', 'Exception ERROR' . $e->getMessage() );
		}

		self::set_no_credentials();
		return false;
	}

	/**
	 * Get Homolog Validate
	 *
	 * @return mixed
	 * @throws WC_WooMercadoPago_Exception Homolog validate exception.
	 */
	public static function get_homolog_validate( $production_mode, $mp_access_token_prod ) {
		$homolog_validate = (int) get_option( WC_WooMercadoPago_Options::HOMOLOG_VALIDATE, 0 );
		$mp               = WC_WooMercadoPago_Module::get_mp_instance_singleton();
		if ( ( $production_mode && ! empty( $mp_access_token_prod ) ) && 0 === $homolog_validate ) {
			if ( $mp instanceof MP ) {
				$homolog_validate = $mp->get_credentials_wrapper( $mp_access_token_prod );
				$homolog_validate = isset( $homolog_validate['homologated'] ) && true === $homolog_validate['homologated'] ? 1 : 0;
				update_option( 'homolog_validate', $homolog_validate, true );
				return $homolog_validate;
			}
			return 0;
		}
		return 1;
	}

	/**
	 *
	 * Get Payment Response function
	 *
	 * @param MP     $mp_instance MP Instance.
	 * @param string $access_token Access token.
	 * @return null
	 */
	public static function get_payment_response( $mp_instance, $access_token ) {
		$payments = $mp_instance->get_payment_methods( $access_token );
		if ( isset( $payments['response'] ) ) {
			return $payments['response'];
		}

		return null;
	}

	/**
	 *
	 * Update Payment Methods function
	 *
	 * @param MP          $mp_instance MP instance.
	 * @param string|null $access_token Access token.
	 * @param array|null  $payments_response Payments response.
	 */
	public static function update_payment_methods( $mp_instance, $access_token = null, $payments_response = null ) {
		if ( empty( $access_token ) || empty( $mp_instance ) ) {
			return;
		}

		if ( empty( $payments_response ) ) {
			$payments_response = self::get_payment_response( $mp_instance, $access_token );
		}

		if ( empty( $payments_response ) || ( isset( $payments_response['status'] ) && 200 !== $payments_response['status'] &&
			201 !== $payments_response['status'] ) ) {
			return;
		}

		$arr      = array();
		$cho      = array();
		$excluded = array( 'consumer_credits', 'paypal', 'account_money' );

		foreach ( $payments_response as $payment ) {
			if ( in_array( $payment['id'], $excluded, true ) ) {
				continue;
			}

			$arr[] = $payment['id'];

			$cho[] = array(
				'id'     => $payment['id'],
				'name'   => $payment['name'],
				'type'   => $payment['payment_type_id'],
				'image'  => $payment['secure_thumbnail'],
				'config' => 'ex_payments_' . $payment['id'],
			);
		}

		update_option( '_all_payment_methods_v0', implode( ',', $arr ), true );
		update_option( '_checkout_payments_methods', $cho, true );
	}

	/**
	 *
	 * Update Pix Method function
	 *
	 * @param MP         $mp_instance Mp instance.
	 * @param string     $access_token Access token.
	 * @param array|null $payments_response Payment response.
	 * @return void
	 */
	public static function update_pix_method( $mp_instance, $access_token, $payments_response = null ) {
		if ( empty( $access_token ) || empty( $mp_instance ) ) {
			return;
		}

		if ( empty( $payments_response ) ) {
			$payments_response = self::get_payment_response( $mp_instance, $access_token );
		}

		if ( empty( $payments_response ) ) {
			return;
		}

		$payment_methods_pix = array();
		$accepted            = array( 'pix' );

		foreach ( $payments_response as $payment ) {
			if ( in_array( $payment['id'], $accepted, true ) ) {
				$payment_methods_pix[ $payment['id'] ] = array(
					'id'               => $payment['id'],
					'name'             => $payment['name'],
					'secure_thumbnail' => $payment['secure_thumbnail'],
				);
			}
		}

		update_option( '_mp_payment_methods_pix', $payment_methods_pix, true );
	}

	/**
	 *
	 * Update Ticket Method function
	 *
	 * @param MP         $mp_instance Mp instance.
	 * @param string     $access_token Access token.
	 * @param array|null $payments_response Payment response.
	 * @return void
	 */
	public static function update_ticket_method( $mp_instance, $access_token, $payments_response = null ) {
		if ( empty( $access_token ) || empty( $mp_instance ) ) {
			return;
		}

		if ( empty( $payments_response ) ) {
			$payments_response = self::get_payment_response( $mp_instance, $access_token );
		}

		if ( empty( $payments_response ) || ( isset( $payments_response['status'] ) && 200 !== $payments_response['status'] &&
			201 !== $payments_response['status'] ) ) {
			return;
		}

		$payment_methods_ticket = array();
		$excluded               = array( 'paypal', 'pse', 'pix' );

		foreach ( $payments_response as $payment ) {
			if (
				! in_array( $payment['id'], $excluded, true ) &&
				'account_money' !== $payment['payment_type_id'] &&
				'credit_card' !== $payment['payment_type_id'] &&
				'debit_card' !== $payment['payment_type_id'] &&
				'prepaid_card' !== $payment['payment_type_id']
			) {
				$payment_methods_ticket[] = $payment;
			}
		}

		update_option( '_all_payment_methods_ticket', $payment_methods_ticket, true );
	}

	/**
	 *
	 * Basic is enabled function
	 *
	 * @return string
	 */
	public static function basic_is_enabled() {
		$basic_is_enabled = 'no';
		$basic_settings   = get_option( 'woocommerce_woo-mercado-pago-basic_settings', '' );

		if ( isset( $basic_settings['enabled'] ) ) {
			$basic_is_enabled = $basic_settings['enabled'];
		}

		return $basic_is_enabled;
	}

	/**
	 *
	 * Validate Credentials Test function
	 *
	 * @param MP $mp_instance Mp instance.
	 * @param string|null $access_token Access token.
	 * @param string|null $public_key Payment response.
	 *
	 * @return bool
	 * @throws WC_WooMercadoPago_Exception
	 */
	public static function validate_credentials_test( $mp_instance, $access_token = null, $public_key = null ) {
		$is_test = $mp_instance->get_credentials_wrapper( $access_token, $public_key );
		if ( is_array( $is_test ) && isset( $is_test['is_test'] ) && true === $is_test['is_test'] ) {
			return true;
		}
		return false;
	}

	/**
	 *
	 * Validate Credentials Prod function
	 *
	 * @param MP $mp_instance Mp instance.
	 * @param string|null $access_token Access token.
	 * @param string|null $public_key Payment response.
	 *
	 * @return bool
	 * @throws WC_WooMercadoPago_Exception
	 */
	public static function validate_credentials_prod( $mp_instance, $access_token = null, $public_key = null ) {
		$log = WC_WooMercadoPago_Log::init_mercado_pago_log( 'mercadopago_requests' );
		$log->write_log( 'Func:', __FUNCTION__ );
		$is_test = $mp_instance->get_credentials_wrapper( $access_token, $public_key );
		if ( is_array( $is_test ) && isset( $is_test['is_test'] ) && false === $is_test['is_test'] ) {
			if ( ! empty($is_test['client_id']) ) {
				update_option('mp_application_id', $is_test['client_id']);
			}
			return true;
		}
		return false;
	}
}
