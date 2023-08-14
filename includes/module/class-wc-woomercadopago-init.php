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
 * WC WooMercadoPago Init class
 */
class WC_WooMercadoPago_Init {
	/**
	 * Load plugin text domain.
	 *
	 * Need to require here before test for PHP version.
	 *
	 * @since 3.0.1
	 */
	public static function woocommerce_mercadopago_load_plugin_textdomain() {
		$text_domain = 'woocommerce-mercadopago';

		/**
		 * Apply filters plugin_locale.
		 *
		 * @since 3.0.1
		 */
		$locale = apply_filters( 'plugin_locale', get_locale(), $text_domain );

		$original_language_file = dirname( __FILE__ ) . '/../../i18n/languages/woocommerce-mercadopago-' . $locale . '.mo';

		// Unload the translation for the text domain of the plugin.
		unload_textdomain( $text_domain );
		// Load first the override file.
		load_textdomain( $text_domain, $original_language_file );
	}

	/**
	 * Notice about unsupported PHP version.
	 *
	 * @since 3.0.1
	 */
	public static function wc_mercado_pago_unsupported_php_version_notice() {
		$type    = 'error';
		$message = __( 'Mercado Pago payments for WooCommerce requires PHP version 5.6 or later. Please update your PHP version.', 'woocommerce-mercadopago' );
		echo wp_kses_post( WC_WooMercadoPago_Notices::get_alert_frame( $message, $type ));
	}

	/**
	 * Curl validation
	 */
	public static function wc_mercado_pago_notify_curl_error() {
		$type    = 'error';
		$message = __( 'Mercado Pago Error: PHP Extension CURL is not installed.', 'woocommerce-mercadopago' );
		echo wp_kses_post( WC_WooMercadoPago_Notices::get_alert_frame( $message, $type ));
	}

	/**
	 * GD validation
	 */
	public static function wc_mercado_pago_notify_gd_error() {
		$type    = 'error';
		$message = __( 'Mercado Pago Error: PHP Extension GD is not installed. Installation of GD extension is required to send QR Code Pix by email.', 'woocommerce-mercadopago' );
		echo wp_kses_post( WC_WooMercadoPago_Notices::get_alert_frame( $message, $type ));
	}

	/**
	 * Summary: Places a warning error to notify user that WooCommerce is missing.
	 * Description: Places a warning error to notify user that WooCommerce is missing.
	 */
	public static function notify_woocommerce_miss() {
		$type    = 'error';
		$message = sprintf(
			/* translators: %s link to WooCommerce */
			__( 'The Mercado Pago module needs an active version of %s in order to work!', 'woocommerce-mercadopago' ),
			' <a href="https://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>'
		);
		WC_WooMercadoPago_Notices::get_alert_woocommerce_miss( $message, $type );
	}

	/**
	 * Add mp order meta box actions function
	 *
	 * @param array $actions actions.
	 * @return array
	 */
	public static function add_mp_order_meta_box_actions( $actions ) {
		$actions['cancel_order'] = __( 'Cancel order', 'woocommerce-mercadopago' );
		return $actions;
	}

