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

use MercadoPago\PP\Sdk\Sdk;

/**
 * Class WC_WooMercadoPago_Payment_Abstract
 */
class WC_WooMercadoPago_Payment_Abstract extends WC_Payment_Gateway {
	/**
	 * Common configs for payments
	 *
	 * @const
	 */
	const COMMON_CONFIGS = array(
		'_mp_public_key_test',
		'_mp_access_token_test',
		'_mp_public_key_prod',
		'_mp_access_token_prod',
		'checkout_country',
		'mp_statement_descriptor',
		'_mp_category_id',
		'_mp_store_identificator',
		'_mp_integrator_id',
		'_mp_custom_domain',
		'_mp_custom_domain_options',
		'installments',
		'auto_return',
	);

	/**
	 * Allowed classes in plugin
	 *
	 * @const
	 */
	const ALLOWED_CLASSES = array(
		'WC_WooMercadoPago_Basic_Gateway',
		'WC_WooMercadoPago_Custom_Gateway',
		'WC_WooMercadoPago_Ticket_Gateway',
	);

	/**
	 * Field forms order
	 *
	 * @var array
	 */
	public $field_forms_order;

	/**
	 * Id
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Method Title
	 *
	 * @var string
	 */
	public $method_title;

	/**
	 * Title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Description
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Payments
	 *
	 * @var array
	 */
	public $ex_payments = array();

	/**
	 * Method
	 *
	 * @var string
	 */
	public $method;

	/**
	 * Method description
	 *
	 * @var string
	 */
	public $method_description;

	/**
	 * Auto return
	 *
	 * @var string
	 */
	public $auto_return;

	/**
	 * Success url
	 *
	 * @var string
	 */
	public $success_url;

	/**
	 * Failure url
	 *
	 * @var string
	 */
	public $failure_url;

	/**
	 * Pending url
	 *
	 * @var string
	 */
	public $pending_url;

	/**
	 * Installments
	 *
	 * @var string
	 */
	public $installments = 1;

	/**
	 * Form fields
	 *
	 * @var array
	 */
	public $form_fields;

	/**
	 * Coupon Mode
	 *
	 * @var string
	 */
	public $coupon_mode;

	/**
	 * Payment Type
	 *
	 * @var string
	 */
	public $payment_type;

	/**
	 * Checkout type
	 *
	 * @var string
	 */
	public $checkout_type;

	/**
	 * Stock reduce mode
	 *
	 * @var string
	 */
	public $stock_reduce_mode;

	/**
	 * Expiration date
	 *
	 * @var int
	 */
	public $date_expiration;

	/**
	 * Hook
	 *
	 * @var WC_WooMercadoPago_Hook_Abstract
	 */
	public $hook;

	/**
	 * Supports
	 *
	 * @var string[]
	 */
	public $supports;

	/**
	 * Icon
	 *
	 * @var mixed
	 */
	public $icon;

	/**
	 * Category Id
	 *
	 * @var mixed|string
	 */
	public $mp_category_id;

	/**
	 * Store Identificator
	 *
	 * @var mixed|string
	 */
	public $store_identificator;

	/**
	 * Integrator Id
	 *
	 * @var mixed|string
	 */
	public $integrator_id;

	/**
	 * Is debug mode
	 *
	 * @var mixed|string
	 */
	public $debug_mode;

	/**
	 * Custom domain
	 *
	 * @var mixed|string
	 */
	public $custom_domain;

	/**
	 * Custom domain
	 *
	 * @var bool
	 */
	public $custom_domain_options;

	/**
	 * Is binary mode
	 *
	 * @var mixed|string
	 */
	public $binary_mode;

	/**
	 * Gateway discount
	 *
	 * @var mixed|string
	 */
	public $gateway_discount;

	/**
	 * Site data
	 *
	 * @var string|null
	 */
	public $site_data;

	/**
	 * Logs
	 *
	 * @var WC_WooMercadoPago_Log
	 */
	public $log;

	/**
	 * Is sandbox?
	 *
	 * @var bool
	 */
	public $sandbox;

	/**
	 * Mercado Pago
	 *
	 * @var MP|null
	 */
	public $mp;

	/**
	 * Public key test
	 *
	 * @var mixed|string
	 */
	public $mp_public_key_test;

	/**
	 * Access token test
	 *
	 * @var mixed|string
	 */
	public $mp_access_token_test;

	/**
	 * Public key prod
	 *
	 * @var mixed|string
	 */
	public $mp_public_key_prod;

	/**
	 * Access token prod
	 *
	 * @var mixed|string
	 */
	public $mp_access_token_prod;

