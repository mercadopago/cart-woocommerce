
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

	/**
	 * Static instance
	 *
	 * @var string
	*/
	private $file_suffix = null;
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
		$this->file_suffix = $this->get_suffix();

		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_notice_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_notice_js' ) );
		add_action( 'wp_ajax_mercadopago_review_dismiss', array( $this, 'review_dismiss' ) );
	}

	/**
	 * Init Singleton
	 *
	 * @return WC_WooMercadoPago_Saved_Cards|null
	 */
	public static function init_singleton() {
		if ( self::$instance === null ) {
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
	public function load_admin_notice_css() {
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
	public function load_admin_notice_js() {
		if ( is_admin() ) {
			wp_enqueue_script(
				'woocommerce-mercadopago-admin-saved-cards',
				plugins_url( '../../assets/js/saved_cards_notice_mercadopago' . $this->file_suffix . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				false
			);
		}
	}

	/**
	 * Should Be Inline Style
	 *
	 * @return string
	*/
	public static function should_be_inline_style() {
		return class_exists( 'WC_WooMercadoPago_Module' )
			&& WC_WooMercadoPago_Module::is_wc_new_version()
			&& isset( $_GET['page'] )
			&& 'wc-settings' === sanitize_key( $_GET['page'] );
	}

	/**
	 * Get Plugin Review Banner
	 *
	 * @return string
	 */
	public static function get_plugin_review_banner() {
		$inline = self::should_be_inline_style() ? 'inline' : null;
		$checkout_custom_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-custom');

		$notice = '<div id="saved-cards-notice" class="notice is-dismissible mp-saved-cards-notice ' . $inline . '">
					<div class="mp-left-saved-cards">
						<div>
							<img src="' . plugins_url( '../../assets/images/generics/icone-cartao-mp.png', plugin_dir_path( __FILE__ ) ) . '">
						</div>
						<div class="mp-left-saved-cards-text">
							<p class="mp-saved-cards-title">' .
								__( 'Saved card title', 'woocommerce-mercadopago' ) .
							'</p>
							<p class="mp-saved-cards-subtitle">' .
								__( 'Saved card description', 'woocommerce-mercadopago' ) .
							'</p>
						</div>
					</div>
					<div class="mp-right-saved-cards">
						<a
							class="mp-saved-cards-link"
							href="' . $checkout_custom_url .'"
						>'
							. __( 'Saved card button', 'woocommerce-mercadopago' ) .
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
	public function review_dismiss() {
		$must_show_notice = (int) get_option( '_mp_dismiss_review', 0 );
		$hide = 1;

		if ( !$must_show_notice ) {
			update_option( '_mp_dismiss_review', $hide, true );
		}

		wp_send_json_success();
	}
}
