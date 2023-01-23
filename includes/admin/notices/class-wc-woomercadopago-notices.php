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
 * Class WC_WooMercadoPago_Notification
 */
class WC_WooMercadoPago_Notices {

	/**
	 * Static Instance
	 *
	 * @var WC_WooMercadoPago_Notices
	 */
	public static $instance = null;


	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_notice_css' ) );
	}

	/**
	 * Initialize
	 *
	 * @return WC_WooMercadoPago_Notices|null
	 * Singleton
	 */
	public static function init_mercadopago_notice() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get sufix to static files
	 */
	public function get_suffix() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Load admin notices CSS
	 */
	public function load_admin_notice_css() {
		if ( is_admin() ) {
			$suffix = $this->get_suffix();

			wp_enqueue_style(
				'woocommerce-mercadopago-admin-notice',
				plugins_url( '../../assets/css/admin_notice_mercadopago' . $suffix . '.css', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION
			);
		}
	}

	/**
	 * Alert frame
	 *
	 * @param string $message message.
	 * @param string $type type.
	 */
	public static function get_alert_frame( $message, $type ) {

		$inline = '';
		if (
			( class_exists( 'WC_WooMercadoPago_Module' ) && WC_WooMercadoPago_Module::is_wc_new_version() )
			&& 'wc-settings' === sanitize_key( isset($_GET['page']) ) // phpcs:disable WordPress.Security.NonceVerification
		) {
			$inline = 'inline';
		}

		$notice = '<div id="message" class="notice ' . $type . ' is-dismissible ' . $inline . '">
                    <div class="mp-alert-frame">
                        <div class="mp-left-alert">
                            <img src="' . plugins_url( '../../assets/images/minilogo.png', plugin_dir_path( __FILE__ ) ) . '">
                        </div>
                        <div class="mp-right-alert">
                            <p>' . $message . '</p>
                        </div>
                    </div>
                </div>';

		if ( class_exists( 'WC_WooMercadoPago_Module' ) ) {
			WC_WooMercadoPago_Module::$notices[] = $notice;
		}

		return $notice;
	}

	/**
	 * Get Alert Woocommer Miss
	 *
	 * @param string $message message.
	 * @param string $type type.
	 */
	public static function get_alert_woocommerce_miss( $message, $type ) {
		$is_installed = false;

		if ( function_exists( 'get_plugins' ) ) {
			$all_plugins  = get_plugins();
			$is_installed = ! empty( $all_plugins['woocommerce/woocommerce.php'] );
		}

		if ( $is_installed && current_user_can( 'install_plugins' ) ) {
			$button_url = '<a href="' . wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php&plugin_status=active' ), 'activate-plugin_woocommerce/woocommerce.php' ) . '" class="button button-primary">' . __( 'Activate WooCommerce', 'woocommerce-mercadopago' ) . '</a>';
		} else {
			if ( current_user_can( 'install_plugins' ) ) {
				$button_url = '<a href="' . wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' ) . '" class="button button-primary">' . __( 'Install WooCommerce', 'woocommerce-mercadopago' ) . '</a>';
			} else {
				$button_url = '<a href="http://wordpress.org/plugins/woocommerce/" class="button button-primary">' . __( 'See WooCommerce', 'woocommerce-mercadopago' ) . '</a>';
			}
		}

		$inline = '';
		if (
			( class_exists( 'WC_WooMercadoPago_Module' ) && WC_WooMercadoPago_Module::is_wc_new_version() )
			// phpcs:disable WordPress.Security.NonceVerification
			&& 'wc-settings' === sanitize_key( isset($_GET['page']) )
		) {
			$inline = 'inline';
		}

		include dirname( __FILE__ ) . '/../views/html-admin-alert-woocommerce-miss.php';
	}
}