	/**
	 * Notification
	 *
	 * @var WC_WooMercadoPago_Notification_Abstract
	 */
	public $notification;

	/**
	 * Checkout country
	 *
	 * @var string
	 */
	public $checkout_country;

	/**
	 * Country
	 *
	 * @var string
	 */
	public $wc_country;

	/**
	 * Comission
	 *
	 * @var mixed|string
	 */
	public $commission;

	/**
	 * Application Id
	 *
	 * @var string
	 */
	public $application_id;

	/**
	 * Type payments
	 *
	 * @var string
	 */
	public $type_payments;

	/**
	 * Actived payments
	 *
	 * @var array
	 */
	public $activated_payment;

	/**
	 * Is validate homolog
	 *
	 * @var int|mixed
	 */
	public $homolog_validate;

	/**
	 * Client Id old version
	 *
	 * @var string
	 */
	public $clientid_old_version;

	/**
	 * Customer
	 *
	 * @var array|mixed|null
	 */
	public $customer;

	/**
	 * Logged user
	 *
	 * @var string|null
	 */
	public $logged_user_email;

	/**
	 * Currency convertion?
	 *
	 * @var boolean
	 */
	public $currency_convertion;

	/**
	 * Options
	 *
	 * @var WC_WooMercadoPago_Options
	 */
	public $mp_options;

	/**
	 * Nonce
	 *
	 * @var WC_WooMercadoPago_Helper_Nonce
	 */
	public $mp_nonce;

	/**
	 * Gateway title
	 *
	 * @var string
	 */
	public $title_gateway;

	/**
	 * User id
	 *
	 * @var int
	 */
	public $prior_uid;

	/**
	 * Discount action URL
	 *
	 * @var string
	 */
	public $discount_action_url;

	/**
	 * WC_WooMercadoPago_PaymentAbstract constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception Load payment exception.
	 */
	public function __construct() {
		$this->mp_options            = $this->get_mp_options();
		$this->mp_nonce              = WC_WooMercadoPago_Helper_Nonce::get_instance();
		$this->mp_public_key_test    = $this->mp_options->get_public_key_test();
		$this->mp_access_token_test  = $this->mp_options->get_access_token_test();
		$this->mp_public_key_prod    = $this->mp_options->get_public_key_prod();
		$this->mp_access_token_prod  = $this->mp_options->get_access_token_prod();
		$this->checkout_country      = $this->mp_options->get_checkout_country();
		$this->wc_country            = $this->mp_options->get_woocommerce_country();
		$this->mp_category_id        = false === $this->mp_options->get_store_category() ? 'others' : $this->mp_options->get_store_category();
		$this->store_identificator   = false === $this->mp_options->get_store_id() ? 'WC-' : $this->mp_options->get_store_id();
		$this->integrator_id         = $this->mp_options->get_integrator_id();
		$this->debug_mode            = false === $this->mp_options->get_debug_mode() ? 'no' : $this->mp_options->get_debug_mode();
		$this->custom_domain         = $this->mp_options->get_custom_domain();
		$this->custom_domain_options = $this->mp_options->get_custom_domain_options();
		$this->binary_mode           = $this->get_option( 'binary_mode', 'no' );
		$this->gateway_discount      = $this->get_activable_value('gateway_discount', 0);
		$this->commission            = $this->get_activable_value('commission', 0);
		$this->sandbox               = $this->is_test_user();
		$this->supports              = array( 'products', 'refunds' );
		$this->site_data             = WC_WooMercadoPago_Module::get_site_data();
		$this->log                   = new WC_WooMercadoPago_Log( $this );
		$this->mp                    = $this->get_mp_instance();
		$this->homolog_validate      = WC_WooMercadoPago_Credentials::get_homolog_validate( $this->is_production_mode(), $this->mp_access_token_prod );
		$this->application_id        = $this->get_application_id( $this->mp_access_token_prod );
		$this->logged_user_email     = ( 0 !== wp_get_current_user()->ID ) ? wp_get_current_user()->user_email : null;
		$this->discount_action_url   = get_site_url() . '/index.php/woocommerce-mercadopago/?wc-api=' . get_class( $this );
		$prior_user                  = wp_get_current_user();
		$this->prior_uid             = (int) $prior_user->ID;

		add_action( 'woocommerce_after_settings_checkout', array($this, 'mercadopago_after_form') );
	}

