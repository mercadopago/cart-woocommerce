<?php

class WC_WooMercadoPago_MercadoPago_Settings {
	const PRIORITY_ON_MENU = 90;

	const SETTINGS_NONCE_ID = 'mp_settings_nonce';

	protected $options;

	protected $nonce;

	protected $current_user;

	/**
	 * WC_WooMercadoPago_MercadoPago_Settings constructor
	 *
	 * @param WC_WooMercadoPago_Options $options
	 * @param WC_WooMercadoPago_Helper_Nonce $nonce
	 * @param WC_WooMercadoPago_Helper_Current_User $current_user
	 */
	public function __construct(
		WC_WooMercadoPago_Options $options,
		WC_WooMercadoPago_Helper_Nonce $nonce,
		WC_WooMercadoPago_Helper_Current_User $current_user
	) {
		$this->options      = $options;
		$this->nonce        = $nonce;
		$this->current_user = $current_user;
	}

	/**
	 * Action to insert Mercado Pago in WooCommerce Menu and Load JavaScript and CSS
	 */
	public function init() {
		$this->load_menu();
		$this->register_endpoints();
		$this->load_scripts_and_styles();
	}

	/**
	 * Load menu
	 */
	public function load_menu() {
		add_action( 'admin_menu', array( $this, 'register_mercadopago_in_woocommerce_menu' ), self::PRIORITY_ON_MENU );
	}

