<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer - Marcelo Mita / marcelo.mita@mercadolivre.com
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// This include Mercado Pago library SDK
require_once dirname( __FILE__ ) . '/sdk/lib/mercadopago.php';

/**
 * Summary: Extending from WooCommerce Payment Gateway class.
 * Description: This class implements Mercado Pago pse payment method.
 * @since 3.0.16
 */
class WC_WooMercadoPago_PSEGateway extends WC_Payment_Gateway {

	public function __construct( $is_instance = false ) {

		// Mercao Pago instance.
		$this->site_data = WC_Woo_Mercado_Pago_Module::get_site_data( true );
		$this->mp = new MP(
			WC_Woo_Mercado_Pago_Module::get_module_version(),
			get_option( '_mp_access_token' )
		);
		$email = ( wp_get_current_user()->ID != 0 ) ? wp_get_current_user()->user_email : null;
		$this->mp->set_email( $email );
		$locale = get_locale();
		$locale = ( strpos( $locale, '_' ) !== false && strlen( $locale ) == 5 ) ? explode( '_', $locale ) : array('','');
		$this->mp->set_locale( $locale[1] );

		// WooCommerce fields.
		$this->id = 'woo-mercado-pago-pse';
		$this->supports = array( 'products', 'refunds' );
		$this->icon = apply_filters(
			'woocommerce_mercadopago_icon',
			plugins_url( 'assets/images/mercadopago.png', plugin_dir_path( __FILE__ ) )
		);

		$this->method_title = __( 'Mercado Pago - PSE', 'woocommerce-mercadopago' );
		$this->method_description = '<img width="200" height="52" src="' .
			plugins_url( 'assets/images/mplogo.png', plugin_dir_path( __FILE__ ) ) .
		'"><br><br><strong>' .
			__( 'We give you the possibility to adapt the payment experience you want to offer 100% in your website, mobile app or anywhere you want. You can build the design that best fits your business model, aiming to maximize conversion.', 'woocommerce-mercadopago' ) .
		'</strong>';

		//$this->sandbox = get_option( '_mp_sandbox_mode', false );
		$this->sandbox = false;
		$this->mp->sandbox_mode( $this->sandbox );

		// How checkout is shown.
		$this->title              = $this->get_option( 'title', __( 'Mercado Pago - PSE', 'woocommerce-mercadopago' ) );
		$this->description        = $this->get_option( 'description' );
		// How checkout payment behaves.
// 		$this->coupon_mode        = $this->get_option( 'coupon_mode', 'no' );
		$this->stock_reduce_mode  = $this->get_option( 'stock_reduce_mode', 'no' );
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
			'woocommerce_api_wc_woomercadopago_psegateway',
			array( $this, 'check_ipn_response' )
		);
		// Used by IPN to process valid incomings.
		add_action(
			'valid_mercadopago_pse_ipn_request',
			array( $this, 'successful_request' )
		);
		// process the cancel order meta box order action
		add_action(
			'woocommerce_order_action_cancel_order',
			array( $this, 'process_cancel_order_meta_box_actions' )
		);
		// Used in settings page to hook "save settings" action.
		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'custom_process_admin_options' )
		);
		// Scripts for custom checkout.
		add_action(
			'wp_enqueue_scripts',
			array( $this, 'add_checkout_scripts_pse' )
		);
		// Apply the discounts.
		add_action(
			'woocommerce_cart_calculate_fees',
			array( $this, 'add_discount_pse' ), 10
		);
		// Display discount in payment method title.
		add_filter(
			'woocommerce_gateway_title',
			array( $this, 'get_payment_method_title_pse' ), 10, 2
		);

		if ( ! empty( $this->settings['enabled'] ) && $this->settings['enabled'] == 'yes' ) {
			if ( ! $is_instance ) {
				// Scripts for order configuration.
				add_action(
					'woocommerce_after_checkout_form',
					array( $this, 'add_mp_settings_script_pse' )
				);
				// Checkout updates.
				add_action(
					'woocommerce_thankyou_' . $this->id,
					array( $this, 'update_mp_settings_script_pse' )
				);
			}
		}

	}

	/**
	 * Summary: Initialise Gateway Settings Form Fields.
	 * Description: Initialise Gateway settings form fields with a customized page.
	 */
	public function init_form_fields() {

		// Show message if credentials are not properly configured.
		$_site_id_v1 = get_option( '_site_id_v1', '' );
		if ( empty( $_site_id_v1 ) ) {
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
		}

		// If module is disabled, we do not need to load and process the settings page.
		if ( empty( $this->settings['enabled'] ) || 'no' == $this->settings['enabled'] ) {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woocommerce-mercadopago' ),
					'type' => 'checkbox',
					'label' => __( 'Enable PSE Payment Method', 'woocommerce-mercadopago' ),
					'default' => 'no'
				)
			);
			return;
		}

		// This array draws each UI (text, selector, checkbox, label, etc).
		$this->form_fields = array(
			'enabled' => array(
				'title' => __( 'Enable/Disable', 'woocommerce-mercadopago' ),
				'type' => 'checkbox',
				'label' => __( 'Enable PSE Payment Method', 'woocommerce-mercadopago' ),
				'default' => 'no'
			),
			'checkout_options_title' => array(
				'title' => __( 'PSE Interface: How checkout is shown', 'woocommerce-mercadopago' ),
				'type' => 'title'
			),
			'title' => array(
				'title' => __( 'Title', 'woocommerce-mercadopago' ),
				'type' => 'text',
				'description' => __( 'Title shown to the client in the pse.', 'woocommerce-mercadopago' ),
				'default' => __( 'Mercado Pago - PSE', 'woocommerce-mercadopago' )
			),
			'description' => array(
				'title' => __( 'Description', 'woocommerce-mercadopago' ),
				'type' => 'textarea',
				'description' => __( 'Description shown to the client in the pse.', 'woocommerce-mercadopago' ),
				'default' => __( 'Pay with Mercado Pago', 'woocommerce-mercadopago' )
			),
			'payment_title' => array(
				'title' => __( 'Payment Options: How payment options behaves', 'woocommerce-mercadopago' ),
				'type' => 'title'
			),
// 			'coupon_mode' => array(
// 				'title' => __( 'Coupons', 'woocommerce-mercadopago' ),
// 				'type' => 'checkbox',
// 				'label' => __( 'Enable coupons of discounts', 'woocommerce-mercadopago' ),
// 				'default' => 'no',
// 				'description' => __( 'If there is a Mercado Pago campaign, allow your store to give discounts to customers.', 'woocommerce-mercadopago' )
// 			),
			'stock_reduce_mode' => array(
				'title' => __( 'Stock Reduce', 'woocommerce-mercadopago' ),
				'type' => 'checkbox',
				'label' => __( 'Reduce Stock in Order Generation', 'woocommerce-mercadopago' ),
				'default' => 'no',
				'description' => __( 'Enable this to reduce the stock on order creation. Disable this to reduce <strong>after</strong> the payment approval.', 'woocommerce-mercadopago' )
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
				if ( $key == 'gateway_discount') {
					if ( ! is_numeric( $value ) || empty ( $value ) || $value < -99 || $value > 99 ) {
						$this->settings[$key] = 0;
					} else {
						$this->settings[$key] = $value;
					}
				} else {
					$this->settings[$key] = $this->get_field_value( $key, $field, $post_data );
				}
			}
		}
		$_site_id_v1 = get_option( '_site_id_v1', '' );
		$is_test_user = get_option( '_test_user_v1', false );
		if ( ! empty( $_site_id_v1 ) && ! $is_test_user ) {
			// Create MP instance.
			$mp = new MP(
				WC_Woo_Mercado_Pago_Module::get_module_version(),
				get_option( '_mp_access_token' )
			);
			$email = ( wp_get_current_user()->ID != 0 ) ? wp_get_current_user()->user_email : null;
			$mp->set_email( $email );
			$locale = get_locale();
			$locale = ( strpos( $locale, '_' ) !== false && strlen( $locale ) == 5 ) ? explode( '_', $locale ) : array('','');
			$mp->set_locale( $locale[1] );
			// Analytics.
			$infra_data = WC_Woo_Mercado_Pago_Module::get_common_settings();
			$infra_data['checkout_custom_pse'] = ( $this->settings['enabled'] == 'yes' ? 'true' : 'false' );
// 			$infra_data['checkout_custom_pse_coupon'] = ( $this->settings['coupon_mode'] == 'yes' ? 'true' : 'false' );
			$response = $mp->analytics_save_settings( $infra_data );
		}
		// Apply updates.
		return update_option(
			$this->get_option_key(),
			apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings )
		);
	}

	/**
	 * Handles the manual order refunding in server-side.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {

		$payments = get_post_meta( $order_id, '_Mercado_Pago_Payment_IDs', true );

		// Validate.
		if ( $this->mp == null || empty( $payments ) ) {
			$this->write_log( __FUNCTION__, 'no payments or credentials invalid' );
			return false;
		}

		// Processing data about this refund.
		$total_available = 0;
		$payment_structs = array();
		$payment_ids = explode( ', ', $payments );
		foreach ( $payment_ids as $p_id ) {
			$p = get_post_meta( $order_id, 'Mercado Pago - Payment ' . $p_id, true );
			$p = explode( '/', $p );
			$paid_arr = explode( ' ', substr( $p[2], 1, -1 ) );
			$paid = ( (float) $paid_arr[1] );
			$refund_arr = explode( ' ', substr( $p[3], 1, -1 ) );
			$refund = ( (float) $refund_arr[1] );
			$p_struct = array( 'id' => $p_id, 'available_to_refund' => $paid - $refund );
			$total_available += $paid - $refund;
			$payment_structs[] = $p_struct;
		}
    
		$this->write_log( __FUNCTION__,
			'refunding ' . $amount . ' because of ' . $reason . ' and payments ' .
			json_encode( $payment_structs, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
		);

		// Do not allow refund more than available or invalid amounts.
		if ( $amount > $total_available || $amount <= 0 ) {
			return false;
		}

		// Iteratively refunfind amount, taking in consideration multiple payments.
		$remaining_to_refund = $amount;
		foreach ( $payment_structs as $to_refund ) {
			if ( $remaining_to_refund <= $to_refund['available_to_refund'] ) {
				// We want to refund an amount that is less than the available for this payment, so we
				// can just refund and return.
				$response = $this->mp->partial_refund_payment(
					$to_refund['id'], $remaining_to_refund,
					$reason, $this->invoice_prefix . $order_id
				);
				$message = $response['response']['message'];
				$status = $response['status'];
				$this->write_log( __FUNCTION__,
					'refund payment of id ' . $p_id . ' => ' .
					( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
				);
				if ( $status >= 200 && $status < 300 ) {
					return true;
				} else {
					return false;
				}
			} elseif ( $to_refund['available_to_refund'] > 0 ) {
				// We want to refund an amount that exceeds the available for this payment, so we
				// totally refund this payment, and try to complete refund in other/next payments.
				$response = $this->mp->partial_refund_payment(
					$to_refund['id'], $to_refund['available_to_refund'],
					$reason, $this->invoice_prefix . $order_id
				);
				$message = $response['response']['message'];
				$status = $response['status'];
				$this->write_log( __FUNCTION__,
					'refund payment of id ' . $p_id . ' => ' .
					( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
				);
				if ( $status < 200 || $status >= 300 ) {
					return false;
				}
				$remaining_to_refund -= $to_refund['available_to_refund'];
			}
			if ( $remaining_to_refund == 0 )
				return true;
		}

		// Reaching here means that there we run out of payments, and there is an amount
		// remaining to be refund, which is impossible as it implies refunding more than
		// available on paid amounts.
		return false;
	}

	/**
	 * Handles the manual order cancellation in server-side.
	 */
	public function process_cancel_order_meta_box_actions( $order ) {

		$used_gateway = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_used_gateway' ) :
			get_post_meta( $order->id, '_used_gateway', true );
		$payments = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_Mercado_Pago_Payment_IDs' ) :
			get_post_meta( $order->id, '_Mercado_Pago_Payment_IDs',	true );

		// A watchdog to prevent operations from other gateways.
		if ( $used_gateway != 'WC_WooMercadoPago_PSEGateway' ) {
			return;
		}

		$this->write_log( __FUNCTION__, 'cancelling payments for ' . $payments );

		// Canceling the order and all of its payments.
		if ( $this->mp != null && ! empty( $payments ) ) {
			$payment_ids = explode( ', ', $payments );
			foreach ( $payment_ids as $p_id ) {
				$response = $this->mp->cancel_payment( $p_id );
				$message = $response['response']['message'];
				$status = $response['status'];
				$this->write_log( __FUNCTION__,
					'cancel payment of id ' . $p_id . ' => ' .
					( $status >= 200 && $status < 300 ? 'SUCCESS' : 'FAIL - ' . $message )
				);
			}
		} else {
			$this->write_log( __FUNCTION__, 'no payments or credentials invalid' );
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

	public function add_mp_settings_script_pse() {
		$client_id = WC_Woo_Mercado_Pago_Module::get_client_id( get_option( '_mp_access_token' ) );
		$is_test_user = get_option( '_test_user_v1', false );
		if ( ! empty( $client_id ) && ! $is_test_user ) {
			$w = WC_Woo_Mercado_Pago_Module::woocommerce_instance();
			$available_payments = array();
			$gateways = WC()->payment_gateways->get_available_payment_gateways();
			foreach ( $gateways as $g ) {
				$available_payments[] = $g->id;
			}
			$available_payments = str_replace( '-', '_', implode( ', ', $available_payments ) );
			if ( wp_get_current_user()->ID != 0 ) {
				$logged_user_email = wp_get_current_user()->user_email;
			} else {
				$logged_user_email = null;
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
					MA.setInstalledModules( '<?php echo $available_payments; ?>' );
					MA.post();
				} catch(err) {}
			</script>
			<?php
		}
	}

	public function update_mp_settings_script_pse( $order_id ) {
		$access_token = get_option( '_mp_access_token' );
		$is_test_user = get_option( '_test_user_v1', false );
		if ( ! empty( $access_token ) && ! $is_test_user ) {
			if ( get_post_meta( $order_id, '_used_gateway', true ) != 'WC_WooMercadoPago_PSEGateway' ) {
				return;
			}
			$this->write_log( __FUNCTION__, 'updating order of ID ' . $order_id );
			echo '<script src="https://secure.mlstatic.com/modules/javascript/analytics.js"></script>
			<script type="text/javascript">
				try {
					var MA = ModuleAnalytics;
					MA.setToken( ' . $access_token . ' );
					MA.setPaymentType("pse");
					MA.setCheckoutType("custom");
					MA.put();
				} catch(err) {}
			</script>';
		}

		$order = wc_get_order( $order_id );
		$used_gateway = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_used_gateway' ) :
			get_post_meta( $order->id, '_used_gateway', true );
		$transaction_details = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_transaction_details_pse' ) :
			get_post_meta( $order->id, '_transaction_details_pse', true );

		// A watchdog to prevent operations from other gateways.
		if ( $used_gateway != 'WC_WooMercadoPago_PSEGateway' || empty( $transaction_details ) ) {
			return;
		}

		$html = '<p>' .
			__( 'Thank you for your order. Please, transfer the money to get your order approved.', 'woocommerce-mercadopago' ) .
		'</p>' .
		'<p><iframe src="' . $transaction_details . '" style="width:100%; height:1000px;"></iframe></p>' .
		'<a id="submit-payment" target="_blank" href="' . $transaction_details . '" class="button alt"' .
		' style="font-size:1.25rem; width:75%; height:48px; line-height:24px; text-align:center;">' .
			__( 'Transfer', 'woocommerce-mercadopago' ) .
		'</a> ';
		$added_text = '<p>' . $html . '</p>';
		echo $added_text;
	}

	public function add_checkout_scripts_pse() {
		if ( is_checkout() && $this->is_available() ) {
			if ( ! get_query_var( 'order-received' ) ) {
				wp_enqueue_style(
					'woocommerce-mercadopago-style',
					plugins_url( 'assets/css/custom_checkout_mercadopago.css', plugin_dir_path( __FILE__ ) )
				);
				wp_enqueue_script(
					'woocommerce-mercadopago-pse-js',
					'https://secure.mlstatic.com/sdk/javascript/v1/mercadopago.js'
				);
			}
		}
	}

	public function payment_fields() {

		$amount = $this->get_order_total();
		$logged_user_email = ( wp_get_current_user()->ID != 0 ) ? wp_get_current_user()->user_email : null;
		$customer = isset( $logged_user_email ) ? $this->mp->get_or_create_customer( $logged_user_email ) : null;
		$discount_action_url = get_site_url() . '/index.php/woocommerce-mercadopago/?wc-api=WC_WooMercadoPago_PSEGateway';
		$address = get_user_meta( wp_get_current_user()->ID, 'shipping_address_1', true );
		$address_2 = get_user_meta( wp_get_current_user()->ID, 'shipping_address_2', true );
		$address .= ( ! empty( $address_2 ) ? ' - ' . $address_2 : '' );
		$country = get_user_meta( wp_get_current_user()->ID, 'shipping_country', true );
		$address .= ( ! empty( $country ) ? ' - ' . $country : '' );

		$currency_ratio = 1;
		$_mp_currency_conversion_v1 = get_option( '_mp_currency_conversion_v1', '' );
		if ( ! empty( $_mp_currency_conversion_v1 ) ) {
			$currency_ratio = WC_Woo_Mercado_Pago_Module::get_conversion_rate( $this->site_data['currency'] );
			$currency_ratio = $currency_ratio > 0 ? $currency_ratio : 1;
		}
    
    $payment_methods = $this->mp->get_payment_methods();
    $financial_institutions = null;
    if($payment_methods['status'] == '200') {
      foreach($payment_methods['response'] as $payment_method) {
        if( $payment_method['id'] == 'pse' ) {
          $financial_institutions = $payment_method['financial_institutions'];
        }
      }
    }
    
      
		$parameters = array(
			'amount'                 => $amount,
			// ===
			'site_id'                => get_option( '_site_id_v1' ),
// 			'coupon_mode'            => isset( $logged_user_email ) ? $this->coupon_mode : 'no',
			'discount_action_url'    => $discount_action_url,
			'payer_email'            => $logged_user_email,
			// ===
			'images_path'            => plugins_url( 'assets/images/', plugin_dir_path( __FILE__ ) ),
			'currency_ratio'         => $currency_ratio,
			'woocommerce_currency'   => get_woocommerce_currency(),
			'account_currency'       => $this->site_data['currency'],
			// ===
      'financial_institutions' => $financial_institutions,
      'pse_data'               => array(
					'docType' => '',
					'docNumber' => '',
					'bank' => '',
					'personType' => ''
			),
			'path_to_javascript'     => plugins_url( 'assets/js/pse.js', plugin_dir_path( __FILE__ ) )
		);
    
		wc_get_template(
			'pse/pse-form.php',
			$parameters,
			'woo/mercado/pago/module/',
			WC_Woo_Mercado_Pago_Module::get_templates_path()
		);
	}

	/**
	* Summary: Handle the payment and processing the order.
	* Description: This function is called after we click on [place_order] button, and each field is
	* passed to this function through $_POST variable.
	* @return an array containing the result of the processment and the URL to redirect.
	*/
	public function process_payment( $order_id ) {

		if ( ! isset( $_POST['mercadopago_pse'] ) ) {
			return;
		}
		$pse_checkout = apply_filters( 'wc_mercadopagopse_pse_checkout', $_POST['mercadopago_pse'] );
    
		$order = wc_get_order( $order_id );

		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( '_used_gateway', 'WC_WooMercadoPago_PSEGateway' );
			$order->save();
		} else {
 			update_post_meta( $order_id, '_used_gateway', 'WC_WooMercadoPago_PSEGateway' );
 		}
    
 		// Check fullfilment of PSE form.
 		if ( get_option( '_site_id_v1' ) == 'MCO' ) {
			if ( ! isset( $pse_checkout['docType'] )    || empty( $pse_checkout['docType'] ) ||
   				 ! isset( $pse_checkout['docNumber'] )  || empty( $pse_checkout['docNumber'] ) ||
    			 ! isset( $pse_checkout['bank'] )       || empty( $pse_checkout['bank'] ) ||
				   ! isset( $pse_checkout['personType'] ) || empty( $pse_checkout['personType'] ) ) {
				wc_add_notice(
					'<p>' .
						__( 'A problem was occurred when processing your payment. Are you sure you have correctly filled all information in the checkout form?', 'woocommerce-mercadopago' ) .
					'</p>',
					'error'
				);
				return array(
					'result' => 'fail',
					'redirect' => '',
				);
			}
		}

 		if ( isset( $pse_checkout['amount'] ) && ! empty( $pse_checkout['amount'] ) &&
			isset( $pse_checkout['paymentMethodId'] ) && ! empty( $pse_checkout['paymentMethodId'] ) ) {
 			
      $response = $this->create_url( $order, $pse_checkout );
      
			if ( array_key_exists( 'status', $response ) ) {
				if ( $response['status'] == 'pending' ) {
					if ( $response['status_detail'] == 'pending_waiting_transfer' ) {
						WC()->cart->empty_cart();
						if ( $this->stock_reduce_mode == 'yes' ) {
							$order->reduce_order_stock();
						}
						// WooCommerce 3.0 or later.
						if ( method_exists( $order, 'update_meta_data' ) ) {
							$order->update_meta_data( '_transaction_details_pse', $response['transaction_details']['external_resource_url'] );
							$order->save();
						} else {
							update_post_meta(
								$order->id,
								'_transaction_details_pse',
								$response['transaction_details']['external_resource_url']
							);
						}
						// Shows some info in checkout page.
						$order->add_order_note(
							'Mercado Pago: ' .
							__( 'Customer haven\'t paid yet.', 'woocommerce-mercadopago' )
						);
						$order->add_order_note(
							'Mercado Pago: ' .
							__( 'Link to bank transfer ', 'woocommerce-mercadopago' ) .
							'<a target="_blank" href="' .
							$response['transaction_details']['external_resource_url'] . '">' .
							__( 'here', 'woocommerce-mercadopago' ) .
							'</a>', 1, false
						);
						return array(
							'result' => 'success',
							'redirect' => $order->get_checkout_order_received_url()
						);
					}
				}
			} else {
				// Process when fields are imcomplete.
				wc_add_notice(
					'<p>' .
						__( 'A problem was occurred when processing your payment. Are you sure you have correctly filled all information in the checkout form? ', 'woocommerce-mercadopago' ) . ' MERCADO PAGO: ' .
						WC_Woo_Mercado_Pago_Module::get_common_error_messages( $response ) .
					'</p>',
					'error'
				);
				return array(
					'result' => 'fail',
					'redirect' => '',
				);
			}
		} else {
			// Process when fields are imcomplete.
			wc_add_notice(
				'<p>' .
					__( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago' ) .
				'</p>',
				'error'
			);
			return array(
				'result' => 'fail',
				'redirect' => '',
			);
		}

	}

	/**
	* Summary: Build Mercado Pago preference.
	* Description: Create Mercado Pago preference and get init_point URL based in the order options
	* from the cart.
	* @return the preference object.
	*/
	private function build_payment_preference( $order, $pse_checkout ) {

		// A string to register items (workaround to deal with API problem that shows only first item).
		$items = array();
		$order_total = 0;
		$list_of_items = array();
		// Find currency rate.
		$currency_ratio = 1;
		$_mp_currency_conversion_v1 = get_option( '_mp_currency_conversion_v1', '' );
		if ( ! empty( $_mp_currency_conversion_v1 ) ) {
			$currency_ratio = WC_Woo_Mercado_Pago_Module::get_conversion_rate( $this->site_data['currency'] );
			$currency_ratio = $currency_ratio > 0 ? $currency_ratio : 1;
		}

		// Here we build the array that contains ordered items, from customer cart.
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $item ) {
				if ( $item['qty'] ) {
					$product = new WC_product( $item['product_id'] );
					$product_title = method_exists( $product, 'get_description' ) ?
						$product->get_name() :
						$product->post->post_title;
					$product_content = method_exists( $product, 'get_description' ) ?
						$product->get_description() :
						$product->post->post_content;
					// Calculates line amount and discounts.
					$line_amount = $item['line_total'] + $item['line_tax'];
					$discount_by_gateway = (float) $line_amount * ( $this->gateway_discount / 100 );
					$order_total += ($line_amount - $discount_by_gateway);
					// Add the item.
					array_push( $list_of_items, $product_title . ' x ' . $item['qty'] );
					array_push( $items, array(
						'id' => $item['product_id'],
						'title' => html_entity_decode( $product_title ) . ' x ' . $item['qty'],
						'description' => sanitize_file_name( html_entity_decode(
							strlen( $product_content ) > 230 ?
							substr( $product_content, 0, 230 ) . '...' :
							$product_content
						) ),
						'picture_url' => sizeof( $order->get_items() ) > 1 ?
							plugins_url( 'assets/images/cart.png', plugin_dir_path( __FILE__ ) ) :
							wp_get_attachment_url( $product->get_image_id() ),
						'category_id' => get_option( '_mp_category_name', 'others' ),
						'quantity' => 1,
						'unit_price' => ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
							floor( ( $line_amount - $discount_by_gateway ) * $currency_ratio ) :
							floor( ( $line_amount - $discount_by_gateway ) * $currency_ratio * 100 ) / 100
					) );
				}
			}
		}

		// Creates the shipment cost structure.
		$ship_cost = ($order->get_total_shipping() + $order->get_shipping_tax());
		if ( $ship_cost > 0 ) {
			$order_total += $ship_cost;
			$item = array(
				'title' => method_exists( $order, 'get_id' ) ?
					$order->get_shipping_method() :
					$order->shipping_method,
				'description' => __( 'Shipping service used by store', 'woocommerce-mercadopago' ),
				'category_id' => get_option( '_mp_category_name', 'others' ),
				'quantity' => 1,
				'unit_price' => ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
					floor( $ship_cost * $currency_ratio ) :
					floor( $ship_cost * $currency_ratio * 100 ) / 100
			);
			$items[] = $item;
		}

		// Discounts features.
		if ( isset( $pse_checkout['discount'] ) && ! empty( $pse_checkout['discount'] ) &&
			isset( $pse_checkout['coupon_code'] ) && ! empty( $pse_checkout['coupon_code'] ) &&
			$pse_checkout['discount'] > 0 && WC()->session->chosen_payment_method == 'woo-mercado-pago-pse' ) {
		 	$item = array(
				'title' => __( 'Discount provided by store', 'woocommerce-mercadopago' ),
				'description' => __( 'Discount provided by store', 'woocommerce-mercadopago' ),
				'category_id' => get_option( '_mp_category_name', 'others' ),
				'quantity' => 1,
				'unit_price' => ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
					-floor( $pse_checkout['discount'] * $currency_ratio ) :
					-floor( $pse_checkout['discount'] * $currency_ratio * 100 ) / 100
			);
	 		$items[] = $item;
		}

		// Build additional information from the customer data.
		$payer_additional_info = array(
			'first_name' => ( method_exists( $order, 'get_id' ) ?
				html_entity_decode( $order->get_billing_first_name() ) :
				html_entity_decode( $order->billing_first_name ) ),
			'last_name' => ( method_exists( $order, 'get_id' ) ?
				html_entity_decode( $order->get_billing_last_name() ) :
				html_entity_decode( $order->billing_last_name ) ),
			//'registration_date' =>
			'phone' => array(
				//'area_code' =>
				'number' => ( method_exists( $order, 'get_id' ) ?
					$order->get_billing_phone() :
					$order->billing_phone )
			),
			'address' => array(
				'zip_code' => ( method_exists( $order, 'get_id' ) ?
					$order->get_billing_postcode() :
					$order->billing_postcode
				),
				//'street_number' =>
				'street_name' => html_entity_decode( method_exists( $order, 'get_id' ) ?
					$order->get_billing_address_1() . ' / ' .
					$order->get_billing_city() . ' ' .
					$order->get_billing_state() . ' ' .
					$order->get_billing_country() :
					$order->billing_address_1 . ' / ' .
					$order->billing_city . ' ' .
					$order->billing_state . ' ' .
					$order->billing_country
				)
			)
		);

		// Create the shipment address information set.
		$shipments = array(
			'receiver_address' => array(
				'zip_code' => method_exists( $order, 'get_id' ) ?
					$order->get_shipping_postcode() :
					$order->shipping_postcode,
				//'street_number' =>
				'street_name' => html_entity_decode( method_exists( $order, 'get_id' ) ?
					$order->get_shipping_address_1() . ' ' .
					$order->get_shipping_address_2() . ' ' .
					$order->get_shipping_city() . ' ' .
					$order->get_shipping_state() . ' ' .
					$order->get_shipping_country() :
					$order->shipping_address_1 . ' ' .
					$order->shipping_address_2 . ' ' .
					$order->shipping_city . ' ' .
					$order->shipping_state . ' ' .
					$order->shipping_country
				),
				//'floor' =>
				'apartment' => method_exists( $order, 'get_id' ) ?
					$order->get_shipping_address_2() :
					$order->shipping_address_2
			)
		);
    
    $ip = "";
    if ( ! empty($_SERVER['HTTP_CLIENT_IP'] ) ) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif ( ! empty($_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

		// The payment preference.
		$preferences = array(
			'date_of_expiration' => $date_of_expiration,
			'transaction_amount' => ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
				floor( $order_total * $currency_ratio ) :
				floor( $order_total * $currency_ratio * 100 ) / 100,
			'description' => implode( ', ', $list_of_items ),
			'payment_method_id' => $pse_checkout['paymentMethodId'],
			'payer' => array(
				'email' => method_exists( $order, 'get_id' ) ?
					$order->get_billing_email() :
					$order->billing_email
			),
			'external_reference' => get_option( '_mp_store_identificator', 'WC-' ) .
				( method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id ),
			'statement_descriptor' => get_option( '_mp_statement_descriptor', 'Mercado Pago' ),
			'binary_mode' => ( $this->binary_mode == 'yes' ),
			'additional_info' => array(
				'items' => $items,
				'payer' => $payer_additional_info,
				'shipments' => $shipments        
			),
      'callback_url' => $order->get_checkout_order_received_url()
		);
    
    // PSE Fields
    if( $pse_checkout['paymentMethodId'] == 'pse' ) {
      $preferences['additional_info']['ip_address'] = $ip;
      $preferences['payer']['identification'] = array(
        'type' => $pse_checkout['docType'],
        'number' => $pse_checkout['docNumber'],
      );
      $preferences['transaction_details'] = array(
        'financial_institution' => $pse_checkout['bank']
      );
      $preferences['payer']['entity_type'] = $pse_checkout['personType'];
    }
    
		// Do not set IPN url if it is a localhost.
		if ( ! strrpos( get_site_url(), 'localhost' ) ) {
			$notification_url = get_option( '_mp_custom_domain', '' );
			// Check if we have a custom URL.
			if ( empty( $notification_url ) || filter_var( $notification_url, FILTER_VALIDATE_URL ) === FALSE ) {
				$preferences['notification_url'] = WC()->api_request_url( 'WC_WooMercadoPago_PSEGateway' );
			} else {
				$preferences['notification_url'] = WC_Woo_Mercado_Pago_Module::fix_url_ampersand( esc_url(
					$notification_url . '/wc-api/WC_WooMercadoPago_PSEGateway/'
				) );
			}
		}

		// Discounts features.
		if ( isset( $pse_checkout['discount'] ) && ! empty( $pse_checkout['discount'] ) &&
			isset( $pse_checkout['coupon_code'] ) && ! empty( $pse_checkout['coupon_code'] ) &&
			$pse_checkout['discount'] > 0 && WC()->session->chosen_payment_method == 'woo-mercado-pago-pse' ) {
			$preferences['campaign_id'] = (int) $pse_checkout['campaign_id'];
			$preferences['coupon_amount'] = ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
				floor( $pse_checkout['discount'] * $currency_ratio ) :
				floor( $pse_checkout['discount'] * $currency_ratio * 100 ) / 100;
			$preferences['coupon_code'] = strtoupper( $pse_checkout['coupon_code'] );
		}

		// Set sponsor ID.
		$_test_user_v1 = get_option( '_test_user_v1', false );
		if ( ! $_test_user_v1 ) {
			$preferences['sponsor_id'] = $this->site_data['sponsor_id'];
		}

		// Debug/log this preference.
		$this->write_log(
			__FUNCTION__,
			'returning just created [$preferences] structure: ' .
			json_encode( $preferences, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
		);

		return $preferences;
	}

	protected function create_url( $order, $pse_checkout ) {
		// Creates the order parameters by checking the cart configuration.
		$preferences = $this->build_payment_preference( $order, $pse_checkout );
		// Checks for sandbox mode.
		$this->mp->sandbox_mode( $this->sandbox );
		// Create order preferences with Mercado Pago API request.
		try {
			$checkout_info = $this->mp->create_payment( json_encode( $preferences ) );
			if ( $checkout_info['status'] < 200 || $checkout_info['status'] >= 300 ) {
				// Mercado Pago throwed an error.
				$this->write_log(
					__FUNCTION__,
					'mercado pago gave error, payment creation failed with error: ' . $checkout_info['response']['message']
				);
				return $checkout_info['response']['message'];
			} elseif ( is_wp_error( $checkout_info ) ) {
				// WordPress throwed an error.
				$this->write_log(
					__FUNCTION__,
					'wordpress gave error, payment creation failed with error: ' . $checkout_info['response']['message']
				);
				return $checkout_info['response']['message'];
			} else {
				// Obtain the URL.
				$this->write_log(
					__FUNCTION__,
					'payment link generated with success from mercado pago, with structure as follow: ' .
					json_encode( $checkout_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
				// TODO: Verify sandbox availability.
				//if ( 'yes' == $this->sandbox ) {
				//	return $checkout_info['response']['sandbox_init_point'];
				//} else {
				return $checkout_info['response'];
				//}
			}
		} catch ( MercadoPagoException $ex ) {
			// Something went wrong with the payment creation.
			$this->write_log(
				__FUNCTION__,
				'payment creation failed with exception: ' .
				json_encode( $ex, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
			return $ex->getMessage();
		}
	}

	/**
	* Summary: Receive post data and applies a discount based in the received values.
	* Description: Receive post data and applies a discount based in the received values.
	*/
	public function add_discount_pse() {

		if ( ! isset( $_POST['mercadopago_pse'] ) ) {
			return;
		}

		if ( is_admin() && ! defined( 'DOING_AJAX' ) || is_cart() ) {
			return;
		}

		$pse_checkout = $_POST['mercadopago_pse'];
		if ( isset( $pse_checkout['discount'] ) && ! empty( $pse_checkout['discount'] ) &&
			isset( $pse_checkout['coupon_code'] ) && ! empty( $pse_checkout['coupon_code'] ) &&
			$pse_checkout['discount'] > 0 && WC()->session->chosen_payment_method == 'woo-mercado-pago-pse' ) {

			$this->write_log( __FUNCTION__, 'pse checkout trying to apply discount...' );

			$value = ( $this->site_data['currency'] == 'COP' || $this->site_data['currency'] == 'CLP' ) ?
				floor( $pse_checkout['discount'] / $pse_checkout['currency_ratio'] ) :
				floor( $pse_checkout['discount'] / $pse_checkout['currency_ratio'] * 100 ) / 100;
			global $woocommerce;
			if ( apply_filters(
				'wc_mercadopagopse_module_apply_discount',
				0 < $value, $woocommerce->cart )
			) {
				$woocommerce->cart->add_fee( sprintf(
					__( 'Discount for %s coupon', 'woocommerce-mercadopago' ),
					esc_attr( $pse_checkout['campaign']
					) ), ( $value * -1 ), false
				);
			}
		}

	}

	// Display the discount in payment method title.
	public function get_payment_method_title_pse( $title, $id ) {
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
		$_mp_public_key = get_option( '_mp_public_key' );
		$_mp_access_token = get_option( '_mp_access_token' );
		$_site_id_v1 = get_option( '_site_id_v1' );
		// If we do not have SSL in production environment, we are not allowed to process.
		$_mp_debug_mode = get_option( '_mp_debug_mode', '' );
		if ( empty( $_SERVER['HTTPS'] ) || $_SERVER['HTTPS'] == 'off' ) {
			if ( empty ( $_mp_debug_mode ) ) {
				return false;
			}
		}
		// Check for recurrent product checkout.
		if ( isset( $w_cart ) ) {
			if ( WC_Woo_Mercado_Pago_Module::is_subscription( $w_cart->get_cart() ) ) {
				return false;
			}
		}
		// Check if this gateway is enabled and well configured.
		$available = ( 'yes' == $this->settings['enabled'] ) &&
			! empty( $_mp_public_key ) &&
			! empty( $_mp_access_token ) &&
			! empty( $_site_id_v1 );
		return $available;
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
		$this->write_log(
			__FUNCTION__,
			'received _get content: ' .
			json_encode( $_GET, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
		);
		// Setup sandbox mode.
		$this->mp->sandbox_mode( $this->sandbox );
		// Over here, $_GET should come with this JSON structure:
		// {
		// 	"topic": <string>,
		// 	"id": <string>
		// }
		// If not, the IPN is corrupted in some way.
		$data = $_GET;
		if ( isset( $data['coupon_id'] ) && ! empty( $data['coupon_id'] ) ) {
			// Process coupon evaluations.
			if ( isset( $data['payer'] ) && ! empty( $data['payer'] ) ) {
				$response = $this->mp->check_discount_campaigns( $data['amount'], $data['payer'], $data['coupon_id'] );
				header( 'HTTP/1.1 200 OK' );
				header( 'Content-Type: application/json' );
				echo json_encode( $response );
			} else {
				$obj = new stdClass();
				$obj->status = 404;
				$obj->response = array(
					'message' => __( 'Please, inform your email in billing address to use this feature', 'woocommerce-mercadopago' ),
					'error' => 'payer_not_found',
					'status' => 404,
					'cause' => array()
				);
				header( 'HTTP/1.1 200 OK' );
				header( 'Content-Type: application/json' );
				echo json_encode( $obj );
			}
			exit( 0 );
		} else if ( ! isset( $data['data_id'] ) || ! isset( $data['type'] ) ) {
			// Received IPN call from v0.
			$this->write_log(
				__FUNCTION__,
				'data_id or type not set: ' .
				json_encode( $data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
			);
			if ( ! isset( $data['id'] ) || ! isset( $data['topic'] ) ) {
				$this->write_log(
					__FUNCTION__,
					'Mercado Pago Request failure: ' .
					json_encode( $data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
				wp_die( __( 'Mercado Pago Request Failure', 'woocommerce-mercadopago' ) );
			} else {
				// At least, check if its a v0 ipn.
				header( 'HTTP/1.1 200 OK' );
			}
		} else {
			// Needed informations are present, so start process then.
			try {
				if ( $data['type'] == 'payment' ) {
					$access_token = array( 'access_token' => $this->mp->get_access_token() );
					$payment_info = $this->mp->get( '/v1/payments/' . $data['data_id'], $access_token, false );
					if ( ! is_wp_error( $payment_info ) && ( $payment_info['status'] == 200 || $payment_info['status'] == 201 ) ) {
						if ( $payment_info['response'] ) {
							header( 'HTTP/1.1 200 OK' );
							do_action( 'valid_mercadopago_pse_ipn_request', $payment_info['response'] );
						}
					} else {
						$this->write_log(
							__FUNCTION__,
							'error when processing received data: ' .
							json_encode( $payment_info, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
						);
					}
				}
			} catch ( MercadoPagoException $ex ) {
				$this->write_log(
					__FUNCTION__,
					'MercadoPagoException: ' .
					json_encode( $ex, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE )
				);
			}
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
		$total_paid = isset( $data['transaction_details']['total_paid_amount'] ) ? $data['transaction_details']['total_paid_amount'] : 0.00;
		$total_refund = isset( $data['transaction_amount_refunded'] ) ? $data['transaction_amount_refunded'] : 0.00;
		// WooCommerce 3.0 or later.
		if ( method_exists( $order, 'update_meta_data' ) ) {
			// Updates the type of gateway.
			$order->update_meta_data( '_used_gateway', 'WC_WooMercadoPago_PESGateway' );
			if ( ! empty( $data['payer']['email'] ) ) {
				$order->update_meta_data( __( 'Payer email', 'woocommerce-mercadopago' ), $data['payer']['email'] );
			}
			if ( ! empty( $data['payment_type_id'] ) ) {
				$order->update_meta_data( __( 'Payment type', 'woocommerce-mercadopago' ), $data['payment_type_id'] );
			}
			$order->update_meta_data(
				'Mercado Pago - Payment ' . $data['id'],
				'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
				']/[Amount ' . $data['transaction_amount'] .
				']/[Paid ' . $total_paid .
				']/[Refund ' . $total_refund . ']'
			);
			$order->update_meta_data( '_Mercado_Pago_Payment_IDs', $data['id'] );
			$order->save();
		} else {
			// Updates the type of gateway.
			update_post_meta( $order_id, '_used_gateway', 'WC_WooMercadoPago_PSEGateway' );
			if ( ! empty( $data['payer']['email'] ) ) {
				update_post_meta( $order_id, __( 'Payer email', 'woocommerce-mercadopago' ), $data['payer']['email'] );
			}
			if ( ! empty( $data['payment_type_id'] ) ) {
				update_post_meta( $order_id, __( 'Payment type', 'woocommerce-mercadopago' ), $data['payment_type_id'] );
			}
			update_post_meta(
				$order_id,
				'Mercado Pago - Payment ' . $data['id'],
				'[Date ' . date( 'Y-m-d H:i:s', strtotime( $data['date_created'] ) ) .
				']/[Amount ' . $data['transaction_amount'] .
				']/[Paid ' . $total_paid .
				']/[Refund ' . $total_refund . ']'
			);
			update_post_meta( $order_id, '_Mercado_Pago_Payment_IDs', $data['id'] );
		}
		// Switch the status and update in WooCommerce.
		$this->write_log(
			__FUNCTION__,
			'Changing order status to: ' .
			WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( str_replace( '_', '', $status ) )
		);
		switch ( $status ) {
			case 'approved':
				$order->add_order_note(
					'Mercado Pago: ' . __( 'Payment approved.', 'woocommerce-mercadopago' )
				);
				if ( $this->stock_reduce_mode == 'no' ) {
					$order->payment_complete();
				}
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'approved' )
				);
				break;
			case 'pending':
				$order->update_status(
					WC_Woo_Mercado_Pago_Module::get_wc_status_for_mp_status( 'pending' )
				);
				// decrease stock if not yet decreased and order not exists.
				$notes = $order->get_customer_order_notes();
				$has_note = false;
				if ( sizeof( $notes ) > 1 ) {
					$has_note = true;
					break;
				}
				if ( ! $has_note ) {
					$order->add_order_note(
						'Mercado Pago: ' . __( 'Waiting for the bank transfer.', 'woocommerce-mercadopago' ),
						1, false
					);
				}
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

new WC_WooMercadoPago_PSEGateway( true );
