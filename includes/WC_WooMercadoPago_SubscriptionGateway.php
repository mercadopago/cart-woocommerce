<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer - Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// This include Mercado Pago library SDK
require_once dirname( __FILE__ ) . '/sdk/lib/mercadopago.php';

/**
 * Summary: Extending from WooCommerce Payment Gateway class.
 * Description: This class implements Mercado Pago Subscription checkout.
 * @since 3.0.0
 */
class WC_WooMercadoPago_SubscriptionGateway extends WC_Payment_Gateway {

	public function __construct() {

		// Mercao Pago instance.
		$this->site_data = WC_Woo_Mercado_Pago_Module::get_site_data( false );
		$this->mp = new MP(
			WC_Woo_Mercado_Pago_Module::get_module_version(),
			get_option( '_mp_client_id' ),
			get_option( '_mp_client_secret' )
		);
		$email = ( wp_get_current_user()->ID != 0 ) ? wp_get_current_user()->user_email : null;
		$this->mp->set_email( $email );
		$locale = get_locale();
		$locale = ( strpos( $locale, '_' ) !== false && strlen( $locale ) == 5 ) ? explode( '_', $locale ) : array('','');
		$this->mp->set_locale( $locale[1] );

		// WooCommerce fields.
		$this->id = 'woo-mercado-pago-subscription';
		//$this->supports = array( 'products', 'refunds' );
		$this->icon = apply_filters(
			'woocommerce_mercadopago_icon',
			plugins_url( 'assets/images/mercadopago.png', plugin_dir_path( __FILE__ ) )
		);

		$this->method_title = __( 'Mercado Pago - Subscription', 'woocommerce-mercadopago' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url( 'assets/images/mplogo.png', plugin_dir_path( __FILE__ ) ) .
		'"><br><br><strong>' .
			__( 'This service allows you to subscribe customers to subscription plans.', 'woocommerce-mercadopago' ) .
		'</strong>';

		// TODO: Verify sandbox availability.
		$this->mp->sandbox_mode( false );

		// How checkout is shown.
		$this->title              = $this->get_option( 'title', __( 'Mercado Pago - Subscription', 'woocommerce-mercadopago' ) );
		$this->description        = $this->get_option( 'description' );
		$this->method             = $this->get_option( 'method', 'iframe' );
		$this->iframe_width       = $this->get_option( 'iframe_width', '640' );
		$this->iframe_height      = $this->get_option( 'iframe_height', '800' );
		// How checkout redirections will behave.
		$this->success_url        = $this->get_option( 'success_url', '' );
		$this->failure_url        = $this->get_option( 'failure_url', '' );
		$this->pending_url        = $this->get_option( 'pending_url', '' );
		// How checkout payment behaves.
		$this->gateway_discount   = $this->get_option( 'gateway_discount', 0 );

		// Logging and debug.
		$_mp_debug_mode = get_option( '_mp_debug_mode', '' );
		if ( ! empty ( $_mp_debug_mode ) ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = WC_Woo_Mercado_Pago_Module::woocommerce_instance()->logger();
			}
		}

		// Render our configuration page and init/load fields.
		$this->init_form_fields();
		$this->init_settings();

		// Used by IPN to receive IPN incomings.
		add_action(
			'woocommerce_api_wc_woomercadopago_subscriptiongateway',
			array( $this, 'check_ipn_response' )
		);
		// Used by IPN to process valid incomings.
		add_action(
			'valid_mercadopago_subscription_ipn_request',
			array( $this, 'successful_request' )
		);
		// Process the cancel order meta box order action.
		add_action(
			'woocommerce_order_action_cancel_order',
			array( $this, 'process_cancel_order_meta_box_actions' )
		);
		// Used by WordPress to render the custom checkout page.
		add_action(
			'woocommerce_receipt_' . $this->id,
			function( $order ) {
				echo $this->render_order_form( $order );
			}
		);
		// Used to fix CSS in some older WordPress/WooCommerce versions.
		add_action(
			'wp_head',
			function() {
				if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
					$page_id = wc_get_page_id( 'checkout' );
				} else {
					$page_id = woocommerce_get_page_id( 'checkout' );
				}
				if ( is_page( $page_id ) ) {
					echo '<style type="text/css">#MP-Checkout-dialog { z-index: 9999 !important; }</style>' . PHP_EOL;
				}
			}
		);
		// Used in settings page to hook "save settings" action.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'custom_process_admin_options' )
		);
		// Scripts for order configuration.
		add_action(
			'woocommerce_after_checkout_form',
			array( $this, 'add_checkout_script' )
		);
		// Display discount in payment method title.
		add_filter(
			'woocommerce_gateway_title',
			array( $this, 'get_payment_method_title_subscription' ), 10, 2
		);
		// Checkout updates.
		add_action(
			'woocommerce_thankyou',
			array( $this, 'update_checkout_status' )
		);

	}

	/**
	 * Summary: Initialise Gateway Settings Form Fields.
	 * Description: Initialise Gateway settings form fields with a customized page.
	 */
	public function init_form_fields() {

		// Show message if credentials are not properly configured or country is not supported.
		$_site_id_v0 = get_option( '_site_id_v0', '' );
		if ( empty( $_site_id_v0 ) ) {
			$this->form_fields = array(
				'no_credentials_title' => array(
					'title' => sprintf(
						__( 'It appears that your credentials are not properly configured.<br/>Please, go to %s and configure it.', 'woocommerce-mercadopago' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=mercado-pago-settings' ) ) . '">' .
						__( 'Mercado Pago Settings', 'woocommerce-mercadopago' ) .
						'</a>'
					),
					'type' => 'title'
				),
			);
			return;
		} elseif ( get_option( '_site_id_v0', '' ) != 'MLA' && get_option( '_site_id_v0', '' ) != 'MLB' && get_option( '_site_id_v0', '' ) != 'MLM' ) {
			$this->form_fields = array(
				'unsupported_country_title' => array(
					'title' => sprintf(
						__( 'It appears that your country is not supported for this solution.<br/>Please, use another payment method or go to %s to use another credential.', 'woocommerce-mercadopago' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=mercado-pago-settings' ) ) . '">' .
						__( 'Mercado Pago Settings', 'woocommerce-mercadopago' ) .
						'</a>'
					),
					'type' => 'title'
				),
			);
			return;
		}

		// If module is disabled, we do not need to load and process the settings page.
		if ( empty( $this->settings['enabled'] ) || 'no' == $this->settings['enabled'] ) {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-mercadopago' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Subscription', 'woocommerce-mercadopago' ),
					'default' => 'no'
				)
			);
			return;
		}

		// Validate back URL.
		if ( ! empty( $this->success_url ) && filter_var( $this->success_url, FILTER_VALIDATE_URL ) === FALSE ) {
			$success_back_url_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/warning.png', plugin_dir_path( __FILE__ ) ) . '"> ' .
			__( 'This appears to be an invalid URL.', 'woocommerce-mercadopago' ) . ' ';
		} else {
			$success_back_url_message = __( 'Where customers should be redirected after a successful purchase. Let blank to redirect to the default store order resume page.', 'woocommerce-mercadopago' );
		}
		if ( ! empty( $this->failure_url ) && filter_var( $this->failure_url, FILTER_VALIDATE_URL ) === FALSE ) {
			$fail_back_url_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/warning.png', plugin_dir_path( __FILE__ ) ) . '"> ' .
			__( 'This appears to be an invalid URL.', 'woocommerce-mercadopago' ) . ' ';
		} else {
			$fail_back_url_message = __( 'Where customers should be redirected after a failed purchase. Let blank to redirect to the default store order resume page.', 'woocommerce-mercadopago' );
		}
		if ( ! empty( $this->pending_url ) && filter_var( $this->pending_url, FILTER_VALIDATE_URL ) === FALSE ) {
			$pending_back_url_message = '<img width="14" height="14" src="' . plugins_url( 'assets/images/warning.png', plugin_dir_path( __FILE__ ) ) . '"> ' .
			__( 'This appears to be an invalid URL.', 'woocommerce-mercadopago' ) . ' ';
		} else {
			$pending_back_url_message = __( 'Where customers should be redirected after a pending purchase. Let blank to redirect to the default store order resume page.', 'woocommerce-mercadopago' );
		}

		$ipn_locale = sprintf(
			'<a href="https://www.mercadopago.com.ar/ipn-notifications" target="_blank">%s</a>, ' .
			'<a href="https://www.mercadopago.com.br/ipn-notifications" target="_blank">%s</a> %s ' .
			'<a href="https://www.mercadopago.com.mx/ipn-notifications" target="_blank">%s</a>',
			__( 'Argentine', 'woocommerce-mercadopago' ),
			__( 'Brazil', 'woocommerce-mercadopago' ),
			__( 'or', 'woocommerce-mercadopago' ),
			__( 'Mexico', 'woocommerce-mercadopago' )
		);

		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-mercadopago' ),
				'type' => 'checkbox',
				'label' => __( 'Enable Subscription', 'woocommerce-mercadopago' ),
				'default' => 'no'
			),
			'checkout_options_title' => array(
				'title' => __( 'Checkout Interface: How checkout is shown', 'woocommerce-mercadopago' ),
				'type' => 'title'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-mercadopago' ),
				'type' => 'text',
				'description' => __( 'Title shown to the client in the checkout.', 'woocommerce-mercadopago' ),
				'default' => __( 'Mercado Pago', 'woocommerce-mercadopago' )
			),
			'description' => array(
				'title' => __( 'Description', 'woocommerce-mercadopago' ),
				'type' => 'textarea',
				'description' => __( 'Description shown to the client in the checkout.', 'woocommerce-mercadopago' ),
				'default' => __( 'Subscribe with Mercado Pago', 'woocommerce-mercadopago' )
			),
			'method' => array(
				'title' => __( 'Integration Method', 'woocommerce-mercadopago' ),
				'type' => 'select',
				'description' => __( 'Select how your clients should interact with Mercado Pago. Modal Window (inside your store), Redirect (Client is redirected to Mercado Pago), or iFrame (an internal window is embedded to the page layout).', 'woocommerce-mercadopago' ),
				'default' => 'iframe',
				'options' => array(
					'iframe' => __( 'iFrame', 'woocommerce-mercadopago' ),
					'modal' => __( 'Modal Window', 'woocommerce-mercadopago' ),
					'redirect' => __( 'Redirect', 'woocommerce-mercadopago' )
				)
			),
			'iframe_width' => array(
				'title' => __( 'iFrame Width', 'woocommerce-mercadopago' ),
				'type' => 'number',
				'description' => __( 'If your integration method is iFrame, please inform the payment iFrame width.', 'woocommerce-mercadopago' ),
				'default' => '640'
			),
			'iframe_height' => array(
				'title' => __( 'iFrame Height', 'woocommerce-mercadopago' ),
				'type' => 'number',
				'description' => __( 'If your integration method is iFrame, please inform the payment iFrame height.', 'woocommerce-mercadopago' ),
				'default' => '800'
			),
			'checkout_navigation_title' => array(
				'title' => __( 'Checkout Navigation: How checkout redirections will behave', 'woocommerce-mercadopago' ),
				'type' => 'title'
			),
			'ipn_url' => array(
				'title' =>
					__( 'Instant Payment Notification (IPN) URL', 'woocommerce-mercadopago' ),
				'type' => 'title',
				'description' => sprintf(
					__( 'For this solution, you need to configure your IPN URL. You can access it in your account for your specific country in:', 'woocommerce-mercadopago' ) .
					'<br>' . ' %s.', $ipn_locale . '. ' . sprintf(
					__( 'Your IPN URL to receive instant payment notifications is', 'woocommerce-mercadopago' ) .
					':<br>%s', '<code>' . WC()->api_request_url( 'WC_WooMercadoPago_SubscriptionGateway' ) . '</code>' )
				)
			),
			'success_url' => array(
				'title' => __( 'Sucess URL', 'woocommerce-mercadopago' ),
				'type' => 'text',
				'description' => $success_back_url_message,
				'default' => ''
			),
			'failure_url' => array(
				'title' => __( 'Failure URL', 'woocommerce-mercadopago' ),
				'type' => 'text',
				'description' => $fail_back_url_message,
				'default' => ''
			),
			'pending_url' => array(
				'title' => __( 'Pending URL', 'woocommerce-mercadopago' ),
				'type' => 'text',
				'description' => $pending_back_url_message,
				'default' => ''
			),
			'payment_title' => array(
				'title' => __( 'Payment Options: How payment options behaves', 'woocommerce-mercadopago' ),
				'type' => 'title'
			),
			'gateway_discount' => array(
				'title' => __( 'Discount/Fee by Gateway', 'woocommerce-mercadopago' ),
				'type' => 'number',
				'description' => __( 'Give a percentual (-99 to 99) discount or fee for your customers if they use this payment gateway. Use negative for fees, positive for discounts.', 'woocommerce-mercadopago' ),
				'default' => '0',
				'custom_attributes' => array(
					'step' 	=> '0.01',
					'min'	=> '-99',
					'max' => '99'
				) 
			)
		);

	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the
	 * erroring field out.
	 * @return bool was anything saved?
	 */
	public function custom_process_admin_options() {
		$this->init_settings();
		$post_data = $this->get_post_data();
		foreach ( $this->get_form_fields() as $key => $field ) {
			if ( 'title' !== $this->get_field_type( $field ) ) {
				$value = $this->get_field_value( $key, $field, $post_data );
				if ( $key == 'iframe_width' ) {
					if ( ! is_numeric( $value ) || empty ( $value ) ) {
						$this->settings[$key] = 480;
					} else {
						$this->settings[$key] = $value;
					}
				} elseif ( $key == 'iframe_height' ) {
					if ( ! is_numeric( $value ) || empty ( $value ) ) {
						$this->settings[$key] = 800;
					} else {
						$this->settings[$key] = $value;
					}
				} elseif ( $key == 'gateway_discount') {
					if ( ! is_numeric( $value ) || empty ( $value ) ) {
						$this->settings[$key] = 0;
					} else {
						if ( $value < -99 || $value > 99 || empty ( $value ) ) {
							$this->settings[$key] = 0;
						} else {
							$this->settings[$key] = $value;
						}
					}
				} else {
					$this->settings[$key] = $this->get_field_value( $key, $field, $post_data );
				}
			}
		}
		$_site_id_v0 = get_option( '_site_id_v0', '' );
		$is_test_user = get_option( '_test_user_v0', false );
		if ( ! empty( $_site_id_v0 ) && ! $is_test_user ) {
			// Create MP instance.
			$mp = new MP(
				WC_Woo_Mercado_Pago_Module::get_module_version(),
				get_option( '_mp_client_id' ),
				get_option( '_mp_client_secret' )
			);
			$email = ( wp_get_current_user()->ID != 0 ) ? wp_get_current_user()->user_email : null;
			$mp->set_email( $email );
			$locale = get_locale();
			$locale = ( strpos( $locale, '_' ) !== false && strlen( $locale ) == 5 ) ? explode( '_', $locale ) : array('','');
			$mp->set_locale( $locale[1] );
			// Analytics.
			$infra_data = WC_Woo_Mercado_Pago_Module::get_common_settings();
			$infra_data['checkout_subscription'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
			$response = $mp->analytics_save_settings( $infra_data );
		}
		// Apply updates.
		return update_option(
			$this->get_option_key(),
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings )
		);
	}

	/**
	 * Handles the manual order cancellation in server-side.
	 */
	public function process_cancel_order_meta_box_actions( $order ) {

		$used_gateway = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_used_gateway' ) :
			get_post_meta( $order->id, '_used_gateway', true );
		$preapproval = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( 'Mercado Pago Pre-Approval' ) :
			get_post_meta( $order->id, 'Mercado Pago Pre-Approval', true );
		$preapproval = explode( '/', $preapproval );
		$preapproval = explode( ' ', substr( $preapproval[0], 1, -1 ) );
		$preapproval_id = $preapproval[1];

		// A watchdog to prevent operations from other gateways.
		if ( $used_gateway != 'WC_WooMercadoPago_SubscriptionGateway' ) {
			return;
		}

		$this->write_log( __FUNCTION__, 'cancelling preapproval for ' . $preapproval_id );

		// Canceling the order and all of its payments.
		if ( $this->mp != null && ! empty( $preapproval_id ) ) {
			$response = $this->mp->cancel_preapproval_payment( $preapproval_id );
			$message = $response['response']['message'];
			$status = $response['status'];
			$this->write_log(
				__FUNCTION__,
				'cancel preapproval of id ' . $preapproval_id . ' => ' .
				( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
			);
		} else {
			$this->write_log( __FUNCTION__, 'no preapproval or credentials invalid' );
		}

	}

	// Write log.
	private function write_log( $function, $message ) {
		$_mp_debug_mode = get_option( '_mp_debug_mode', '' );
		if ( ! empty ( $_mp_debug_mode ) ) {
			$this->log->add(
				$this->id,
				'[' . $function . ']: ' . $message
			);
		}
	}

	/*
	 * ========================================================================
	 * CHECKOUT BUSINESS RULES (CLIENT SIDE)
	 * ========================================================================
	 */

	public function payment_fields() {
		// subscription checkout
		if ( $description = $this->get_description() ) {
			echo wpautop( wptexturize( $description ) );
		}
		if ( $this->supports( 'default_credit_card_form' ) ) {
			$this->credit_card_form();
		}
	}

	public function add_checkout_script() {

		$client_id = get_option( 'client_id' );
		$is_test_user = get_option( '_test_user_v0', false );

		if ( ! empty( $client_id ) && ! $is_test_user ) {

			$w = WC_Woo_Mercado_Pago_Module::woocommerce_instance();
			$logged_user_email = null;
			$payments = array();
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			foreach ( $gateways as $g ) {
				$payments[] = $g->id;
			}
			$payments = str_replace( '-', '_', implode( ', ', $payments ) );

			if ( wp_get_current_user()->ID != 0 ) {
				$logged_user_email = wp_get_current_user()->user_email;
			}

			?>
			<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
			<script type="text/javascript">
				try {
					var MA = ModuleAnalytics;
					MA.setToken( '<?php echo $client_id; ?>' );
					MA.setPlatform( 'WooCommerce' );
					MA.setPlatformVersion( '<?php echo $w->version; ?>' );
					MA.setModuleVersion( '<?php echo WC_Woo_Mercado_Pago_Module::VERSION; ?>' );
					MA.setPayerEmail( '<?php echo ( $logged_user_email != null ? $logged_user_email : "" ); ?>' );
					MA.setUserLogged( <?php echo ( empty( $logged_user_email ) ? 0 : 1 ); ?> );
					MA.setInstalledModules( '<?php echo $payments; ?>' );
					MA.post();
				} catch(err) {}
			</script>
			<?php

		}

	}

	public function update_checkout_status( $order_id ) {
		$client_id = get_option( '_mp_client_id' );
		$is_test_user = get_option( '_test_user_v0', false );
		if ( ! empty( $client_id ) && ! $is_test_user ) {
			if ( get_post_meta( $order_id, '_used_gateway', true ) != 'WC_WooMercadoPago_SubscriptionGateway' ) {
				return;
			}
			$this->write_log( __FUNCTION__, 'updating order of ID ' . $order_id );
			echo '<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
			<script type="text/javascript">
				try {
					var MA = ModuleAnalytics;
					MA.setToken( ' . $client_id . ' );
					MA.setPaymentType("subscription");
					MA.setCheckoutType("subscription");
					MA.put();
				} catch(err) {}
			</script>';

		}

	}

	/**
	 * Summary: Handle the payment and processing the order.
	 * Description: First step occurs when the customer selects Mercado Pago and proceed to checkout.
	 * This method verify which integration method was selected and makes the build for the checkout
	 * URL.
	 * @return an array containing the result of the processment and the URL to redirect.
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );
		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_used_gateway', 'WC_WooMercadoPago_SubscriptionGateway' );
			$order->save();
		} else {
			update_post_meta( $order_id, '_used_gateway', 'WC_WooMercadoPago_SubscriptionGateway' );
		}

		if ( 'redirect' == $this->method ) {
			$this->write_log( __FUNCTION__, 'customer being redirected to Mercado Pago.' );
			return array(
				'result' => 'success',
				'redirect' => $this->create_url( $order )
			);
		} elseif ( 'modal' == $this->method || 'iframe' == $this->method ) {
			$this->write_log( __FUNCTION__, 'preparing to render Mercado Pago checkout view.' );
			return array(
				'result' => 'success',
				'redirect' => $order->get_checkout_payment_url( true )
			);
		}

	}

	/**
	 * Summary: Show the custom renderization for the checkout.
	 * Description: Order page and this generates the form that shows the pay button. This step
	 * generates the form to proceed to checkout.
	 * @return the html to be rendered.
	 */
	public function render_order_form( $order_id ) {

		$order = wc_get_order( $order_id );
		$url = $this->create_url( $order );
		
		$banner_url = get_option( '_mp_custom_banner' );
		if ( ! isset( $banner_url ) || empty( $banner_url ) ) {
			$banner_url = $this->site_data['checkout_banner'];
		}

		if ( 'modal' == $this->method && $url ) {

			$this->write_log( __FUNCTION__, 'rendering Mercado Pago lightbox (modal window).' );

			// ===== The checkout is made by displaying a modal to the customer =====
			$html = '<style type="text/css">
						#MP-Checkout-dialog #MP-Checkout-IFrame { bottom: -28px !important; height: 590px !important; }
					</style>';
			$html = '<script type="text/javascript" src="https://secure.mlstatic.com/mptools/render.js"></script>
					<script type="text/javascript">
						(function() { $MPC.openCheckout({ url: "' . esc_url( $url ) . '", mode: "modal" }); })();
					</script>';
			$html = '<img width="468" height="60" src="' . $banner_url . '">';
			$html = '<p></p><p>' . wordwrap(
						__( 'Thank you for your order. Please, proceed with your payment clicking in the bellow button.', 'woocommerce-mercadopago' ),
						60, '<br>'
					) . '</p>
					<a id="submit-payment" href="' . esc_url( $url ) . '" name="MP-Checkout" class="button alt" mp-mode="modal">' .
						__( 'Pay with Mercado Pago', 'woocommerce-mercadopago' ) .
					'</a> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' .
						__( 'Cancel order &amp; Clear cart', 'woocommerce-mercadopago' ) .
					'</a>';
			return $html;
			// ===== The checkout is made by displaying a modal to the customer =====

		} elseif ( 'modal' != $this->method && $url ) {

			$this->write_log( __FUNCTION__, 'embedding Mercado Pago iframe.' );

			// ===== The checkout is made by rendering Mercado Pago form within a iframe =====
			$html = '<img width="468" height="60" src="' . $banner_url . '">';
			$html = '<p></p><p>' . wordwrap(
						__( 'Thank you for your order. Proceed with your payment completing the following information.', 'woocommerce-mercadopago' ),
						60, '<br>'
					) . '</p>
					<iframe src="' . esc_url( $url ) . '" name="MP-Checkout" ' .
					'width="' . $this->iframe_width . '" ' . 'height="' . $this->iframe_height . '" ' .
					'frameborder="0" scrolling="no" id="checkout_mercadopago"></iframe>';
			return $html;
			// ===== The checkout is made by rendering Mercado Pago form within a iframe =====

		} else {

			$this->write_log( __FUNCTION__, 'unable to build Mercado Pago checkout URL.' );

			// ===== Reaching at this point means that the URL could not be build by some reason =====
			$html = '<p>' .
						__( 'An error occurred when proccessing your payment. Please try again or contact us for assistence.', 'woocommerce-mercadopago' ) .
					'</p>' .
					'<a class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' .
						__( 'Click to try again', 'woocommerce-mercadopago' ) .
					'</a>
			';
			return $html;
			// ===== Reaching at this point means that the URL could not be build by some reason =====

		}

	}

	/**
	 * Summary: Build Mercado Pago preapproval.
	 * Description: Create Mercado Pago preapproval structure and get init_point URL based in the order options
	 * from the cart.
	 * @return the preapproval structure.
	 */
	public function build_preapproval( $order ) {

		$preapproval = null;

		// Here we build the array that contains ordered items, from customer cart.
		foreach ( $order->get_items() as $item ) {
			if ( $item['qty'] ) {
				$product = new WC_product( $item['product_id'] );
				$product_title = method_exists( $product, 'get_description' ) ?
					$product->get_name() :
					$product->post->post_title;
				$unit_price	= $item['line_total'] + $item['line_tax'];
				$method_discount = $unit_price * ( $this->gateway_discount / 100 );
				$ship_amount = $order->get_total_shipping() + $order->get_shipping_tax();

				$currency_ratio = 1;
				$_mp_currency_conversion_v0 = get_option( '_mp_currency_conversion_v0', '' );
				if ( ! empty( $_mp_currency_conversion_v0 ) ) {
					$currency_ratio = WC_Woo_Mercado_Pago_Module::get_conversion_rate( $this->site_data['currency'] );
					$currency_ratio = $currency_ratio > 0 ? $currency_ratio : 1;
				}

				// Get the custom fields
				$frequency = get_post_meta( $item['product_id'], '_mp_recurring_frequency', true );
				$frequency_type = get_post_meta( $item['product_id'], '_mp_recurring_frequency_type', true );
				$start_date = get_post_meta( $item['product_id'], '_mp_recurring_start_date', true );
				$end_date = get_post_meta( $item['product_id'], '_mp_recurring_end_date', true );

				$preapproval = array(
					'payer_email' => ( method_exists( $order, 'get_id' ) ) ?
						$order->get_billing_email() :
						$order->billing_email,
					'back_url' => ( empty( $this->success_url ) ?
						WC_Woo_Mercado_Pago_Module::fix_url_ampersand(
							esc_url( $this->get_return_url( $order ) )
						) : $this->success_url
					),
					'reason' => $product_title,
					'external_reference' => get_option( '_mp_store_identificator', 'WC-' ) .
						( method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id ),
					'auto_recurring' => array(
						'frequency' => $frequency,
						'frequency_type' => $frequency_type,
						'transaction_amount' => ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
							floor( ( $unit_price + $ship_amount - $method_discount ) * $currency_ratio ) :
							floor( ( $unit_price + $ship_amount - $method_discount ) * $currency_ratio * 100 ) / 100,
						'currency_id' => $this->site_data['currency']
					)
				);

				if ( isset( $start_date ) && ! empty( $start_date ) ) {
					$preapproval['auto_recurring']['start_date'] = $start_date . 'T16:00:00.000-03:00';
				}
				if ( isset( $end_date ) && ! empty( $end_date ) ) {
					$preapproval['auto_recurring']['end_date'] = $end_date . 'T16:00:00.000-03:00';
				}

				// Do not set IPN url if it is a localhost.
				if ( ! strrpos( get_site_url(), 'localhost' ) ) {
					$notification_url = get_option( '_mp_custom_domain', '' );
					// Check if we have a custom URL.
					if ( empty( $notification_url ) || filter_var( $notification_url, FILTER_VALIDATE_URL ) === FALSE ) {
						$preferences['notification_url'] = WC()->api_request_url( 'WC_WooMercadoPago_SubscriptionGateway' );
					} else {
						$preferences['notification_url'] = WC_Woo_Mercado_Pago_Module::fix_url_ampersand( esc_url(
							$notification_url . '/wc-api/WC_WooMercadoPago_SubscriptionGateway/'
						) );
					}
				}

				// Set sponsor ID.
				$_test_user_v0 = get_option( '_test_user_v0', false );
				if ( ! $_test_user_v0 ) {
					$preapproval['sponsor_id'] = $this->site_data['sponsor_id'];
				}

				// Debug/log this preapproval.
				$this->write_log(
					__FUNCTION__,
					'preapproval created with following structure: ' .
					json_encode( $preapproval, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
			}
		}

		return $preapproval;
	}

	protected function create_url( $order ) {
		// Creates the order parameters by checking the cart configuration.
		$preapproval_payment = $this->build_preapproval( $order );
		// Create order preferences with Mercado Pago API request.
		try {
			$checkout_info = $this->mp->create_preapproval_payment( json_encode( $preapproval_payment ) );
			if ( $checkout_info['status'] < 200 || $checkout_info['status'] >= 300 ) {
				// Mercado Pago throwed an error.
				$this->write_log(
					__FUNCTION__,
					'mercado pago gave error, payment creation failed with error: ' . $checkout_info['response']['message']
				);
				return false;
			} elseif ( is_wp_error( $checkout_info ) ) {
				// WordPress throwed an error.
				$this->write_log(
					__FUNCTION__,
					'wordpress gave error, payment creation failed with error: ' . $checkout_info['response']['message']
				);
				return false;
			} else {
				// Obtain the URL.
				$this->write_log(
					__FUNCTION__,
					'pre-approval link generated with success from mercado pago, with structure as follow: ' .
					json_encode( $checkout_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
				// TODO: Verify sandbox availability.
				//if ( 'yes' == $this->sandbox ) {
				//	return $checkout_info['response']['sandbox_init_point'];
				//} else {
				return $checkout_info['response']['init_point'];
				//}
			}
		} catch ( MercadoPagoException $ex ) {
			// Something went wrong with the payment creation.
			$this->write_log(
				__FUNCTION__,
				'payment creation failed with exception: ' .
				json_encode( $ex, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
			return false;
		}
	}

	/*
	 * ========================================================================
	 * AUXILIARY AND FEEDBACK METHODS (SERVER SIDE)
	 * ========================================================================
	 */

	// Called automatically by WooCommerce, verify if Module is available to use.
	public function is_available() {
		if ( ! did_action( 'wp_loaded' ) ) {
			return false;
		}
		global $woocommerce;
		$w_cart = $woocommerce->cart;
		// Check for recurrent product checkout.
		if ( isset( $w_cart ) ) {
			if ( ! WC_Woo_Mercado_Pago_Module::is_subscription( $w_cart->get_cart() ) ) {
				return false;
			}
		}
		$_mp_client_id = get_option( '_mp_client_id' );
		$_mp_client_secret = get_option( '_mp_client_secret' );
		$_site_id_v0 = get_option( '_site_id_v0' );
		// Check for country support.
		if ( $_site_id_v0 != 'MLA' && $_site_id_v0 != 'MLB' && $_site_id_v0 != 'MLM') {
			return false;
		}
		$available = ( 'yes' == $this->settings['enabled'] ) &&
			! empty( $_mp_client_id ) &&
			! empty( $_mp_client_secret ) &&
			! empty( $_site_id_v0 );
		return $available;
	}

	// Get the URL to admin page.
	protected function admin_url() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.1', '>=' ) ) {
			return admin_url(
				'admin.php?page=wc-settings&tab=checkout&section=wc_woomercadopago_subscriptiongateway'
			);
		}
		return admin_url(
			'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_WooMercadoPago_SubscriptionGateway'
		);
	}

	// Display the discount in payment method title.
	public function get_payment_method_title_subscription( $title, $id ) {
		if ( ! is_checkout() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return $title;
		}
		if ( $title != $this->title || $this->gateway_discount == 0 ) {
			return $title;
		}
		if ( ! is_numeric( $this->gateway_discount ) || $this->gateway_discount < -99 || $this->gateway_discount > 99 ) {
			return $title;
		}
		$total = (float) WC()->cart->subtotal;
		$price_percent = $this->gateway_discount / 100;
		if ( $price_percent > 0 ) {
			$title .= ' (' . __( 'Discount of', 'woocommerce-mercadopago' ) . ' ' .
				strip_tags( wc_price( $total * $price_percent ) ) . ')';
		} elseif ( $price_percent < 0 ) {
			$title .= ' (' . __( 'Fee of', 'woocommerce-mercadopago' ) . ' ' .
				strip_tags( wc_price( -$total * $price_percent ) ) . ')';
		}
		return $title;
	}

	/*
	 * ========================================================================
	 * IPN MECHANICS (SERVER SIDE)
	 * ========================================================================
	 */

	/**
	 * Summary: This call checks any incoming notifications from Mercado Pago server.
	 * Description: This call checks any incoming notifications from Mercado Pago server.
	 */
	public function check_ipn_response() {
		@ob_clean();
		$this->write_log( __FUNCTION__, 'received _get content: ' . json_encode( $_GET, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE ) );
		// Setup sandbox mode, in subscription it should be always false.
		$this->mp->sandbox_mode( false );
		// Over here, $_GET should come with this JSON structure:
		// {
		// 	"topic": <string>,
		// 	"id": <string>
		// }
		// If not, the IPN is corrupted in some way.
		$data = $_GET;
		if ( isset( $data['action_mp_payment_id'] ) && ! empty( $data['action_mp_payment_id'] ) && isset( $data['action_mp_payment_amount'] ) ) {

			// ===== Client calls server when performing requests about cancelling or refund orders =====
			$this->write_log( __FUNCTION__, 'cancelling or refunding payment of ID ' . $data['action_mp_payment_id'] );
			if ( $data['action_mp_payment_action'] === 'cancel' ) {
				$response = $this->mp->cancel_payment( $data['action_mp_payment_id'] );
				$message = $response['response']['message'];
				$status = $response['status'];
				$this->write_log(
					__FUNCTION__,
					'cancel payment of id ' . $data['action_mp_payment_id'] . ' => ' .
					( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
				);
				if ( $status >= 200 && $status < 300 ) {
					header( 'HTTP/1.1 200 OK' );
					echo json_encode( array(
						'status' => 200,
						'message' => __( 'Operation successfully completed.', 'woocommerce-mercadopago' )
					) );
				} else {
					header( 'HTTP/1.1 200 OK' );
					echo json_encode( array(
						'status' => $status,
						'message' => $message
					) );
				}
			} elseif ( $data['action_mp_payment_action'] === 'refund' ) {
				$response = $this->mp->partial_refund_payment(
					$data['action_mp_payment_id'],
					(float) str_replace( ',', '.', $data['action_mp_payment_amount'] ),
					// TODO: here, we should improve by placing the actual reason and the external refarence
					__( 'Refund Payment', 'woocommerce-mercadopago' ) . ' ' . $data['action_mp_payment_id'],
					__( 'Refund Payment', 'woocommerce-mercadopago' ) . ' ' . $data['action_mp_payment_id']
				);
				$message = $response['response']['message'];
				$status = $response['status'];
				$this->write_log(
					__FUNCTION__,
					'refund payment of id ' . $data['action_mp_payment_id'] . ' => ' .
					( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
				);
				if ( $status >= 200 && $status < 300 ) {
					header( 'HTTP/1.1 200 OK' );
					echo json_encode( array(
						'status' => 200,
						'message' => __( 'Operation successfully completed.', 'woocommerce-mercadopago' )
					) );
				} else {
					header( 'HTTP/1.1 200 OK' );
					echo json_encode( array(
						'status' => $status,
						'message' => $message
					) );
				}
			}
			// ===== Client calls server when performing requests about cancelling or refund orders =====

		} elseif ( isset( $data['id'] ) && isset( $data['topic'] ) ) {

			// ===== We have received a normal IPN call for this gateway =====
			// Start process by getting the access token...
			$access_token = array( 'access_token' => $this->mp->get_access_token() );
			// Now, we should handle the topic type that has come...
			if ( $data['topic'] == 'preapproval' ) {
				// Get the preapproval reported by the IPN.
				$ipn_info = $this->mp->get_preapproval_payment( $data['id'] );
				if ( ! is_wp_error( $ipn_info ) && ( $ipn_info['status'] == 200 || $ipn_info['status'] == 201 ) ) {
					$ipn_info['response']['ipn_type'] = 'preapproval';
					do_action( 'valid_mercadopago_subscription_ipn_request', $ipn_info['response'] );
					header( 'HTTP/1.1 200 OK' );
				} else {
					$this->write_log(
						__FUNCTION__, 'got status not equal 200: ' . json_encode( $ipn_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
					);
				}
			} elseif ( $data['topic'] == 'payment' ) {
				$payment_info = $this->mp->get( '/v1/payments/' . $data['id'], $access_token, false );
				if ( ! is_wp_error( $payment_info ) && ( $payment_info['status'] == 200 || $payment_info['status'] == 201 ) ) {
					$payment_info['response']['ipn_type'] = 'payment';
					do_action( 'valid_mercadopago_subscription_ipn_request', $payment_info['response'] );
					header( 'HTTP/1.1 200 OK' );
				} else {
					$this->write_log(
						__FUNCTION__, 'got status not equal 200: ' . json_encode( $payment_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
					);
					return false;
				}
			} else {
				// We have received an unhandled topic...
				$this->write_log( __FUNCTION__, 'request failure, received an unhandled topic' );
			}
			// ===== We have received a normal IPN call for this gateway =====

		} elseif ( isset( $data['data_id'] ) && isset( $data['type'] ) ) {
			// We have received a bad, however valid) IPN call for this gateway (data is set for API V1).
			// At least, we should respond 200 to notify server that we already received it.
			header( 'HTTP/1.1 200 OK' );
		} else {
			// Reaching here means that we received an IPN call but there are no data!
			// Just kills the processment. No IDs? No process!
			$this->write_log(
				__FUNCTION__,
				'request failure, received ipn call with no data.'
			);
			wp_die( __( 'Mercado Pago Request Failure', 'woocommerce-mercadopago' ) );
		}
	}

	/**
	 * Summary: Properly handles each case of notification, based in payment status.
	 * Description: Properly handles each case of notification, based in payment status.
	 */
	public function successful_request( $data ) {
		$this->write_log( __FUNCTION__, 'starting to process ipn update...' );
		// Get the order and check its presence.
		$order_key = $data['external_reference'];
		if ( empty( $order_key ) ) {
			return;
		}
		$invoice_prefix = get_option( '_mp_store_identificator', 'WC-' );
		$id = (int) str_replace( $invoice_prefix, '', $order_key );
		$order = wc_get_order( $id );
		// Check if order exists.
		if ( ! $order ) {
			return;
		}
		// WooCommerce 3.0 or later.
		$order_id = ( method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id );
		// Check if we have the correct order.
		if ( $order_id !== $id ) {
			return;
		}
		$this->write_log(
			__FUNCTION__,
			'updating metadata and status with data: ' .
			json_encode( $data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
		);
		// Here, we process the status... this is the business rules!
		// Reference: https://www.mercadopago.com.br/developers/en/api-docs/basic-checkout/ipn/payment-status/
		$status = isset( $data['status'] ) ? $data['status'] : 'pending';
		if ( $data['ipn_type'] == 'payment' ) {
			// ===== Here we process IPN from recurrency payments =====
			$total_paid = isset( $data['transaction_details']['total_paid_amount'] ) ? $data['transaction_details']['total_paid_amount'] : 0.00;
			$total_refund = isset( $data['transaction_amount_refunded'] ) ? $data['transaction_amount_refunded'] : 0.00;
			$total = $data['transaction_amount'];
			// WooCommerce 3.0 or later.
			if ( method_exists( $order, 'update_meta_data' ) ) {
				// Updates the type of gateway.
				$order->update_meta_data( '_used_gateway', 'WC_WooMercadoPago_SubscriptionGateway' );
				if ( ! empty( $data['payer']['email'] ) ) {
					$order->update_meta_data( __( 'Payer email', 'woocommerce-mercadopago' ), $data['payer']['email'] );
				}
				if ( ! empty( $data['payment_type_id'] ) ) {
					$order->update_meta_data( __( 'Payment type', 'woocommerce-mercadopago' ), $data['payment_type_id'] );
				}
				if ( ! empty( $data['id'] ) ) {
					$order->update_meta_data(
						'Mercado Pago - Payment ID ' . $data['id'],
						'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
						']/[Amount ' . $total .
						']/[Paid ' . $total_paid .
						']/[Refund ' . $total_refund . ']'
					);
					$payment_ids_str = $order->get_meta( '_Mercado_Pago_Sub_Payment_IDs' );
					$payment_ids = array();
					if ( ! empty( $payment_ids_str ) ) {
						$payment_ids = explode( ', ', $payment_ids_str );
					}
					$payment_ids[] = $data['id'];
					$order->update_meta_data( '_Mercado_Pago_Sub_Payment_IDs', implode( ', ', $payment_ids ) );
				}
				$order->save();
			} else {
				// Updates the type of gateway.
				update_post_meta( $order->id, '_used_gateway', 'WC_WooMercadoPago_SubscriptionGateway' );
				if ( ! empty( $data['payer']['email'] ) ) {
					update_post_meta( $order_id, __( 'Payer email', 'woocommerce-mercadopago' ), $data['payer']['email'] );
				}
				if ( ! empty( $data['payment_type_id'] ) ) {
					update_post_meta( $order_id, __( 'Payment type', 'woocommerce-mercadopago' ), $data['payment_type_id'] );
				}
				if ( ! empty( $data['id'] ) ) {
					update_post_meta(
						$order_id,
						'Mercado Pago - Payment ID ' . $data['id'],
						'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
						']/[Amount ' . $total .
						']/[Paid ' . $total_paid .
						']/[Refund ' . $total_refund . ']'
					);
					$payment_ids_str = get_post_meta( $order->id, '_Mercado_Pago_Sub_Payment_IDs', true );
					$payment_ids = array();
					if ( ! empty( $payment_ids_str ) ) {
						$payment_ids = explode( ', ', $payment_ids_str );
					}
					$payment_ids[] = $data['id'];
					update_post_meta( $order_id, '_Mercado_Pago_Sub_Payment_IDs', implode( ', ', $payment_ids ) );
				}
			}
			// ===== Here we process IPN from recurrency payments =====
		} elseif ( $data['ipn_type'] == 'preapproval' ) {
			// ===== Here we process IPN from recurrency preapprovals =====
			// WooCommerce 3.0 or later.
			if ( method_exists( $order, 'update_meta_data' ) ) {
				// Updates the type of gateway.
				$order->update_meta_data( '_used_gateway', 'WC_WooMercadoPago_SubscriptionGateway' );
				if ( ! empty( $data['payer_email'] ) ) {
					$order->update_meta_data( __( 'Payer email', 'woocommerce-mercadopago' ), $data['payer_email'] );
				}
				if ( ! empty( $data['id'] ) ) {
					$order->update_meta_data(
						'Mercado Pago Pre-Approval',
						'[ID ' . $data['id'] .
						']/[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
						']/[Amount ' . $data['auto_recurring']['transaction_amount'] .
						']/[End ' . date( 'Y-m-d', strtotime( $data['auto_recurring']['end_date'] ) ) . ']'
					);
				}
				$order->save();
			} else {
				// Updates the type of gateway.
				update_post_meta( $order->id, '_used_gateway', 'WC_WooMercadoPago_SubscriptionGateway' );
				if ( ! empty( $data['payer_email'] ) ) {
					update_post_meta( $order_id, __( 'Payer email', 'woocommerce-mercadopago' ), $data['payer_email'] );
				}
				if ( ! empty( $data['id'] ) ) {
					update_post_meta(
						$order_id,
						'Mercado Pago Pre-Approval',
						'[ID ' . $data['id'] .
						']/[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
						']/[Amount ' . $data['auto_recurring']['transaction_amount'] .
						']/[End ' . date( 'Y-m-d', strtotime( $data['auto_recurring']['end_date'] ) ) . ']'
					);
				}
			}
			// ===== Here we process IPN from recurrency preapprovals =====
		}
		// Switch the status and update in WooCommerce.
		$this->write_log(
			__FUNCTION__,
			'Changing order status to: ' .
			WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( str_replace( '_', '', $status ) )
		);
		switch ( $status ) {
			case 'authorized':
				$order->add_order_note(
					'Mercado Pago: ' . __( 'Subscription approved, waiting payment confirmation.', 'woocommerce-mercadopago' )
				);
					$order->payment_complete();
					$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'pending' )
				);
				break;	
			case 'approved':
				$order->add_order_note(
					'Mercado Pago: ' . __( 'Payment approved.', 'woocommerce-mercadopago' )
				);
				$order->payment_complete();
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'approved' )
				);
				break;
			case 'pending':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'pending' )
				);
				$order->add_order_note(
					'Mercado Pago: ' . __( 'Customer haven\'t paid yet.', 'woocommerce-mercadopago' )
				);
				break;
			case 'in_process':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'on-hold' ),
					'Mercado Pago: ' . __( 'Payment under review.', 'woocommerce-mercadopago' )
				);
				break;
			case 'rejected':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'rejected' ),
					'Mercado Pago: ' . __( 'The payment was refused. The customer can try again.', 'woocommerce-mercadopago' )
				);
				break;
			case 'refunded':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'refunded' ),
					'Mercado Pago: ' . __( 'The payment was refunded to the customer.', 'woocommerce-mercadopago' )
				);
				break;
			case 'cancelled':
				$this->process_cancel_order_meta_box_actions( $order );
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'cancelled' ),
					'Mercado Pago: ' . __( 'The payment was cancelled.', 'woocommerce-mercadopago' )
				);
				break;
			case 'in_mediation':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'inmediation' )
				);
				$order->add_order_note(
					'Mercado Pago: ' . __( 'The payment is under mediation or it was charged-back.', 'woocommerce-mercadopago' )
				);
				break;
			case 'charged-back':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'chargedback' )
				);
				$order->add_order_note(
					'Mercado Pago: ' . __( 'The payment is under mediation or it was charged-back.', 'woocommerce-mercadopago' )
				);
				break;
			default:
				break;
		}
	}

}