	/**
	 * Load Scripts
	 *
	 * @return void
	 */
	public function load_scripts_and_styles() {
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_style' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_research_script' ) );
	}

	/**
	 * Load CSS
	 */
	public function load_admin_style() {
		if ( is_admin() && ( WC_WooMercadoPago_Helper_Current_Url::validate_page( 'mercadopago-settings' ) || WC_WooMercadoPago_Helper_Current_Url::validate_section( 'woo-mercado-pago' ) ) ) {
			wp_register_style(
				'mercadopago_settings_admin_css',
				$this->get_url( '../../../assets/css/mercadopago-settings/mercadopago_settings', '.css' ),
				false,
				WC_WooMercadoPago_Constants::VERSION
			);
			wp_enqueue_style( 'mercadopago_settings_admin_css' );
		}
	}

	/**
	 * Load JavaScripts
	 */
	public function load_admin_scripts() {
		if (
			is_admin() && (
				WC_WooMercadoPago_Helper_Current_Url::validate_page( 'mercadopago-settings' ) ||
				WC_WooMercadoPago_Helper_Current_Url::validate_section( 'woo-mercado-pago' )
			)
		) {
			$script_name = 'mercadopago_settings_javascript';

			wp_enqueue_script(
				$script_name,
				$this->get_url( '../../../assets/js/mercadopago-settings/mercadopago_settings', '.js' ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				true
			);

			wp_localize_script($script_name, $script_name . '_vars', [
				'nonce' => $this->nonce->generate_nonce(self::SETTINGS_NONCE_ID),
			]);
		}
	}

	/**
	 * Load Caronte Research Scripts
	 */
	public function load_research_script() {
		if (
			is_admin() && (
				WC_WooMercadoPago_Helper_Current_Url::validate_page( 'mercadopago-settings' ) ||
				WC_WooMercadoPago_Helper_Current_Url::validate_section( 'woo-mercado-pago' )
			)
		) {
			global $woocommerce;

			wp_enqueue_script(
				'mercadopago_research_javascript',
				plugins_url( '../../assets/js/caronte/caronte-client' . $this->get_suffix() . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				true
			);

			wp_localize_script(
				'mercadopago_research_javascript',
				'wc_mercadopago_params',
				array(
					'locale'                => get_locale(),
					'site_id'               => $this->options->get_site_id() ? strtoupper( $this->options->get_site_id() ) : 'MLA',
					'platform_id'           => WC_WooMercadoPago_Constants::PLATAFORM_ID,
					'platform_version'      => $woocommerce->version,
					'plugin_version'        => WC_WooMercadoPago_Constants::VERSION,
					'public_key_element_id' => 'mp-public-key-prod',
					'reference_element_id'  => 'reference'
				)
			);
		}
	}

	/**
	 * Register Mercado Pago Option in WooCommerce Menu
	 */
	public function register_mercadopago_in_woocommerce_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Mercado Pago Settings', 'woocommerce-mercadopago' ),
			'Mercado Pago',
			'manage_options',
			'mercadopago-settings',
			array( $this, 'mercadopago_submenu_page_callback' )
		);
	}

	/**
	 * Mercado Pago Template Call
	 */
	public function mercadopago_submenu_page_callback() {
		$categories_store            = WC_WooMercadoPago_Module::$categories;
		$category_selected           = false === $this->options->get_store_category() ? 'others' : $this->options->get_store_category();
		$category_id                 = false === $this->options->get_store_id() ? 'WC-' : $this->options->get_store_id();
		$store_identificator         = false === $this->options->get_store_name_on_invoice() ? 'Mercado Pago' : $this->options->get_store_name_on_invoice();
		$integrator_id               = $this->options->get_integrator_id();
		$devsite_links               = WC_WooMercadoPago_Helper_Links::get_mp_devsite_links();
		$debug_mode                  = false === $this->options->get_debug_mode() ? 'no' : $this->options->get_debug_mode();
		$url_ipn                     = $this->options->get_custom_domain();
		$url_ipn_options_checked     = $this->options->get_custom_domain_options() ? 'checked="checked"' : '';
		$links                       = WC_WooMercadoPago_Helper_Links::woomercadopago_settings_links();
		$checkbox_checkout_test_mode = false === $this->options->get_checkbox_checkout_test_mode() ? 'yes' : $this->options->get_checkbox_checkout_test_mode();
		$options_credentials         = $this->options->get_access_token_and_public_key();
		$translation_header          = self::mp_translation_admin_header();
		$translation_credential      = self::mp_translation_admin_credential();
		$translation_store           = self::mp_translation_admin_store();
		$translation_payment         = self::mp_translation_admin_payment();
		$translation_test_mode       = self::mp_translation_admin_test_mode();

		include __DIR__ . '/../../../templates/mercadopago-settings/mercadopago-settings.php';
	}

	/**
	 * Register Mercado Pago Endpoints
	 */
	public function register_endpoints() {
		add_action( 'wp_ajax_mp_get_requirements', array( $this, 'mercadopago_get_requirements' ) );
		add_action( 'wp_ajax_mp_validate_credentials', array( $this, 'mp_validate_credentials' ) );
		add_action( 'wp_ajax_mp_update_store_information', array( $this, 'mp_update_store_info' ) );
		add_action( 'wp_ajax_mp_store_mode', array( $this, 'mp_set_mode' ) );
		add_action( 'wp_ajax_mp_get_payment_properties', array( $this, 'mp_get_payment_class_properties' ) );
		add_action( 'wp_ajax_mp_validate_store_tips', array( $this, 'mp_validate_store_tips' ) );
		add_action( 'wp_ajax_mp_validate_credentials_tips', array( $this, 'mp_validate_credentials_tips' ) );
		add_action( 'wp_ajax_mp_validate_payment_tips', array( $this, 'mp_validate_payment_tips' ) );
		add_action( 'wp_ajax_mp_update_option_credentials', array( $this, 'mp_update_option_credentials' ) );
	}

	/**
	 * Admin translation header
	 *
	 * @return array
	 */
	public function mp_translation_admin_header() {
		return array(
			'title_head_part_one'             => __( 'Accept ', 'woocommerce-mercadopago' ),
			'title_head_part_two'             => __( 'payments on the spot ', 'woocommerce-mercadopago' ),
			'title_head_part_three'           => __( 'with', 'woocommerce-mercadopago' ),
			'title_head_part_four'            => __( 'the ', 'woocommerce-mercadopago' ),
			'title_head_part_six'             => __( 'security ', 'woocommerce-mercadopago' ),
			'title_head_part_seven'           => __( 'from Mercado Pago', 'woocommerce-mercadopago' ),
			'title_requirements'              => __( 'Technical requirements', 'woocommerce-mercadopago' ),
			'ssl'                             => __( 'SSL', 'woocommerce-mercadopago' ),
			'gd_extensions'                   => __( 'GD Extensions', 'woocommerce-mercadopago' ),
			'curl'                            => __( 'Curl', 'woocommerce-mercadopago' ),
			'description_ssl'                 => __( 'Implementation responsible for transmitting data to Mercado Pago in a secure and encrypted way.', 'woocommerce-mercadopago' ),
			'description_gd_extensions'       => __( 'These extensions are responsible for the implementation and operation of Pix in your store.', 'woocommerce-mercadopago' ),
			'description_curl'                => __( 'It is an extension responsible for making payments via requests from the plugin to Mercado Pago.', 'woocommerce-mercadopago' ),
			'title_installments'              => __( 'Collections and installments', 'woocommerce-mercadopago' ),
			'descripition_installments'       => __( 'Choose ', 'woocommerce-mercadopago' ),
			'descripition_installments_one'   => __( 'when you want to receive the money ', 'woocommerce-mercadopago' ),
			'descripition_installments_two'   => __( 'from your sales and if you want to offer ', 'woocommerce-mercadopago' ),
			'descripition_installments_three' => __( 'interest-free installments ', 'woocommerce-mercadopago' ),
			'descripition_installments_four'  => __( 'to your clients.', 'woocommerce-mercadopago' ),
			'button_installments'             => __( 'Set deadlines and fees', 'woocommerce-mercadopago' ),
			'title_questions'                 => __( 'Questions? ', 'woocommerce-mercadopago' ),
			'descripition_questions_one'      => __( 'Review the step-by-step of ', 'woocommerce-mercadopago' ),
			'descripition_questions_two'      => __( 'how to integrate the Mercado Pago Plugin ', 'woocommerce-mercadopago' ),
			'descripition_questions_three'    => __( 'on our webiste for developers.', 'woocommerce-mercadopago' ),
			'button_questions'                => __( 'Plugin manual', 'woocommerce-mercadopago' ),
		);
	}

	/**
	 * Admin translation credential
	 *
	 * @return array
	 */
	public function mp_translation_admin_credential() {
		return array(
			'title_credentials'        => __( '1. Integrate your store with Mercado Pago  ', 'woocommerce-mercadopago' ),
			'subtitle_credentials_one' => __( 'To enable orders, you must create and activate production credentials in your Mercado Pago Account. ', 'woocommerce-mercadopago' ),
			'subtitle_credentials_two' => __( 'Copy and paste the credentials below.', 'woocommerce-mercadopago' ),
			'button_link_credentials'  => __( 'Check credentials', 'woocommerce-mercadopago' ),
			'title_credential_test'    => __( 'Test credentials ', 'woocommerce-mercadopago' ),
			'subtitle_credential_test' => __( 'Enable Mercado Pago checkouts for test purchases in the store.', 'woocommerce-mercadopago' ),
			'public_key'               => __( 'Public key', 'woocommerce-mercadopago' ),
			'access_token'             => __( 'Access Token', 'woocommerce-mercadopago' ),
			'title_credential_prod'    => __( 'Production credentials', 'woocommerce-mercadopago' ),
			'subtitle_credential_prod' => __( 'Enable Mercado Pago checkouts to receive real payments in the store.', 'woocommerce-mercadopago' ),
			'placeholder_public_key'   => __( 'Paste your Public Key here', 'woocommerce-mercadopago' ),
			'placeholder_access_token' => __( 'Paste your Access Token here', 'woocommerce-mercadopago' ),
			'button_credentials'       => __( 'Save and continue', 'woocommerce-mercadopago' ),
		);
	}

	/**
	 * Admin translation store
	 *
	 * @return array
	 */
	public function mp_translation_admin_store() {
		return array(
			'title_store'                  => __( '2. Customize your business', 'woocommerce-mercadopago' ),
			'subtitle_store'               => __( 'Fill out the following information to have a better experience and offer more information to your clients', 'woocommerce-mercadopago' ),
			'title_info_store'             => __( 'Your store information', 'woocommerce-mercadopago' ),
			'subtitle_name_store'          => __( "Name of your store in your client's invoice", 'woocommerce-mercadopago' ),
			'placeholder_name_store'       => __( "Eg: Mary's store", 'woocommerce-mercadopago' ),
			'helper_name_store'            => __( 'If this field is empty, the purchase will be identified as Mercado Pago.', 'woocommerce-mercadopago' ),
			'subtitle_activities_store'    => __( 'Identification in Activities of Mercad Pago', 'woocommerce-mercadopago' ),
			'placeholder_activities_store' => __( 'Eg: Marystore', 'woocommerce-mercadopago' ),
			'helper_activities_store'      => __( 'In Activities, you will view this term before the order number', 'woocommerce-mercadopago' ),
			'subtitle_category_store'      => __( 'Store category', 'woocommerce-mercadopago' ),
			'placeholder_category_store'   => __( 'Select', 'woocommerce-mercadopago' ),
			'helper_category_store'        => __( 'Select ”Other” if you do not find the appropriate category.', 'woocommerce-mercadopago' ),
			'title_advanced_store'         => __( 'Advanced integration options (optional)', 'woocommerce-mercadopago' ),
			'subtitle_advanced_store'      => __( 'For further integration of your store with Mercado Pago (IPN, Certified Partners, Debug Mode)', 'woocommerce-mercadopago' ),
			'accordion_advanced_store'     => __( 'View advanced options', 'woocommerce-mercadopago' ),
			'subtitle_url'                 => __( 'URL for IPN ', 'woocommerce-mercadopago' ),
			'placeholder_url'              => __( 'Eg: https://examples.com/my-custom-ipn-url', 'woocommerce-mercadopago' ),
			'helper_url'                   => __( 'Add the URL to receive payments notifications. Find out more information in the ', 'woocommerce-mercadopago' ),
			'helper_url_link'              => __( 'guides.', 'woocommerce-mercadopago' ),
			'options_url'                  => __( 'Add plugin default params', 'woocommerce-mercadopago' ),
			'subtitle_integrator'          => __( 'integrator_id', 'woocommerce-mercadopago' ),
			'placeholder_integrator'       => __( 'Eg: 14987126498', 'woocommerce-mercadopago' ),
			'helper_integrator'            => __( 'If you are a Mercado Pago Certified Partner, make sure to add your integrator_id. If you do not have the code, please ', 'woocommerce-mercadopago' ),
			'helper_integrator_link'       => __( 'request it now. ', 'woocommerce-mercadopago' ),
			'title_debug'                  => __( 'Debug and Log Mode', 'woocommerce-mercadopago' ),
			'subtitle_debug'               => __( "We record your store's actions in order to provide a better assistance.", 'woocommerce-mercadopago' ),
			'button_store'                 => __( 'Save and continue', 'woocommerce-mercadopago' ),
		);
	}

	/**
	 * Admin translation payment
	 *
	 * @return array
	 */
	public function mp_translation_admin_payment() {
		return array(
			'title_payments'    => __( '3. Set payment methods', 'woocommerce-mercadopago' ),
			'subtitle_payments' => __( 'To view more options, please select a payment method below', 'woocommerce-mercadopago' ),
			'settings_payment'  => __( 'Settings', 'woocommerce-mercadopago' ),
			'button_payment'    => __( 'Continue', 'woocommerce-mercadopago' ),
		);
	}

	/**
	 * Admin translation test mode
	 *
	 * @return array
	 */
	public function mp_translation_admin_test_mode() {
		return array(
			'title_test_mode'        => __( '4. Test your store before you sell', 'woocommerce-mercadopago' ),
			'subtitle_test_mode'     => __( 'Test the experience in Test Mode and then enable the Sale Mode (Production) to sell.', 'woocommerce-mercadopago' ),
			'title_mode'             => __( 'Choose how you want to operate your store:', 'woocommerce-mercadopago' ),
			'title_test'             => __( 'Test Mode', 'woocommerce-mercadopago' ),
			'subtitle_test'          => __( 'Mercado Pago Checkouts disabled for real collections. ', 'woocommerce-mercadopago' ),
			'subtitle_test_link'     => __( 'Test Mode rules.', 'woocommerce-mercadopago' ),
			'title_prod'             => __( 'Sale Mode (Production)', 'woocommerce-mercadopago' ),
			'subtitle_prod'          => __( 'Mercado Pago Checkouts enabled for real collections.', 'woocommerce-mercadopago' ),
			'title_message_prod'     => __( 'Mercado Pago payment methods in Production Mode', 'woocommerce-mercadopago' ),
			'subtitle_message_prod'  => __( 'The clients can make real purchases in your store.', 'woocommerce-mercadopago' ),
			'title_message_test'     => __( 'Mercado Pago payment methods in Test Mode', 'woocommerce-mercadopago' ),
			'subtitle_test1'         => __( 'Create your ', 'woocommerce-mercadopago' ),
			'subtitle_link_test1'    => __( 'test user ', 'woocommerce-mercadopago' ),
			'subtitle_message_test1' => __( '(Optional. Can be used in Production Mode and Test Mode, to test payments).', 'woocommerce-mercadopago' ),
			'subtitle_link_test2'    => __( 'Use our test cards, ', 'woocommerce-mercadopago' ),
			'subtitle_test2'         => __( 'never use real cards. ', 'woocommerce-mercadopago' ),
			'subtitle_link_test3'    => __( 'Visit your store ', 'woocommerce-mercadopago' ),
			'subtitle_test3'         => __( 'to test purchases', 'woocommerce-mercadopago' ),
			'button_mode'            => __( 'Save changes', 'woocommerce-mercadopago' ),
			'badge_test'             => __( 'Store under test', 'woocommerce-mercadopago' ),
			'badge_mode'             => __( 'Store in sale mode (Production)', 'woocommerce-mercadopago' ),
			'title_alert_test'       => __( 'Enter test credentials', 'woocommerce-mercadopago' ),
			'subtitle_alert_test'    => __( 'To enable test mode, ', 'woocommerce-mercadopago' ),
			'title_alert_test_link'  => __( 'copy your test credentials ', 'woocommerce-mercadopago' ),
			'title_alert_tes_one'    => __( 'and paste them above in section 1 of this page.', 'woocommerce-mercadopago' )
		);
	}

	/**
	 * Requirements
	 */
	public function mercadopago_get_requirements() {
		$this->validate_ajax_nonce();

		$hasCurl = in_array( 'curl', get_loaded_extensions(), true );
		$hasGD   = in_array( 'gd', get_loaded_extensions(), true );
		$hasSSL  = is_ssl();

		wp_send_json_success([
			'ssl'      => $hasSSL,
			'gd_ext'   => $hasGD,
			'curl_ext' => $hasCurl
		]);
	}

	/**
	 * Validate credentials Ajax
	 */
	public function mp_validate_credentials() {
		try {
			$this->validate_ajax_nonce();

			$access_token = WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'access_token' );
			$public_key   = WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'public_key' );
			$is_test      = ( WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'is_test' ) === 'true' );

			$mp = WC_WooMercadoPago_Module::get_mp_instance_singleton();

			if ( $access_token ) {
				$validate_access_token = $mp->get_credentials_wrapper( $access_token, null );
				if ( ! $validate_access_token || $validate_access_token['is_test'] !== $is_test ) {
					wp_send_json_error( __( 'Invalid Access Token', 'woocommerce-mercadopago' ) );
				}
				wp_send_json_success( __( 'Valid Access Token', 'woocommerce-mercadopago' ) );
			}

			if ( $public_key ) {
				$validate_public_key = $mp->get_credentials_wrapper( null, $public_key );
				if ( ! $validate_public_key || $validate_public_key['is_test'] !== $is_test ) {
					wp_send_json_error( __( 'Invalid Public Key', 'woocommerce-mercadopago' ) );
				}
				wp_send_json_success( __( 'Valid Public Key', 'woocommerce-mercadopago' ) );
			}

			throw new Exception( __( 'Credentials must be valid', 'woocommerce-mercadopago' ) );
		} catch ( Exception $e ) {
			$response = [
				'message' => $e->getMessage()
			];

			wp_send_json_error( $response );
		}
	}

	/**
	 *  Update option Credentials
	 */
	public function mp_update_option_credentials() {
		try {
			$this->validate_ajax_nonce();

			$public_key_test   = WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'public_key_test' );
			$access_token_test = WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'access_token_test' );
			$public_key_prod   = WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'public_key_prod' );
			$access_token_prod = WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'access_token_prod' );

			$mp = WC_WooMercadoPago_Module::get_mp_instance_singleton();

			$validate_public_key_test   = $mp->get_credentials_wrapper( null, $public_key_test );
			$validate_access_token_test = $mp->get_credentials_wrapper( $access_token_test , null );
			$validate_public_key_prod   = $mp->get_credentials_wrapper( null, $public_key_prod );
			$validate_access_token_prod = $mp->get_credentials_wrapper( $access_token_prod , null );
			$me                         = $mp->get_me( $access_token_prod );

			if ( $validate_public_key_prod && $validate_access_token_prod && false === $validate_public_key_prod['is_test'] && false === $validate_access_token_prod['is_test'] ) {
				update_option( WC_WooMercadoPago_Options::CREDENTIALS_ACCESS_TOKEN_PROD, $access_token_prod, true );
				update_option( WC_WooMercadoPago_Options::CREDENTIALS_PUBLIC_KEY_PROD, $public_key_prod, true );
				update_option( WC_WooMercadoPago_Options::CHECKOUT_COUNTRY, $me['site_id'], true );
				update_option( WC_WooMercadoPago_Options::SITE_ID, $me['site_id'], true );
				if ( ( empty( $public_key_test ) && empty( $access_token_test ) )
				|| ( true === $validate_public_key_test['is_test'] && true === $validate_access_token_test['is_test'] ) ) {
					update_option( WC_WooMercadoPago_Options::CREDENTIALS_PUBLIC_KEY_TEST, $public_key_test, true );
					update_option( WC_WooMercadoPago_Options::CREDENTIALS_ACCESS_TOKEN_TEST, $access_token_test, true );
					WC_WooMercadoPago_Credentials::mercadopago_payment_update();
					if ( empty( $public_key_test ) && empty( $access_token_test ) && ( 'yes' === get_option( 'checkbox_checkout_test_mode', '' ) ) ) {
						$response = [
						'message' => __( 'Your store has exited Test Mode and is making real sales in Production Mode.', 'woocommerce-mercadopago' ),
						'subtitle' => __( 'To test the store, re-enter both test credentials.', 'woocommerce-mercadopago' ),
						'type' => 'alert',
						'test_mode' => 'no'
						];
						update_option( 'checkbox_checkout_test_mode', 'no' );
						throw new Exception();
					} else {
						wp_send_json_success( __( 'Credentials were updated', 'woocommerce-mercadopago' ) );
					}
				}
			}
			$links    = WC_WooMercadoPago_Helper_Links::woomercadopago_settings_links();
			$response = [
				'message'           => __( 'Invalid credentials', 'woocommerce-mercadopago' ),
				'subtitle'          => __( 'See our manual to learn ', 'woocommerce-mercadopago' ),
				'subtitle_one'      => __( 'how to enter the credentials the right way.', 'woocommerce-mercadopago' ),
				'subtitle_one_link' => $links['link_credentials'],
				'type'              => 'error',
				'test_mode'         => get_option( 'checkbox_checkout_test_mode' )
			];

			throw new Exception();
		} catch ( Exception $e ) {
			wp_send_json_error( $response );
		}
	}

	/**
	 * Get URL with path
	 *
	 * @param $path
	 * @param $extension
	 *
	 * @return string
	 */
	public function get_url( $path, $extension ) {
		return sprintf(
			'%s%s%s%s',
			plugin_dir_url( __FILE__ ),
			$path,
			$this->get_suffix(),
			$extension
		);
	}

	/**
	 * Get suffix to static files
	 *
	 * @return string
	 */
	public function get_suffix() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Validate store info Ajax
	 */
	public function mp_update_store_info() {
		try {
			$this->validate_ajax_nonce();

			$store_info = array(
				'mp_statement_descriptor'   => WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'store_identificator' ),
				'_mp_category_id'           => WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'store_categories' ),
				'_mp_store_identificator'   => WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'store_category_id' ),
				'_mp_custom_domain'         => WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'store_url_ipn' ),
				'_mp_custom_domain_options' => WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'store_url_ipn_options' ),
				'_mp_integrator_id'         => WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'store_integrator_id' ),
				'_mp_debug_mode'            => WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'store_debug_mode' ),
			);

			foreach ( $store_info as $key => $value ) {
				update_option( $key, $value, true );
			}

			wp_send_json_success( __( 'Store information is valid', 'woocommerce-mercadopago' ) );

		} catch ( Exception $e ) {
			$response = [
				'message' => $e->getMessage()
			];

			wp_send_json_error( $response );
		}
	}

	/**
	 * Switch store mode
	 */
	public function mp_set_mode() {
		try {
			$this->validate_ajax_nonce();

			$checkout_test_mode = WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'input_mode_value' );

			$verify_alert_test_mode   = WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'input_verify_alert_test_mode' );
			$without_test_credentials = ( ( '' === get_option( WC_WooMercadoPago_Options::CREDENTIALS_PUBLIC_KEY_TEST, '' ) || '' === get_option( WC_WooMercadoPago_Options::CREDENTIALS_ACCESS_TOKEN_TEST, '' ) ) );

			if ( 'yes' === $verify_alert_test_mode || ( 'yes' === $checkout_test_mode && $without_test_credentials ) ) {
				throw new Exception( __( 'Invalid credentials for test mode', 'woocommerce-mercadopago' ) );
			} else {
				$this->update_credential_production();
				update_option( 'checkbox_checkout_test_mode', $checkout_test_mode, true );

				$response = 'yes' === $checkout_test_mode ?
					__( 'Mercado Pago\'s Payment Methods in Test Mode', 'woocommerce-mercadopago' ) :
					__( 'Mercado Pago\'s Payment Methods in Production Mode', 'woocommerce-mercadopago' );

				wp_send_json_success( $response );
			}
		} catch ( Exception $e ) {
			$response = [
				'message' => $e->getMessage()
			];

			wp_send_json_error( $response );
		}
	}

	/**
	 * Update Credentials for production
	 */
	public function update_credential_production() {
		$this->validate_ajax_nonce();

		foreach ( WC_WooMercadoPago_Constants::PAYMENT_GATEWAYS as $gateway ) {
			$key     = 'woocommerce_' . $gateway::get_id() . '_settings';
			$options = get_option( $key );
			if ( ! empty( $options ) ) {
				$old_credential_is_prod                 = array_key_exists( 'checkout_credential_prod', $options ) && isset( $options['checkout_credential_prod'] ) ? $options['checkout_credential_prod'] : 'no';
				$has_new_key                            = array_key_exists( 'checkbox_checkout_test_mode', $options ) && isset( $options['checkbox_checkout_test_mode'] );
				$options['checkbox_checkout_test_mode'] = $has_new_key && 'deprecated' === $old_credential_is_prod
					? $options['checkbox_checkout_test_mode']
					: ( 'yes' === $old_credential_is_prod ? 'no' : 'yes' );
				$options['checkout_credential_prod']    = 'deprecated';

				/**
				 * Update if options were changed.
				 *
				 * @since 3.0.1
				 */
				update_option( $key, apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $gateway::get_id(), $options ) );
			}
		}
	}

	/**
	 * Get payment class properties
	 */
	public function mp_get_payment_class_properties() {
		try {
			$this->validate_ajax_nonce();

			$payments_gateways          = WC_WooMercadoPago_Constants::PAYMENT_GATEWAYS;
			$payment_gateway_properties = array();
			$payment_methods            = ( new WC_WooMercadoPago_Configs() )->get_available_payment_methods();

			foreach ( $payments_gateways as $payment_gateway ) {
				if ( ! in_array( $payment_gateway, $payment_methods, true ) ) {
					continue;
				}

				$gateway = new $payment_gateway();

				$additional_info = [
					'woo-mercado-pago-basic'   => [ 'icon' => 'mp-settings-icon-mp' ],
					'woo-mercado-pago-credits' => [ 'icon' => 'mp-settings-icon-mp' ],
					'woo-mercado-pago-custom'  => [ 'icon' => 'mp-settings-icon-card' ],
					'woo-mercado-pago-ticket'  => [ 'icon' => 'mp-settings-icon-code' ],
					'woo-mercado-pago-pix'     => [ 'icon' => 'mp-settings-icon-pix' ],
				];

				$payment_gateway_properties[] = array(
					'id'               => $gateway->id,
					'title_gateway'    => $gateway->title_gateway,
					'description'      => $gateway->description,
					'title'            => $gateway->title,
					'enabled'          => $gateway->settings['enabled'],
					'icon'             => $additional_info[ $gateway->id ]['icon'],
					'link'             => admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' ) . $gateway->id,
					'badge_translator' => [
						'yes' => __( 'Enabled', 'woocommerce-mercadopago' ),
						'no'  => __( 'Disabled', 'woocommerce-mercadopago' )
					],
				);
			}

			wp_send_json_success( $payment_gateway_properties );
		} catch ( Exception $e ) {
			$response = [
				'message' => $e->getMessage()
			];

			wp_send_json_error( $response );
		}
	}

	/**
	 * Validate credentials tips
	 */
	public function mp_validate_credentials_tips() {
		try {
			$this->validate_ajax_nonce();

			$public_key_test   = $this->options->get_public_key_test();
			$access_token_test = $this->options->get_access_token_test();
			$public_key_prod   = $this->options->get_public_key_prod();
			$access_token_prod = $this->options->get_access_token_prod();

			if ( ! ( $public_key_test xor $access_token_test ) && $public_key_prod && $access_token_prod ) {
				wp_send_json_success( __( 'Valid Credentials', 'woocommerce-mercadopago' ) );
			}

			throw new Exception( __( 'Credentials couldn\'t be validated', 'woocommerce-mercadopago' ) );
		} catch ( Exception $e ) {
			$response = [
				'message' => $e->getMessage()
			];

			wp_send_json_error( $response );
		}
	}


	/**
	 * Validate store tips
	 */
	public function mp_validate_store_tips() {
		try {
			$this->validate_ajax_nonce();

			$statement_descriptor = $this->options->get_store_name_on_invoice();
			$category_id          = $this->options->get_store_category();
			$identificator        = $this->options->get_store_id();

			if ( $statement_descriptor && $category_id && $identificator ) {
				wp_send_json_success( __( 'Store business fields are valid', 'woocommerce-mercadopago' ) );
			}

			throw new Exception( __( 'Store business fields couldn\'t be validated', 'woocommerce-mercadopago' ) );
		} catch ( Exception $e ) {
			$response = [
				'message' => $e->getMessage()
			];

			wp_send_json_error( $response );
		}
	}

	/**
	 * Validate field payment
	 */
	public function mp_validate_payment_tips() {
		try {
			$this->validate_ajax_nonce();

			$payments_gateways = WC_WooMercadoPago_Constants::PAYMENT_GATEWAYS;

			foreach ( $payments_gateways as $payment_gateway ) {
				$gateway = new $payment_gateway();

				if ( 'yes' === $gateway->settings['enabled'] ) {
					wp_send_json_success( __( 'At least one paymet method is enabled', 'woocommerce-mercadopago' ) );
				}
			}
			throw new Exception( __( 'No payment method enabled', 'woocommerce-mercadopago' ) );
		} catch ( Exception $e ) {
			$response = [
				'message' => $e->getMessage()
			];

			wp_send_json_error( $response );
		}
	}

	/**
	 * Validate ajax nonce
	 *
	 * @return void
	 */
	private function validate_ajax_nonce() {
		$this->current_user->validate_user_needed_permissions();
		$this->nonce->validate_nonce(
			self::SETTINGS_NONCE_ID,
			WC_WooMercadoPago_Helper_Filter::get_sanitize_text_from_post( 'nonce' )
		);
	}
}
