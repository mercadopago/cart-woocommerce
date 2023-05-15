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

use MercadoPago\PP\Sdk\Entity\Payment\Payment;
use MercadoPago\PP\Sdk\Entity\Preference\Preference;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_WooMercadoPago_Preference_Abstract
 */
abstract class WC_WooMercadoPago_Preference_Abstract extends WC_Payment_Gateway {

	/**
	 * Sdk
	 */
	protected $sdk;

	/**
	 * Transaction
	 *
	 * @var Payment|Preference
	 */
	protected $transaction;

	/**
	 * Order
	 *
	 * @var object
	 */
	protected $order;

	/**
	 * Payment
	 *
	 * @var WC_WooMercadoPago_Payment_Abstract
	 */
	protected $payment;

	/**
	 * Logger
	 *
	 * @var WC_WooMercadoPago_Log
	 */
	protected $log;

	/**
	 * Checkout
	 *
	 * @var null
	 */
	protected $checkout;

	/**
	 * Discount
	 *
	 * @var mixed|string
	 */
	protected $gateway_discount;

	/**
	 * Commission
	 *
	 * @var mixed|string
	 */
	protected $commission;

	/**
	 * Currency ratio
	 *
	 * @var int
	 */
	protected $currency_ratio;

	/**
	 * Items
	 *
	 * @var array
	 */
	protected $items;

	/**
	 * Order total
	 *
	 * @var int
	 */
	protected $order_total;

	/**
	 * List of items
	 *
	 * @var array
	 */
	protected $list_of_items;

	/**
	 * Preference
	 *
	 * @var array
	 */
	protected $preference;

	/**
	 * Selected shipping
	 *
	 * @var mixed
	 */
	protected $selected_shipping;

	/**
	 * Shipping cost
	 *
	 * @var mixed
	 */
	protected $ship_cost;

	/**
	 * Site id
	 *
	 * @var false|mixed|void
	 */
	protected $site_id;

	/**
	 * Site data
	 *
	 * @var array
	 */
	protected $site_data;

	/**
	 * Test user v1
	 *
	 * @var false|mixed|void
	 */
	protected $test_user_v1;

	/**
	 * Is sandbox?
	 *
	 * @var bool
	 */
	protected $sandbox;

	/**
	 * Notification class
	 *
	 * @var false|string
	 */
	protected $notification_class;

	/**
	 * Payments
	 *
	 * @var array
	 */
	protected $ex_payments;

	/**
	 * Installments
	 *
	 * @var string
	 */
	protected $installments;

	/**
	 * WC_WooMercadoPago_PreferenceAbstract constructor.
	 *
	 * @param WC_WooMercadoPago_Payment_Abstract $payment Payment.
	 * @param object                             $order Order.
	 * @param null                               $request_checkout Request checkout.
	 * @throws Exception Preference Init abstract exception.
	 */
	public function __construct( $payment, $order, $request_checkout = null ) {
		$this->payment            = $payment;
		$this->log                = $payment->log;
		$this->order              = $order;
		$this->gateway_discount   = $this->payment->gateway_discount;
		$this->commission         = $this->payment->commission;
		$this->ex_payments        = $this->payment->ex_payments;
		$this->installments       = $this->payment->installments;
		$this->notification_class = get_class( $this->payment );
		$this->sandbox            = $this->payment->is_test_user();
		$this->test_user_v1       = get_option( '_test_user_v1', '' );
		$this->site_id            = strtolower(get_option( '_site_id_v1', '' ));
		$this->site_data          = WC_WooMercadoPago_Module::$country_configs;
		$this->order              = $order;
		$this->checkout           = $request_checkout;

		try {
			$this->currency_ratio = $this->get_currency_conversion();
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, 'Currency conversion rate failed: payment creation failed with exception: ' . $e->getMessage() );
			throw new Exception( __( 'This payment method cannot process your payment.', 'woocommerce-mercadopago' ) );
		}

		$this->items             = array();
		$this->order_total       = 0;
		$this->list_of_items     = array();
		$this->selected_shipping = $order->get_shipping_method();
		$this->ship_cost         = $this->order->get_total_shipping() + $this->order->get_shipping_tax();

		if ( count( $this->order->get_items() ) > 0 ) {
			$this->items = $this->get_items_build_array();
		}

