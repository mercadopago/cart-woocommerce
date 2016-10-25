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
// This extension implements the ticket payment method.
class WC_WooMercadoPagoTicket_Gateway extends WC_Payment_Gateway {

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
		$this->id = 'woocommerce-mercadopago-ticket-module';
		$this->method_title = __( 'Mercado Pago - Ticket', 'woocommerce-mercadopago-module' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url( 'images/mplogo.png', plugin_dir_path( __FILE__ ) ) . '"><br><br>' . '<strong>' .
			wordwrap( __( 'This module enables WooCommerce to use Mercado Pago as payment method for purchases made in your virtual store.', 'woocommerce-mercadopago-module' ), 80, "\n" ) .
			'</strong>';

		// These fields are used in our Mercado Pago Module configuration page.
		$this->access_token = $this->get_option( 'access_token' );
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->coupon_mode = $this->get_option( 'coupon_mode' );
		$this->category_id = $this->get_option( 'category_id' );
		$this->invoice_prefix = $this->get_option( 'invoice_prefix', 'WC-' );
		$this->currency_conversion = $this->get_option('currency_conversion', false);
		$this->debug = $this->get_option( 'debug' );

		// Render our configuration page and init/load fields.
		$this->init_form_fields();
		$this->init_settings();

		// Hook actions for WordPress.
		add_action( // Used by IPN to receive IPN incomings.
			'woocommerce_api_wc_woomercadopagoticket_gateway',
			array($this, 'process_http_request')
		);
		add_action( // Used by IPN to process valid incomings.
			'valid_mercadopagoticket_ipn_request',
			array($this, 'successful_request')
		);
		add_action( // Used in settings page to hook "save settings" action.
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
		add_action( // Scripts for custom checkout
			'wp_enqueue_scripts',
			array( $this, 'ticketCheckoutScripts' )
		);
		add_action( // Apply the discounts
			'woocommerce_cart_calculate_fees',
			array( $this, 'add_discount_ticket' ), 10
		);

		// Verify if access token is empty.
		if ( empty( $this->access_token ) && $this->settings[ 'enabled' ] == 'yes' ) {
			add_action( 'admin_notices', array( $this, 'credentialsMissingMessage' ) );
		} else {
			add_action( // Verify if SSL is supported.
				'admin_notices', array( $this, 'checkSSLAbsence' )
			);
		}

		// Logging and debug.
		if ( 'yes' == $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = WC_MercadoPago_Module::woocommerce_instance()->logger();
			}
		}

	}

	// Required inherited method from WC_Payment_Gateway class: init_form_fields.
	// Initialise Gateway settings form fields with a customized page.
	public function init_form_fields() {

		if ( 'yes' == $this->settings[ 'enabled' ] ) {
			$api_secret_locale = sprintf(
				'<a href="https://www.mercadopago.com/mla/account/credentials?type=custom" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mlb/account/credentials?type=custom" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mlc/account/credentials?type=custom" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mco/account/credentials?type=custom" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mlm/account/credentials?type=custom" target="_blank">%s</a>, ' .
				'<a href="https://www.mercadopago.com/mpe/account/credentials?type=custom" target="_blank">%s</a> %s ' .
				'<a href="https://www.mercadopago.com/mlv/account/credentials?type=custom" target="_blank">%s</a>',
				__( 'Argentine', 'woocommerce-mercadopago-module' ),
				__( 'Brazil', 'woocommerce-mercadopago-module' ),
				__( 'Chile', 'woocommerce-mercadopago-module' ),
				__( 'Colombia', 'woocommerce-mercadopago-module' ),
				__( 'Mexico', 'woocommerce-mercadopago-module' ),
				__( 'Peru', 'woocommerce-mercadopago-module' ),
				__( 'or', 'woocommerce-mercadopago-module' ),
				__( 'Venezuela', 'woocommerce-mercadopago-module' )
			);

			// Trigger API to get payment methods and site_id, also validates access_token.
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
				} catch ( MercadoPagoException $e ) {
					$this->credentials_message = '<img width="12" height="12" src="' .
						plugins_url( 'images/error.png', plugin_dir_path( __FILE__ ) ) . '">' .
						' ' . __( 'Your credentials are <strong>not valid</strong>!', 'woocommerce-mercadopago-module' );
				}
			} else {
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
		}

