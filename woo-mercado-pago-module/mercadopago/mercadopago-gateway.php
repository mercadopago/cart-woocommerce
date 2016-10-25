<?php
/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer - Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// This include Mercado Pago library SDK
require_once "sdk/lib/mercadopago.php";

// Extending from WooCommerce Payment Gateway class.
// This class implements Mercado Pago Basic checkout.
class WC_WooMercadoPago_Gateway extends WC_Payment_Gateway {

	// This array stores each banner image, depending on the country it belongs to or on
	// the type of checkout we use.
	private $banners_mercadopago_standard = array(
		"MLA" => 'MLA/standard_mla.jpg',
    "MLB" => 'MLB/standard_mlb.jpg',
    "MCO" => 'MCO/standard_mco.jpg',
    "MLC" => 'MLC/standard_mlc.gif',
		"MPE" => 'MPE/standard_mpe.png',
    "MLV" => 'MLV/standard_mlv.jpg',
    "MLM" => 'MLM/standard_mlm.jpg'
  );

  // Sponsor ID array by country
  private $sponsor_id = array(
		"MLA" => '208682286',
		"MLB" => '208686191',
		"MCO" => '208687643',
		"MLC" => '208690789',
		"MPE" => '216998692',
		"MLV" => '208692735',
  	"MLM" => '208692380'
	);

	// Required inherited method from WC_Payment_Gateway class: __construct.
	// Please check:
	//    [https://docs.woothemes.com/wc-apidocs/class-WC_Payment_Gateway.html]
	// for documentation and further information.
	public function __construct() {

		// These fields are declared because we use them dinamically in our gateway class.
		$this->mp = null;
		$this->domain = get_site_url() . '/index.php';
		$this->currency_ratio = -1;
		$this->site_id = null;
		$this->isTestUser = false;
		$this->payment_methods = array();
		$this->store_categories_id = array();
  	$this->store_categories_description = array();

		// Within your constructor, you should define the following variables.
		$this->id = 'woocommerce-mercadopago-module';
		$this->icon = apply_filters(
			'woocommerce_mercadopago_icon',
			plugins_url( 'images/mercadopago.png', plugin_dir_path( __FILE__ ) )
		);
		$this->method_title = __( 'Mercado Pago - Basic Checkout', 'woocommerce-mercadopago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url( 'images/mplogo.png', plugin_dir_path( __FILE__ ) ) . '"><br><br>' . '<strong>' .
			wordwrap( __( 'This module enables WooCommerce to use Mercado Pago as payment method for purchases made in your virtual store.', 'woocommerce-mercadopago-module' ), 80, "\n" ) .
			'</strong>';

		// These fields are used in our Mercado Pago Module configuration page.
		$this->client_id = $this->get_option( 'client_id' );
		$this->client_secret = $this->get_option( 'client_secret' );
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->category_id = $this->get_option( 'category_id' );
		$this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->method = $this->get_option( 'method', 'modal' );
		$this->iframe_width = $this->get_option( 'iframe_width', 640 );
		$this->iframe_height = $this->get_option( 'iframe_height', 800 );
		$this->auto_return = $this->get_option( 'auto_return', true );
		$this->currency_conversion = $this->get_option('currency_conversion', false);
		$this->installments = $this->get_option( 'installments', '24' );
		$this->ex_payments = $this->get_option( 'ex_payments', 'n/d' );
		$this->sandbox = $this->get_option('sandbox', false);
		$this->debug = $this->get_option('debug');

		// Render our configuration page and init/load fields.
		$this->init_form_fields();
		$this->init_settings();

		// Hook actions for WordPress.
		add_action( // Used by IPN to receive IPN incomings.
			'woocommerce_api_wc_woomercadopago_gateway',
			array( $this, 'check_ipn_response' )
		);
		add_action( // Used by IPN to process valid incomings.
			'valid_mercadopago_ipn_request',
			array( $this, 'successful_request' )
		);
		add_action( // Used by WordPress to render the custom checkout page.
			'woocommerce_receipt_' . $this->id,
			array( $this, 'receipt_page' )
		);
		add_action( // Used to fix CSS in some older WordPress/WooCommerce versions.
			'wp_head',
			array( $this, 'css' )
		);
		add_action( // Used in settings page to hook "save settings" action.
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);

		// Verify if client_id or client_secret is empty.
		if ( empty( $this->client_id ) || empty( $this->client_secret ) ) {
			add_action( 'admin_notices', array( $this, 'clientIdOrSecretMissingMessage' ) );
		}

		// Logging and debug.
		if ( 'yes' == $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = WC_MercadoPago_Module::woocommerceInstance()->logger();
			}
		}

	}

