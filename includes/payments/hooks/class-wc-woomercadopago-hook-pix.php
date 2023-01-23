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
 * Class WC_WooMercadoPago_Hook_Pix
 */
class WC_WooMercadoPago_Hook_Pix extends WC_WooMercadoPago_Hook_Abstract {
	/**
	 * Load Hooks
	 */
	public function load_hooks() {
		parent::load_hooks();

		if ( ! empty( $this->payment->settings['enabled'] ) && 'yes' === $this->payment->settings['enabled'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'add_checkout_scripts_pix' ) );
			add_action( 'woocommerce_after_checkout_form', array( $this, 'add_mp_settings_script_pix' ) );
			add_action( 'woocommerce_thankyou_' . $this->payment->id, array( $this, 'update_mp_settings_script_pix' ) );
		}
	}

	/**
	 *  Add Discount
	 */
	public function add_discount() {
		// @codingStandardsIgnoreLine
		if ( ! isset( $_POST['mercadopago_pix'] ) ) {
			return;
		}

		if ( is_admin() && ! defined( 'DOING_AJAX' ) || is_cart() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$pix_checkout = map_deep( $_POST['mercadopago_pix'], 'sanitize_text_field' );
		parent::add_discount_abst( $pix_checkout );
	}

	/**
	 * Add Checkout Scripts
	 */
	public function add_checkout_scripts_pix() {
		if ( is_checkout() && $this->payment->is_available() && ! get_query_var( 'order-received' ) ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_script(
				'woocommerce-mercadopago-narciso-scripts',
				plugins_url( '../../assets/js/mp-plugins-components.js', plugin_dir_path( __FILE__ ) ),
				array( 'jquery' ),
				WC_WooMercadoPago_Constants::VERSION,
				true
			);

			wp_localize_script(
				'woocommerce-mercadopago-pix-checkout',
				'wc_mercadopago_pix_params',
				array(
					'site_id'             => strtolower(get_option( '_site_id_v1' )),
					'discount_action_url' => $this->payment->discount_action_url,
					'payer_email'         => esc_js( $this->payment->logged_user_email ),
					'apply'               => __( 'Apply', 'woocommerce-mercadopago' ),
					'remove'              => __( 'Remove', 'woocommerce-mercadopago' ),
					'coupon_empty'        => __( 'Please, inform your coupon code', 'woocommerce-mercadopago' ),
					'choose'              => __( 'To choose', 'woocommerce-mercadopago' ),
					'other_bank'          => __( 'Other bank', 'woocommerce-mercadopago' ),
					'discount_info1'      => __( 'You will save', 'woocommerce-mercadopago' ),
					'discount_info2'      => __( 'with discount of', 'woocommerce-mercadopago' ),
					'discount_info3'      => __( 'Total of your purchase:', 'woocommerce-mercadopago' ),
					'discount_info4'      => __( 'Total of your purchase with discount:', 'woocommerce-mercadopago' ),
					'discount_info5'      => __( '*After payment approval', 'woocommerce-mercadopago' ),
					'discount_info6'      => __( 'Terms and conditions of use', 'woocommerce-mercadopago' ),
					'loading'             => plugins_url( '../../assets/images/', plugin_dir_path( __FILE__ ) ) . 'loading.gif',
					'check'               => plugins_url( '../../assets/images/', plugin_dir_path( __FILE__ ) ) . 'check.png',
					'error'               => plugins_url( '../../assets/images/', plugin_dir_path( __FILE__ ) ) . 'error.png',
				));
		}
	}

	/**
	 * MP Settings pix
	 */
	public function add_mp_settings_script_pix() {
		parent::add_mp_settings_script();
	}

	/**
	 * Update settings script pix
	 *
	 * @param string $order_id Order Id.
	 */
	public function update_mp_settings_script_pix( $order_id ) {
		parent::update_mp_settings_script( $order_id );

		$order              = wc_get_order( $order_id );
		$qr_base64          = ( method_exists( $order, 'get_meta' ) ) ? $order->get_meta( 'mp_pix_qr_base64' ) : get_post_meta( $order->get_id(), 'mp_pix_qr_base64', true );
		$qr_code            = ( method_exists( $order, 'get_meta' ) ) ? $order->get_meta( 'mp_pix_qr_code' ) : get_post_meta( $order->get_id(), 'mp_pix_qr_code', true );
		$transaction_amount = ( method_exists( $order, 'get_meta' ) ) ? $order->get_meta( 'mp_transaction_amount' ) : get_post_meta( $order->get_id(), 'mp_transaction_amount', true );
		$currency_symbol    = WC_WooMercadoPago_Configs::get_country_configs();

		if ( empty( $qr_base64 ) && empty( $qr_code ) ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		// add js
		wp_enqueue_script(
			'woocommerce-mercadopago-pix-order-recived',
			plugins_url( '../../assets/js/pix_mercadopago_order_received' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION,
			false
		);

		// add css
		wp_enqueue_style(
			'woocommerce-mercadopago-pix-checkout',
			plugins_url( '../../assets/css/basic_checkout_mercadopago' . $suffix . '.css', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION
		);

		$parameters = array(
			'img_pix'             => plugins_url( '../../assets/images/img-pix.png', plugin_dir_path( __FILE__ ) ),
			'amount'              => number_format( $transaction_amount, 2, ',', '.' ),
			'qr_base64'           => $qr_base64,
			'title_purchase_pix'  => __( 'Now you just need to pay with Pix to finalize your purchase', 'woocommerce-mercadopago' ),
			'title_how_to_pay'    => __( 'How to pay with Pix:', 'woocommerce-mercadopago' ),
			'step_one'            => __( 'Go to your bank\'s app or website', 'woocommerce-mercadopago' ),
			'step_two'            => __( 'Search for the option to pay with Pix', 'woocommerce-mercadopago' ),
			'step_three'          => __( 'Scan the QR code or Pix code', 'woocommerce-mercadopago' ),
			'step_four'           => __( 'Done! You will see the payment confirmation', 'woocommerce-mercadopago' ),
			'text_amount'         => __( 'Value: ', 'woocommerce-mercadopago' ),
			'currency'            => $currency_symbol[ strtolower(get_option( '_site_id_v1' )) ]['currency_symbol'],
			'text_scan_qr'        => __( 'Scan the QR code:', 'woocommerce-mercadopago' ),
			'text_time_qr_one'    => __( 'Code valid for ', 'woocommerce-mercadopago' ),
			'qr_date_expiration'  => __($this->payment->get_option_mp( 'checkout_pix_date_expiration', '30 minutes' ), 'woocommerce-mercadopago' ),
			'text_description_qr' => __( 'If you prefer, you can pay by copying and pasting the following code', 'woocommerce-mercadopago' ),
			'qr_code'             => $qr_code,
			'text_button'         => __( 'Copy code', 'woocommerce-mercadopago' ),
		);

		wc_get_template(
			'order-received/show-pix.php',
			$parameters,
			'woo/mercado/pago/module/',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}
}
