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
 * Class WC_WooMercadoPago_Review_Notice
 */
class WC_WooMercadoPago_Review_Notice {

	const REVIEW_NOTICE_NONCE_ID = 'mp_review_notice_nonce';

	/**
	 * WP_Nonce
	 *
	 * @var WC_WooMercadoPago_Helper_Nonce
	 */
	protected $nonce;

	/**
	 * WP_Current_user
	 *
	 * @var WC_WooMercadoPago_Helper_Current_User
	 */
	protected $current_user;

	/**
	 * Static instance
	 *
	 * @var WC_WooMercadoPago_Review_Notice
	 */
	public static $instance = null;

	/**
	 * WC_WooMercadoPago_ReviewNotice constructor.
	 */
	private function __construct() {
		$this->nonce        = WC_WooMercadoPago_Helper_Nonce::get_instance();
		$this->current_user = WC_WooMercadoPago_Helper_Current_User::get_instance();

		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_notice_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_notice_js' ) );
		add_action( 'wp_ajax_mercadopago_review_dismiss', array( $this, 'review_dismiss' ) );
	}

	/**
	 * Singleton
	 *
	 * @return WC_WooMercadoPago_Review_Notice|null
	 */
	public static function init_mercadopago_review_notice() {
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
	 * Load admin notices JS
	 */
	public function load_admin_notice_js() {
		if ( is_admin() ) {
			$suffix      = $this->get_suffix();
			$script_name = 'woocommerce_mercadopago_admin_notice_review';

			wp_enqueue_script(
				$script_name,
				plugins_url( '../../assets/js/review' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				false
			);

			wp_localize_script($script_name, $script_name . '_vars', [
				'nonce' => $this->nonce->generate_nonce(self::REVIEW_NOTICE_NONCE_ID),
			]);
		}
	}

	/**
	 * Get Plugin Review Banner
	 *
	 * @return string
	 */
	public static function get_plugin_review_banner() {
		$inline = null;

		if (
			class_exists( 'WC_WooMercadoPago_Module' ) && WC_WooMercadoPago_Module::is_wc_new_version() &&
				isset( $_GET['page']) && 'wc-settings' === sanitize_key( $_GET['page'] ) // phpcs:ignore WordPress.Security.NonceVerification
		) {
			$inline = 'inline';
		}

		$notice = '<div id="message" class="notice is-dismissible mp-rating-notice ' . $inline . '">
                    <div class="mp-rating-frame">
                        <div class="mp-left-rating">
                            <div>
                                <img src="' . plugins_url( '../../assets/images/minilogo.png', plugin_dir_path( __FILE__ ) ) . '">
                            </div>
                            <div class="mp-left-rating-text">
                                <p class="mp-rating-title">' .
									wp_get_current_user()->user_login . ', ' .
									__( 'do you have a minute to share your experience with our plugin?', 'woocommerce-mercadopago' ) .
								'</p>
                                <p class="mp-rating-subtitle">' .
									__( 'Your opinion is very important so that we can offer you the best possible payment solution and continue to improve.', 'woocommerce-mercadopago' ) .
								'</p>
                            </div>
                        </div>
                        <div class="mp-right-rating">
                            <a
                                class="mp-rating-link"
                                href="https://wordpress.org/support/plugin/woocommerce-mercadopago/reviews/?filter=5#new-post" target="blank"
                            >'
								. __( 'Rate the plugin', 'woocommerce-mercadopago' ) .
							'</a>
                        </div>
                    </div>
                </div>';

		if ( class_exists( 'WC_WooMercadoPago_Module' ) ) {
			WC_WooMercadoPago_Module::$notices[] = $notice;
		}

		return $notice;
	}

	/**
	 * Dismiss the review admin notice
	 */
	public function review_dismiss() {
		$this->current_user->validate_user_needed_permissions();
		$this->nonce->validate_nonce(
			self::REVIEW_NOTICE_NONCE_ID,
			WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'nonce' )
		);

		$dismissed_review = (int) get_option( '_mp_dismiss_review', 0 );

		if ( 0 === $dismissed_review ) {
			update_option( '_mp_dismiss_review', 1, true );
		}

		wp_send_json_success();
	}

}
