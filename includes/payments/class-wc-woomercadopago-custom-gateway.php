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
 * Class WC_WooMercadoPago_Custom_Gateway
 */
class WC_WooMercadoPago_Custom_Gateway extends WC_WooMercadoPago_Payment_Abstract {
	/**
	 * ID
	 *
	 * @const
	 */
	const ID = 'woo-mercado-pago-custom';

	/**
	 * Is enable Wallet Button?
	 *
	 * @var string
	 */
	protected $wallet_button;

	/**
	 * WC_WooMercadoPago_CustomGateway constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception Exception load payment.
	 */
	public function __construct() {
		$this->id            = self::ID;
		$this->title         = __( 'Debit and Credit', 'woocommerce-mercadopago' );
		$this->title_gateway = __( 'Debit and Credit', 'woocommerce-mercadopago' );
		$this->description   = __( 'Transparent Checkout in your store environment', 'woocommerce-mercadopago' );
		$this->mp_options    = $this->get_mp_options();

		if ( ! $this->validate_section() ) {
			return;
		}

		$this->form_fields        = array();
		$this->method_title       = __( 'Mercado pago - Customized Checkout', 'woocommerce-mercadopago' );
		$this->title              = $this->get_option( 'title', __( 'Debit and Credit', 'woocommerce-mercadopago' ) );
		$this->method_description = $this->description;
		$this->coupon_mode        = $this->get_option( 'coupon_mode', 'no' );
		$this->wallet_button      = $this->get_option( 'wallet_button', 'yes' );
		$this->field_forms_order  = $this->get_fields_sequence();

		parent::__construct();
		$this->form_fields         = $this->get_form_mp_fields();
		$this->hook                = new WC_WooMercadoPago_Hook_Custom( $this );
		$this->notification        = new WC_WooMercadoPago_Notification_Core( $this );
		$this->currency_convertion = true;
		$this->icon                = $this->get_checkout_icon();
	}

