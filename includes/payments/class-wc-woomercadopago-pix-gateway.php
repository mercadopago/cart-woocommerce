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
 * Class WC_WooMercadoPago_Pix_Gateway
 */
class WC_WooMercadoPago_Pix_Gateway extends WC_WooMercadoPago_Payment_Abstract {
	/**
	 * ID
	 *
	 * @const
	 */
	const ID = 'woo-mercado-pago-pix';

	/**
	 * WC_WooMercadoPago_PixGateway constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception Load payment exception.
	 */
	public function __construct() {
		$this->id            = self::ID;
		$this->title         = __( 'Pix', 'woocommerce-mercadopago' );
		$this->title_gateway = __( 'Pix', 'woocommerce-mercadopago' );
		$this->description   = __( 'Transparent Checkout in your store environment', 'woocommerce-mercadopago' );
		$this->mp_options    = $this->get_mp_options();

		if ( ! $this->validate_section() ) {
			return;
		}

		$this->form_fields        = array();
		$this->method_title       = __( 'Mercado pago - Customized Checkout', 'woocommerce-mercadopago' );
		$this->title              = $this->get_option( 'title', __( 'Pix', 'woocommerce-mercadopago' ) );
		$this->method_description = $this->description;
		$this->date_expiration    = (int) $this->get_option( 'checkout_pix_date_expiration', '1' );
		$this->type_payments      = $this->get_option( 'type_payments', 'no' );
		$this->payment_type       = 'pix';
		$this->checkout_type      = 'custom';
		$this->activated_payment  = get_option( '_mp_payment_methods_pix', '' );
		$this->field_forms_order  = $this->get_fields_sequence();

		parent::__construct();

		$this->update_pix_method();
		$this->form_fields         = $this->get_form_mp_fields();
		$this->hook                = new WC_WooMercadoPago_Hook_Pix( $this );
		$this->notification        = new WC_WooMercadoPago_Notification_Core( $this );
		$this->currency_convertion = true;
		$this->icon                = $this->get_checkout_icon();

		add_action( 'woocommerce_email_before_order_table', array(__CLASS__,'get_pix_template'), 20, 4 );
		add_action( 'woocommerce_order_details_after_order_table', array(__CLASS__,'get_pix_template_order_details') );
	}