		if ( 'yes' == $this->settings[ 'enabled' ] ) {
			// This array draws each UI (text, selector, checkbox, label, etc).
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Ticket Payment Method', 'woocommerce-mercadopago-module' ),
					'default' => 'yes'
				),
				'credentials_title' => array(
					'title' => __( 'Mercado Pago Credentials', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => sprintf( '%s', $this->credentials_message ) . '<br>' . sprintf( __( 'You can obtain your credentials for', 'woocommerce-mercadopago-module' ) . ' %s.', $api_secret_locale )
				),
				'access_token' => array(
					'title' => 'Access token',
					'type' => 'text',
					'description' => __( 'Insert your Mercado Pago Access token.', 'woocommerce-mercadopago-module' ),
					'default' => '',
					'required' => true
				),
				'ipn_url' => array(
					'title' => __( 'Instant Payment Notification (IPN) URL', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => sprintf( __( 'Your IPN URL to receive instant payment notifications is', 'woocommerce-mercadopago-module' ) . '<br>%s', '<code>' . $this->domain . '/woocommerce-mercadopago-module/?wc-api=WC_WooMercadoPagoTicket_Gateway' . '</code>.' )
				),
				'checkout_options_title' => array(
					'title' => __( 'Ticket Options', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => ''
				),
				'title' => array(
					'title' => __( 'Title', 'woocommerce-mercadopago-module' ),
					'type' => 'text',
					'description' => __( 'Title shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
					'default' => __( 'Mercado Pago - Ticket', 'woocommerce-mercadopago-module' )
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce-mercadopago-module' ),
					'type' => 'textarea',
					'description' => __( 'Description shown to the client in the checkout.', 'woocommerce-mercadopago-module' ),
					'default' => __( 'Pay with Mercado Pago', 'woocommerce-mercadopago-module' )
				),
				'coupon_mode' => array(
					'title'   => __( 'Coupons', 'woocommerce-mercadopago-module' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable coupons of discounts', 'woocommerce-mercadopago-module' ),
					'default' => 'no',
					'description' => __( 'If there is a Mercado Pago campaign, allow your store to give discounts to customers.', 'woocommerce-mercadopago-module' )
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
				'currency_conversion' => array(
					'title' => __( 'Currency Conversion', 'woocommerce-mercadopago-module' ),
					'type' => 'checkbox',
					'label' => __( 'If the used currency in WooCommerce is different or not supported by Mercado Pago, convert values of your transactions using Mercado Pago currency ratio', 'woocommerce-mercadopago-module' ),
					'default' => 'no',
					'description' => sprintf( '%s', $this->currency_message )
				),
				'testing' => array(
					'title' => __( 'Test and Debug Options', 'woocommerce-mercadopago-module' ),
					'type' => 'title',
					'description' => ''
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
					'label' => __( 'Enable Ticket Payment Method', 'woocommerce-mercadopago-module' ),
					'default' => 'yes'
				)
			);
		}

	}

	public function admin_options() {
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

	public function ticketCheckoutScripts() {
		if ( is_checkout() && $this->is_available() ) {
			if ( !get_query_var( 'order-received' ) ) {
				wp_enqueue_style(
					'woocommerce-mercadopago-style', plugins_url(
						'assets/css/custom_checkout_mercadopago.css',
						plugin_dir_path( __FILE__ ) ) );
				wp_enqueue_script(
					'woocommerce-mercadopago-v1',
					'https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js' );
			}
		}
	}

	public function payment_fields() {
		$amount = $this->get_order_total();

		$parameters = array(
			'payment_methods' => $this->payment_methods,
			'site_id' => $this->site_id,
			'images_path' => plugins_url( 'images/', plugin_dir_path( __FILE__ ) ),
			'amount' => $amount * ( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ),
			'coupon_mode' => $this->coupon_mode,
			'is_currency_conversion' => $this->currency_ratio,
			'woocommerce_currency' => get_woocommerce_currency(),
			'account_currency' => $this->getCurrencyId( $this->site_id ),
			'discount_action_url' => $this->domain . '/woocommerce-mercadopago-module/?wc-api=WC_WooMercadoPagoTicket_Gateway',
			'form_labels' => array(
				"form" => array(
					"payment_converted" => __("Payment converted from", "woocommerce-mercadopago-module" ),
					"to" => __("to", "woocommerce-mercadopago-module" ),
					"coupon_empty" => __( "Please, inform your coupon code", "woocommerce-mercadopago-module" ),
					'apply' => __( "Apply", "woocommerce-mercadopago-module" ),
					'remove' => __( "Remove", "woocommerce-mercadopago-module" ),
					'discount_info1' => __( "You will save", "woocommerce-mercadopago-module" ),
					'discount_info2' => __( "with discount from", "woocommerce-mercadopago-module" ),
					'discount_info3' => __( "Total of your purchase:", "woocommerce-mercadopago-module" ),
					'discount_info4' => __( "Total of your purchase with discount:", "woocommerce-mercadopago-module" ),
					'discount_info5' => __( "*Uppon payment approval", "woocommerce-mercadopago-module" ),
					'discount_info6' => __( "Terms and Conditions of Use", "woocommerce-mercadopago-module" ),
					'coupon_of_discounts' => __( "Discount Coupon", "woocommerce-mercadopago-module" ),
					'label_choose' => __( "Choose", "woocommerce-mercadopago-module" ),
					"issuer_selection" => __( 'Please, select the ticket issuer of your preference.', 'woocommerce-mercadopago-module' ),
					"payment_instructions" => __( 'Click "Place order" button. The ticket will be generated and you will be redirected to print it.', 'woocommerce-mercadopago-module' ),
					"ticket_note" => __( 'Important: The order will be confirmed only after the payment approval.', 'woocommerce-mercadopago-module' )
      	)
			)
		);

		wc_get_template(
			'ticket/ticket-form.php',
			$parameters,
			'woocommerce/mercadopago/',
			WC_WooMercadoPago_Module::getTemplatesPath()
		);
	}

	// This function is called after we clock on [place_order] button, and each field is passed to this
	// function through $_POST variable.
	public function process_payment( $order_id ) {
		$order = new WC_Order( $order_id );
		// we have got parameters from checkout page, now its time to charge the card
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, $this->id .
				': @[process_payment] - Received [$_POST] from customer front-end page: ' .
				json_encode( $_POST, JSON_PRETTY_PRINT ) );
		}

		if ( isset( $_POST[ 'mercadopago_ticket' ][ 'amount' ] ) && !empty( $_POST[ 'mercadopago_ticket' ][ 'amount' ] ) &&
			isset( $_POST[ 'mercadopago_ticket' ][ 'paymentMethodId' ] ) && !empty( $_POST[ 'mercadopago_ticket' ][ 'paymentMethodId' ] ) ) {
			return $this->createUrl( $order, $_POST );
    } else {
	    	// process when fields are imcomplete
			wc_add_notice(
				'<p>' . __( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago-module' ) . '</p>',
				'error'
			);
			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
    }
	}

	protected function createUrl( $order, $post_from_form ) {

		$this->mp->sandbox_mode( false );

		// Creates the order parameters by checking the cart configuration
		$preferences = $this->createPreferences( $order, $post_from_form );
		try {
			// Create order preferences with Mercado Pago API request
			$ticket_info = $this->mp->create_payment( json_encode( $preferences ) );
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, $this->id .
					': @[createUrl] - Received [$checkout_info] from Mercado Pago API: ' .
					json_encode( $ticket_info, JSON_PRETTY_PRINT ) );
			}
			if ( is_wp_error( $ticket_info ) ||
				$ticket_info[ 'status' ] < 200 || $ticket_info[ 'status' ] >= 300 ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, $this->id .
						': @[createUrl] - payment creation failed with error: ' .
						$ticket_info[ 'response' ][ 'status' ] );
				}
			} else {
				$response = $ticket_info[ 'response' ];
				if ( array_key_exists( 'status', $response ) ) {
        	if ( $response[ 'status' ] == "pending" && $response[ 'status_detail' ] == "pending_waiting_payment" ) {
        		WC()->cart->empty_cart();
        		$html = '<p></p><p>' . wordwrap(
        			__( 'Thank you for your order. Please, pay the ticket to get your order approved.', 'woocommerce-mercadopago-module'),
							60, '<br>') . '</p>';
						$html .=
							'<a id="submit-payment" target="_blank" href="' . $response[ 'transaction_details' ][ 'external_resource_url' ] . '" class="button alt">' .
							__( 'Print the Ticket', 'woocommerce-mercadopago-module' ) .
							'</a> ';
        		wc_add_notice(
        			'<p>' . $html . '</p>',
        			'notice'
        		);
        		$order->add_order_note(
							'Mercado Pago: ' .
							__( 'Customer haven\'t paid yet.', 'woocommerce-mercadopago-module' )
						);
						$order->add_order_note(
							'Mercado Pago: ' .
							__( 'To reprint the ticket click ', 'woocommerce-mercadopago-module' ) .
							'<a target="_blank" href="' . $response[ 'transaction_details' ][ 'external_resource_url' ] . '">' .
							__( 'here', 'woocommerce-mercadopago-module' ) .
							'</a>', 1, false
						);
						return array(
							'result' => 'success',
							'redirect' => $order->get_checkout_payment_url( true )
						);
        	}
        }
			}
		} catch ( MercadoPagoException $e ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id, $this->id .
					': @[createUrl] - payment creation failed with exception: ' .
					json_encode( array( "status" => $e->getCode(), "message" => $e->getMessage() ) ) );
			}
		}
		return false;
	}

	private function createPreferences( $order, $post_from_form ) {

		// Here we build the array that contains ordered itens, from customer cart
		$items = array();
		$purchase_description = "";
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item['qty'] ) {
					$product = new WC_product( $item[ 'product_id' ] );
					$purchase_description =
						$purchase_description . ' ' .
						( $product->post->post_title . ' x ' . $item[ 'qty' ] );
					array_push( $items, array(
						'id' => $item[ 'product_id' ],
						'title' => ( $product->post->post_title . ' x ' . $item[ 'qty' ] ),
						'description' => sanitize_file_name( (
							// This handles description width limit of Mercado Pago
							strlen( $product->post->post_content ) > 230 ?
							substr( $product->post->post_content, 0, 230 ) . "..." :
							$product->post->post_content
						) ),
						'picture_url' => wp_get_attachment_url( $product->get_image_id() ),
						'category_id' => $this->store_categories_id[ $this->category_id ],
						'quantity' => 1,
						'unit_price' => floor( ( (float) $item[ 'line_total' ] + (float) $item[ 'line_tax' ] ) *
							( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ) * 100 ) / 100
					));
				}
			}
		}

    // Creates the shipment cost structure
    $shipping_cost = (float) $order->get_total_shipping() + (float) $order->get_shipping_tax();
    if ( $shipping_cost > 0 ) {
      $item = array(
        'title' => $this->workaroundAmperSandBug( $order->get_shipping_to_display() ),
        'description' => __( 'Shipping service used by store', 'woocommerce-mercadopago-module' ),
        'quantity' => 1,
        'category_id' => $this->store_categories_id[ $this->category_id ],
        'unit_price' => floor( $shipping_cost * ( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 ) * 100 ) / 100
      );
      $items[] = $item;
    }

    // Discounts features
    if ( isset( $post_from_form[ 'mercadopago_ticket' ][ 'discount' ] ) &&
    	$post_from_form[ 'mercadopago_ticket' ][ 'discount' ] != "" &&
    	$post_from_form[ 'mercadopago_ticket' ][ 'discount' ] > 0 &&
    	isset( $post_from_form[ 'mercadopago_ticket' ][ 'coupon_code' ] ) &&
    	$post_from_form[ 'mercadopago_ticket' ][ 'coupon_code' ] != "" &&
    	WC()->session->chosen_payment_method == "woocommerce-mercadopago-ticket-module" ) {
      $item = array(
        'title' => __( 'Discount', 'woocommerce-mercadopago-module' ),
        'description' => __( 'Discount provided by store', 'woocommerce-mercadopago-module' ),
        'quantity' => 1,
        'category_id' => $this->store_categories_id[ $this->category_id ],
        'unit_price' => - ( (float) $post_from_form[ 'mercadopago_ticket' ][ 'discount' ] )
      );
      $items[] = $item;
    }

		// Build additional information from the customer data
    $payer_additional_info = array(
	    'first_name' => $order->billing_first_name,
	    'last_name' => $order->billing_last_name,
      //'registration_date' =>
      'phone'	=> array(
    		//'area_code' =>
				'number' => $order->billing_phone
			),
      'address' => array(
			'zip_code' => $order->billing_postcode,
			//'street_number' =>
			'street_name' => $order->billing_address_1 . ' / ' .
				$order->billing_city . ' ' .
				$order->billing_state . ' ' .
				$order->billing_country
			)
    );

    // Create the shipment address information set
    $shipments = array(
	  	'receiver_address' => array(
  		'zip_code' => $order->shipping_postcode,
  		//'street_number' =>
  		'street_name' => $order->shipping_address_1 . ' ' .
  			$order->shipping_address_2 . ' ' .
  			$order->shipping_city . ' ' .
  			$order->shipping_state . ' ' .
  			$order->shipping_country,
    		//'floor' =>
    		'apartment' => $order->shipping_address_2
    	)
    );

    // The payment preference
    $payment_preference = array (
    	'transaction_amount' => floor( ( (float) $post_from_form[ 'mercadopago_ticket' ][ 'amount' ] ) * 100 ) / 100,
    	'description' => $purchase_description,
      'payment_method_id' => $post_from_form[ 'mercadopago_ticket' ][ 'paymentMethodId' ],
      'payer' => array(
      	'email' => $order->billing_email
      ),
      'external_reference' => $this->invoice_prefix . $order->id,
      'additional_info' => array(
        'items' => $items,
        'payer' => $payer_additional_info,
        'shipments' => $shipments
      )
    );

    // Do not set IPN url if it is a localhost!
    $notification_url = $this->domain . '/woocommerce-mercadopago-module/?wc-api=WC_WooMercadoPagoTicket_Gateway';
    if ( !strrpos( $notification_url, "localhost" ) ) {
      $payment_preference['notification_url'] = $this->workaroundAmperSandBug( $notification_url );
    }

    // Discounts features
    if ( isset( $post_from_form[ 'mercadopago_ticket' ][ 'discount' ] ) &&
	  	$post_from_form[ 'mercadopago_ticket' ][ 'discount' ] != "" &&
	  	$post_from_form[ 'mercadopago_ticket' ][ 'discount' ] > 0 &&
	  	isset( $post_from_form[ 'mercadopago_ticket' ][ 'coupon_code' ] ) &&
	  	$post_from_form[ 'mercadopago_ticket' ][ 'coupon_code' ] != "" &&
	  	WC()->session->chosen_payment_method == "woocommerce-mercadopago-ticket-module" ) {
    	$payment_preference[ 'campaign_id' ] =  (int) $post_from_form[ 'mercadopago_ticket' ][ 'campaign_id' ];
      $payment_preference[ 'coupon_amount' ] = ( (float) $post_from_form[ 'mercadopago_ticket' ][ 'discount' ] );
      $payment_preference[ 'coupon_code' ] = strtoupper( $post_from_form[ 'mercadopago_ticket' ][ 'coupon_code' ] );
    }

    if ( !$this->isTestUser ) {
			$preferences[ 'sponsor_id' ] = (int) ( $this->sponsor_id[ $this->site_id ] );
		}

		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, $this->id .
				': @[createPreferences] - Returning just created [$payment_preference] structure: ' .
				json_encode( $payment_preference, JSON_PRETTY_PRINT ) );
		}

    $payment_preference = apply_filters(
    	'woocommerce_mercadopago_module_ticket_preferences',
      	$payment_preference, $order
    );
		return $payment_preference;

  }

  public function add_discount_ticket() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) || is_cart() ) {
			return;
		}
		if ( isset( $_POST[ 'mercadopago_ticket' ][ 'discount' ] ) &&
    	$_POST[ 'mercadopago_ticket' ][ 'discount' ] != "" &&
    	$_POST[ 'mercadopago_ticket' ][ 'discount' ] > 0 &&
			isset( $_POST[ 'mercadopago_ticket' ][ 'coupon_code' ] ) &&
    	$_POST[ 'mercadopago_ticket' ][ 'coupon_code' ] != "" &&
			WC()->session->chosen_payment_method == "woocommerce-mercadopago-ticket-module" ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, $this->id . ': @[add_discount_ticket] - Ticket trying to apply discount...' );
			}
			$value = ( $_POST[ 'mercadopago_ticket' ][ 'discount' ] ) /
				( (float) $this->currency_ratio > 0 ? (float) $this->currency_ratio : 1 );
			global $woocommerce;
			if ( apply_filters( 'wc_mercadopagoticket_module_apply_discount', 0 < $value, $woocommerce->cart ) ) {
				$woocommerce->cart->add_fee(
					sprintf( __( 'Discount for %s coupon', 'woocommerce-mercadopago-module' ), esc_attr( $_POST[ 'mercadopago_ticket' ][ 'campaign' ] ) ),
					( $value * -1 ), true
				);
			}
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
		if ( empty( $this->access_token ) ) return false;
		if ( strlen( $this->access_token ) > 0 ) {
			try {
				$this->mp = new MP( $this->access_token );
				$get_request = $this->mp->get( "/users/me?access_token=" . $this->access_token );
				if ( isset( $get_request[ 'response' ][ 'site_id' ] ) ) {
					$this->isTestUser = in_array( 'test_user', $get_request[ 'response' ][ 'tags' ] );
					$this->site_id = $get_request[ 'response' ][ 'site_id' ];
					// get ticket payments
					$payments = $this->mp->get( "/v1/payment_methods/?access_token=" . $this->access_token );
					foreach ( $payments[ "response" ] as $payment ) {
						if ( $payment[ 'payment_type_id' ] != 'account_money' && $payment[ 'payment_type_id' ] != 'credit_card' &&
	 						 $payment[ 'payment_type_id' ] != 'debit_card' && $payment[ 'payment_type_id' ] != 'prepaid_card' ) {
							array_push( $this->payment_methods, $payment );
						}
					}
					// check for auto converstion of currency
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

	public function checkSSLAbsence() {
		if ( empty( $_SERVER[ 'HTTPS' ] ) || $_SERVER[ 'HTTPS' ] == 'off' ) {
			if ( 'yes' == $this->settings[ 'enabled' ] ) {
				echo '<div class="error"><p><strong>' .
					__( 'Ticket is Inactive', 'woocommerce-mercadopago-module' ) .
					'</strong>: ' .
					sprintf(
						__( 'Your site appears to not have SSL certification. SSL is a pre-requisite because the payment process is made in your server.', 'woocommerce-mercadopago-module' )
					) . '</p></div>';
			}
		}
	}

	// Called automatically by WooCommerce, verify if Module is available to use.
	public function is_available() {
		// check SSL connection, as we can't use normal http in custom checkout
		if ( empty( $_SERVER[ 'HTTPS' ] ) || $_SERVER[ 'HTTPS' ] == 'off' ) {
			return false;
		}
		$available = ( 'yes' == $this->settings[ 'enabled' ] ) &&
			! empty( $this->access_token );
		return $available;
	}

	// Get the URL to admin page.
	protected function admin_url() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			return admin_url(
				'admin.php?page=wc-settings&tab=checkout&section=wc_woomercadopagoticket_gateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPagoTicket_Gateway'
		);
	}

	// Notify that access_token are not valid.
	public function credentialsMissingMessage() {
		echo '<div class="error"><p><strong>' .
			__( 'Ticket is Inactive', 'woocommerce-mercadopago-module' ) .
			'</strong>: ' .
			sprintf(
				__( 'Your Mercado Pago credentials Access Token appears to be misconfigured.', 'woocommerce-mercadopago-module' ) . ' %s',
				'<a href="' . $this->admin_url() . '">' .
				__( 'Click here and configure!', 'woocommerce-mercadopago-module' ) . '</a>' ) .
			'</p></div>';
	}

	public function getCountryName( $site_id ) {
		$country = $site_id;
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

	// [Server Side] This call checks any incoming notifications from Mercado Pago server.
	public function process_http_request() {
		@ob_clean();
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, $this->id .
				': @[process_http_request] - Received _get content: ' .
				json_encode( $_GET, JSON_PRETTY_PRINT ) );
		}
		if ( isset( $_GET[ 'coupon_id' ] ) && $_GET[ 'coupon_id' ] != '' ) {
			// process coupon evaluations
			if ( isset( $_GET[ 'payer' ] ) && $_GET[ 'payer' ] != '' ) {
				$logged_user_email = $_GET[ 'payer' ];
				$coupon_id = $_GET[ 'coupon_id' ];
		    if ( 'yes' == $this->sandbox )
					$this->mp->sandbox_mode( true );
				else
					$this->mp->sandbox_mode( false );
			    $response = $this->mp->check_discount_campaigns(
			    	$_GET[ 'amount' ],
			    	$logged_user_email,
			    	$coupon_id
			    );
			    header( 'HTTP/1.1 200 OK' );
			    header( 'Content-Type: application/json' );
				echo json_encode( $response );
			} else {
				$obj = new stdClass();
				$obj->status = 404;
				$obj->response = array(
					'message' => __( 'Please, inform your email in billing address to use this feature', 'woocommerce-mercadopago-module' ),
					'error' => 'payer_not_found',
					'status' => 404,
					'cause' => array()
				);
				header( 'HTTP/1.1 200 OK' );
			    header( 'Content-Type: application/json' );
				echo json_encode( $obj );
			}
			exit(0);
		} else {
			// process IPN messages
			$data = $this->check_ipn_request_is_valid( $_GET );
			if ( $data ) {
				header( 'HTTP/1.1 200 OK' );
				do_action( 'valid_mercadopagoticket_ipn_request', $data );
			}
		}
	}

	// Get received data from IPN and checks if we have an associated
	// payment. If we have these information, we return data to be
	// processed by successful_request function.
	public function check_ipn_request_is_valid( $data ) {

		if ( !isset( $data[ 'data_id' ] ) || !isset( $data[ 'type' ] ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( $this->id, $this->id .
					': @[check_ipn_request_is_valid] - data_id or type not set: ' .
					json_encode( $data, JSON_PRETTY_PRINT ) );
			}
			// at least, check if its a v0 ipn
			if ( !isset( $data[ 'id' ] ) || !isset( $data[ 'topic' ] ) ) {
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

		$this->mp->sandbox_mode( false );
		try {
			$access_token = array( "access_token" => $this->mp->get_access_token() );
			if ( $data[ "type" ] == 'payment' ) {
				$payment_info = $this->mp->get( "/v1/payments/" . $data[ "data_id" ], $access_token, false );
				if ( !is_wp_error( $payment_info ) &&
					( $payment_info[ "status" ] == 200 || $payment_info[ "status" ] == 201 ) ) {
					return $payment_info[ 'response' ];
				} else {
					if ( 'yes' == $this->debug ) {
						$this->log->add( $this->id, $this->id .
							': @[check_ipn_request_is_valid] - error when processing received data: ' .
							json_encode( $payment_info, JSON_PRETTY_PRINT ) );
					}
					return false;
				}
			}
		} catch ( MercadoPagoException $e ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add(
					$this->id, $this->id .
					': @[check_ipn_request_is_valid] - MercadoPagoException: ' .
					json_encode( array( "status" => $e->getCode(), "message" => $e->getMessage() ) ) );
			}
			return false;
		}
		return true;
	}

	// Properly handles each case of notification, based in payment status.
	public function successful_request( $data ) {
		if ( 'yes' == $this->debug ) {
			$this->log->add( $this->id, $this->id .
				': @[successful_request] - starting to process ipn update...' );
		}
		$order_key = $data[ 'external_reference' ];
		if ( !empty( $order_key ) ) {
			$order_id = (int) str_replace( $this->invoice_prefix, '', $order_key );
			$order = new WC_Order( $order_id );
			// Checks whether the invoice number matches the order, if true processes the payment
			if ( $order->id === $order_id ) {
				if ( 'yes' == $this->debug ) {
					$this->log->add( $this->id, $this->id .
						': @[successful_request] - got order with ID ' . $order->id .
						' and data: ' . json_encode( $data, JSON_PRETTY_PRINT ) );
				}
				// Order details.
				if ( !empty( $data[ 'payer' ][ 'email' ] ) ) {
					update_post_meta(
						$order_id,
						__( 'Payer email',
							'woocommerce-mercadopago-module' ),
						$data[ 'payer' ][ 'email' ]
					);
				}
				if ( !empty( $data[ 'payment_type_id' ] ) ) {
					update_post_meta(
						$order_id,
						__( 'Payment type',
							'woocommerce-mercadopago-module' ),
						$data[ 'payment_type_id' ]
					);
				}
				if ( !empty( $data ) ) {
					update_post_meta(
						$order_id,
						__( 'Mercado Pago Payment ID',
							'woocommerce-mercadopago-module' ),
						$data[ 'id' ]
					);
				}
				// Switch the status and update in WooCommerce
				switch ( $data[ 'status' ] ) {
					case 'approved':
						$order->add_order_note(
							'Mercado Pago: ' . __( 'Payment approved.',
								'woocommerce-mercadopago-module' )
						);
						$order->payment_complete();
						break;
					case 'pending':
						// decrease stock if not yet decreased and order not exists
						$notes = $order->get_customer_order_notes();
						$has_note = false;
						if ( sizeof($notes) > 1 ) {
							$has_note = true;
							break;
						}
						if ( !$has_note ) {
							// dont have order note
							/*if ( sizeof( $order->get_items() ) > 0 ) {
								foreach ( $order->get_items() as $item ) {
									if ( $item['qty'] ) {
										$product = new WC_product( $item[ 'product_id' ] );
										if ( !$product->is_downloadable('yes') ) {
											wc_update_product_stock(
												$item[ 'product_id' ],
												$product->get_stock_quantity()-$item[ 'qty' ]
											);
										}
									}
								}
							}*/
							$order->add_order_note(
								'Mercado Pago: ' . __( 'Waiting for the ticket payment.',
									'woocommerce-mercadopago-module' )
							);
							$order->add_order_note(
								'Mercado Pago: ' . __( 'Waiting for the ticket payment.',
									'woocommerce-mercadopago-module' ), 1, false
							);
						}
						break;
					case 'in_process':
						$order->update_status(
							'on-hold',
							'Mercado Pago: ' . __( 'Payment under review.',
								'woocommerce-mercadopago-module' )
						);
						break;
					case 'rejected':
						$order->update_status(
							'failed',
							'Mercado Pago: ' . __( 'The payment was refused. The customer can try again.',
								'woocommerce-mercadopago-module' )
						);
						break;
					case 'refunded':
						$order->update_status(
							'refunded',
							'Mercado Pago: ' . __( 'The payment was refunded to the customer.',
								'woocommerce-mercadopago-module' )
						);
						break;
					case 'cancelled':
						$order->update_status(
							'cancelled',
							'Mercado Pago: ' . __( 'The payment was cancelled.',
								'woocommerce-mercadopago-module' )
						);
						break;
					case 'in_mediation':
						$order->add_order_note(
							'Mercado Pago: ' . __( 'The payment is under mediation or it was charged-back.',
								'woocommerce-mercadopago-module' )
						);
						break;
					case 'charged-back':
						$order->add_order_note(
							'Mercado Pago: ' . __( 'The payment is under mediation or it was charged-back.',
								'woocommerce-mercadopago-module' )
						);
						break;
					default:
						break;
				}
			}
		}
	}

}

new WC_WooMercadoPagoTicket_Gateway();
