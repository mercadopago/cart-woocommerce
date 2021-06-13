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
 * Class WC_WooMercadoPago_Module
 */
class WC_WooMercadoPago_Module extends WC_WooMercadoPago_Configs {

	/**
	 * Categories variable
	 *
	 * @var array
	 */
	public static $categories = array();

	/**
	 * Country Configs variable
	 *
	 * @var array
	 */
	public static $country_configs = array();

	/**
	 * Site data variable
	 *
	 * @var string
	 */
	public static $site_data;

	/**
	 * Undocumented variable
	 *
	 * @var MP
	 */
	public static $instance = null;

	/**
	 * MP instance ayment variable
	 *
	 * @var array
	 */
	public static $mp_instance_ayment = array();

	/**
	 * MP instance variable
	 *
	 * @var MP
	 */
	public static $mp_instance = null;

	/**
	 * Payments name variable
	 *
	 * @var string
	 */
	public static $payments_name = null;

	/**
	 * Notices variable
	 *
	 * @var array
	 */
	public static $notices = array();

	/**
	 * WC_WooMercadoPago_Module constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception Error.
	 */
	public function __construct() {
		try {
			$this->load_helpers();
			$this->load_configs();
			$this->load_log();
			$this->load_hooks();
			$this->load_preferences();
			$this->load_payments();
			$this->load_notifications();
			$this->load_stock_manager();

			add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_css' ) );

			add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_payment_method_by_shipping' ) );
			add_filter( 'plugin_action_links_' . WC_MERCADOPAGO_BASENAME, array( $this, 'woomercadopago_settings_link' ) );
			add_filter( 'plugin_row_meta', array( $this, 'mp_plugin_row_meta' ), 10, 2 );
			add_action( 'mercadopago_plugin_updated', array( 'WC_WooMercadoPago_Credentials', 'mercadopago_payment_update' ) );

			if ( is_admin() ) {
				// validate credentials.
				if ( isset( $_REQUEST['section'] ) ) { // phpcs:disable WordPress.Security.NonceVerification
					$credentials = new WC_WooMercadoPago_Credentials();
					if ( ! $credentials->token_is_valid() ) {
						add_action( 'admin_notices', array( $this, 'enable_payment_notice' ) );
					}
				}
			}
		} catch ( Exception $e ) {
			$log = WC_WooMercadoPago_Log::init_mercado_pago_log( 'WC_WooMercadoPago_Module' );
			$log->write_log( '__construct: ', $e->getMessage() );
		}
	}

	/**
	 *
	 * Get Mp Instance
	 *
	 * @param object $payment payment.
	 * @return MP MP.
	 * @throws WC_WooMercadoPago_Exception Error.
	 */
	public static function get_mp_instance( $payment = null ) {
		$credentials               = new WC_WooMercadoPago_Credentials( $payment );
		$validate_credentials_type = $credentials->validate_credentials_type();
		if ( WC_WooMercadoPago_Credentials::TYPE_ACCESS_TOKEN === $validate_credentials_type ) {
			$mp = new MP( $credentials->access_token );
			$mp->set_payment_class( $payment );
		}
		if ( WC_WooMercadoPago_Credentials::TYPE_ACCESS_CLIENT === $validate_credentials_type ) {
			$mp = new MP( $credentials->client_id, $credentials->client_secret );
			$mp->set_payment_class( $payment );
			if ( ! empty( $payment ) ) {
				$payment->sandbox = false;
			}
		}

		if ( ! isset( $mp ) ) {
			return false;
		}

		$email = ( 0 !== wp_get_current_user()->ID ) ? wp_get_current_user()->user_email : null;
		$mp->set_email( $email );

		$locale = get_locale();
		$locale = false !== ( strpos( $locale, '_' ) && 5 === strlen( $locale ) ) ? explode( '_', $locale ) : array( '', '' );
		$mp->set_locale( $locale[1] );

		return $mp;
	}