	/**
	 * Mp show admin notices function
	 *
	 * @return void
	 */
	public static function mp_show_admin_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['page']) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification
		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

		if ( ! WC_WooMercadoPago_Module::is_wc_new_version() || 'wc-settings' === $page && is_plugin_active( 'woocommerce-admin/woocommerce-admin.php' ) ) {
			return;
		}

		$notices_array = WC_WooMercadoPago_Module::$notices;
		$notices       = array_unique( $notices_array, SORT_STRING );
		foreach ( $notices as $notice ) {
			echo wp_kses_post($notice);
		}
	}

	/**
	 * Activation plugin hook
	 */
	public static function mercadopago_plugin_activation() {
		$dismissed_review = (int) get_option( '_mp_dismiss_review' );
		if ( ! isset( $dismissed_review ) || 1 === $dismissed_review ) {
			update_option( '_mp_dismiss_review', 0, true );
		}
	}

	/**
	 * Handle saved cards notice
	*/
	public static function mercadopago_handle_saved_cards_notice() {
		$must_not_show_review = (int) get_option( '_mp_dismiss_saved_cards_notice' );
		if ( ! isset( $must_not_show_review ) || $must_not_show_review ) {
			/**
			 * Update if option was changed.
			 *
			 * @since 3.0.1
			 */
			update_option( '_mp_dismiss_saved_cards_notice', 0, true );
		}
	}

	/**
	 * Update plugin version in db
	 */
	public static function update_plugin_version() {
		$old_version = get_option( '_mp_version', '0' );
		if ( version_compare( WC_WooMercadoPago_Constants::VERSION, $old_version, '>' ) ) {
			/**
			 * Do action mercadopago_plugin_updated.
			 *
			 * @since 3.0.1
			 */
			do_action( 'mercadopago_plugin_updated' );

			/**
			 * Do action mercadopago_test_mode_update.
			 *
			 * @since 3.0.1
			 */
			do_action( 'mercadopago_test_mode_update' );

			update_option( '_mp_version', WC_WooMercadoPago_Constants::VERSION, true );
		}
	}

	/**
	 * Sdk validation
	 */
	public static function wc_mercado_pago_notify_sdk_package_error() {
		$type    = 'error';
		$message = __( 'The Mercado Pago module needs the SDK package to work!', 'woocommerce-mercadopago' );
		echo wp_kses_post( WC_WooMercadoPago_Notices::get_alert_frame( $message, $type ));
	}

	/**
	 * Load sdk package
	 */
	public static function woocommerce_mercadopago_load_sdk() {
		$sdk_autoload_file = dirname( __FILE__ ) . '/../../packages/sdk/vendor/autoload.php';
		if ( file_exists( $sdk_autoload_file ) ) {
			require_once $sdk_autoload_file;
		} else {
			add_action('admin_notices', array( __CLASS__, 'wc_mercado_pago_notify_sdk_package_error' ));
		}
	}

	/**
	 * Init the plugin
	 */
	public static function woocommerce_mercadopago_init() {
		$isAdmin = is_admin();

		self::woocommerce_mercadopago_load_plugin_textdomain();
		self::woocommerce_mercadopago_load_sdk();

		require_once dirname( __FILE__ ) . '/sdk/lib/rest-client/class-mp-rest-client-abstract.php';
		require_once dirname( __FILE__ ) . '/sdk/lib/rest-client/class-mp-rest-client.php';
		require_once dirname( __FILE__ ) . '/config/class-wc-woomercadopago-constants.php';

		if ( $isAdmin ) {
			require_once dirname( __FILE__ ) . '../../admin/notices/class-wc-woomercadopago-notices.php';
			require_once dirname( __FILE__ ) . '../../admin/notices/class-wc-woomercadopago-saved-cards.php';
			require_once dirname( __FILE__ ) . '../../admin/hooks/class-wc-woomercadopago-hook-order-details.php';
			WC_WooMercadoPago_Notices::init_mercadopago_notice();
		}

		// Check for PHP version and throw notice.
		if ( version_compare( PHP_VERSION, '5.6', '<=' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'wc_mercado_pago_unsupported_php_version_notice' ) );
			return;
		}

		if ( ! in_array( 'curl', get_loaded_extensions(), true ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'wc_mercado_pago_notify_curl_error' ) );
			return;
		}

		if ( ! in_array( 'gd', get_loaded_extensions(), true ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'wc_mercado_pago_notify_gd_error' ) );
		}

		// Load Mercado Pago SDK.
		require_once dirname( __FILE__ ) . '/sdk/lib/class-mp.php';

		// Checks with WooCommerce is installed.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once dirname( __FILE__ ) . '/class-wc-woomercadopago-exception.php';
			require_once dirname( __FILE__ ) . '/class-wc-woomercadopago-configs.php';
			require_once dirname( __FILE__ ) . '/log/class-wc-woomercadopago-log.php';
			require_once dirname( __FILE__ ) . '/class-wc-woomercadopago-module.php';
			require_once dirname( __FILE__ ) . '/class-wc-woomercadopago-credentials.php';
			require_once dirname( __FILE__ ) . '/class-wc-woomercadopago-options.php';
			include_once dirname( __FILE__ ) . '/../helpers/class-wc-woomercadopago-helper-nonce.php';
			include_once dirname( __FILE__ ) . '/../helpers/class-wc-woomercadopago-helper-filter.php';
			include_once dirname( __FILE__ ) . '/../helpers/class-wc-woomercadopago-helper-current-user.php';
			include_once dirname( __FILE__ ) . '/../helpers/class-wc-woomercadopago-helper-credits-enable.php';

			if ( $isAdmin ) {
				require_once dirname( __FILE__ ) . '../../admin/notices/class-wc-woomercadopago-review-notice.php';
				require_once dirname( __FILE__ ) . '/mercadopago-settings/class-wc-woomercadopago-mercadopago-settings.php';

				// Init Get Option
				$option = WC_WooMercadoPago_Options::get_instance();

				// Init Nonce Helper
				$nonce = WC_WooMercadoPago_Helper_Nonce::get_instance();

				// Init Current User Helper
				$current_user = WC_WooMercadoPago_Helper_Current_User::get_instance();

				WC_WooMercadoPago_Review_Notice::init_mercadopago_review_notice();
				WC_WooMercadoPago_Saved_Cards::init_singleton();
				new WC_WooMercadoPago_Hook_Order_Details();

				// Load Mercado Pago Settings Screen
				( new WC_WooMercadoPago_MercadoPago_Settings( $option, $nonce, $current_user ) )->init();
			}

			require_once dirname( __FILE__ ) . '../../pix/class-wc-woomercadopago-image-generator.php';

			WC_WooMercadoPago_Module::init_mercado_pago_class();
			new WC_WooMercadoPago_Products_Hook_Credits();
			WC_WooMercadoPago_Image_Generator::init_image_generator_class();
			WC_WooMercadoPago_Helper_Credits_Enable::register_enable_credits_action();

			self::update_plugin_version();

			add_action( 'woocommerce_order_actions', array( __CLASS__, 'add_mp_order_meta_box_actions' ) );
			/**
			 * Activate credits by default, if the seller has cho pro enabled
			 * and it was not previously enabled by default.
			 *
			 * @since 6.9.3
			 */
			do_action(WC_WooMercadoPago_Helper_Credits_Enable::ENABLE_CREDITS_ACTION);

		} else {
			add_action( 'admin_notices', array( __CLASS__, 'notify_woocommerce_miss' ) );
		}
		add_action( 'woocommerce_settings_checkout', array( __CLASS__, 'mp_show_admin_notices' ) );
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'wallet_button';
			return $vars;
		} );
	}
}