	/**
	 * Get SDK instance
	 *
	 * @return SDK
	 */
	public function get_sdk_instance() {
		$is_production_mode = $this->is_production_mode();

		$access_token = 'no' === $is_production_mode || ! $is_production_mode
			? get_option( '_mp_access_token_test' )
			: get_option( '_mp_access_token_prod' );

		$platform_id   = WC_WooMercadoPago_Constants::PLATAFORM_ID;
		$product_id    = WC_WooMercadoPago_Module::is_mobile() ? WC_WooMercadoPago_Constants::PRODUCT_ID_MOBILE : WC_WooMercadoPago_Constants::PRODUCT_ID_DESKTOP;
		$integrator_id = $this->integrator_id;

		return new Sdk($access_token, $platform_id, $product_id, $integrator_id);
	}

	public function mercadopago_after_form() {
		wc_get_template(
			'components/research-fields.php',
			array (
				'field_key' => 'mp-public-key-prod',
				'field_value' => $this->get_public_key(),
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);

		$page = [
			'woo-mercado-pago-basic'  => 'checkout-pro',
			'woo-mercado-pago-custom' => 'checkout-custom',
			'woo-mercado-pago-ticket' => 'checkout-ticket',
			'woo-mercado-pago-pix'    => 'checkout-pix',
			'woo-mercado-pago-credits' => 'checkout-credits'
		];

		wc_get_template(
			'components/research-fields.php',
			array (
				'field_key' => 'reference',
				'field_value' => '{"mp-screen-name":"' . $page[$this->get_id()] . '"}',
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Get Id
	 *
	 * @return string
	 */
	public static function get_id() {
		return 'abstract';
	}

	/**
	 * Get Options
	 *
	 * @return mixed
	 */
	public function get_mp_options() {
		if ( null === $this->mp_options ) {
			$this->mp_options = WC_WooMercadoPago_Options::get_instance();
		}
		return $this->mp_options;
	}

	/**
	 * Get Access token
	 *
	 * @return mixed|string
	 */
	public function get_access_token() {
		if ( ! $this->is_production_mode() ) {
			return $this->mp_access_token_test;
		}
		return $this->mp_access_token_prod;
	}

	/**
	 * Public key
	 *
	 * @return mixed|string
	 */
	public function get_public_key() {
		if ( ! $this->is_production_mode() ) {
			return $this->mp_public_key_test;
		}
		return $this->mp_public_key_prod;
	}

	/**
	 * Configs
	 *
	 * @return array
	 */
	public function get_common_config() {
		return array(
			'_mp_public_key_test'       => $this->mp_options->get_public_key_test(),
			'_mp_access_token_test'     => $this->mp_options->get_access_token_test(),
			'_mp_public_key_prod'       => $this->mp_options->get_public_key_prod(),
			'_mp_access_token_prod'     => $this->mp_options->get_access_token_prod(),
			'checkout_country'          => $this->mp_options->get_checkout_country(),
			'mp_statement_descriptor'   => $this->mp_options->get_store_name_on_invoice(),
			'_mp_category_id'           => $this->mp_options->get_store_category(),
			'_mp_store_identificator'   => $this->mp_options->get_store_id(),
			'_mp_integrator_id'         => $this->mp_options->get_integrator_id(),
			'_mp_custom_domain'         => $this->mp_options->get_custom_domain(),
			'_mp_custom_domain_options' => $this->mp_options->get_custom_domain_options(),
			'installments'              => $this->get_option('installments'),
			'auto_return'               => $this->get_option('auto_return'),
		);
	}

	/**
	 * Get options Mercado Pago
	 *
	 * @param string $key key.
	 * @param string $default default.
	 * @return mixed|string
	 */
	public function get_option_mp( $key, $default = '' ) {
		$wordpress_configs = self::COMMON_CONFIGS;
		if ( in_array( $key, $wordpress_configs, true ) ) {
			return get_option( $key, $default );
		}

		$option = $this->get_option( $key, $default );
		if ( ! empty( $option ) ) {
			return $option;
		}

		return get_option( $key, $default );
	}

	/**
	 * Normalize fields in admin
	 */
	public function normalize_common_admin_fields() {
		if ( empty( $this->mp_access_token_test ) && empty( $this->mp_access_token_prod ) ) {
			if ( isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ) {
				$this->settings['enabled'] = 'no';
				$this->disable_all_payments_methods_mp();
			}
		}

		$changed = false;
		$options = self::get_common_config();
		foreach ( $options as $config => $common_option ) {
			if ( isset( $this->settings[ $config ] ) && $this->settings[ $config ] !== $common_option ) {
				$changed                   = true;
				$this->settings[ $config ] = $common_option;
			}
		}

		if ( $changed ) {
			/**
			 * Update if options were changed.
			 *
			 * @since 3.0.1
			 */
			update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
		}
	}

	public function get_activable_value( $option_key, $default ) {
		$active = $this->get_option( $option_key . '_checkbox', false );

		if ( $active && 'yes' === $active ) {
			return $this->get_option( $option_key, $default );
		}

		return $default;
	}

	/**
	 * Validate section
	 *
	 * @return bool
	 */
	public function validate_section() {

		if (
				// phpcs:ignore WordPress.Security.NonceVerification
				isset( $_GET['section'] ) && ! empty( $_GET['section']
				)
			&& (
				// phpcs:ignore WordPress.Security.NonceVerification
				$this->id !== $_GET['section'] ) && ! in_array( $_GET['section'], self::ALLOWED_CLASSES, true )
			) {
			return false;
		}

		return true;
	}

	/**
	 * Is manage section?
	 *
	 * @return bool
	 */
	public function is_manage_section() {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_GET['section'] ) || ( $this->id !== $_GET['section'] ) && ! in_array( $_GET['section'], self::ALLOWED_CLASSES, true )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Get Mercado Pago Icon
	 *
	 * @return mixed
	 */
	public function get_mp_icon() {
		/**
		 * Add Mercado Pago icon.
		 *
		 * @since 3.0.1
		 */
		return apply_filters( 'woocommerce_mercadopago_icon', plugins_url( '../assets/images/mercadopago.png', plugin_dir_path( __FILE__ ) ) );
	}

	/**
	 * Update Option
	 *
	 * @param string $key key.
	 * @param string $value value.
	 * @return bool
	 */
	public function update_option( $key, $value = '' ) {
		if ( 'enabled' === $key && 'yes' === $value ) {
			if ( empty( $this->mp->get_access_token() ) ) {
				$message = __( 'Configure your credentials to enable Mercado Pago payment methods.', 'woocommerce-mercadopago' );
				$this->log->write_log( __FUNCTION__, $message );
				echo wp_json_encode(
					array(
						'success' => false,
						'data'    => $message,
					)
				);
				die();
			}
		}
		return parent::update_option( $key, $value );
	}

	/**
	 * Get Mercado Pago form fields
	 *
	 * @return array
	 */
	public function get_form_mp_fields() {
		$this->init_form_fields();
		$this->init_settings();
		$form_fields = array();

		if ( ! empty( $this->checkout_country ) ) {
			if ( ! empty( $this->get_access_token() ) && ! empty( $this->get_public_key() ) ) {
				if ( 0 === $this->homolog_validate ) {
					$form_fields['checkout_card_homolog'] = $this->field_checkout_card_homolog();
				}
				$form_fields['enabled']                                = $this->field_enabled();
				$form_fields['title']                                  = $this->field_title();
				$form_fields['description']                            = $this->field_description();
				$form_fields['gateway_discount']                       = $this->field_gateway_discount();
				$form_fields['commission']                             = $this->field_commission();
				$form_fields['checkout_payments_advanced_description'] = $this->field_checkout_payments_advanced_description();
				$form_fields[ WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY ] = $this->field_currency_conversion( $this );
			}
		}

		if ( is_admin() && $this->is_manage_section() && ( WC_WooMercadoPago_Helper_Current_Url::validate_page('mercadopago-settings') || WC_WooMercadoPago_Helper_Current_Url::validate_section('woo-mercado-pago') ) ) {
			$this->load_custom_credentials_js();
		}

		if ( is_admin() && ( WC_WooMercadoPago_Helper_Current_Url::validate_page('mercadopago-settings') || WC_WooMercadoPago_Helper_Current_Url::validate_section('woo-mercado-pago') ) ) {
			$this->load_custom_js_for_checkbox();
			$this->normalize_common_admin_fields();
		}
		$form_fields['checkout_card_validate'] = $this->field_checkout_card_validate();
		return $form_fields;
	}

	/**
	 * Field title
	 *
	 * @return array
	 */
	public function field_title() {
		$field_title = array(
			'title'       => __( 'Title in the store Checkout', 'woocommerce-mercadopago' ),
			'type'        => 'text',
			'description' => __('Change the display text in Checkout, maximum characters: 85', 'woocommerce-mercadopago'),
			'maxlength'   => 100,
			'desc_tip'    => __( 'The text inserted here will not be translated to other languages', 'woocommerce-mercadopago' ),
			'class'       => 'limit-title-max-length',
			'default'     => $this->title,
		);
		return $field_title;
	}

	/**
	 * Field description
	 *
	 * @return array
	 */
	public function field_description() {
		$field_description = array(
			'title'       => __( 'Description', 'woocommerce-mercadopago' ),
			'type'        => 'text',
			'class'       => 'hidden-field-mp-desc',
			'description' => '',
			'default'     => $this->method_description,
		);
		return $field_description;
	}

	/**
	 * Sort form fields
	 *
	 * @param array $form_fields fields.
	 * @param array $ordination ordination.
	 *
	 * @return array
	 */
	public function sort_form_fields( $form_fields, $ordination ) {
		$array = array();
		foreach ( $ordination as $order => $key ) {
			if ( ! isset( $form_fields[ $key ] ) ) {
				continue;
			}
			$array[ $key ] = $form_fields[ $key ];
			unset( $form_fields[ $key ] );
		}
		return array_merge_recursive( $array, $form_fields );
	}

	/**
	 * Field checkout card validate
	 *
	 * @return array
	 */
	public function field_checkout_card_validate() {

		$value = array(
			'title'             => __('Important! To sell you must enter your credentials.', 'woocommerce-mercadopago'),
			'subtitle'          => __('You must enter&nbsp;<b>production credentials</b>.', 'woocommerce-mercadopago'),
			'button_text'       => __('Enter credentials', 'woocommerce-mercadopago'),
			'button_url'        => admin_url( 'admin.php?page=mercadopago-settings' ),
			'icon'              => 'mp-icon-badge-warning',
			'color_card'        => 'mp-alert-color-error',
			'size_card'         => 'mp-card-body-size',
			'target'            => '_self',
		);

		if ( ! empty( $this->checkout_country ) && ! empty( $this->get_access_token() ) && ! empty( $this->get_public_key() ) ) {
			$value = array(
				'title'             => __('Mercado Pago Plugin general settings', 'woocommerce-mercadopago'), __('Important! To sell you must enter your credentials.' , 'woocommerce-mercadopago'),
				'subtitle'          => __('Set the deadlines and fees, test your store or access the Plugin manual.', 'woocommerce-mercadopago'),
				'button_text'       => __('Go to Settings', 'woocommerce-mercadopago'),
				'button_url'        => admin_url( 'admin.php?page=mercadopago-settings' ),
				'icon'              => 'mp-icon-badge-info',
				'color_card'        => 'mp-alert-color-sucess',
				'size_card'         => 'mp-card-body-size',
				'target'            => '_self',
			);
		}

		return array(
			'type'               => 'mp_card_info',
			'value'              => $value,
		);
	}

	/**
	 * Field checkout card homolog
	 *
	 * @return array
	 */
	public function field_checkout_card_homolog() {
		$country_link   = strtolower($this->checkout_country);
		$application_id = $this->application_id;
		$value          = array(
			'title'             => __( 'Activate your credentials to be able to sell', 'woocommerce-mercadopago' ),
			'subtitle'          => __( 'Credentials are codes that you must enter to enable sales. Go below on Activate Credentials. On the next screen, use again the Activate Credentials button and fill in the fields with the requested information.', 'woocommerce-mercadopago' ),
			'button_text'       => __( 'Activate credentials', 'woocommerce-mercadopago' ),
			'button_url'        => 'https://www.mercadopago.com/' . $country_link . '/account/credentials/appliance?application_id=' . $application_id,
			'icon'              => 'mp-icon-badge-warning',
			'color_card'        => 'mp-alert-color-alert',
			'size_card'         => 'mp-card-body-size-homolog',
			'target'            => '_blank'
		);

		return array(
			'type'              => 'mp_card_info',
			'value'             => $value,
		);
	}


	/**
	 * Get Application Id
	 *
	 * @param string $mp_access_token_prod access token.
	 *
	 * @return mixed|string
	 * @throws WC_WooMercadoPago_Exception Application Id not found exception.
	 */
	public function get_application_id( $mp_access_token_prod ) {
		if ( empty( $mp_access_token_prod ) ) {
			return '';
		} else {
			$application_id = $this->mp_options->get_application_id();
			if ( $application_id && '' !== $application_id ) {
				return $application_id;
			}
			$application_id = $this->mp->get_credentials_wrapper( $this->mp_access_token_prod );
			if ( is_array( $application_id ) && isset( $application_id['client_id'] ) ) {
				update_option('mp_application_id', $application_id['client_id']);
				return $application_id['client_id'];
			}
			return '';
		}
	}

	/**
	 * Field enabled
	 *
	 * @return array
	 */
	public function field_enabled() {
		return array(
			'title'       => __( 'Enable the checkout', 'woocommerce-mercadopago' ),
			'subtitle'    => __( 'By disabling it, you will disable all payment methods of this checkout.', 'woocommerce-mercadopago' ),
			'type'        => 'mp_toggle_switch',
			'default'     => 'no',
			'descriptions' => array(
				'enabled' => __( 'The checkout is <b>enabled</b>.', 'woocommerce-mercadopago' ),
				'disabled' => __( 'The checkout is <b>disabled</b>.', 'woocommerce-mercadopago' ),
			),
		);
	}

	/**
	 * Generates the toggle switch template
	 *
	 * @param string $key key, $settings settings array
	 * @return string html toggle switch template
	 */
	public function generate_mp_toggle_switch_html( $key, $settings ) {
		return wc_get_template_html(
			'components/toggle-switch.php',
			array (
				'field_key' => $this->get_field_key( $key ),
				'field_value' => $this->get_option( $key, $settings['default'] ),
				'settings' => $settings,
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Generates tip information template
	 *
	 * @param string $key key, $settings settings array
	 * @return string html tip information template
	 */
	public function generate_mp_card_info_html( $key, $settings ) {
		return wc_get_template_html(
			'components/card-info.php',
			array (
				'settings' => $settings,
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Generates the toggle switch template
	 *
	 * @param string $key key, $settings settings array
	 * @return string html toggle switch template
	 */
	public function generate_mp_checkbox_list_html( $key, $settings ) {
		return wc_get_template_html(
			'components/checkbox-list.php',
			array (
				'settings' => $settings,
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Get sufix to static files
	 *
	 * @return String
	 */
	private function get_suffix() {
		return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	}

	/**
	 * Load Custom JS For Checkbox
	 *
	 * @return void
	 */
	private function load_custom_js_for_checkbox() {
		$suffix = $this->get_suffix();

		wp_enqueue_script(
			'woocommerce-mercadopago-components',
			plugins_url( '../assets/js/components_mercadopago' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION,
			true
		);
	}

	/**
	 * Load Custom JS For Checkbox
	 *
	 * @return void
	 */
	private function load_custom_credentials_js() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script(
			'woocommerce-mercadopago-credentials',
			plugins_url( '../assets/js/validate-credentials' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION,
			true
		);
	}

	/**
	 * Field Checkout Payments Subtitle
	 *
	 * @return array
	 */
	public function field_checkout_payments_subtitle() {
		return array(
			'title' => __( 'Basic Configuration', 'woocommerce-mercadopago' ),
			'type'  => 'title',
			'class' => 'mp_subtitle mp-mt-5 mp-mb-0',
		);
	}

	/**
	 * Field Coupon Mode
	 *
	 * @return array
	 */
	public function field_coupon_mode() {
		return array(
			'title'       => __( 'Discount coupons', 'woocommerce-mercadopago' ),
			'type'        => 'mp_toggle_switch',
			'default'     => 'no',
			'subtitle' => __( 'Will you offer discount coupons to customers who buy with Mercado Pago?', 'woocommerce-mercadopago' ),
			'descriptions' => array(
				'enabled' => __( 'Discount coupons is <b>active</b>.', 'woocommerce-mercadopago' ),
				'disabled' => __( 'Discount coupons is <b>disabled</b>.', 'woocommerce-mercadopago' ),
			),
		);
	}

	/**
	 * Field Binary Mode
	 *
	 * @return array
	 */
	public function field_binary_mode() {
		return array(
			'title'       => __( 'Automatic decline of payments without instant approval', 'woocommerce-mercadopago' ),
			'subtitle'    => __( 'Enable it if you want to automatically decline payments that are not instantly approved by banks or other institutions. ', 'woocommerce-mercadopago' ),
			'type'        => 'mp_toggle_switch',
			'default'     => 'no',
			'descriptions' => array(
				'enabled' => __( 'Pending payments <b>will be automatically declined</b>.', 'woocommerce-mercadopago' ),
				'disabled' => __( 'Pending payments <b>will not be automatically declined</b>.', 'woocommerce-mercadopago' ),
			),
		);
	}

	/**
	 * Field Gateway Discount
	 *
	 * @return array
	 */
	public function field_gateway_discount() {
		return array(
			'title'             => __( 'Discount in Mercado Pago Checkouts', 'woocommerce-mercadopago' ),
			'type'              => 'mp_activable_input',
			'input_type'        => 'number',
			'description'       => __( 'Choose a percentage value that you want to discount your customers for paying with Mercado Pago.', 'woocommerce-mercadopago' ),
			'checkbox_label'    => __( 'Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago' ),
			'default'           => '0',
			'custom_attributes' => array(
				'step' => '0.01',
				'min'  => '0',
				'max'  => '99',
			),
		);
	}

	/**
	 * Field Commission
	 *
	 * @return array
	 */
	public function field_commission() {
		return array(
			'title'             => __( 'Commission in Mercado Pago Checkouts', 'woocommerce-mercadopago' ),
			'type'              => 'mp_activable_input',
			'input_type'        => 'number',
			'description'       => __( 'Choose an additional percentage value that you want to charge as commission to your customers for paying with Mercado Pago.', 'woocommerce-mercadopago' ),
			'checkbox_label'    => __( 'Activate and show this information on Mercado Pago Checkout', 'woocommerce-mercadopago' ),
			'default'           => '0',
			'custom_attributes' => array(
				'step' => '0.01',
				'min'  => '0',
				'max'  => '99',
			),
		);
	}

	public function generate_mp_activable_input_html( $key, $settings ) {
		return wc_get_template_html(
			'components/activable-input.php',
			array (
				'field_key'          => $this->get_field_key( $key ),
				'field_key_checkbox' => $this->get_field_key( $key . '_checkbox' ),
				'value'              => $this->get_option( $key ),
				'enabled'            => $this->get_option( $key . '_checkbox' ),
				'custom_attributes'  => $this->get_custom_attribute_html( $settings ),
				'settings'           => $settings,
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Field Currency Conversion
	 *
	 * @param WC_WooMercadoPago_Payment_Abstract $method Payment abstract.
	 * @return array
	 */
	public function field_currency_conversion( WC_WooMercadoPago_Payment_Abstract $method ) {
		$description = WC_WooMercadoPago_Helpers_CurrencyConverter::get_instance()->get_description( $method );

		return array(
			'title'       => __( 'Convert Currency', 'woocommerce-mercadopago' ),
			'subtitle'    => $description,
			'type'        => 'mp_toggle_switch',
			'default'     => 'no',
			'descriptions' => array(
				'enabled' => __( 'Currency convertion is <b>enabled</b>.', 'woocommerce-mercadopago' ),
				'disabled' => __( 'Currency convertion is <b>disabled</b>.', 'woocommerce-mercadopago' ),
			),
		);
	}

	/**
	 * Is available?
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! did_action( 'wp_loaded' ) ) {
			return false;
		}
		global $woocommerce;
		$w_cart = $woocommerce->cart;
		// Check for recurrent product checkout.
		if ( isset( $w_cart ) ) {
			if ( WC_WooMercadoPago_Module::is_subscription( $w_cart->get_cart() ) ) {
				return false;
			}
		}

		$_mp_public_key   = $this->get_public_key();
		$_mp_access_token = $this->get_access_token();
		$_site_id_v1      = $this->mp_options->get_site_id();

		if ( ! isset( $this->settings['enabled'] ) ) {
			return false;
		}

		return ( 'yes' === $this->settings['enabled'] ) && ! empty( $_mp_public_key ) && ! empty( $_mp_access_token ) && ! empty( $_site_id_v1 );
	}

	/**
	 * Get Admin Url
	 *
	 * @return mixed
	 */
	public function admin_url() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $this->id );
		}
		return admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=' . get_class( $this ) );
	}

	/**
	 * Get common configs
	 *
	 * @return array
	 */
	public function get_common_configs() {
		return self::COMMON_CONFIGS;
	}

	/**
	 * Is test user?
	 *
	 * @return bool
	 */
	public function is_test_user() {
		if ( $this->is_production_mode() ) {
			return false;
		}
		return true;
	}

	/**
	 * Get Mercado Pago Instance
	 *
	 * @return false|MP|null
	 * @throws WC_WooMercadoPago_Exception Get mercado pago instance error.
	 */
	public function get_mp_instance() {
		$mp = WC_WooMercadoPago_Module::get_mp_instance_singleton( $this );
		if ( ! empty( $mp ) ) {
			$mp->sandbox_mode( $this->sandbox );
		}
		return $mp;
	}

	/**
	 * Disable Payments MP
	 */
	public function disable_all_payments_methods_mp() {
		foreach ( WC_WooMercadoPago_Constants::PAYMENT_GATEWAYS as $gateway ) {
			$key     = 'woocommerce_' . $gateway::get_id() . '_settings';
			$options = get_option( $key );

			if ( ! empty( $options ) ) {
				if ( isset( $options['checkbox_checkout_test_mode'] ) && 'no' === $options['checkbox_checkout_test_mode'] && ! empty( $this->mp_access_token_prod ) ) {
					continue;
				}

				if ( isset( $options['checkbox_checkout_test_mode'] ) && 'yes' === $options['checkbox_checkout_test_mode'] && ! empty( $this->mp_access_token_test ) ) {
					continue;
				}

				$options['enabled'] = 'no';

				/**
				 * Update if options were changed
				 *
				 * @since 3.0.1
				 */
				update_option( $key, apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $gateway::get_id(), $options ) );
			}
		}
	}

	/**
	 * Field Checkout Payments Advanced Description
	 *
	 * @return array
	 */
	public function field_checkout_payments_advanced_description() {
		return array(
			'title' => __( 'Edit these advanced fields only when you want to modify the preset values.', 'woocommerce-mercadopago' ),
			'type'  => 'title',
			'class' => 'mp_small_text mp-mt--12 mp-mb-18',
		);
	}

	/**
	 * Is currency convertable?
	 *
	 * @return bool
	 */
	public function is_currency_convertable() {
		return $this->currency_convertion;
	}

	/**
	 * Is production mode?
	 *
	 * @return bool
	 */
	public function is_production_mode() {
		return 'no' === get_option( WC_WooMercadoPago_Options::CHECKBOX_CHECKOUT_TEST_MODE, 'yes' );
	}

	/**
	 * Get Country Domain By MELI Acronym
	 *
	 * @return String
	 */
	public function get_country_domain_by_meli_acronym( $meliAcronym ) {
		$countries = array(
			'mla' => 'ar',
			'mlb' => 'br',
			'mlc' => 'cl',
			'mco' => 'co',
			'mlm' => 'mx',
			'mpe' => 'pe',
			'mlu' => 'uy',
		);

		return $countries[$meliAcronym];
	}

	/**
	 * Get Mercado Pago Devsite Page Link
	 *
	 * @param String $country Country Acronym
	 *
	 * @return String
	 */
	public function get_mp_devsite_link( $country ) {
		$country_links = [
			'mla' => 'https://www.mercadopago.com.ar/developers/es/docs/woocommerce/integration-test',
			'mlb' => 'https://www.mercadopago.com.br/developers/pt/docs/woocommerce/integration-test',
			'mlc' => 'https://www.mercadopago.cl/developers/es/docs/woocommerce/integration-test',
			'mco' => 'https://www.mercadopago.com.co/developers/es/docs/woocommerce/integration-test',
			'mlm' => 'https://www.mercadopago.com.mx/developers/es/docs/woocommerce/integration-test',
			'mpe' => 'https://www.mercadopago.com.pe/developers/es/docs/woocommerce/integration-test',
			'mlu' => 'https://www.mercadopago.com.uy/developers/es/docs/woocommerce/integration-test',
		];
		$link          = array_key_exists($country, $country_links) ? $country_links[$country] : $country_links['mla'];

		return $link;
	}

	/**
	 * Set Order to Status Pending when is a new attempt
	 *
	 * @param $order
	 */
	public function set_order_to_pending_on_retry( $order ) {
		if ( $order->get_status() === 'failed' ) {
			$order->set_status('pending');
			$order->save();
		}
	}

	/**
	 * Get Country Link to Mercado Pago
	 *
	 * @param string $checkout Checkout by country.
	 * @return string
	 */
	public static function get_country_link_mp_terms() {

		$country_link = [
			'mla' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.ar/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',  // Argentinian.
			],
			'mlb' => [
				'help'      => 'ajuda',
				'sufix_url' => 'com.br/',
				'translate' => 'pt',
				'term_conditition' => '/termos-e-politicas_194',   //Brasil
			],
			'mlc' => [
				'help'      => 'ayuda',
				'sufix_url' => 'cl/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Chile.
			],
			'mco' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.co/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Colombia.
			],
			'mlm' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.mx/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Mexico.
			],
			'mpe' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.pe/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Peru.
			],
			'mlu' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.uy/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Uruguay.
			],
		];

	$checkout_country = strtolower(get_option( 'checkout_country', '' ));
		return $country_link[ $checkout_country ];
	}

	/**
	 *
	 * Define terms and conditions link
	 *
	 * @return array
	 */
	public static function mp_define_terms_and_conditions() {
		$links_mp       = self::get_country_link_mp_terms();
		$link_prefix_mp = 'https://www.mercadopago.';

		return array (
			'link_terms_and_conditions' => $link_prefix_mp . $links_mp['sufix_url'] . $links_mp['help'] . $links_mp['term_conditition']
		);
	}

	/**
	 * Validate if installments is equal to zero
	 *
	 * @return int
	 */
	public function get_valid_installments( $installments ) {
		$installments = (int) $installments;

		if ( 0 === $installments ) {
			return 12;
		}

		return $installments;
	}

}