	/**
	 *
	 * Get Mp InstanceSingleton
	 *
	 * @param null|object $payment payment.
	 * @return MP|null
	 * @throws WC_WooMercadoPago_Exception Error.
	 */
	public static function get_mp_instance_singleton( $payment = null ) {
		$mp = null;
		if ( ! empty( $payment ) ) {
			$class = get_class( $payment );
			if ( ! isset( self::$mp_instance_ayment[ $class ] ) ) {
				self::$mp_instance_ayment[ $class ] = self::get_mp_instance( $payment );
				$mp                                 = self::$mp_instance_ayment[ $class ];
				if ( ! empty( $mp ) ) {
					return $mp;
				}
			}
		}

		if ( null === self::$mp_instance || empty( $mp ) ) {
			self::$mp_instance = self::get_mp_instance();
		}

		return self::$mp_instance;
	}

	/**
	 *
	 * Init Mercado Pago Class
	 *
	 * @return WC_WooMercadoPago_Module|null
	 * Singleton
	 */
	public static function init_mercado_pago_class() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 *
	 * Load Config / Categories
	 *
	 * @return void
	 */
	public function load_configs() {
		self::$country_configs = self::get_country_configs();
		$configs               = new parent();
		self::$categories      = $configs->get_categories();
		self::$site_data       = self::get_site_data();
		self::$payments_name   = self::set_payment_gateway();
	}

	/**
	 *
	 *  Load Hooks
	 *
	 *  @return void
	 */
	public function load_hooks() {
		include_once dirname( __FILE__ ) . '/../payments/hooks/class-wc-woomercadopago-hook-abstract.php';
		include_once dirname( __FILE__ ) . '/../payments/hooks/class-wc-woomercadopago-hook-basic.php';
		include_once dirname( __FILE__ ) . '/../payments/hooks/class-wc-woomercadopago-hook-custom.php';
		include_once dirname( __FILE__ ) . '/../payments/hooks/class-wc-woomercadopago-hook-ticket.php';
		include_once dirname( __FILE__ ) . '/../payments/hooks/class-wc-woomercadopago-hook-pix.php';
	}

	/**
	 * Load Helpers
	 *
	 * @return void
	 */
	public function load_helpers() {
		include_once dirname( __FILE__ ) . '/../helpers/class-wc-woomercadopago-helpers-currencyconverter.php';
	}

	/**
	 * Load Preferences Classes
	 *
	 * @return void
	 */
	public function load_preferences() {
		include_once dirname( __FILE__ ) . '/preference/class-wc-woomercadopago-preference-abstract.php';
		include_once dirname( __FILE__ ) . '/preference/class-wc-woomercadopago-preference-basic.php';
		include_once dirname( __FILE__ ) . '/preference/class-wc-woomercadopago-preference-custom.php';
		include_once dirname( __FILE__ ) . '/preference/class-wc-woomercadopago-preference-ticket.php';
		include_once dirname( __FILE__ ) . '/preference/class-wc-woomercadopago-preference-pix.php';
		include_once dirname( __FILE__ ) . '/preference/analytics/class-wc-woomercadopago-preferenceanalytics.php';
	}

	/**
	 *  Load Payment Classes
	 *
	 *  @return void
	 */
	public function load_payments() {
		include_once dirname( __FILE__ ) . '/../payments/class-wc-woomercadopago-payment-abstract.php';
		include_once dirname( __FILE__ ) . '/../payments/class-wc-woomercadopago-basic-gateway.php';
		include_once dirname( __FILE__ ) . '/../payments/class-wc-woomercadopago-custom-gateway.php';
		include_once dirname( __FILE__ ) . '/../payments/class-wc-woomercadopago-ticket-gateway.php';
		include_once dirname( __FILE__ ) . '/../payments/class-wc-woomercadopago-pix-gateway.php';
		add_filter( 'woocommerce_payment_gateways', array( $this, 'set_payment_gateway' ) );
	}

	/**
	 *
	 * Load Notifications
	 *
	 * @return void
	 */
	public function load_notifications() {
		include_once dirname( __FILE__ ) . '/../notification/class-wc-woomercadopago-notification-abstract.php';
		include_once dirname( __FILE__ ) . '/../notification/class-wc-woomercadopago-notification-ipn.php';
		include_once dirname( __FILE__ ) . '/../notification/class-wc-woomercadopago-notification-webhook.php';
	}