	/**
	 * Get form mp fields
	 *
	 * @param string $label Label.
	 * @return array
	 */
	public function get_form_mp_fields() {
		if ( is_admin() && $this->is_manage_section() && ( WC_WooMercadoPago_Helper_Current_Url::validate_page('mercadopago-settings') || WC_WooMercadoPago_Helper_Current_Url::validate_section('woo-mercado-pago') ) ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script(
				'woocommerce-mercadopago-pix-config-script',
				plugins_url( '../assets/js/pix_config_mercadopago' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				false
			);
		}

		if ( empty( $this->checkout_country ) ) {
			$this->field_forms_order = array_slice( $this->field_forms_order, 0, 7 );
		}

		if ( ! empty( $this->checkout_country ) && empty( $this->get_access_token() ) && empty( $this->get_public_key() ) ) {
			$this->field_forms_order = array_slice( $this->field_forms_order, 0, 22 );
		}

		$form_fields = array();
		if ( ! empty( $this->checkout_country ) && ! empty( $this->get_access_token() ) && ! empty( $this->get_public_key() ) ) {
			$form_fields['checkout_pix_header'] = $this->field_checkout_pix_header();
			if ( empty( $this->activated_payment ) || ! is_array( $this->activated_payment ) || ! in_array( 'pix', $this->activated_payment['pix'], true ) ) {
				$form_fields['checkout_steps_pix'] = $this->field_checkout_steps_pix();

				// phpcs:ignore WordPress.Security.NonceVerification
				if ( isset( $_GET['section'] ) && $_GET['section'] === $this->id ) {
					add_action( 'admin_notices', array( $this, 'enable_pix_payment_notice' ) );
				}
			}
				$form_fields['checkout_pix_payments_advanced_title'] = $this->field_checkout_pix_payments_advanced_title();
				$form_fields['checkout_pix_date_expiration']         = $this->field_pix_date_expiration();
				$form_fields['checkout_about_pix']                   = $this->field_checkout_about_pix();
				$form_fields['checkout_pix_card_info']               = $this->field_checkout_pix_card_info();
		}

		$form_fields_abs = parent::get_form_mp_fields();
		if ( 1 === count( $form_fields_abs ) ) {
			return $form_fields_abs;
		}
		$form_fields_merge = array_merge( $form_fields_abs, $form_fields );
		$fields            = $this->sort_form_fields( $form_fields_merge, $this->field_forms_order );

		if ( empty( $this->activated_payment ) || ! is_array( $this->activated_payment ) || ! in_array( 'pix', $this->activated_payment['pix'], true ) ) {
			$form_fields_not_show = array_flip( $this->get_fields_not_show() );
			$fields               = array_diff_key( $fields, $form_fields_not_show );
		}

		return $fields;
	}

	/**
	 * Update Pix Method
	 *
	 * @return void
	 */
	public function update_pix_method() {
		$wc_country       = WC_WooMercadoPago_Module::get_woocommerce_default_country();
		$site_id          = $this->mp_options->get_site_id();
		$_mp_access_token = $this->get_access_token();
		if ( ( 'BR' === $wc_country && '' === $site_id ) || ( 'mlb' === $site_id ) ) {
			WC_WooMercadoPago_Credentials::update_pix_method( $this->mp, $_mp_access_token );
		}
	}

	/**
	 * Get fields sequence
	 *
	 * @return array
	 */
	public function get_fields_sequence() {
		return array(
			// Necessary to run.
			'description',
			// Checkout de pagos con dinero en efectivo<br> Aceptá pagos al instante y maximizá la conversión de tu negocio.
			'checkout_pix_header',
			// No olvides de homologar tu cuenta.
			'checkout_card_homolog',
			// Steps configuration pix.
			'checkout_steps_pix',
			// Configure the personalized payment experience in your store.
			'checkout_payments_subtitle',
			'checkout_card_validate',
			'enabled',
			'title',
			'checkout_pix_date_expiration',
			WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY,
			// About PIX.
			'checkout_about_pix',
			'checkout_pix_card_info',
			// Advanced configuration of the personalized payment experience.
			'checkout_pix_payments_advanced_title',
			'checkout_payments_advanced_description',
			'gateway_discount',
			'commission',
		);
	}

	/**
	 * Get fields NOT allow to show
	 *
	 * @return array
	 */
	public function get_fields_not_show() {
		return array(
			// Configure the personalized payment experience in your store.
			'checkout_payments_subtitle',
			'enabled',
			'title',
			'checkout_pix_date_expiration',
			WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY,
			// About PIX.
			'checkout_about_pix',
			'checkout_pix_card_info',
			// Advanced configuration of the personalized payment experience.
			'checkout_pix_payments_advanced_title',
			'checkout_payments_advanced_description',
			'gateway_discount',
			'commission',
		);
	}

	/**
	 * Field enabled
	 *
	 * @return array
	 */
	public function field_enabled() {
		return array(
			'title'       => __( 'Enable the checkout', 'woocommerce-mercadopago' ),
			'subtitle'    => __( 'By disabling it, you will disable all Pix payments from Mercado Pago Transparent Checkout.', 'woocommerce-mercadopago' ),
			'type'        => 'mp_toggle_switch',
			'default'     => 'no',
			'descriptions' => array(
				'enabled' => __( 'The transparent checkout for Pix payment is <b>enabled</b>.', 'woocommerce-mercadopago' ),
				'disabled' => __( 'The transparent checkout for Pix payment is <b>disabled</b>.', 'woocommerce-mercadopago' ),
			),
		);
	}


	/**
	 * Field checkout steps
	 *
	 * @return array
	 */
	public function field_checkout_steps_pix() {
		$steps_content = wc_get_template_html(
			'checkout/credential/steps-pix.php',
			array(
				'title'                       => __( 'To activate Pix, you must have a key registered in Mercado Pago.', 'woocommerce-mercadopago' ),
				'step_one_text'               => __( 'Download the Mercado Pago app on your cell phone.', 'woocommerce-mercadopago' ),
				'step_two_text_one'           => __( 'Go to the ', 'woocommerce-mercadopago' ),
				'step_two_text_two'           => __( 'area and choose the ', 'woocommerce-mercadopago' ),
				'step_two_text_highlight_one' => __( 'Your Profile ', 'woocommerce-mercadopago' ),
				'step_two_text_highlight_two' => __( 'Your Pix Keys section.', 'woocommerce-mercadopago' ),
				'step_three_text'             => __( 'Choose which data to register as Pix keys. After registering, you can set up Pix in your checkout.', 'woocommerce-mercadopago' ),
				'observation_one'             => __( 'Remember that, for the time being, the Central Bank of Brazil is open Monday through Friday, from 9am to 6pm.', 'woocommerce-mercadopago' ),
				'observation_two'             => __( 'If you requested your registration outside these hours, we will confirm it within the next business day.', 'woocommerce-mercadopago' ),
				'button_about_pix'            => __( 'Learn more about Pix', 'woocommerce-mercadopago' ),
				'observation_three'           => __( 'If you have already registered a Pix key at Mercado Pago and cannot activate Pix in the checkout, ', 'woocommerce-mercadopago' ),
				'link_title_one'              => __( 'click here.', 'woocommerce-mercadopago' ),
				'link_url_one'                => 'https://www.mercadopago.com.br/pix/',
				'link_url_two'                => 'https://www.mercadopago.com.br/developers/pt/support/contact',
			),
			'woo/mercado/pago/steps/',
			WC_WooMercadoPago_Module::get_templates_path()
		);

		return array(
			'title' => $steps_content,
			'type'  => 'title',
			'class' => 'mp_title_checkout',
		);
	}

	/**
	 * Field checkout pix header
	 *
	 * @return array
	 */
	public function field_checkout_pix_header() {
		return array(
			'title' => sprintf(
				'<div class="mp-row">
                <div class="mp-col-md-12 mp_subtitle_header">
                ' . __( 'Transparent Checkout | Pix', 'woocommerce-mercadopago' ) . '
                 </div>
              <div class="mp-col-md-12">
                <p class="mp-text-checkout-body mp-mb-0">
                  ' . __( 'With the Transparent Checkout, you can sell inside your store environment, without redirection and all the safety from Mercado Pago. ', 'woocommerce-mercadopago' ) . '
                </p>
              </div>
            </div>'
			),
			'type'  => 'title',
			'class' => 'mp_title_header',
		);
	}

	/**
	 * Field checkout pix payments advanced title
	 *
	 * @return array
	 */
	public function field_checkout_pix_payments_advanced_title() {
		return array(
			'title' => __( 'Advanced configuration of the Pix experience', 'woocommerce-mercadopago' ),
			'type'  => 'title',
			'class' => 'mp_subtitle_bd',
		);
	}

	/**
	 * Field date expiration
	 *
	 * @return array
	 */
	public function field_pix_date_expiration() {
		$pix_expiration_values = array(
			'15 minutes'       => __( '15 minutes', 'woocommerce-mercadopago' ),
			'30 minutes'       => __( '30 minutes (recommended)', 'woocommerce-mercadopago' ),
			'60 minutes'       => __( '60 minutes', 'woocommerce-mercadopago' ),
			'12 hours'       => __( '12 hours', 'woocommerce-mercadopago' ),
			'24 hours'       => __( '24 hours', 'woocommerce-mercadopago' ),
			'2 days'        => __( '2 days', 'woocommerce-mercadopago' ),
			'3 days'        => __( '3 days', 'woocommerce-mercadopago' ),
			'4 days'        => __( '4 days', 'woocommerce-mercadopago' ),
			'5 days'        => __( '5 days', 'woocommerce-mercadopago' ),
			'6 days'        => __( '6 days', 'woocommerce-mercadopago' ),
			'7 days'        => __( '7 days', 'woocommerce-mercadopago' ),
		);

		return array(
			'title'       => __( 'Expiration for payments via Pix', 'woocommerce-mercadopago' ),
			'type'        => 'select',
			'description' => __( 'Set the limit in minutes for your clients to pay via Pix.', 'woocommerce-mercadopago' ),
			'default'     => '30 minutes',
			'options'     => $pix_expiration_values,
		);
	}

	/**
	 * Field checkout about pix
	 *
	 * @return array
	 */
	public function field_checkout_about_pix() {
		$link_content = wc_get_template_html(
			'checkout/credential/generic-alert.php',
			array(
				'title'       => __( 'Want to learn how Pix works?', 'woocommerce-mercadopago' ),
				'subtitle'    => __( 'We have created a page to explain how this new payment method works and its advantages.', 'woocommerce-mercadopago' ),
				'url_link'    => 'https://www.mercadopago.com.br/pix/',
				'button_text' => __( 'Learn more about Pix', 'woocommerce-mercadopago' ),
			),
			'woo/mercado/pago/about-pix/',
			WC_WooMercadoPago_Module::get_templates_path()
		);

		return array(
			'title' => $link_content,
			'type'  => 'title',
		);
	}

	/**
	 * Field checkout card info
	 *
	 * @return array
	 */
	public function field_checkout_pix_card_info() {
		$value = array(
			'title'             => __('Would you like to know how Pix works?', 'woocommerce-mercadopago'), __('Important! To sell you must enter your credentials.' , 'woocommerce-mercadopago'),
			'subtitle'          => __('We have a dedicated page where we explain how it works and its advantages.', 'woocommerce-mercadopago'),
			'button_text'       => __('Find out more about Pix', 'woocommerce-mercadopago'),
			'button_url'        => 'https://www.mercadopago.com.br/pix/',
			'icon'              => 'mp-icon-badge-info',
			'color_card'        => 'mp-alert-color-sucess',
			'size_card'         => 'mp-card-body-size',
			'target'            => '_blank'
		);
		return array(
			'type'                  => 'mp_card_info',
			'value'                 => $value,
		);
	}

	/**
	 * Payment fields
	 */
	public function payment_fields() {
		// add css.
		wp_enqueue_style(
			'woocommerce-mercadopago-narciso-styles',
			plugins_url( '../assets/css/mp-plugins-components.css', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION
		);

		$parameters = [
			'test_mode' => ! $this->is_production_mode(),
			'pix_image' => plugins_url( '../assets/images/pix.png', plugin_dir_path( __FILE__ ) ),
		];

		$parameters = array_merge($parameters, WC_WooMercadoPago_Helper_Links::mp_define_terms_and_conditions());
		wc_get_template( 'checkout/pix-checkout.php', $parameters, 'woo/mercado/pago/module/', WC_WooMercadoPago_Module::get_templates_path() );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order Id.
	 * @return array|string[]
	 */
	public function process_payment( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		$pix_checkout = map_deep($_POST, 'sanitize_text_field');
		$this->log->write_log( __FUNCTION__, 'Payment via Pix POST: ' );
		$order          = wc_get_order( $order_id );
		$shipping_taxes = floatval($order->get_shipping_total());

		$amount = $this->get_order_total();

		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( 'is_production_mode', 'no' === $this->mp_options->get_checkbox_checkout_test_mode() ? 'yes' : 'no' );
			$order->update_meta_data( '_used_gateway', get_class( $this ) );

			if ( ! empty( $this->gateway_discount ) ) {
				$discount = ( $amount - $shipping_taxes ) * $this->gateway_discount / 100;
				$order->update_meta_data( 'Mercado Pago: discount', __( 'discount of', 'woocommerce-mercadopago' ) . ' ' . $this->gateway_discount . '% / ' . __( 'discount of', 'woocommerce-mercadopago' ) . ' = ' . $discount );
				$order->set_total($amount - $discount);
			}

			if ( ! empty( $this->commission ) ) {
				$comission = $amount * ( $this->commission / 100 );
				$order->update_meta_data( 'Mercado Pago: comission', __( 'fee of', 'woocommerce-mercadopago' ) . ' ' . $this->commission . '% / ' . __( 'fee of', 'woocommerce-mercadopago' ) . ' = ' . $comission );
			}
			$order->save();
		} else {
			update_post_meta( $order_id, '_used_gateway', get_class( $this ) );

			if ( ! empty( $this->gateway_discount ) ) {
				$discount = ( $amount - $shipping_taxes ) * $this->gateway_discount / 100;
				update_post_meta( $order_id, 'Mercado Pago: discount', __( 'discount of', 'woocommerce-mercadopago' ) . ' ' . $this->gateway_discount . '% / ' . __( 'discount of', 'woocommerce-mercadopago' ) . ' = ' . $discount );
				$order->set_total($amount - $discount);
			}

			if ( ! empty( $this->commission ) ) {
				$comission = $amount * ( $this->commission / 100 );
				update_post_meta( $order_id, 'Mercado Pago: comission', __( 'fee of', 'woocommerce-mercadopago' ) . ' ' . $this->commission . '% / ' . __( 'fee of', 'woocommerce-mercadopago' ) . ' = ' . $comission );
			}
		}

		if ( filter_var( $order->get_billing_email(), FILTER_VALIDATE_EMAIL ) ) {
			$response = $this->create_payment( $order, $pix_checkout );

			if ( is_array( $response ) && array_key_exists( 'status', $response ) ) {
				$this->hook->update_mp_order_payments_metadata( $order->get_id(), [ $response['id'] ] );

				if ( 'pending' === $response['status'] ) {
					if ( 'pending_waiting_payment' === $response['status_detail'] || 'pending_waiting_transfer' === $response['status_detail'] ) {
						WC()->cart->empty_cart();
						// WooCommerce 3.0 or later.
						if ( method_exists( $order, 'update_meta_data' ) ) {
							$order->update_meta_data( 'mp_transaction_amount', $response['transaction_amount'] );
							$order->update_meta_data( 'mp_pix_qr_base64', $response['point_of_interaction']['transaction_data']['qr_code_base64'] );
							$order->update_meta_data( 'mp_pix_qr_code', $response['point_of_interaction']['transaction_data']['qr_code'] );
							$order->update_meta_data( 'checkout_pix_date_expiration', __( $this->get_option( 'checkout_pix_date_expiration', '30 minutes' ), 'woocommerce-mercadopago' ) );
							$order->update_meta_data( 'pix_on', 1 );
							$order->save();
						} else {
							update_post_meta( $order->get_id(), 'mp_transaction_amount', $response['transaction_amount'] );
							update_post_meta( $order->get_id(), 'mp_pix_qr_base64', $response['point_of_interaction']['transaction_data']['qr_code_base64'] );
							update_post_meta( $order->get_id(), 'mp_pix_qr_code', $response['point_of_interaction']['transaction_data']['qr_code'] );
							update_post_meta( $order->get_id(), 'checkout_pix_date_expiration', __( $this->get_option( 'checkout_pix_date_expiration', '30 minutes' ), 'woocommerce-mercadopago' ) );
							update_post_meta( $order->get_id(), 'pix_on', 1 );
						}

						// Shows some info in checkout page.
						$order->add_order_note(
							'Mercado Pago: ' .
							__( 'The customer has not paid yet.', 'woocommerce-mercadopago' )
						);

						if ( 'pix' === $response['payment_method_id'] ) {
							$order->add_order_note(
								'<div style="text-align: justify;"><p>Mercado Pago: ' . __( 'Now you just need to pay with Pix to finalize your purchase.', 'woocommerce-mercadopago' ) . ' ' .
								__( 'Scan the QR code below or copy and paste the code into your bank\'s application.', 'woocommerce-mercadopago' ) . '</small></p>',
								1,
								false
							);
						}

						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_order_received_url(),
						);
					}
				}
			} else {
				// Process when fields are imcomplete.
				wc_add_notice(
					'<p>' .
					__( 'A problem occurred when processing your payment. Are you sure you have correctly filled in all the information on the checkout form?', 'woocommerce-mercadopago' ) . ' MERCADO PAGO: ' .
					WC_WooMercadoPago_Module::get_common_error_messages( $response ) .
					'</p>',
					'error'
				);

				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}
		} else {
			// Process when fields are incomplete.
			wc_add_notice(
				'<p>' .
				__( 'A problem occurred when processing your payment. Please try again.', 'woocommerce-mercadopago' ) .
				'</p>',
				'error'
			);

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Create payment
	 *
	 * @param object $order Order.
	 * @param array  $pix_checkout Picket checkout.
	 * @return string|array
	 */
	public function create_payment( $order, $pix_checkout ) {
		$preferences_pix = new WC_WooMercadoPago_Preference_Pix( $this, $order, $pix_checkout );
		$payment         = $preferences_pix->get_transaction( 'Payment' );

		try {
			$checkout_info = $payment->save();
			$this->log->write_log( __FUNCTION__, 'Created Payment: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			return $checkout_info;
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, 'payment creation failed with error: ' . $e->getMessage() );
			return $e->getMessage();
		}
	}

	/**
	 * Is available?
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! parent::is_available() ) {
			return false;
		}

		$payment_methods = $this->activated_payment;
		if ( empty( $payment_methods ) || ! is_array( $payment_methods ) || ! in_array( 'pix', $payment_methods['pix'], true ) ) {
			$this->log->write_log( __FUNCTION__, 'Pix key not found in payment_methods API, no active Pix payment method. ' );
			return false;
		}

		$_mp_access_token    = $this->mp_options->get_access_token_prod();
		$is_prod_credentials = false === WC_WooMercadoPago_Credentials::validate_credentials_test( $this->mp, $_mp_access_token, null );

		if ( ( empty( $_SERVER['HTTPS'] ) || 'off' === $_SERVER['HTTPS'] ) && $is_prod_credentials ) {
			$this->log->write_log( __FUNCTION__, 'NO HTTPS, Pix unavailable.' );
			return false;
		}

		return true;
	}

	/**
	 * Enable pix payment notice
	 *
	 * @return void
	 */
	public function enable_pix_payment_notice() {
		$type    = 'notice-warning';
		$message = wc_get_template_html(
			'checkout/credential/alert/alert-pix-not-registered.php',
			array(
				'message'   => __( 'Please note that to receive payments via Pix at our checkout, you must have a Pix key registered in your Mercado Pago account.', 'woocommerce-mercadopago' ),
				'text_link' => __( 'Register your Pix key at Mercado Pago.', 'woocommerce-mercadopago' ),
				'url_link'  => 'https://www.mercadopago.com.br/stop/pix?url=https%3A%2F%2Fwww.mercadopago.com.br%2Fadmin-pix-keys%2Fmy-keys&authentication_mode=required',
			),
			'woo/mercado/pago/alert-pix-not-registered.php/',
			WC_WooMercadoPago_Module::get_templates_path()
		);
		WC_WooMercadoPago_Notices::get_alert_frame( $message, $type );
	}

	/**
	 * Get Id
	 *
	 * @return string
	 */
	public static function get_id() {
		return self::ID;
	}

	/**
	 * Get pix template
	 *
	 * @param object $order Order.
	 * @return string
	 */
	public static function get_pix_template( $order ) {
		$pix_on = get_post_meta( $order->get_id(), 'pix_on' );
		$pix_on = (int) array_pop( $pix_on );

		if ( 1 === $pix_on && 'pending' === $order->get_status() ) {
			$mp_pix_qr_code               = get_post_meta( $order->get_id(), 'mp_pix_qr_code' );
			$mp_pix_qr_base64             = get_post_meta( $order->get_id(), 'mp_pix_qr_base64' );
			$checkout_pix_date_expiration = get_post_meta($order->get_id(), 'checkout_pix_date_expiration');

			$qr_code         = array_pop( $mp_pix_qr_code );
			$qr_image        = array_pop( $mp_pix_qr_base64 );
			$src             = 'data:image/jpeg;base64';
			$expiration_date = array_pop( $checkout_pix_date_expiration );

			$order         = $order->get_id();
			$qr_code_image = get_option('siteurl') . '/?wc-api=wc_mp_pix_image&id=' . $order;

			if ( ! in_array( 'gd', get_loaded_extensions(), true ) ) {
				$qr_code_image = $src . ',' . $qr_image;
			}

			$pix_template = wc_get_template(
				'pix/pix-image-template.php',
				array(
					'qr_code'              => $qr_code,
					'expiration_date'      => $expiration_date,
					'text_expiration_date' => __( 'Code valid for ', 'woocommerce-mercadopago' ),
					'qr_code_image'        => $qr_code_image,
				),
				'',
				WC_WooMercadoPago_Module::get_templates_path()
			);

			return $pix_template;
		}
	}

	/**
	 * Get pix template to send via email
	 *
	 * @param object $order Order.
	 * @param bool $sent_to_admin.
	 * @param bool $plain_text.
	 * @param $email
	 * @return string|array
	 */
	public static function get_pix_template_email( $order, $sent_to_admin, $plain_text, $email ) {

		return self::get_pix_template( $order );

	}

	/**
	 * Get pix template to show in order details
	 *
	 * @param object $order Order.
	 * @return string|array
	 */
	public static function get_pix_template_order_details( $order ) {

		return self::get_pix_template( $order );

	}

	/**
	 * Get Mercado Pago Icon
	 *
	 * @return mixed
	 */
	public function get_checkout_icon() {
		/**
		 * Add Mercado Pago icon.
		 *
		 * @since 3.0.1
		 */
		return apply_filters( 'woocommerce_mercadopago_icon', plugins_url( '../assets/images/icons/pix.png', plugin_dir_path( __FILE__ ) ) );
	}

}
