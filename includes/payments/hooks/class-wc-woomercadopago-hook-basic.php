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
 * Class WC_WooMercadoPago_Hook_Basic
 */
class WC_WooMercadoPago_Hook_Basic extends WC_WooMercadoPago_Hook_Abstract {

	/**
	 * Load hooks
	 *
	 * @param bool $is_instance Check is instance call.
	 */
	public function load_hooks( $is_instance = false ) {
		parent::load_hooks();

		if ( ! empty( $this->payment->settings['enabled'] ) && 'yes' === $this->payment->settings['enabled'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'add_checkout_scripts_basic' ) );
			add_action( 'woocommerce_after_checkout_form', array( $this, 'add_mp_settings_script_basic' ) );
			add_action( 'woocommerce_thankyou', array( $this, 'update_mp_settings_script_basic' ) );
		}

		add_action(
			'woocommerce_receipt_' . $this->payment->id,
			function ( $order ) {
				// phpcs:ignore WordPress.Security.EscapeOutput
				echo $this->render_order_form( $order );
			}
		);

		add_action(
			'wp_head',
			function () {
				if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
					$page_id = wc_get_page_id( 'checkout' );
				} else {
					$page_id = woocommerce_get_page_id( 'checkout' );
				}
				if ( is_page( $page_id ) ) {
					echo '<style>#MP-Checkout-dialog { z-index: 9999 !important; }</style>' . PHP_EOL;
				}
			}
		);
	}

	/**
	 * Get Order Form
	 *
	 * @param string $order_id Order Id.
	 *
	 * @return string
	 */
	public function render_order_form( $order_id ) {
		$order = wc_get_order( $order_id );
		$url   = $this->payment->create_preference( $order );

		if ( 'modal' === $this->payment->method && $url ) {
			$this->payment->log->write_log( __FUNCTION__, 'rendering Mercado Pago lightbox (modal window).' );

			$html = '<style type="text/css">
            #MP-Checkout-dialog #MP-Checkout-IFrame { bottom: 0px !important; top:50%!important; margin-top: -280px !important; height: 590px !important; }
            </style>';
			// mlstatic is a domain for the Mercado Pago CDN.
			// This script is used to render the checkout pro in the modal function, avoiding redirection from the store to Mercado Pago.
			// @todo use wp_enqueue_script
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
			$html .= '<script type="text/javascript" src="https://secure.mlstatic.com/mptools/render.js"></script>';
			$html .= '<script type="text/javascript">
						(function() { $MPC.openCheckout({ url: "' . esc_url( $url ) . '", mode: "modal" }); })();
					</script>';
			$html .= '<a id="submit-payment" href="' . esc_url( $url ) . '" name="MP-Checkout" class="button alt" mp-mode="modal">' .
				__( 'Pay with Mercado Pago', 'woocommerce-mercadopago' ) .
				'</a> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' .
				__( 'Cancel &amp; Clear Cart', 'woocommerce-mercadopago' ) .
				'</a>';
			return $html;
		} else {
			$this->payment->log->write_log( __FUNCTION__, 'unable to build Checkout Pro URL.' );
			$html = '<p>' .
				__( 'There was an error processing your payment. Please try again or contact us for Assistance.', 'woocommerce-mercadopago' ) .
				'</p>' .
				'<a class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' .
				__( 'Click to try again', 'woocommerce-mercadopago' ) .
				'</a>
			';
			return $html;
		}
	}

	/**
	 * Add Checkout Scripts
	 */
	public function add_checkout_scripts_basic() {
		if ( is_checkout() && $this->payment->is_available() && ! get_query_var( 'order-received' ) ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_script(
				'woocommerce-mercadopago-narciso-scripts',
				plugins_url( '../../assets/js/mp-plugins-components.js', plugin_dir_path( __FILE__ ) ),
				array( 'jquery' ),
				WC_WooMercadoPago_Constants::VERSION,
				true
			);
		}
	}

	/**
	 * Scripts to basic
	 */
	public function add_mp_settings_script_basic() {
		parent::add_mp_settings_script();
	}

	/**
	 * Update settings script basic
	 *
	 * @param string $order_id Order Id.
	 */
	public function update_mp_settings_script_basic( $order_id ) {
		parent::update_mp_settings_script( $order_id );

		$payments_id   = [];
		$collection_id = sanitize_text_field($_GET['collection_id'] ?? ''); // phpcs:ignore

		if ( ! empty( $collection_id ) ) {
			$collection_id = explode( ',', $collection_id );
			foreach ( $collection_id as $payment_id ) {
				$payments_id[] = preg_replace( '/\D/', '', $payment_id );
			}
		}

		$this->update_mp_order_payments_metadata( $order_id, $payments_id );
	}

	/**
	 *  Discount not apply
	 */
	public function add_discount() {
		// Do nothing.
	}

}