	/**
	 *
	 * Load log
	 *
	 * @return void
	 */
	public function load_log() {
		include_once dirname( __FILE__ ) . '/log/class-wc-woomercadopago-log.php';
	}

	/**
	 *
	 * Load Admin Css
	 *
	 * @return void
	 */
	public function load_admin_css() {
		if ( is_admin() ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

			wp_enqueue_style(
				'woocommerce-mercadopago-basic-config-styles',
				plugins_url( '../assets/css/config_mercadopago' . $suffix . '.css', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION
			);
		}
	}

	/**
	 *
	 * Load Stock Manager
	 *
	 * @return void
	 */
	public function load_stock_manager() {
		include_once dirname( __FILE__ ) . '/../stock/class-wc-woomercadopago-stock-manager.php';
	}

	/**
	 *
	 * Filter Payment Method by Shipping
	 *
	 * @param array $methods methods.
	 * @return array
	 */
	public function filter_payment_method_by_shipping( $methods ) {
		return $methods;
	}

	/**
	 *
	 * Enable Payment Notice
	 *
	 * @return void
	 */
	public function enable_payment_notice() {
		$type    = 'notice-warning';
		$message = __( 'Fill in your credentials to enable payment methods.', 'woocommerce-mercadopago' );
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 *
	 * Woomercadopago Settings Link add settings link on plugin page.
	 * Enable Payment Notice
	 *
	 * @param array $links links.
	 * @return array
	 */
	public function woomercadopago_settings_link( $links ) {
		$links_mp       = self::define_link_country();
		$plugin_links   = array();
		$plugin_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Set up', 'woocommerce-mercadopago' ) . '</a>';
		$plugin_links[] = '<a target="_blank" href="https://wordpress.org/support/plugin/woocommerce-mercadopago/reviews/?rate=5#new-post">' . __( 'Your opinion helps us get better', 'woocommerce-mercadopago' ) . '</a>';
		$plugin_links[] = '<br><a target="_blank" href="https://www.mercadopago.' . $links_mp['sufix_url'] . 'developers/' . $links_mp['translate'] . '/guides/plugins/woocommerce/introduction/">' . __( 'Guides and Documentation', 'woocommerce-mercadopago' ) . '</a>';
		$plugin_links[] = '<a target="_blank" href="https://www.mercadopago.' . $links_mp['sufix_url'] . $links_mp['help'] . '">' . __( 'Report Problem', 'woocommerce-mercadopago' ) . '</a>';
		return array_merge( $plugin_links, $links );
	}

	/**
	 *
	 * Define link country
	 *
	 * @return array
	 */
	public static function define_link_country() {
		$sufix_country = 'AR';
		$country       = array(
			'AR' => array( // Argentinian.
				'help'      => 'ayuda',
				'sufix_url' => 'com.ar/',
				'translate' => 'es',
			),
			'BR' => array( // Brazil.
				'help'      => 'ajuda',
				'sufix_url' => 'com.br/',
				'translate' => 'pt',
			),
			'CL' => array( // Chile.
				'help'      => 'ayuda',
				'sufix_url' => 'cl/',
				'translate' => 'es',
			),
			'CO' => array( // Colombia.
				'help'      => 'ayuda',
				'sufix_url' => 'com.co/',
				'translate' => 'es',
			),
			'MX' => array( // Mexico.
				'help'      => 'ayuda',
				'sufix_url' => 'com.mx/',
				'translate' => 'es',
			),
			'PE' => array( // Peru.
				'help'      => 'ayuda',
				'sufix_url' => 'com.pe/',
				'translate' => 'es',
			),
			'UY' => array( // Uruguay.
				'help'      => 'ayuda',
				'sufix_url' => 'com.uy/',
				'translate' => 'es',
			),
		);

		$sufix_country = strtoupper( self::get_woocommerce_default_country() );
		$links_country = array_key_exists( $sufix_country, $country ) ? $country[ $sufix_country ] : $country['AR'];

		return $links_country;
	}

	/**
	 *
	 * Get Woocommerce default country configured
	 *
	 * @return string
	 */
	public static function get_woocommerce_default_country() {
		$wc_country = get_option( 'woocommerce_default_country', '' );
		if ( '' !== $wc_country ) {
			$wc_country = strlen( $wc_country ) > 2 ? substr( $wc_country, 0, 2 ) : $wc_country;
		}

		return $wc_country;
	}

	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param mixed $links Plugin Row Meta.
	 * @param mixed $file Plugin Base file.
	 * @return array
	 */
	public function mp_plugin_row_meta( $links, $file ) {
		if ( WC_MERCADOPAGO_BASENAME === $file ) {
			$new_link   = array();
			$new_link[] = $links[0];
			$new_link[] = esc_html__( 'By Mercado Pago', 'woocommerce-mercadopago' );

			return $new_link;
		}

		return (array) $links;
	}

	/**
	 *
	 * Get WooCommerce instance
	 * Summary: Check if we have valid credentials for v1.
	 * Description: Check if we have valid credentials.
	 *
	 * @return boolean true/false depending on the validation result.
	 */
	public static function woocommerce_instance() {
		if ( function_exists( 'WC' ) ) {
			return WC();
		} else {
			global $woocommerce;
			return $woocommerce;
		}
	}

	/**
	 * Get Common Error Messages function
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function get_common_error_messages( $key ) {
		if ( 'Invalid payment_method_id' === $key ) {
			return __( 'The payment method is not valid or not available.', 'woocommerce-mercadopago' );
		}
		if ( 'Invalid transaction_amount' === $key ) {
			return __( 'The transaction amount cannot be processed by Mercado Pago.', 'woocommerce-mercadopago' ) . ' ' .
				__( 'Possible causes: Currency not supported; Amounts below the minimum or above the maximum allowed.', 'woocommerce-mercadopago' );
		}
		if ( 'Invalid users involved' === $key ) {
			return __( 'The users are not valid.', 'woocommerce-mercadopago' ) . ' ' .
				__( 'Possible causes: Buyer and seller have the same account in Mercado Pago; The transaction involving production and test users.', 'woocommerce-mercadopago' );
		}
		if ( 'Unauthorized use of live credentials' === $key ) {
			return __( 'Unauthorized use of production credentials.', 'woocommerce-mercadopago' ) . ' ' .
				__( 'Possible causes: Use permission in use for the credential of the seller.', 'woocommerce-mercadopago' );
		}
		return $key;
	}

	/**
	 * Summary: Get the rate of conversion between two currencies.
	 * Description: The currencies are the one used in WooCommerce and the one used in $site_id.
	 *
	 * @param string $used_currency Used currency.
	 * @return float float that is the rate of conversion.
	 */
	public static function get_conversion_rate( $used_currency ) {
		$from_currency = get_woocommerce_currency();
		$to_currency   = $used_currency;

		return WC_WooMercadoPago_Helpers_CurrencyConverter::get_instance()->load_ratio( $from_currency, $to_currency );
	}

	/**
	 *
	 * Get Common Settings function
	 *
	 * @return array
	 */
	public static function get_common_settings() {
		$w          = self::woocommerce_instance();
		$infra_data = array(
			'module_version'   => WC_WooMercadoPago_Constants::VERSION,
			'platform'         => 'WooCommerce',
			'platform_version' => $w->version,
			'code_version'     => phpversion(),
			'so_server'        => PHP_OS,
		);
		return $infra_data;
	}

	/**
	 * Summary: Get Sponsor ID to preferences.
	 * Description: This function verifies, if the sponsor ID was configured,
	 * if NO, return Sponsor ID determined of get_site_data(),
	 * if YES return Sponsor ID configured on plugin
	 *
	 * @return string.
	 */
	public static function get_sponsor_id() {
		$site_data = self::get_site_data();
		return $site_data['sponsor_id'];
	}

	/**
	 * Summary: Get information about the used Mercado Pago account based in its site.
	 * Description: Get information about the used Mercado Pago account based in its site.
	 *
	 * @return array with the information.
	 */
	public static function get_site_data() {
		$site_id = get_option( '_site_id_v1', '' );
		if ( isset( $site_id ) && ! empty( $site_id ) ) {
			return self::$country_configs[ $site_id ];
		} else {
			return null;
		}
	}

	/**
	 *
	 * Fix url ampersand
	 * Fix to URL Problem : #038; replaces & and breaks the navigation.
	 *
	 * @param string $link Link.
	 * @return string
	 */
	public static function fix_url_ampersand( $link ) {
		return str_replace( '\/', '/', str_replace( '&#038;', '&', $link ) );
	}

	/**
	 * Summary: Find template's folder.
	 * Description: Find template's folder.
	 *
	 * @return string string that identifies the path.
	 */
	public static function get_templates_path() {
		return plugin_dir_path( __FILE__ ) . '../../templates/';
	}

	/**
	 * Is Subscription function
	 * Check if an order is recurrent.
	 *
	 * @param object $items items.
	 * @return boolean
	 */
	public static function is_subscription( $items ) {
		$is_subscription = false;
		if ( 1 === count( $items ) ) {
			foreach ( $items as $cart_item_key => $cart_item ) {
				$is_recurrent = is_object($cart_item) && method_exists( $cart_item, 'get_meta' ) ?
					$cart_item->get_meta( '_used_gateway' ) : get_post_meta( $cart_item['product_id'], '_mp_recurring_is_recurrent', true );
				if ( 'yes' === $is_recurrent ) {
					$is_subscription = true;
				}
			}
		}
		return $is_subscription;
	}

	/**
	 * Get Country Name function
	 *
	 * @param string $site_id Site id.
	 * @return string
	 */
	public static function get_country_name( $site_id ) {
		switch ( $site_id ) {
			case 'MCO':
				return __( 'Colombia', 'woocommerce-mercadopago' );
			case 'MLA':
				return __( 'Argentina', 'woocommerce-mercadopago' );
			case 'MLB':
				return __( 'Brazil', 'woocommerce-mercadopago' );
			case 'MLC':
				return __( 'Chile', 'woocommerce-mercadopago' );
			case 'MLM':
				return __( 'Mexico', 'woocommerce-mercadopago' );
			case 'MLU':
				return __( 'Uruguay', 'woocommerce-mercadopago' );
			case 'MLV':
				return __( 'Venezuela', 'woocommerce-mercadopago' );
			case 'MPE':
				return __( 'Peru', 'woocommerce-mercadopago' );
		}
		return '';
	}

	/**
	 * Get Map function
	 *
	 * @param array $selector_id Selector id.
	 * @return array
	 */
	public static function get_map( $selector_id ) {
		$html      = '';
		$arr       = explode( '_', $selector_id );
		$defaults  = array(
			'pending'     => 'pending',
			'approved'    => 'processing',
			'inprocess'   => 'on_hold',
			'inmediation' => 'on_hold',
			'rejected'    => 'failed',
			'cancelled'   => 'cancelled',
			'refunded'    => 'refunded',
			'chargedback' => 'refunded',
		);
		$selection = get_option( '_mp_' . $selector_id, $defaults[ $arr[2] ] );

		foreach ( wc_get_order_statuses() as $slug => $status ) {
			$slug  = str_replace( array( 'wc-', '-' ), array( '', '_' ), $slug );
			$html .= sprintf(
				'<option value="%s"%s>%s %s</option>',
				$slug,
				selected( $selection, $slug, false ),
				__( 'Update the WooCommerce order to ', 'woocommerce-mercadopago' ),
				$status
			);
		}

		return $html;
	}

	/**
	 *
	 * Is_wc_new_version function
	 *
	 * @return bool
	 */
	public static function is_wc_new_version() {
		$woo_commerce_version = WC()->version;
		if ( $woo_commerce_version <= '4.0.0' ) {
			return false;
		}
		return true;
	}

	/**
	 *
	 * Is Mobile function
	 *
	 * @return bool
	 */
	public static function is_mobile() {
		$mobile = false;
		// @todo need fix Processing form data without nonce verification.
		$user_agent = $_SERVER['HTTP_USER_AGENT']; //phpcs:ignore
		if ( preg_match( '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i', $user_agent ) || preg_match( '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr( $user_agent, 0, 4 ) ) ) {
			$mobile = true;
		}
		return $mobile;
	}
}