	/**
	 * Get Form Mercado Pago fields
	 *
	 * @return array
	 */
	public function get_form_mp_fields() {
		if ( is_admin() && $this->is_manage_section() && ( WC_WooMercadoPago_Helper_Current_Url::validate_page('mercadopago-settings') || WC_WooMercadoPago_Helper_Current_Url::validate_section('woo-mercado-pago') ) ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script(
				'woocommerce-mercadopago-custom-config-script',
				plugins_url( '../assets/js/custom_config_mercadopago' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
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
			$form_fields['checkout_custom_header']                  = $this->field_checkout_custom_header();
			$form_fields['binary_mode']                             = $this->field_binary_mode();
			$form_fields['field_checkout_about_fees']               = $this->field_checkout_about_fees();
			$form_fields['field_checkout_custom_card_info_fees']    = $this->field_checkout_custom_card_info_fees();
			$form_fields['checkout_custom_payments_advanced_title'] = $this->field_checkout_custom_payments_advanced_title();
			$form_fields['coupon_mode']                             = $this->field_coupon_mode();
			$form_fields['wallet_button']                           = $this->field_checkout_custom_wallet_button_title();
		}
		$form_fields_abs = parent::get_form_mp_fields();
		if ( 1 === count( $form_fields_abs ) ) {
			return $form_fields_abs;
		}
		$form_fields_merge = array_merge( $form_fields_abs, $form_fields );
		$fields            = $this->sort_form_fields( $form_fields_merge, $this->field_forms_order );

		return $fields;
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
			// Checkout de pagos con tarjetas de débito y crédito<br> Aceptá pagos al instante y maximizá la conversión de tu negocio.
			'checkout_custom_header',
			// No olvides de homologar tu cuenta.
			'checkout_card_homolog',
			// Configure the personalized payment experience in your store.
			'checkout_card_validate',
			'checkout_custom_payments_title',
			'checkout_payments_subtitle',
			'enabled',
			'title',
			// About card info pcj and fees.
			'field_checkout_about_fees',
			'field_checkout_custom_card_info_fees',
			WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY,
			'checkout_custom_wallet_button_toggle',
			'wallet_button',
			// Advanced configuration of the personalized payment experience.
			'checkout_custom_payments_advanced_title',
			'checkout_payments_advanced_description',
			'coupon_mode',
			'binary_mode',
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
			'subtitle'    => __( 'By disabling it, you will disable all credit cards payments from Mercado Pago Transparent Checkout.', 'woocommerce-mercadopago' ),
			'type'        => 'mp_toggle_switch',
			'default'     => 'no',
			'descriptions' => array(
				'enabled' => __( 'Transparent Checkout for credit cards is <b>enabled</b>.', 'woocommerce-mercadopago' ),
				'disabled' => __( 'Transparent checkout for credit cards is <b>disabled</b>.', 'woocommerce-mercadopago' ),
			),
		);
	}

	/**
	 * Field checkout about fees
	 *
	 * @return array
	 */
	public function field_checkout_about_fees() {
		$link_content = wc_get_template_html(
			'checkout/credential/generic-alert.php',
			array(),
			'woo/mercado/pago/generic-alert/',
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
	public function field_checkout_custom_card_info_fees() {
		$links = WC_WooMercadoPago_Helper_Links::woomercadopago_settings_links();
		$value = array(
			'title'             => __('Installments Fees', 'woocommerce-mercadopago'),
			'subtitle'          => __('Set installment fees and whether they will be charged from the store or from the buyer.', 'woocommerce-mercadopago'),
			'button_text'       => __('Set fees', 'woocommerce-mercadopago'),
			'button_url'        => $links['link_costs'],
			'icon'              => 'mp-icon-badge-info',
			'color_card'        => 'mp-alert-color-sucess',
			'size_card'         => 'mp-card-body-size',
			'target'            => '_blank',
		);

		return array(
			'type'               => 'mp_card_info',
			'value'              => $value,
		);
	}

	/**
	 * Field checkout custom header
	 *
	 * @return array
	 */
	public function field_checkout_custom_header() {
		$checkout_custom_header = array(
			'title' => sprintf(
				/* translators: %s card */
				'<div class="mp-row">
                <div class="mp-col-md-12 mp_subtitle_header">
                ' . __( 'Transparent Checkout | Credit card ', 'woocommerce-mercadopago' ) . '
                 </div>
              <div class="mp-col-md-12">
                <p class="mp-text-checkout-body mp-mb-0">
                  ' . __( 'With the Transparent Checkout, you can sell inside your store environment, without redirection and with the security from Mercado Pago.', 'woocommerce-mercadopago' ) . '
                </p>
              </div>
            </div>'
			),
			'type'  => 'title',
			'class' => 'mp_title_header',
		);
		return $checkout_custom_header;
	}

	/**
	 * Field checkout custom payment advanced title
	 *
	 * @return array
	 */
	public function field_checkout_custom_payments_advanced_title() {
		$checkout_custom_payments_advanced_title = array(
			'title' => __( 'Advanced configuration of the personalized payment experience', 'woocommerce-mercadopago' ),
			'type'  => 'title',
			'class' => 'mp_subtitle_bd',
		);
		return $checkout_custom_payments_advanced_title;
	}

	/**
	 * Field Wallet Button toggle
	 *
	 * @return array
	 */
	public function field_checkout_custom_wallet_button_title() {
		return array(
			'title'        => __( 'Payments via Mercado Pago account', 'woocommerce-mercadopago' ),
			'subtitle'     => __( 'Your customers pay faster with saved cards, money balance or other available methods in their Mercado Pago accounts.', 'woocommerce-mercadopago' ),
			'type'         => 'mp_toggle_switch',
			'default'      => 'yes',
			'descriptions' => array(
				'enabled'  => __( 'Payments via Mercado Pago accounts are <b>active</b>.', 'woocommerce-mercadopago' ),
				'disabled' => __( 'Payments via Mercado Pago accounts are <b>inactive</b>.', 'woocommerce-mercadopago' ),
			),
			'after_toggle' => $this->wallet_button_preview(),
		);
	}

	/**
	 * Generate Wallet Button HTML
	 *
	 * @param $key field key
	 * @param $settings settings array
	 *
	 * @return array
	 */
	public function wallet_button_preview() {
		return wc_get_template_html(
			'components/wallet-button.php',
			array (
				'img_wallet_button_uri'         => $this->get_wallet_button_example_uri(),
				'img_wallet_button_description' => __( 'Check an example of how it will appear in your store:', 'woocommerce-mercadopago' ),
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Returns wallet button URI based on current store locale
	 *
	 * @return string
	 */
	public function get_wallet_button_example_uri() {
		$locale = substr( strtolower(get_locale()), 0, 2 );

		if ( 'pt' !== $locale && 'es' !== $locale ) {
			$locale = 'en';
		}

		return plugins_url( '../assets/images/pix-admin/example-' . $locale . '.png', plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get Order Status
	 *
	 * @param string $status_detail Status.
	 * @return string|void
	 */
	public function get_order_status( $status_detail ) {
		switch ( $status_detail ) {
			case 'accredited':
				return __( 'That’s it, payment accepted!', 'woocommerce-mercadopago' );
			case 'pending_contingency':
				return __( 'We are processing your payment. In less than an hour we will send you the result by email.', 'woocommerce-mercadopago' );
			case 'pending_review_manual':
				return __( 'We are processing your payment. In less than 2 days we will send you by email if the payment has been approved or if additional information is needed.', 'woocommerce-mercadopago' );
			case 'cc_rejected_bad_filled_card_number':
				return __( 'Check the card number.', 'woocommerce-mercadopago' );
			case 'cc_rejected_bad_filled_date':
				return __( 'Check the expiration date.', 'woocommerce-mercadopago' );
			case 'cc_rejected_bad_filled_other':
				return __( 'Check the information provided.', 'woocommerce-mercadopago' );
			case 'cc_rejected_bad_filled_security_code':
				return __( 'Check the informed security code.', 'woocommerce-mercadopago' );
			case 'cc_rejected_card_error':
			case 'cc_rejected_blacklist':
				return __( 'Your payment cannot be processed.', 'woocommerce-mercadopago' );
			case 'cc_rejected_call_for_authorize':
				return __( 'You must authorize payments for your orders.', 'woocommerce-mercadopago' );
			case 'cc_rejected_card_disabled':
				return __( 'Contact your card issuer to activate it. The phone is on the back of your card.', 'woocommerce-mercadopago' );
			case 'cc_rejected_duplicated_payment':
				return __( 'You have already made a payment of this amount. If you have to pay again, use another card or other method of payment.', 'woocommerce-mercadopago' );
			case 'cc_rejected_high_risk':
				return __( 'Your payment was declined. Please select another payment method. It is recommended in cash.', 'woocommerce-mercadopago' );
			case 'cc_rejected_insufficient_amount':
				return __( 'Your payment does not have sufficient funds.', 'woocommerce-mercadopago' );
			case 'cc_rejected_invalid_installments':
				return __( 'Payment cannot process the selected fee.', 'woocommerce-mercadopago' );
			case 'cc_rejected_max_attempts':
				return __( 'You have reached the limit of allowed attempts. Choose another card or other payment method.', 'woocommerce-mercadopago' );
			default:
				return __( 'This payment method cannot process your payment.', 'woocommerce-mercadopago' );
		}
	}

	/**
	 * Payment Fields
	 */
	public function payment_fields() {
		// add css.
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'woocommerce-mercadopago-narciso-styles',
			plugins_url( '../assets/css/mp-plugins-components.css', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION
		);

		$total     = $this->get_order_total();
		$subtotal  = (float) WC()->cart->subtotal;
		$tax       = $total - $subtotal;
		$discount  = $subtotal * ( $this->gateway_discount / 100 );
		$comission = $subtotal * ( $this->commission / 100 );
		$amount    = $subtotal - $discount + $comission;
		$amount    = $amount + $tax;

		$banner_url     = $this->get_option( '_mp_custom_banner' );
		$test_mode_link = $this->get_mp_devsite_link($this->checkout_country);
		if ( ! isset( $banner_url ) || empty( $banner_url ) ) {
			$banner_url = $this->site_data['checkout_banner_custom'];
		}

		// credit or debit card.
		$debit_card  = array();
		$credit_card = array();
		$tarjetas    = get_option( '_checkout_payments_methods', '' );

		foreach ( $tarjetas as $tarjeta ) {
			if ( 'credit_card' === $tarjeta['type'] ) {
				$credit_card[] = array(
					'src' => $tarjeta['image'],
					'alt' => $tarjeta['name']
				);
			} elseif ( 'debit_card' === $tarjeta['type'] || 'prepaid_card' === $tarjeta['type'] ) {
				$debit_card[] = array(
					'src' => $tarjeta['image'],
					'alt' => $tarjeta['name']
				);
			}
		}

		$payment_methods = array();

		if ( 0 !== count( $credit_card ) ) {
			$payment_methods[] = array(
				'title'           => __( 'Credit cards', 'woocommerce-mercadopago' ),
				'label'           => __( 'Up to ' , 'woocommerce-mercadopago' ) . 12 . __( ' installments' , 'woocommerce-mercadopago' ),
				'payment_methods' => $credit_card,
			);
		}

		if ( 0 !== count( $debit_card ) ) {
			$payment_methods[] = array(
				'title' => __( 'Debit cards', 'woocommerce-mercadopago' ),
				'payment_methods' => $debit_card,
			);
		}

		try {
			$currency_ratio = WC_WooMercadoPago_Helpers_CurrencyConverter::get_instance()->ratio( $this );
		} catch ( Exception $e ) {
			$currency_ratio = WC_WooMercadoPago_Helpers_CurrencyConverter::DEFAULT_RATIO;
		}

		$parameters = array(
			'test_mode'            => ! $this->is_production_mode(),
			'test_mode_link'       => $test_mode_link,
			'amount'               => $amount,
			'site_id'              => $this->mp_options->get_site_id(),
			'public_key'           => $this->get_public_key(),
			'coupon_mode'          => isset( $this->logged_user_email ) ? $this->coupon_mode : 'no',
			'discount_action_url'  => $this->discount_action_url,
			'payer_email'          => esc_js( $this->logged_user_email ),
			'images_path'          => plugins_url( '../assets/images/', plugin_dir_path( __FILE__ ) ),
			'currency_ratio'       => $currency_ratio,
			'woocommerce_currency' => get_woocommerce_currency(),
			'account_currency'     => $this->site_data['currency'],
			'payment_methods'      => $payment_methods,
			'wallet_button'        => $this->wallet_button,
		);

		$parameters = array_merge($parameters, WC_WooMercadoPago_Helper_Links::mp_define_terms_and_conditions());
		wc_get_template( 'checkout/custom-checkout.php', $parameters, 'woo/mercado/pago/module/', WC_WooMercadoPago_Module::get_templates_path() );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order Id.
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification
		if ( ! isset( $_POST['mercadopago_custom']) ) {
			return $this->process_result_fail(
				__FUNCTION__,
				__( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago' ),
				__( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago' )
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$custom_checkout     = map_deep($_POST['mercadopago_custom'], 'sanitize_text_field');
		$custom_checkout_log = $custom_checkout;

		if ( isset($custom_checkout_log['token']) ) {
			unset($custom_checkout_log['token']);
		}

		$this->log->write_log( __FUNCTION__, 'POST Custom: ' . wp_json_encode( $custom_checkout_log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		$order = wc_get_order( $order_id );

		$this->process_discount_and_commission( $order_id, $order );

		if ( 'wallet_button' === $custom_checkout['checkout_type'] ) {
			$this->log->write_log( __FUNCTION__, 'preparing to render wallet button checkout.' );
			$response = $this->process_custom_checkout_wallet_button_flow( $order );
		} else {
			$this->log->write_log( __FUNCTION__, 'preparing to get response of custom checkout.' );
			$response = $this->process_custom_checkout_flow( $custom_checkout, $order );
		}

		if ( $response ) {
			return $response;
		}

		return $this->process_result_fail(
			__FUNCTION__,
			__( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago' ),
			__( 'A problem was occurred when processing your payment. Please, try again.', 'woocommerce-mercadopago' )
		);
	}

	/**
	 * Process Custom Wallet Button Flow
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	protected function process_custom_checkout_wallet_button_flow( $order ) {
		return array(
			'result'   => 'success',
			'redirect' => add_query_arg(
				array(
					'wallet_button' => 'open'
				),
				$order->get_checkout_payment_url( true )
			),
		);
	}

	/**
	 * Process Custom Payment Flow
	 *
	 * @param array $custom_checkout
	 * @param WC_Order $order
	 *
	 * @return array|string[]
	 * @throws WC_Data_Exception
	 */
	protected function process_custom_checkout_flow( $custom_checkout, $order ) {
		if (
			isset( $custom_checkout['amount'] ) && ! empty( $custom_checkout['amount'] ) &&
			isset( $custom_checkout['token'] ) && ! empty( $custom_checkout['token'] ) &&
			isset( $custom_checkout['paymentMethodId'] ) && ! empty( $custom_checkout['paymentMethodId'] ) &&
			isset( $custom_checkout['installments'] ) && ! empty( $custom_checkout['installments'] ) &&
			-1 !== $custom_checkout['installments']
		) {
			$response = $this->create_payment( $order, $custom_checkout );

			if ( ! is_array( $response ) ) {
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			}

			$installments       = (float) $response['installments'];
			$installment_amount = (float) $response['transaction_details']['installment_amount'];
			$transaction_amount = (float) $response['transaction_amount'];
			$total_paid_amount  = (float) $response['transaction_details']['total_paid_amount'];
			$last_four_digits   = (float) $response['transaction_details']['last_four_digits'];
			$id_payment         = (float) $response['id'];

			// Old
			$order->add_meta_data('mp_installments', $installments);
			$order->add_meta_data('mp_transaction_details', $installment_amount);
			$order->add_meta_data('mp_transaction_amount', $transaction_amount);
			$order->add_meta_data('mp_total_paid_amount', $total_paid_amount);
			$order->save();

			// New key names
			$order->add_meta_data('Mercado Pago - ' . $id_payment . ' - installments', $installments);
			$order->add_meta_data('Mercado Pago - ' . $id_payment . ' - installment_amount', $installment_amount);
			$order->add_meta_data('Mercado Pago - ' . $id_payment . ' - transaction_amount', $transaction_amount);
			$order->add_meta_data('Mercado Pago - ' . $id_payment . ' - total_paid_amount', $total_paid_amount);
			$order->add_meta_data('Mercado Pago - ' . $id_payment . ' - card_last_four_digits', $last_four_digits);
			$order->save();

			$this->hook->update_mp_order_payments_metadata( $order->get_id(), [ $response['id'] ] );

			// Switch on response.
			if ( array_key_exists( 'status', $response ) ) {
				switch ( $response['status'] ) {
					case 'approved':
						WC()->cart->empty_cart();
						wc_add_notice( '<p>' . $this->get_order_status( 'accredited' ) . '</p>', 'notice' );
						$this->set_order_to_pending_on_retry( $order );
						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_order_received_url(),
						);
					case 'pending':
						// Order approved/pending, we just redirect to the thankyou page.
						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_order_received_url(),
						);
					case 'in_process':
						// For pending, we don't know if the purchase will be made, so we must inform this status.
						WC()->cart->empty_cart();
						wc_add_notice(
							'<p>' . $this->get_order_status( $response['status_detail'] ) . '</p>' .
							'<p><a id="mp_pending_payment_button" class="button" href="' . esc_url( $order->get_checkout_order_received_url() ) . '" data-mp-checkout-type="woo-mercado-pago-' . $custom_checkout['checkout_type'] . '">' .
							__( 'See your order form', 'woocommerce-mercadopago' ) .
							'</a></p>',
							'notice'
						);
						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_payment_url( true ),
						);
					case 'rejected':
						// If rejected is received, the order will not proceed until another payment try, so we must inform this status.
						wc_add_notice(
							'<p>' . __(
								'Your payment was declined. You can try again.',
								'woocommerce-mercadopago'
							) . '<br>' .
							$this->get_order_status( $response['status_detail'] ) .
							'</p>' .
							'<p><a id="mp_failed_payment_button" class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '" data-mp-checkout-type="woo-mercado-pago-' . $custom_checkout['checkout_type'] . '">' .
							__( 'Click to try again', 'woocommerce-mercadopago' ) .
							'</a></p>',
							'error'
						);
						return array(
							'result'   => 'success',
							'redirect' => $order->get_checkout_payment_url( true ),
						);
					case 'cancelled':
					case 'in_mediation':
					case 'charged_back':
						// If we enter here (an order generating a direct [cancelled, in_mediation, or charged_back] status),
						// then there must be something very wrong!
						break;
					default:
						break;
				}
			}

			// Process when fields are incomplete.
			return $this->process_result_fail(
				__FUNCTION__,
				__( 'A problem was occurred when processing your payment. Are you sure you have correctly filled all information in the checkout form?', 'woocommerce-mercadopago' ),
				__( 'A problem was occurred when processing your payment. Are you sure you have correctly filled all information in the checkout form?', 'woocommerce-mercadopago' ) . ' MERCADO PAGO: ' .
				WC_WooMercadoPago_Module::get_common_error_messages( $response )
			);
		}
	}

	/**
	 * Display installment fee as an order attachment item
	 *
	 * @param $order_id
	 */
	public function display_installment_fee_order( $order_id ) {
		$order = wc_get_order( $order_id );
		$order->get_meta_data();

		$gatewayName = $order->get_meta( '_used_gateway' );
		if ( strpos($gatewayName, 'Custom_Gateway') ) {
			$total_paid_amount      = $order->get_meta( 'mp_total_paid_amount' );
			$transaction_amount     = $order->get_meta( 'mp_transaction_amount' );
			$installment_fee_amount = (float) $total_paid_amount - (float) $transaction_amount;
			$total_with_fees        = $installment_fee_amount + (float) $transaction_amount;

			if ( $installment_fee_amount > 0 ) {
				$this->display_order_note( [
					'tip_text' => __('Represents the installment fee charged by Mercado Pago.', 'woocommerce-mercadopago'),
					'title'    => __('Mercado Pago Installment Fee:', 'woocommerce-mercadopago'),
					'value'    => wc_price($installment_fee_amount, []),
				] );

				$this->display_order_note( [
					'tip_text' => __('Represents the total purchase plus the installment fee charged by Mercado Pago.', 'woocommerce-mercadopago'),
					'title'    => __('Mercado Pago Total:', 'woocommerce-mercadopago'),
					'value'    => wc_price($total_with_fees, []),
				] );
			}
		}
	}

	/**
	 * Display generic template for order notes
	 *
	 * @param array $params
	 */
	public function display_order_note( $params ) {
		wc_get_template(
			'order/generic-order-note.php',
			$params,
			'woo/mercado/pago/module/',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Fill a commission and discount information
	 *
	 * @param $order_id
	 * @param $order
	 */
	protected function process_discount_and_commission( $order_id, $order ) {
		$amount         = (float) WC()->cart->subtotal;
		$shipping_taxes = floatval($order->get_shipping_total());

		if ( method_exists( $order, 'update_meta_data' ) ) {
			$order->update_meta_data( 'is_production_mode', 'no' === $this->mp_options->get_checkbox_checkout_test_mode() ? 'yes' : 'no' );
			$order->update_meta_data( '_used_gateway', get_class( $this ) );

			if ( ! empty( $this->gateway_discount ) ) {
				$discount = ( $amount * $this->gateway_discount / 100 );
				$order->set_total($amount - $discount + $shipping_taxes);
			}

			if ( ! empty( $this->commission ) ) {
				$comission = $amount * ( $this->commission / 100 );
				$order->update_meta_data( 'Mercado Pago: commission', __( 'fee of', 'woocommerce-mercadopago' ) . ' ' . $this->commission . '% / ' . __( 'fee of', 'woocommerce-mercadopago' ) . ' = ' . $comission );
			}
			$order->save();
		} else {
			update_post_meta( $order_id, '_used_gateway', get_class( $this ) );
			if ( ! empty( $this->gateway_discount ) ) {
				$discount = ( $amount * $this->gateway_discount / 100 );
				$order->update_meta_data( 'Mercado Pago: discount', __( 'discount of', 'woocommerce-mercadopago' ) . ' ' . $this->gateway_discount . '% / ' . __( 'discount of', 'woocommerce-mercadopago' ) . ' = ' . $discount );
				$order->set_total($amount - $discount + $shipping_taxes);
			}

			if ( ! empty( $this->commission ) ) {
				$comission = $amount * ( $this->commission / 100 );
				update_post_meta( $order_id, 'Mercado Pago: commission', __( 'fee of', 'woocommerce-mercadopago' ) . ' ' . $this->commission . '% / ' . __( 'fee of', 'woocommerce-mercadopago' ) . ' = ' . $comission );
			}
		}
	}

	/**
	 * Process if result is fail
	 *
	 * @param $function
	 * @param $log_message
	 * @param $notice_message
	 *
	 * @return string[]
	 */
	protected function process_result_fail( $function, $log_message, $notice_message ) {
		$this->log->write_log( $function, $log_message );

		wc_add_notice(
			'<p>' . $notice_message . '</p>',
			'error'
		);

		return array(
			'result'   => 'fail',
			'redirect' => '',
		);
	}

	/**
	 * Create Payment
	 *
	 * @param object $order Order.
	 * @param mixed  $custom_checkout Checkout info.
	 * @return string|array
	 */
	protected function create_payment( $order, $custom_checkout ) {
		$preferences_custom = new WC_WooMercadoPago_Preference_Custom( $this, $order, $custom_checkout );
		$payment            = $preferences_custom->get_transaction( 'Payment' );

		try {
			$checkout_info = $payment->save();
			$this->log->write_log( __FUNCTION__, 'Payment created: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			return $checkout_info;
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, 'payment creation failed with error: ' . $e->getMessage() );
			return $e->getMessage();
		}
	}

	/**
	 * Create Wallet Button Preference
	 *
	 * @param $order
	 *
	 * @return false|mixed
	 */
	public function create_preference_wallet_button( $order ) {
		$this->installments       = 12;
		$preference_wallet_button = new WC_WooMercadoPago_Preference_Custom_Wallet_Button( $this, $order );
		$preference               = $preference_wallet_button->get_transaction( 'Preference' );

		try {
			$checkout_info = $preference->save();
			$this->log->write_log( __FUNCTION__, 'Created Preference: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			return $checkout_info;
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, 'preference creation failed with error: ' . $e->getMessage() );
			return false;
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

		$_mp_access_token    = $this->mp_options->get_access_token_prod();
		$is_prod_credentials = false === WC_WooMercadoPago_Credentials::validate_credentials_test( $this->mp, $_mp_access_token, null );

		if ( ( empty( $_SERVER['HTTPS'] ) || 'off' === $_SERVER['HTTPS'] ) && $is_prod_credentials ) {
			$this->log->write_log( __FUNCTION__, 'NO HTTPS, Custom unavailable.' );
			return false;
		}

		return true;
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
		return apply_filters( 'woocommerce_mercadopago_icon', plugins_url( '../assets/images/icons/card.png', plugin_dir_path( __FILE__ ) ) );
	}

}