		// shipping is added to items.
		$this->items = array_merge( $this->items, $this->prepare_shipping() );

		// fees is added to items.
		if ( 0 < count( $this->order->get_fees() ) ) {
			$this->items = array_merge( $this->items, $this->fees_cost_item() );
		}

		$this->sdk = $payment->get_sdk_instance();
	}

	/**
	 * Number format value
	 *
	 * @param mixed $value Value.
	 * @return float
	 */
	protected function number_format_value( $value ) {
		return (float) number_format( $value, 2, '.', '' );
	}

	/**
	 * Prepare shipping
	 *
	 * @return array
	 */
	protected function prepare_shipping() {
		$result = array();

		if ( $this->ship_cost > 0 ) {
			$result[] = $this->ship_cost_item();
		}

		return $result;
	}

	/**
	 * Make common transaction
	 */
	public function make_common_transaction() {
		$this->transaction->__set('binary_mode', $this->get_binary_mode( $this->payment ));
		$this->transaction->__set('external_reference', $this->get_external_reference( $this->payment ));
		$this->transaction->__set('notification_url', $this->get_notification_url());
		$this->transaction->__set('statement_descriptor', get_option( 'mp_statement_descriptor', 'Mercado Pago' ));
		$this->transaction->__set('metadata', $this->get_internal_metadata());

		if ( ! $this->test_user_v1 && ! $this->sandbox ) {
			$this->transaction->__set('sponsor_id', $this->get_sponsor_id());
		}
	}

	/**
	 * Get currency conversion
	 *
	 * @return int
	 */
	public function get_currency_conversion() {
		return WC_WooMercadoPago_Helpers_CurrencyConverter::get_instance()->ratio( $this->payment );
	}

	/**
	 * Get e-mail
	 *
	 * @return mixed
	 */
	public function get_email() {
		if ( method_exists( $this->order, 'get_id' ) ) {
			return $this->order->get_billing_email();
		} else {
			return $this->order->billing_email;
		}
	}

	/**
	 * Get payer custom
	 *
	 * @return array
	 */
	public function get_payer_custom() {
		return array(
			'first_name' => ( method_exists( $this->order, 'get_id' ) ? html_entity_decode( $this->order->get_billing_first_name() ) : html_entity_decode( $this->order->billing_first_name ) ),
			'last_name'  => ( method_exists( $this->order, 'get_id' ) ? html_entity_decode( $this->order->get_billing_last_name() ) : html_entity_decode( $this->order->billing_last_name ) ),
			'phone'      => array(
				'number' => ( method_exists( $this->order, 'get_id' ) ? $this->order->get_billing_phone() : $this->order->billing_phone ),
			),
			'address'    => array(
				'zip_code'    => ( method_exists( $this->order, 'get_id' ) ? $this->order->get_billing_postcode() : $this->order->billing_postcode ),
				'street_name' => html_entity_decode(
					method_exists( $this->order, 'get_id' ) ?
						$this->order->get_billing_address_1() . ' / ' .
						$this->order->get_billing_city() . ' ' .
						$this->order->get_billing_state() . ' ' .
						$this->order->get_billing_country() : $this->order->billing_address_1 . ' / ' .
						$this->order->billing_city . ' ' .
						$this->order->billing_state . ' ' .
						$this->order->billing_country
				),
			),
		);
	}

	/**
	 * Get items build array
	 *
	 * @return array
	 */
	public function get_items_build_array() {
		$items = array();
		foreach ( $this->order->get_items() as $item ) {
			if ( $item['qty'] ) {
				$product         = wc_get_product( $item['product_id'] );
				$product_title   = method_exists( $product, 'get_name' ) ? $product->get_name() : $product->post->post_title;
				$product_content = method_exists( $product, 'get_description' ) ? $product->get_description() : $product->post->post_content;
				$product_image   = method_exists( $product, 'get_image_id' ) ? wp_get_attachment_url( $product->get_image_id() ) : plugins_url( '../../assets/images/cart.png', plugin_dir_path( __FILE__ ) );
				// Calculates line amount and discounts.
				$line_amount           = $item['line_total'] + $item['line_tax'];
				$discount_by_gateway   = (float) $line_amount * ( $this->gateway_discount / 100 );
				$commission_by_gateway = (float) $line_amount * ( $this->commission / 100 );
				$item_amount           = $this->calculate_price( $line_amount - $discount_by_gateway + $commission_by_gateway );
				$this->order_total    += $this->number_format_value( $item_amount );

				// Add the item.
				$this->list_of_items[] = $product_title . ' x ' . $item['qty'];
				$items[]               = array(
					'id'          => $item['product_id'],
					'title'       => html_entity_decode($product_title) . ' x ' . $item['qty'],
					'description' => sanitize_file_name(
						html_entity_decode(
							strlen($product_content) > 230 ?
								substr($product_content, 0, 230) . '...' : $product_content
						)
					),
					'picture_url' => $product_image,
					'category_id' => get_option('_mp_category_id', 'others'),
					'quantity'    => 1,
					'unit_price'  => $this->number_format_value($item_amount),
					'currency_id' => $this->site_data[$this->site_id]['currency'],
				);
			}
		}
		return $items;
	}

	/**
	 * Shipping cost item
	 *
	 * @return array
	 */
	public function ship_cost_item() {
		$ship_cost          = $this->calculate_price( $this->ship_cost );
		$this->order_total += $this->number_format_value( $ship_cost );

		return array(
			'title'       => method_exists( $this->order, 'get_id' ) ? $this->order->get_shipping_method() : $this->order->shipping_method,
			'description' => __( 'Shipping service used by the store.', 'woocommerce-mercadopago' ),
			'category_id' => get_option( '_mp_category_id', 'others' ),
			'quantity'    => 1,
			'unit_price'  => $this->number_format_value( $ship_cost ),
		);
	}

	/**
	 * Fees cost item
	 *
	 * @return array
	 */
	public function fees_cost_item() {
		$items = array();
		foreach ( $this->order->get_fees() as $fee ) {
			$final = ( $fee['total'] + $fee['total_tax'] ) * $this->currency_ratio;

			$this->order_total += $this->number_format_value( $final );
			$items[]            = array(
				'title'       => sanitize_file_name(
					html_entity_decode(
						strlen($fee['name']) > 230 ?
							substr($fee['name'], 0, 230) . '...' : $fee['name']
					)
				),
				'description' => sanitize_file_name(
					html_entity_decode(
						strlen($fee['name']) > 230 ?
							substr($fee['name'], 0, 230) . '...' : $fee['name']
					)
				),
				'category_id' => get_option('_mp_category_id', 'others'),
				'quantity'    => 1,
				'unit_price'  => $this->number_format_value($final),
			);
		}
		return $items;
	}

	/**
	 * Shipments receiver address
	 *
	 * @return array
	 */
	public function shipments_receiver_address() {
		return array(
			'receiver_address' => array(
				'zip_code'    => html_entity_decode( ( is_object($this->order) && method_exists( $this->order, 'get_shipping_postcode' ) ) ?
					$this->order->get_shipping_postcode() : $this->order->shipping_postcode ),
				'street_name' => html_entity_decode( ( is_object($this->order) &&
					method_exists( $this->order, 'get_id' ) ) ?
						$this->order->get_shipping_address_1() . ' ' .
						$this->order->get_shipping_address_2() . ' ' .
						$this->order->get_shipping_city() . ' ' .
						$this->order->get_shipping_state() . ' ' .
						$this->order->get_shipping_country() : $this->order->shipping_address_1 . ' ' .
						$this->order->shipping_address_2 . ' ' .
						$this->order->shipping_city . ' ' .
						$this->order->shipping_state . ' ' .
						$this->order->shipping_country
				),
				'apartment'   => ( is_object($this->order) && method_exists( $this->order, 'get_shipping_address_2' ) ) ?
					$this->order->get_shipping_address_2() : $this->order->shipping_address_2,
				'city_name'  => ( is_object($this->order) && method_exists( $this->order, 'get_shipping_city' ) ) ?
					$this->order->get_shipping_city() : $this->order->shipping_city,
				'state_name'  => ( is_object($this->order) && method_exists( $this->order, 'get_shipping_state' ) ) ?
					$this->order->get_shipping_state() : $this->order->shipping_state,
			),
		);
	}

	/**
	 * Get notification type
	 *
	 * @param $notification_url
	 * @param $notification_class
	 *
	 * @return string
	 */
	public function get_notification_type( $notification_url, $notification_class ) {
		$type               = WC_WooMercadoPago_Module::get_notification_type($notification_class);
		$url_join_character = preg_match('#/wc-api/#', $notification_url) ? '?' : '&';
		return sprintf('%s%ssource_news=%s', $notification_url, $url_join_character, $type);
	}

	/**
	 * Get notification url
	 *
	 * @return string|void
	 */
	public function get_notification_url() {
		$notification_url         = $this->payment->custom_domain;
		$notification_url_options = $this->payment->custom_domain_options;

		if ( ! empty( $notification_url ) && (
			strrpos( $notification_url, 'localhost' ) === false ||
			filter_var( $notification_url, FILTER_VALIDATE_URL ) === false
		) ) {
			if ( $notification_url_options ) {
				return $this->get_notification_type(
					WC_WooMercadoPago_Module::fix_url_ampersand(esc_url($notification_url . '/wc-api/' . $this->notification_class . '/')),
					$this->notification_class
				);
			} else {
				return WC_WooMercadoPago_Module::fix_url_ampersand(esc_url($notification_url));
			}
		}

		if ( empty( $notification_url ) && ! strrpos( get_site_url(), 'localhost' ) ) {
			return $this->get_notification_type(
				WC()->api_request_url( $this->notification_class ),
				$this->notification_class
			);
		}
	}

	/**
	 * Get binary_mode
	 *
	 * @param WC_WooMercadoPago_Payment_Abstract $payment Payment.
	 * @return bool
	 */
	public function get_binary_mode( $payment = null ) {
		$binary_mode = ! is_null( $payment ) ? $payment->get_option_mp( 'binary_mode', 'no' ) : 'no';

		if ( 'no' !== $binary_mode ) {
			return true;
		}

		return false;
	}

	/**
	 * Get sponsor id
	 *
	 * @return string
	 */
	public function get_sponsor_id() {
		return WC_WooMercadoPago_Module::get_sponsor_id();
	}

	/**
	 * Get external reference
	 *
	 * @param null|WC_WooMercadoPago_Payment_Abstract $payment Payment.
	 * @return string
	 */
	public function get_external_reference( $payment = null ) {
		$store_identificator = get_option( '_mp_store_identificator', 'WC-' );

		if ( method_exists( $this->order, 'get_id' ) ) {
			return $store_identificator . $this->order->get_id();
		} else {
			return $store_identificator . $this->order->id;
		}
	}

	/**
	 * Get transaction
	 *
	 * @param string $transactionType
	 *
	 * @return Payment|Preference
	 */
	public function get_transaction( $transactionType = 'Preference' ) {
		$transaction_log = clone $this->transaction;

		if ( isset( $transaction_log->token ) ) {
			unset( $transaction_log->token );
		}

		$this->log->write_log( __FUNCTION__, $transactionType . ': ' . wp_json_encode( $transaction_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
		return $this->transaction;
	}

	/**
	 * Get transaction amount
	 *
	 * @return float
	 */
	public function get_transaction_amount() {
		return $this->number_format_value( $this->order_total );
	}

	/**
	 * Add discounts
	 *
	 * @return array
	 */
	public function add_discounts() {
		return array(
			'title'       => __( 'Discount provided by store', 'woocommerce-mercadopago' ),
			'description' => __( 'Discount provided by store', 'woocommerce-mercadopago' ),
			'quantity'    => 1,
			'category_id' => get_option( '_mp_category_name', 'others' ),
			'unit_price'  => ( 'COP' === $this->site_data[ $this->site_id ]['currency'] || 'CLP' === $this->site_data[ $this->site_id ]['currency'] ) ?
				-round( $this->checkout['discount'] * $this->currency_ratio ) : -round( $this->checkout['discount'] * $this->currency_ratio * 100 ) / 100,
		);
	}

	/**
	 * Discount Campaign
	 *
	 * @return array
	 */
	public function add_discounts_campaign() {
		return array(
			'campaign_id'   => (int) $this->checkout['campaign_id'],
			'coupon_code'   => strtoupper( $this->checkout['coupon_code'] ),
			'coupon_amount' => ( 'COP' === $this->site_data[ $this->site_id ]['currency'] || 'CLP' === $this->site_data[ $this->site_id ]['currency'] )
				? round( $this->checkout['discount'] * $this->currency_ratio )
				: round( $this->checkout['discount'] * $this->currency_ratio * 100 ) / 100,
		);
	}

	/**
	 * Get internal metadata
	 *
	 * @return array
	 */
	public function get_internal_metadata() {
		$access_token = get_option( '_mp_access_token_prod', '' );
		$test_mode    = false;

		if ( 'yes' === get_option( 'checkbox_checkout_test_mode', '' ) ) {
			$test_mode    = true;
			$access_token = get_option( '_mp_access_token_test', '' );
		}

		if ( empty( $access_token ) ) {
			return array();
		}

		$analytics = new WC_WooMercadoPago_PreferenceAnalytics();

		$w       = WC_WooMercadoPago_Module::woocommerce_instance();
		$seller  = get_option( '_collector_id_v1', '' );
		$user_id = get_current_user_id();

		return array(
			'platform'         => WC_WooMercadoPago_Constants::PLATAFORM_ID,
			'platform_version' => $w->version,
			'module_version'   => WC_WooMercadoPago_Constants::VERSION,
			'php_version'      => PHP_VERSION,
			'site_id'          => strtolower(get_option( '_site_id_v1' )),
			'sponsor_id'       => $this->get_sponsor_id(),
			'collector'        => $seller,
			'test_mode'        => $test_mode,
			'details'          => '',
			'basic_settings'   => $analytics->get_basic_settings(),
			'custom_settings'  => $analytics->get_custom_settings(),
			'ticket_settings'  => $analytics->get_ticket_settings(),
			'pix_settings'     => $analytics->get_pix_settings(),
			'credits_settings' => $analytics->get_credits_settings(),
			'seller_website'   => get_option('siteurl'),
			'billing_address' => array(
				'zip_code'    => html_entity_decode(str_replace('-', '', ( is_object($this->order) &&
				method_exists( $this->order, 'get_billing_postcode' ) ?
					$this->order->get_billing_postcode() : $this->order->billing_postcode ))
				),
				'street_name' => html_entity_decode(
				method_exists( $this->order, 'get_billing_address_1' ) ?
					$this->order->get_billing_address_1() : $this->order->billing_address_1
				),
				'city_name' => html_entity_decode( is_object($this->order) &&
				method_exists( $this->order, 'get_billing_city' ) ?
					$this->order->get_billing_city() : $this->order->billing_city
				),
				'state_name' => html_entity_decode( is_object($this->order) &&
				method_exists( $this->order, 'get_billing_state' ) ?
					$this->order->get_billing_state() : $this->order->billing_state
				),
				'country_name' => html_entity_decode( is_object($this->order) &&
				method_exists( $this->order, 'get_billing_country' ) ?
					$this->order->get_billing_country() : $this->order->billing_country
				),
			),
			'user' => array(
				'registered_user'        => ( null !== $user_id && '' !== $user_id && 0 !== $user_id ) ? 'yes' : 'no',
				'user_email'             => ( null !== $user_id && '' !== $user_id && 0 !== $user_id ) ? get_userdata( $user_id )->user_email : null,
				'user_registration_date' => ( null !== $user_id && ' ' !== $user_id && 0 !== $user_id ) ? gmdate('Y-m-d\TH:i:s.vP', strtotime(get_userdata($user_id)->user_registered) ) : null,
			),
		);
	}

	/**
	 * Calculate price
	 *
	 * @param mixed $amount
	 *
	 * @return float
	 */
	private function calculate_price( $amount ) {
		if ( 'COP' === $this->site_data[ $this->site_id ]['currency'] || 'CLP' === $this->site_data[ $this->site_id ]['currency'] ) {
			return number_format( $amount * $this->currency_ratio, 0, '.', '' );
		}
		return number_format( $amount * $this->currency_ratio * 100, 2, '.', '' ) / 100;
	}

	/**
	 * Get date of expiration
	 *
	 * @param string $date_expiration
	 *
	 * @return string date
	 */
	public function get_date_of_expiration( $date_expiration ) {
		if ( '' !== $date_expiration ) {
			return gmdate( 'Y-m-d\TH:i:s.000O', strtotime( '+' . $date_expiration ) );
		}
	}
}
