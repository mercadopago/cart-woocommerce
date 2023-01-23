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
 * Class WC_WooMercadoPago_Saved_Cards
 */
class WC_WooMercadoPago_Saved_Cards {

	const SAVED_CARDS_NONCE_ID = 'mp_saved_cards_nonce';

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
	 * File Suffix
	 *
	 * @var string
	*/
	private $file_suffix;

	/**
	 * Static instance
	 *
	 * @var WC_WooMercadoPago_Saved_Cards
	 */
	private static $instance = null;

	/**
	 * WC_WooMercadoPago_Saved_Cards constructor.
	 */
	public function __construct() {
		$this->file_suffix  = $this->get_suffix();
		$this->nonce        = WC_WooMercadoPago_Helper_Nonce::get_instance();
		$this->current_user = WC_WooMercadoPago_Helper_Current_User::get_instance();

		add_action( 'admin_enqueue_scripts', array( $this, 'load_saved_cards_notice_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_saved_cards_notice_js' ) );
		add_action( 'wp_ajax_mercadopago_saved_cards_notice_dismiss', array( $this, 'saved_cards_notice_dismiss' ) );
	}

	/**
	 * Init Singleton
	 *
	 * @return WC_WooMercadoPago_Saved_Cards|null
	 */
	public static function init_singleton() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get sufix to static files
	 */
	private function get_suffix() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Load admin notices CSS
	 *
	 * @return void
	 */
	public function load_saved_cards_notice_css() {
		if ( is_admin() ) {
			wp_enqueue_style(
				'woocommerce-mercadopago-admin-saved-cards',
				plugins_url( '../../assets/css/saved_cards_notice_mercadopago' . $this->file_suffix . '.css', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION
			);
		}
	}

	/**
	 * Load admin notices JS
	 *
	 * @return void
	 */
	public function load_saved_cards_notice_js() {
		if ( is_admin() ) {
			$script_name = 'woocommerce_mercadopago_admin_saved_cards';

			wp_enqueue_script(
				$script_name,
				plugins_url( '../../assets/js/saved_cards_notice_mercadopago' . $this->file_suffix . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				false
			);

			wp_localize_script($script_name, $script_name . '_vars', [
				'nonce' => $this->nonce->generate_nonce(self::SAVED_CARDS_NONCE_ID),
			]);
		}
	}

	/**
	 * Should Be Inline Style
	 *
	 * @return bool
	 */
	public static function should_be_inline_style() {
		return class_exists( 'WC_WooMercadoPago_Module' )
			&& WC_WooMercadoPago_Module::is_wc_new_version()
			&& isset( $_GET['page']) // phpcs:ignore WordPress.Security.NonceVerification
			&& 'wc-settings' === sanitize_key( $_GET['page'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Get Plugin Review Banner
	 *
	 * @return string
	 */
	public static function get_plugin_review_banner() {
		$inline              = self::should_be_inline_style() ? 'inline' : null;
		$checkout_custom_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-custom');

		$notice = '<div id="saved-cards-notice" class="notice is-dismissible mp-saved-cards-notice ' . $inline . '">
					<div class="mp-left-saved-cards">
						<div>
							<img src="' . plugins_url( '../../assets/images/generics/icone-cartao-mp.png', plugin_dir_path( __FILE__ ) ) . '">
						</div>
						<div class="mp-left-saved-cards-text">
							<p class="mp-saved-cards-title">' .
								__( 'Enable payments via Mercado Pago account', 'woocommerce-mercadopago' ) .
							'</p>
							<p class="mp-saved-cards-subtitle">' .
								__( 'When you enable this function, your customers pay faster using their Mercado Pago accounts.</br>The approval rate of these payments in your store can be 25% higher compared to other payment methods.', 'woocommerce-mercadopago' ) .
							'</p>
						</div>
					</div>
					<div class="mp-right-saved-cards">
						<a
							class="mp-saved-cards-link"
							href="' . $checkout_custom_url . '"
						>'
							. __( 'Activate', 'woocommerce-mercadopago' ) .
						'</a>
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
	public function saved_cards_notice_dismiss() {
		$this->current_user->validate_user_needed_permissions();
		$this->nonce->validate_nonce(
			self::SAVED_CARDS_NONCE_ID,
			WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'nonce' )
		);

		$hide             = 1;
		$must_show_notice = (int) get_option( '_mp_dismiss_saved_cards_notice', 0 );

		if ( ! $must_show_notice ) {
			update_option( '_mp_dismiss_saved_cards_notice', $hide, true );
		}

		wp_send_json_success();
	}
}