	// Required inherited method from WC_Payment_Gateway class: init_form_fields.
	// Initialise Gateway settings form fields with a customized page.
	public function init_form_fields() {

		if ( 'yes' == $this->settings[ 'enabled' ] ) {
			$api_secret_locale = sprintf(
				'<a href="https://www.mercadopago.com/mla/account/credentials?type=basic" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mlb/account/credentials?type=basic" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mlc/account/credentials?type=basic" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mco/account/credentials?type=basic" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mlm/account/credentials?type=basic" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mpe/account/credentials?type=basic" target="_blank">%s</a> %s ' .
				'<a href="https://www.mercadopago.com/mlv/account/credentials?type=basic" target="_blank">%s</a>',
				__( 'Argentine', 'woocommerce-mercadopago-module' ),
				__( 'Brazil', 'woocommerce-mercadopago-module' ),
				__( 'Chile', 'woocommerce-mercadopago-module' ),
				__( 'Colombia', 'woocommerce-mercadopago-module' ),
				__( 'Mexico', 'woocommerce-mercadopago-module' ),
				__( 'Peru', 'woocommerce-mercadopago-module' ),
				__( 'or', 'woocommerce-mercadopago-module' ),
				__( 'Venezuela', 'woocommerce-mercadopago-module' )
			);

			// Trigger API to get payment methods and site_id, also validates Client_id/Client_secret.
			if ( $this->validateCredentials() ) {
				try {
					// checking the currency
					$this->currency_message = "";
					if ( !$this->isSupportedCurrency() && 'yes' == $this->settings[ 'enabled' ] ) {
						if ( $this->currency_conversion == 'no' ) {
							$this->currency_ratio = -1;
							$this->currency_message .= '<img width="12" height="12" src="' .
								plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' .
								' ' . __( 'ATTENTION: The currency', 'woocommerce-mercadopago-module' ) . ' ' . get_woocommerce_currency() .
								' ' . __( 'defined in WooCommerce is different from the one used in your credentials country.<br>The currency for transactions in this payment method will be', 'woocommerce-mercadopago-module' ) .
								' ' . $this->getCurrencyId( $this->site_id ) . ' (' . $this->getCountryName( $this->site_id ) . ').' .
								' ' . __( 'Currency conversions should be made outside this module.', 'woocommerce-mercadopago-module' );
						} else if ( $this->currency_conversion == 'yes' && $this->currency_ratio != -1 ) {
							$this->currency_message .= '<img width="12" height="12" src="' .
								plugins_url( 'images/check.png', plugin_dir_path( __FILE__ ) ) . '">' .
								' ' . __( 'CURRENCY CONVERTED: The currency conversion ratio from', 'woocommerce-mercadopago-module' )  . ' ' . get_woocommerce_currency() .
								' ' . __( 'to', 'woocommerce-mercadopago-module' ) . ' ' . $this->getCurrencyId( $this->site_id ) . __( ' is: ', 'woocommerce-mercadopago-module' ) . $this->currency_ratio . ".";
						} else {
							$this->currency_ratio = -1;
							$this->currency_message .= '<img width="12" height="12" src="' .
								plugins_url( 'images/error.png', plugin_dir_path( __FILE__ ) ) . '">' .
								' ' . __( 'ERROR: It was not possible to convert the unsupported currency', 'woocommerce-mercadopago-module' ) . ' ' . get_woocommerce_currency() .
								' '	. __( 'to', 'woocommerce-mercadopago-module' ) . ' ' . $this->getCurrencyId( $this->site_id ) . '.' .
								' ' . __( 'Currency conversions should be made outside this module.', 'woocommerce-mercadopago-module' );
						}
					} else {
						$this->currency_ratio = -1;
					}
					$this->credentials_message = '<img width="12" height="12" src="' .
						plugins_url( 'images/check.png', plugin_dir_path( __FILE__ ) ) . '">' .
						' ' . __( 'Your credentials are <strong>valid</strong> for', 'woocommerce-mercadopago-module' ) .
						': ' . $this->getCountryName( $this->site_id ) . ' <img width="18.6" height="12" src="' .
						plugins_url( 'images/' . $this->site_id . '/' . $this->site_id . '.png', plugin_dir_path( __FILE__ ) ) . '"> ';
					$this->payment_desc =
						__( 'Select the payment methods that you <strong>don\'t</strong> want to receive with Mercado Pago.', 'woocommerce-mercadopago-module' );
				} catch ( MercadoPagoException $e ) {
					array_push( $this->payment_methods, "n/d" );
					$this->payment_desc = '<img width="12" height="12" src="' .
						plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' .
						' ' . __( 'Configure your Client_id and Client_secret to have access to more options.', 'woocommerce-mercadopago-module' );
					$this->credentials_message = '<img width="12" height="12" src="' .
						plugins_url( 'images/error.png', plugin_dir_path( __FILE__ ) ) . '">' .
						' ' . __( 'Your credentials are <strong>not valid</strong>!', 'woocommerce-mercadopago-module' );
				}
			} else {
				array_push( $this->payment_methods, "n/d" );
				$this->payment_desc = '<img width="12" height="12" src="' .
					plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' .
					' ' . __( 'Configure your Client_id and Client_secret to have access to more options.', 'woocommerce-mercadopago-module' );
				$this->credentials_message = '<img width="12" height="12" src="' .
					plugins_url( 'images/error.png', plugin_dir_path( __FILE__ ) ) . '">' .
					' ' . __( 'Your credentials are <strong>not valid</strong>!', 'woocommerce-mercadopago-module' );
			}

			// Fills categoy selector. We do not need credentials to make this call.
			$categories = MPRestClient::get( array( "uri" => "/item_categories" ) );
			foreach ( $categories[ "response" ] as $category ) {
				array_push( $this->store_categories_id, str_replace( "_", " ", $category[ 'id' ] ) );
				array_push( $this->store_categories_description, str_replace( "_", " ", $category[ 'description' ] ) );
			}

			// Checks validity of iFrame width/height fields.
			if ( !is_numeric( $this->iframe_width ) ) {
				$this->iframe_width_desc = '<img width="12" height="12" src="' .
					plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' .
					' ' . __( 'This field should be an integer.', 'woocommerce-mercadopago-module' );
			} else {
				$this->iframe_width_desc =
					__( 'If your integration method is iFrame, please inform the payment iFrame width.', 'woocommerce-mercadopago-module' );
			}
			if ( !is_numeric( $this->iframe_height ) ) {
				$this->iframe_height_desc = '<img width="12" height="12" src="' .
					plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' .
					' ' . __( 'This field should be an integer.', 'woocommerce-mercadopago-module' );
			} else {
				$this->iframe_height_desc =
					__( 'If your integration method is iFrame, please inform the payment iFrame height.', 'woocommerce-mercadopago-module' );
			}

			// Checks if max installments is a number.
			if ( !is_numeric( $this->installments ) ) {
				$this->installments_desc = '<img width="12" height="12" src="' .
					plugins_url( 'images/warning.png', plugin_dir_path( __FILE__ ) ) . '">' .
					' ' . __( 'This field should be an integer.', 'woocommerce-mercadopago-module' );
			} else {
				$this->installments_desc =
					__( 'Select the max number of installments for your customers.', 'woocommerce-mercadopago-module' );
			}
		}

		if ( 'yes' == $this->settings[ 'enabled' ] ) {
			// This array draws each UI (text, selector, checkbox, label, etc).
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Basic Checkout', 'woocommerce-mercadopago-module' ),
					'default' => 'no'
				),
				'credentials_title' => array(
					'title' => __( 'Mercado Pago Credentials', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => sprintf( '%s', $this->credentials_message ) . '<br>' . sprintf( __( 'You can obtain your credentials for', 'woocommerce-mercadopago-module' ) . ' %s.', $api_secret_locale )
				),
				'client_id' => array(
					'title' => 'Client_id',
					'type' => 'text',
					'description' => __( 'Insert your Mercado Pago Client_id.', 'woocommerce-mercadopago-module' ),
					'default' => '',
					'required' => true
				),
				'client_secret' => array(
					'title' => 'Client_secret',
					'type' => 'text',
					'description' => __( 'Insert your Mercado Pago Client_secret.', 'woocommerce-mercadopago-module' ),
					'default' => '',
					'required' => true
				),
				'ipn_url' => array(
					'title' => __( 'Instant Payment Notification (IPN) URL', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => sprintf( __( 'Your IPN URL to receive instant payment notifications is', 'woocommerce-mercadopago-module' ) . '<br>%s', '<code>' . $this->domain . '/' . $this->id . '/?wc-api=WC_WooMercadoPago_Gateway' . '</code>.' )
				),
				'checkout_options_title' => array(
					'title' => __( 'Checkout Options', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => ''
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce-mercadopago-module' ),
					'type' => 'text',
					'description' => __( 'Title shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
					'default' => __( 'Mercado Pago', 'woocommerce-mercadopago-module' )
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce-mercadopago-module' ),
					'type' => 'textarea',
					'description' => __( 'Description shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
					'default' => __( 'Pay with Mercado Pago', 'woocommerce-mercadopago-module' )
				),
				'category_id' => array(
					'title' => __( 'Store Category', 'woocommerce-mercadopago-module' ),
					'type' => 'select',
					'description' => __( 'Define which type of products your store sells.', 'woocommerce-mercadopago-module' ),
					'options' => $this->store_categories_id
				),
				'invoice_prefix' => array(
					'title' => __( 'Store Identificator', 'woocommerce-mercadopago-module' ),
					'type' => 'text',
					'description' => __( 'Please, inform a prefix to your store.', 'woocommerce-mercadopago-module' ) . ' ' . __( 'If you use your Mercado Pago account on multiple stores you should make sure that this prefix is unique as Mercado Pago will not allow orders with same identificators.', 'woocommerce-mercadopago-module' ),
					'default' => 'WC-'
				),
				'method' => array(
					'title' => __( 'Integration Method', 'woocommerce-mercadopago-module' ),
					'type' => 'select',
					'description' => __( 'Select how your clients should interact with Mercado Pago. Modal Window (inside your store), Redirect (Client is redirected to Mercado Pago), or iFrame (an internal window is embedded to the page layout).', 'woocommerce-mercadopago-module' ),
					'default' => 'iframe',
					'options' => array(
						'iframe' => __( 'iFrame', 'woocommerce-mercadopago-module' ),
						'modal' => __( 'Modal Window', 'woocommerce-mercadopago-module' ),
						'redirect' => __( 'Redirect', 'woocommerce-mercadopago-module' )
					)
				),
				'iframe_width' => array(
					'title' => __( 'iFrame Width', 'woocommerce-mercadopago-module' ),
					'type' => 'text',
					'description' => $this->iframe_width_desc,
					'default' => '640'
				),
				'iframe_height' => array(
					'title' => __( 'iFrame Height', 'woocommerce-mercadopago-module' ),
					'type' => 'text',
					'description' => $this->iframe_height_desc,
					'default' => '800'
				),
				'auto_return' => array(
					'title' => __( 'Auto Return', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'Automatic Return After Payment', 'woocommerce-mercadopago-module' ),
					'default' => 'yes',
					'description' => __( 'After the payment, client is automatically redirected.', 'woocommerce-mercadopago-module' ),
				),
				'payment_title' => array(
					'title' => __( 'Payment Options', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => ''
				),
				'currency_conversion' => array(
					'title' => __( 'Currency Conversion', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'If the used currency in WooCommerce is different or not supported by Mercado Pago, convert values of your transactions using Mercado Pago currency ratio', 'woocommerce-mercadopago-module' ),
					'default' => 'no',
					'description' => sprintf( '%s', $this->currency_message )
				),
				'installments' => array(
					'title' => __( 'Max installments', 'woocommerce-mercadopago-module' ),
					'type' => 'text',
					'description' => $this->installments_desc,
					'default' => '24'
				),
				'ex_payments' => array(
		      'title' => __( 'Exclude Payment Methods', 'woocommerce-mercadopago-module' ),
		      'description' => $this->payment_desc,
		      'type' => 'multiselect',
		      'options' => $this->payment_methods,
		      'default' => ''
	      ),
				'testing' => array(
					'title' => __( 'Test and Debug Options', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => ''
				),
				'sandbox' => array(
					'title' => __( 'Mercado Pago Sandbox', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Mercado Pago Sandbox', 'woocommerce-mercadopago-module' ),
					'default' => 'no',
					'description' => __( 'This option allows you to test payments inside a sandbox environment.', 'woocommerce-mercadopago-module' ),
				),
				'debug' => array(
					'title' => __( 'Debug and Log', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'Enable log', 'woocommerce-mercadopago-module' ),
					'default' => 'no',
					'description' => sprintf( __( 'Register event logs of Mercado Pago, such as API requests, in the file', 'woocommerce-mercadopago-module' ) .
						' %s.', $this->buildLogPathString() . '.<br>' . __( 'File location: ', 'woocommerce-mercadopago-module' ) .
						'<code>wordpress/wp-content/uploads/wc-logs/' . $this->id . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log</code>')
				)
			);
		} else {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Basic Checkout', 'woocommerce-mercadopago-module' ),
					'default' => 'no'
				)
			);
		}

	}

	public function admin_options() {
		$this->validate_settings_fields();
		if ( count( $this->errors ) > 0 ) {
			$this->display_errors();
			return false;
		} else {
			echo wpautop( $this->method_description );
			?>
				<p><a href="https://wordpress.org/support/view/plugin-reviews/woo-mercado-pago-module?filter=5#postform" target="_blank" class="button button-primary">
					<?php esc_html_e( sprintf( __( 'Please, rate us %s on WordPress.org and give your feedback to help improve this module!', 'woocommerce-mercadopago-module' ), '&#9733;&#9733;&#9733;&#9733;&#9733;' ) ); ?>
				</a></p>
				<table class="form-table">
					<?php $this->generate_settings_html(); ?>
				</table>
			<?php
			return true;
		}
	}

	/*
	 * ========================================================================
	 * CHECKOUT BUSINESS RULES
	 * ========================================================================
	 */

	public function payment_fields() {
		// basic checkout
		if ($description = $this->get_description() ) {
    	echo wpautop( wptexturize( $description ) );
  	}
    if ( $this->supports( 'default_credit_card_form' ) ) {
     	$this->credit_card_form();
    }
	}

	// 1. First step occurs when the customer selects Mercado Pago and proceed to
	// checkout. This method verify which integration method was selected and
	// makes the build for the checkout URL.
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );
		// Check for the type of integration.
		if ( 'redirect' == $this->method ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, $this->id . ': @[process_payment] - customer being redirected to Mercado Pago environment.' );
			}
			return array(
				'result' => 'success',
				'redirect' => $this->createUrl( $order )
			);
		} else if ( 'modal' == $this->method || 'iframe' == $this->method ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, $this->id . ': @[process_payment] - preparing to render Mercado Pago checkout view.' );
			}
			if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
				return array(
					'result' => 'success',
					'redirect' => $order->get_checkout_payment_url( true )
				);
			} else {
				return array(
					'result' => 'success',
					'redirect' => add_query_arg(
						'order', $order->id, add_query_arg(
							'key',
							$order->order_key,
							get_permalink( woocommerce_get_page_id( 'pay' ) )
						)
					)
				);
			}
		}
	}

	// 2. Order page and this generates the form that shows the pay button. This step
	// generates the form to proceed to checkout.
	public function receipt_page( $order ) {
		echo $this->renderOrderForm( $order );
	}
	// --------------------------------------------------
	public function renderOrderForm( $order_id ) {
		$order = new WC_Order( $order_id );
		$url = $this->createUrl( $order );
		if ( $url ) {
			// Display checkout.
			$html =
				'<img width="468" height="60" src="' .
				plugins_url( 'images/' . $this->banners_mercadopago_standard[ $this->site_id ], plugin_dir_path( __FILE__ ) ) . '">';
			if ( 'iframe' != $this->method ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, $this->id . ': @[renderOrderForm] - rendering Mercado Pago lightbox (modal window).' );
				}
				$html .= '<p></p><p>' . wordwrap(
					__( 'Thank you for your order. Please, proceed with your payment clicking in the bellow button.', 'woocommerce-mercadopago-module' ),
					60, '<br>') . '</p>';
				$html .=
					'<a id="submit-payment" href="' . $url . '" name="MP-Checkout" class="button alt" mp-mode="modal">' .
					__( 'Pay with Mercado Pago', 'woocommerce-mercadopago-module' ) .
					'</a> ';
				$html .=
					'<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' .
					__( 'Cancel order &amp; Clear cart', 'woocommerce-mercadopago-module' ) .
					'</a><style type="text/css">#MP-Checkout-dialog #MP-Checkout-IFrame { bottom: -28px !important;  height: 590px !important; }</style>';
				// Includes javascript.
				$html .=
					'<script type="text/javascript">(function(){function $MPBR_load(){window.$MPBR_loaded !== true && (function(){var s = document.createElement("script");s.type = "text/javascript";s.async = true;s.src = ("https:"==document.location.protocol?"https://www.mercadopago.com/org-img/jsapi/mptools/buttons/":"https://mp-tools.mlstatic.com/buttons/")+"render.js";var x = document.getElementsByTagName("script")[0];x.parentNode.insertBefore(s, x);window.$MPBR_loaded = true;})();}window.$MPBR_loaded !== true ? (window.attachEvent ? window.attachEvent("onload", $MPBR_load) : window.addEventListener("load", $MPBR_load, false)) : null;})();</script>';
			} else {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, $this->id . ': @[renderOrderForm] - embedding Mercado Pago iFrame.' );
				}
				$html .= '<p></p><p>' . wordwrap(
					__( 'Thank you for your order. Proceed with your payment completing the following information.', 'woocommerce-mercadopago-module' ),
					60, '<br>' ) . '</p>';
				$html .=
					'<iframe src="' . $url . '" name="MP-Checkout" ' .
					'width="' . ( is_numeric( (int) $this->iframe_width ) ? $this->iframe_width : 640 ) . '" ' .
					'height="' . ( is_numeric( (int) $this->iframe_height ) ? $this->iframe_height : 800 ) . '" ' .
					'frameborder="0" scrolling="no" id="checkout_mercadopago"></iframe>';
			}
			return $html;
		} else {
			$html =
				'<p>' . __( 'An error occurred when proccessing your payment. Please try again or contact us for assistence.', 'woocommerce-mercadopago-module' ) . '</p>';
			$html .=
				'<a class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' .
				__( 'Click to try again', 'woocommerce-mercadopago-module' ) .
				'</a>';
			return $html;
		}
	}

	// 3. Create Mercado Pago preference and get init_point URL based in the
	// order options from the cart.
	public function buildPaymentPreference( $order ) {

		// Using a string to register each item (this is a workaround to deal with API problem that shows only first item)
		$list_of_items = array();
		// Here we build the array that contains ordered itens, from customer cart
		$items = array();
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item['qty'] ) {
					$product = new WC_product( $item[ 'product_id' ] );
					array_push( $list_of_items, $product->post->post_title . ' x ' . $item[ 'qty' ] );
					array_push( $items, array(
						'id' => $item[ 'product_id' ],
						'title' => ( $product->post->post_title . ' x ' . $item[ 'qty' ] ),
						'description' => sanitize_file_name( (
							// This handles description width limit of Mercado Pago.
							strlen( $product->post->post_content ) > 230 ?
							substr( $product->post->post_content, 0, 230 ) . "..." :
							$product->post->post_content
						) ),
						'picture_url' => wp_get_attachment_url( $product->get_image_id() ),
						'category_id' => $this->store_categories_id[ $this->category_id ],
						'quantity' => 1,
						'unit_price' => ( ( (float) $item[ 'line_total' ] + (float) $item[ 'line_tax' ] ) ) *
							( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ),
						'currency_id' => $this->getCurrencyId($this->site_id)
					));
				}
			}
			$ship_cost = ((float)$order->get_total_shipping() + (float)$order->get_shipping_tax()) *
				( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 );
			if ( $ship_cost > 0 ) {
				array_push( $list_of_items, __( 'Shipping service used by store', 'woocommerce-mercadopago-module' ) );
				// shipment cost as an item (workaround to prevent API showing shipment setup again)
				array_push($items, array(
					'title' => $this->workaroundAmperSandBug( sanitize_file_name( $order->get_shipping_to_display() ) ),
					'description' => __( 'Shipping service used by store', 'woocommerce-mercadopago-module' ),
					'category_id' => $this->store_categories_id[$this->category_id],
					'quantity' => 1,
					'unit_price' => $ship_cost,
					'currency_id' => $this->getCurrencyId( $this->site_id )
				));
			}
			// Using a string to register each item (this is a workaround to deal with API problem that shows only first item)
			$items[0]['title'] = implode( ', ', $list_of_items );
		}

		// Find excluded payment methods. If 'n/d' is in array index, we should
		// disconsider the remaining values.
    $excluded_payment_methods = array();
    if ( is_array( $this->ex_payments ) || is_object( $this->ex_payments ) ) {
	    try { // in some PHP versions, $this->ex_payments is interpreted as a not iterable object
	      foreach ( $this->ex_payments as $excluded ) {
					if ( $excluded == 0 ) // if "n/d" is selected, we just not add any items to the array
						break;
	  			array_push( $excluded_payment_methods, array(
	  				"id" => $this->payment_methods[ $excluded ]
		    	));
	    	}
	  	} catch ( MercadoPagoException $e ) {
	  		if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id, $this->id .
						': @[DEBUG] - excluded payments: exception caught: ' .
						json_encode( array( "status" => $e->getCode(), "message" => $e->getMessage() ) )
					);
				}
	    }
  	}
    $payment_methods = array(
      'installments' => ( is_numeric( (int) $this->installments) ? (int) $this->installments : 24 ),
      'default_installments' => 1
    );
    // Set excluded payment methods.
    if ( count( $excluded_payment_methods ) > 0 ) {
    	$payment_methods[ 'excluded_payment_methods' ] = $excluded_payment_methods;
    }

    // Create Mercado Pago preference.
		$preferences = array(
			'items' => $items,
			// Payer should be filled with billing info because orders can be made with non-logged customers.
			'payer' => array(
				'name' => $order->billing_first_name,
				'surname' => $order->billing_last_name,
				'email' => $order->billing_email,
				'phone'	=> array(
					'number' => $order->billing_phone
				),
				'address' => array(
					'street_name' => $order->billing_address_1 . ' / ' .
						$order->billing_city . ' ' .
						$order->billing_state . ' ' .
						$order->billing_country,
					'zip_code' => $order->billing_postcode
				)
			),
			'back_urls' => array(
				'success' => $this->workaroundAmperSandBug( esc_url( $this->get_return_url( $order ) ) ),
				'failure' => $this->workaroundAmperSandBug( str_replace( '&amp;', '&', $order->get_cancel_order_url() ) ),
				'pending' => $this->workaroundAmperSandBug( esc_url( $this->get_return_url( $order ) ) )
			),
			//'marketplace' =>
      //'marketplace_fee' =>
      'shipments' => array(
	    	//'cost' => (float) $order->get_total_shipping(),
	    	//'mode' =>
      	'receiver_address' => array(
      		'zip_code' => $order->shipping_postcode,
      		//'street_number' =>
      		'street_name' => $order->shipping_address_1 . ' ' .
      			$order->shipping_city . ' ' .
      			$order->shipping_state . ' ' .
      			$order->shipping_country,
      		//'floor' =>
      		'apartment' => $order->shipping_address_2
      	)
      ),
			'payment_methods' => $payment_methods,
			//'notification_url' => $this->domain . '/' . $this->id . '/?wc-api=WC_WooMercadoPago_Gateway',
			'external_reference' => $this->invoice_prefix . $order->id
			//'additional_info' => $order->customer_message
      //'expires' =>
      //'expiration_date_from' =>
      //'expiration_date_to' =>
		);
		// Do not set IPN url if it is a localhost!
    $notification_url = $this->domain . '/woocommerce-mercadopago-module/?wc-api=WC_WooMercadoPago_Gateway';
    if ( !strrpos( $notification_url, "localhost" ) ) {
        $preferences['notification_url'] = $this->workaroundAmperSandBug( $notification_url );
    }
		// Set sponsor ID
		if ( !$this->isTestUser ) {
			$preferences[ 'sponsor_id' ] = (int) ( $this->sponsor_id[ $this->site_id ] );
		}
		// Auto return options.
		if ( 'yes' == $this->auto_return ) {
			$preferences[ 'auto_return' ] = "approved";
		}
		if ( 'yes' == $this->debug ) {
			$this->log->add(
				$this->id, $this->id .
				': @[buildPaymentPreference] - requesting mercado pago preference creation with following structure: ' .
				json_encode( $preferences, JSON_PRETTY_PRINT ) );
		}
		$preferences = apply_filters( 'woocommerce_mercadopago_module_preferences', $preferences, $order );
		return $preferences;
	}
	// --------------------------------------------------
	protected function createUrl( $order ) {
		// Creates the order parameters by checking the cart configuration.
		$preferences = $this->buildPaymentPreference( $order );
		// Checks for sandbox mode.
		if ( 'yes' == $this->sandbox ) {
			$this->mp->sandbox_mode( true );
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, $this->id . ': @[createUrl] - sandbox mode is enabled' );
			}
		} else {
			$this->mp->sandbox_mode( false );
		}
		// Create order preferences with Mercado Pago API request.
		try {
			$checkout_info = $this->mp->create_preference( json_encode( $preferences ) );
			if ( is_wp_error( $checkout_info ) || $checkout_info[ 'status' ] < 200 || $checkout_info[ 'status' ] >= 300 ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, $this->id . ': @[createUrl] - payment creation failed with error: ' . $checkout_info[ 'response' ][ 'status' ] );
				}
				return false;
			} else {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, $this->id .
						': @[createUrl] - payment link generated with success from mercado pago, with structure as follow: ' .
						json_encode( $checkout_info, JSON_PRETTY_PRINT ) );
				}
				if ( 'yes' == $this->sandbox ) {
					return $checkout_info[ 'response' ][ 'sandbox_init_point' ];
				} else {
					return $checkout_info[ 'response' ][ 'init_point' ];
				}
			}
		} catch ( MercadoPagoException $e ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id, $this->id .
					': @[createUrl] - payment creation failed with exception: ' .
					json_encode( array( "status" => $e->getCode(), "message" => $e->getMessage() ) ) );
			}
			return false;
		}
	}

	/*
	 * ========================================================================
	 * AUXILIARY AND FEEDBACK METHODS
	 * ========================================================================
	 */

	// Fix to URL Problem : #038; replaces & and breaks the navigation
	function workaroundAmperSandBug( $link ) {
		return str_replace('&#038;', '&', $link);
	}

	// Check if we have valid credentials.
	public function validateCredentials() {
		if ( empty( $this->client_id ) ) return false;
		if ( empty( $this->client_secret ) ) return false;
		if ( strlen( $this->client_id ) > 0 && strlen( $this->client_secret ) > 0 ) {
			try {
				$this->mp = new MP( $this->client_id, $this->client_secret );
				$access_token = $this->mp->get_access_token();
				$get_request = $this->mp->get( "/users/me?access_token=" . $access_token );
				if ( isset( $get_request[ 'response' ][ 'site_id' ] ) ) {
					$this->isTestUser = in_array( 'test_user', $get_request[ 'response' ][ 'tags' ] );
					$this->site_id = $get_request[ 'response' ][ 'site_id' ];
					$payments = $this->mp->get( "/v1/payment_methods/?access_token=" . $access_token );
					array_push( $this->payment_methods, "n/d" );
					foreach ( $payments[ "response" ] as $payment ) {
						array_push( $this->payment_methods, str_replace( "_", " ", $payment[ 'id' ] ) );
					}
					// check for auto converstion of currency (only if it is enabled)
					$this->currency_ratio = -1;
					if ( $this->currency_conversion == "yes" ) {
						$currency_obj = MPRestClient::get_ml( array( "uri" =>
							"/currency_conversions/search?from=" .
							get_woocommerce_currency() .
							"&to=" .
							$this->getCurrencyId( $this->site_id )
						) );
						if ( isset( $currency_obj[ 'response' ] ) ) {
							$currency_obj = $currency_obj[ 'response' ];
							if ( isset( $currency_obj['ratio'] ) ) {
								$this->currency_ratio = (float) $currency_obj['ratio'];
							} else {
								$this->currency_ratio = -1;
							}
						} else {
							$this->currency_ratio = -1;
						}
					}
					return true;
				} else return false;
			} catch ( MercadoPagoException $e ) {
				return false;
			}
		}
		return false;
	}

	// Build the string representing the path to the log file
	protected function buildLogPathString() {
		return '<a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' .
			esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' .
			__( 'WooCommerce &gt; System Status &gt; Logs', 'woocommerce-mercadopago-module' ) . '</a>';
	}

	// Return boolean indicating if currency is supported.
	protected function isSupportedCurrency() {
		return get_woocommerce_currency() == $this->getCurrencyId( $this->site_id );
	}

	// Get currency id for a country
	protected function getCurrencyId( $site_id ) {
		switch ( $site_id ) {
			case 'MLA': return 'ARS';
			case 'MLB': return 'BRL';
			case 'MCO': return 'COP';
			case 'MLC': return 'CLP';
			case 'MLM': return 'MXN';
			case 'MLV': return 'VEF';
			case 'MPE': return 'PEN';
			default: return '';
		}
	}

	// Called automatically by WooCommerce, verify if Module is available to use.
	public function is_available() {
		// Test if is valid for use.
		$available = ( 'yes' == $this->settings[ 'enabled' ] ) &&
			! empty( $this->client_id ) &&
			! empty( $this->client_secret );
		return $available;
	}

	// Fix css for Mercado Pago in specific cases.
	public function css() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			$page_id = wc_get_page_id( 'checkout' );
		} else {
			$page_id = woocommerce_get_page_id( 'checkout' );
		}
		if ( is_page($page_id ) ) {
			echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>' . PHP_EOL;
		}
	}

	// Get the URL to admin page.
	protected function admin_url() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			return admin_url(
				'admin.php?page=wc-settings&tab=checkout&section=wc_woomercadopago_gateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPago_Gateway'
		);
	}

	// Notify that Client_id and/or Client_secret are not valid.
	public function clientIdOrSecretMissingMessage() {
		echo '<div class="error"><p><strong>' .
			__( 'Basic Checkout is Inactive', 'woocommerce-mercadopago-module' ) .
			'</strong>: ' .
			sprintf(
				__( 'Your Mercado Pago credentials Client_id/Client_secret appears to be misconfigured.', 'woocommerce-mercadopago-module' ) . ' %s',
				'<a href="' . $this->admin_url() . '">' . __( 'Click here and configure!', 'woocommerce-mercadopago-module' ) . '</a>' ) .
			'</p></div>';
	}

	public function getCountryName( $site_id ) {
		switch ( $site_id ) {
			case 'MLA': return __( 'Argentine', 'woocommerce-mercadopago-module' );
			case 'MLB': return __( 'Brazil', 'woocommerce-mercadopago-module' );
			case 'MCO': return __( 'Colombia', 'woocommerce-mercadopago-module' );
			case 'MLC': return __( 'Chile', 'woocommerce-mercadopago-module' );
			case 'MLM': return __( 'Mexico', 'woocommerce-mercadopago-module' );
			case 'MLV': return __( 'Venezuela', 'woocommerce-mercadopago-module' );
			case 'MPE': return __( 'Peru', 'woocommerce-mercadopago-module' );
		}
	}

	/*
	 * ========================================================================
	 * IPN MECHANICS
	 * ========================================================================
	 */

	// This call checks any incoming notifications from Mercado Pago server.
	public function check_ipn_response() {
		@ob_clean();
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, $this->id .
				': @[check_ipn_response] - Received _get content: ' .
				json_encode( $_GET, JSON_PRETTY_PRINT ) );
		}
		$data = $this->check_ipn_request_is_valid( $_GET );
		if ( $data ) {
			header( 'HTTP/1.1 200 OK' );
			do_action( 'valid_mercadopago_ipn_request', $data );
		}
	}

	// Get received data from IPN and checks if we have a merchant_order or
	// payment associated. If we have these information, we return data to be
	// processed by successful_request function.
	public function check_ipn_request_is_valid( $data ) {

		if ( !isset( $data[ 'id' ] ) || !isset( $data[ 'topic' ] ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, $this->id .
					': @[check_ipn_request_is_valid] - data_id or type not set: ' .
					json_encode( $data, JSON_PRETTY_PRINT ) );
			}
			// at least, check if its a v0 ipn
			if ( !isset( $data[ 'data_id' ] ) || !isset( $data[ 'type' ] ) ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add(
						$this->id, $this->id .
						': @[check_ipn_response] - Mercado Pago Request Failure: ' .
						json_encode( $_GET, JSON_PRETTY_PRINT ) );
				}
				wp_die( __( 'Mercado Pago Request Failure', 'woocommerce-mercadopago-module' ) );
			} else {
				header( 'HTTP/1.1 200 OK' );
			}
			// No ID? No process!
			return false;
		}

		if ( 'yes' == $this->sandbox ) {
			$this->mp->sandbox_mode( true );
		} else {
			$this->mp->sandbox_mode( false );
		}
		try { // Get the merchant_order reported by the IPN. Glossary of attributes response in https://developers.mercadopago.com
			$params = array( "access_token" => $this->mp->get_access_token() );
			if ( $data[ "topic" ] == 'merchant_order' ) {
				$merchant_order_info = $this->mp->get( "/merchant_orders/" . $_GET[ "id" ], $params, false );
				// If the payment's transaction amount is equal (or bigger) than the merchant order's amount you can release your items
				if ( !is_wp_error( $merchant_order_info ) && ($merchant_order_info[ "status" ] == 200 ) ) {
					$payments = $merchant_order_info[ "response" ][ "payments" ];
				   	// check if we have more than one payment method
			  		if ( sizeof( $payments ) >= 1 ) { // We have payments
				  		return $merchant_order_info[ 'response' ];
				   	} else { // We have no payments?
						if ( 'yes' == $this->debug ) {
							$this->log->add( $this->id, $this->id . ': @[check_ipn_request_is_valid] - order received but has no payment' );
						}
						return false;
					}
				} else {
					if ( 'yes' == $this->debug ) {
						$this->log->add( $this->id, $this->id . ': @[check_ipn_request_is_valid] - got status not equal 200 or some error' );
					}
					return false;
				}
			}
		} catch ( MercadoPagoException $e ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, $this->id .
					': @[check_ipn_request_is_valid] - GOT EXCEPTION: ' .
					json_encode( array( "status" => $e->getCode(), "message" => $e->getMessage() ) ) );
			}
			return false;
		}
		return true;
	}

	// Properly handles each case of notification, based in payment status.
	public function successful_request( $data ) {
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, $this->id . ': @[successful_request] - starting to process ipn update...' );
		}
		$order_key = $data[ 'external_reference' ];
		if ( !empty( $order_key ) ) {
			$order_id = (int) str_replace( $this->invoice_prefix, '', $order_key );
			$order = new WC_Order( $order_id );
			// Checks whether the invoice number matches the order. If true processes the payment.
			if ( $order->id === $order_id ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, $this->id . ': @[successful_request] - got order with ID ' . $order->id . ' and status ' . $data[ 'payments' ][ 0 ][ 'status' ] );
				}
				// Order details.
				if ( !empty( $data[ 'payer' ][ 'email' ] ) ) {
					update_post_meta(
						$order_id,
						__( 'Payer email', 'woocommerce-mercadopago-module' ),
						$data[ 'payer' ][ 'email' ]
					);
				}
				if ( !empty( $data[ 'payment_type' ] ) ) {
					update_post_meta(
						$order_id,
						__( 'Payment type', 'woocommerce-mercadopago-module' ),
						$data[ 'payment_type' ]
					);
				}
				if ( !empty( $data[ 'payments' ] ) ) {
					$payment_ids = array();
					foreach ( $data[ 'payments' ] as $payment ) {
						$payment_ids[] = $payment[ 'id' ];
					}
					if ( sizeof( $payment_ids ) > 0 ) {
						update_post_meta(
							$order_id,
							__( 'Mercado Pago Payment ID', 'woocommerce-mercadopago-module' ),
							implode( ', ', $payment_ids )
						);
					}
				}
				// Here, we process the status...
				$status = 'pending';
				if ( sizeof( $data[ 'payments' ] ) == 1 ) {
					// if there's only one payment, then we get its status
					$status = $data[ 'payments' ][ 0 ][ 'status' ];
				} else if ( sizeof( $data[ 'payments' ] ) > 1 ) {
					// otherwise, we check payment sum
					$total_paid = 0.00;
					foreach ( $data[ 'payments' ] as $payment ) {
						if ( $payment[ 'status' ] === 'approved' ) {
							$total_paid = $total_paid + (float) $payment[ 'total_paid_amount' ];
						}
					}
					$total = $data[ 'shipping_cost' ] + $data[ 'total_amount' ];
					if ( $total_paid >= $total ) {
						// At this point, the sum of approved payments are above or equal than the total order amount, so it is approved
						$status = 'approved';
					}
				}
				// Switch the status and update in WooCommerce
				switch ( $status ) {
					case 'approved':
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Payment approved.', 'woocommerce-mercadopago-module' )
						);
						$order->payment_complete();
						break;
					case 'pending':
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Customer haven\'t paid yet.', 'woocommerce-mercadopago-module' )
						);
						break;
					case 'in_process':
						$order->update_status(
							'on-hold',
							'Mercado Pago: ' . __( 'Payment under review.', 'woocommerce-mercadopago-module' )
						);
						break;
					case 'rejected':
						$order->update_status(
							'failed',
							'Mercado Pago: ' . __( 'The payment was refused. The customer can try again.', 'woocommerce-mercadopago-module' )
						);
						break;
					case 'refunded':
						$order->update_status(
							'refunded',
							'Mercado Pago: ' . __( 'The payment was refunded to the customer.', 'woocommerce-mercadopago-module' )
						);
						break;
					case 'cancelled':
						$order->update_status(
							'cancelled',
							'Mercado Pago: ' . __( 'The payment was cancelled.', 'woocommerce-mercadopago-module' )
						);
						break;
					case 'in_mediation':
						$order->add_order_note(
							'Mercado Pago: ' . __( 'The payment is under mediation or it was charged-back.', 'woocommerce-mercadopago-module' )
						);
						break;
					case 'charged-back':
						$order->add_order_note(
							'Mercado Pago: ' . __( 'The payment is under mediation or it was charged-back.', 'woocommerce-mercadopago-module' )
						);
						break;
					default:
						break;
				}
			}
		}
	}

}
