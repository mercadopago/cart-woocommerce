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
 * Class WC_WooMercadoPago_Basic_Gateway
 */
class WC_WooMercadoPago_Basic_Gateway extends WC_WooMercadoPago_Payment_Abstract {
	/**
	 * ID
	 *
	 * @const
	 */
	const ID = 'woo-mercado-pago-basic';

	/**
	 * Credits Helper Class
	 *
	 * @var WC_WooMercadoPago_Helper_Credits
	 */
	private $credits_helper;

	/**
	 * WC_WooMercadoPago_BasicGateway constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception On load payment exception.
	 */
	public function __construct() {
		$this->id            = self::ID;
		$this->title         = __('Checkout Pro', 'woocommerce-mercadopago');
		$this->title_gateway = __('Checkout Pro', 'woocommerce-mercadopago');
		$this->description   = __('Debit, Credit and invoice in Mercado Pago environment', 'woocommerce-mercadopago');
		$this->mp_options    = $this->get_mp_options();

		if ( ! $this->validate_section() ) {
			return;
		}

		$this->form_fields          = array();
		$this->method_title         = __( 'Mercado Pago - Checkout Pro', 'woocommerce-mercadopago' );
		$this->method               = $this->get_option_mp( 'method', 'redirect' );
		$this->title                = $this->get_option_mp( 'title', __( 'Your saved cards or money in Mercado Pago', 'woocommerce-mercadopago' ) );
		$this->method_description   = $this->description;
		$this->auto_return          = $this->get_option('auto_return', 'yes');
		$this->success_url          = $this->get_option('success_url', '');
		$this->failure_url          = $this->get_option('failure_url', '');
		$this->pending_url          = $this->get_option('pending_url', '');
		$this->installments         = $this->get_option('installments', '24');
		$this->gateway_discount     = $this->get_option('gateway_discount', 0);
		$this->clientid_old_version = $this->get_client_id();
		$this->field_forms_order    = $this->get_fields_sequence();
		$this->ex_payments          = $this->get_ex_payments();

		parent::__construct();
		$this->credits_helper      = new WC_WooMercadoPago_Helper_Credits();
		$this->form_fields         = $this->get_form_mp_fields();
		$this->hook                = new WC_WooMercadoPago_Hook_Basic($this);
		$this->notification        = new WC_WooMercadoPago_Notification_Core($this);
		$this->currency_convertion = true;
		$this->icon                = $this->get_checkout_icon();
	}

	/**
	 * Get MP fields label
	 *
	 * @return array
	 */
	public function get_form_mp_fields() {
		if ( is_admin() && $this->is_manage_section() && ( WC_WooMercadoPago_Helper_Current_Url::validate_page('mercadopago-settings') || WC_WooMercadoPago_Helper_Current_Url::validate_section('woo-mercado-pago') ) ) {
			$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_script(
				'woocommerce-mercadopago-basic-config-script',
				plugins_url( '../assets/js/basic_config_mercadopago' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
				array(),
				WC_WooMercadoPago_Constants::VERSION,
				true
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
			$form_fields['checkout_header']                  = $this->field_checkout_header();
			$form_fields['binary_mode']                      = $this->field_binary_mode();
			$form_fields['installments']                     = $this->field_installments();
			$form_fields['checkout_payments_advanced_title'] = $this->field_checkout_payments_advanced_title();
			$form_fields['method']                           = $this->field_method();
			$form_fields['success_url']                      = $this->field_success_url();
			$form_fields['failure_url']                      = $this->field_failure_url();
			$form_fields['pending_url']                      = $this->field_pending_url();
			$form_fields['auto_return']                      = $this->field_auto_return();
			$form_fields['ex_payments']                      = $this->field_ex_payments();
		}

		$form_fields_abs = parent::get_form_mp_fields();
		if ( count($form_fields_abs) === 1 ) {
			return $form_fields_abs;
		}
		$form_fields_merge = array_merge($form_fields_abs, $form_fields);
		return $this->sort_form_fields($form_fields_merge, $this->field_forms_order);
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
			// Checkout Básico. Acepta todos los medios de pago y lleva tus cobros a otro nivel.
			'checkout_header',
			// No olvides de homologar tu cuenta.
			'checkout_card_homolog',
			// Set up the payment experience in your store.
			'checkout_card_validate',
			'enabled',
			'title',
			WC_WooMercadoPago_Helpers_CurrencyConverter::CONFIG_KEY,
			'ex_payments',
			'installments',

			// Advanced settings.
			'checkout_payments_advanced_title',
			'checkout_payments_advanced_description',
			'method',
			'auto_return',
			'success_url',
			'failure_url',
			'pending_url',
			'binary_mode',
			'gateway_discount',
			'commission',
		);
	}

	/**
	 * Field Installments
	 *
	 * @return array
	 */
	public function field_installments() {
		return array(
			'title'       => __('Maximum number of installments', 'woocommerce-mercadopago'),
			'type'        => 'select',
			'description' => __('What is the maximum quota with which a customer can buy?', 'woocommerce-mercadopago'),
			'default'     => '24',
			'options'     => array(
				'1'  => __('1 installment', 'woocommerce-mercadopago'),
				'2'  => __('2 installments', 'woocommerce-mercadopago'),
				'3'  => __('3 installments', 'woocommerce-mercadopago'),
				'4'  => __('4 installments', 'woocommerce-mercadopago'),
				'5'  => __('5 installments', 'woocommerce-mercadopago'),
				'6'  => __('6 installments', 'woocommerce-mercadopago'),
				'10' => __('10 installments', 'woocommerce-mercadopago'),
				'12' => __('12 installments', 'woocommerce-mercadopago'),
				'15' => __('15 installments', 'woocommerce-mercadopago'),
				'18' => __('18 installments', 'woocommerce-mercadopago'),
				'24' => __('24 installments', 'woocommerce-mercadopago'),
			),
		);
	}

	/**
	 * Is available?
	 *
	 * @return bool
	 * @throws WC_WooMercadoPago_Exception Load access token exception.
	 */
	public function is_available() {
		if ( parent::is_available() ) {
			return true;
		}

		if ( isset($this->settings['enabled']) && 'yes' === $this->settings['enabled'] ) {
			if ( $this->mp instanceof MP ) {
				$access_token = $this->mp->get_access_token();
				if (
				false === WC_WooMercadoPago_Credentials::validate_credentials_test($this->mp, $access_token)
				&& true === $this->sandbox
				) {
					return false;
				}

				if (
				false === WC_WooMercadoPago_Credentials::validate_credentials_prod($this->mp, $access_token)
				&& false === $this->sandbox
				) {
					return false;
				}
				return true;
			}
		}
		return false;
	}

	/**
	 * Get clientID when update version 3.0.17 to 4 latest
	 *
	 * @return string
	 */
	public function get_client_id() {
		$client_id = $this->mp_options->get_client_id();
		if ( ! empty($client_id) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get Payments
	 *
	 * @return array
	 */
	private function get_ex_payments() {
		$ex_payments            = array();
		$get_ex_payment_options = get_option('_all_payment_methods_v0', '');
		if ( ! empty($get_ex_payment_options) ) {
			$options = explode(',', $get_ex_payment_options);
			foreach ( $options as $option ) {
				if ( 'no' === $this->get_option('ex_payments_' . $option, 'yes') ) {
					$ex_payments[] = $option;
				}
			}
		}
		return $ex_payments;
	}

	/**
	 * Field enabled
	 *
	 * @return array
	 */
	public function field_enabled() {
		return array(
			'title'       => __('Enable the checkout', 'woocommerce-mercadopago'),
			'subtitle'    => __('By disabling it, you will disable all payments from Mercado Pago Checkout at Mercado Pago website by redirect.', 'woocommerce-mercadopago'),
			'type'        => 'mp_toggle_switch',
			'default'     => 'no',
			'descriptions' => array(
				'enabled' => __('The checkout is <b>enabled</b>.', 'woocommerce-mercadopago'),
				'disabled' => __('The checkout is <b>disabled</b>.', 'woocommerce-mercadopago'),
			),
		);
	}

	/**
	 * Field checkout header
	 *
	 * @return array
	 */
	public function field_checkout_header() {
		return array(
			'title' => sprintf(
				'<div class="row">
								<div class="mp-col-md-12 mp_subtitle_header">
								' . __('Checkout Pro', 'woocommerce-mercadopago') . '
								 </div>
							<div class="mp-col-md-12">
								<p class="mp-text-checkout-body mp-mb-0">
									' . __('With Checkout Pro you sell with all the safety inside Mercado Pago environment.', 'woocommerce-mercadopago') . '
								</p>
							</div>
						</div>'
			),
			'type'  => 'title',
			'class' => 'mp_title_header',
		);
	}

	/**
	 * Field checkout payments advanced title
	 *
	 * @return array
	 */
	public function field_checkout_payments_advanced_title() {
		return array(
			'title' => __('Advanced settings', 'woocommerce-mercadopago'),
			'type'  => 'title',
			'class' => 'mp_subtitle_bd',
		);
	}

	/**
	 * Field method
	 *
	 * @return array
	 */
	public function field_method() {
		return array(
			'title'       => __('Payment experience', 'woocommerce-mercadopago'),
			'type'        => 'select',
			'description' => __('Define what payment experience your customers will have, whether inside or outside your store.', 'woocommerce-mercadopago'),
			'default'     => ( 'iframe' === $this->method ) ? 'redirect' : $this->method,
			'options'     => array(
				'redirect' => __('Redirect', 'woocommerce-mercadopago'),
				'modal'    => __('Modal', 'woocommerce-mercadopago'),
			),
		);
	}

	/**
	 * Field success url
	 *
	 * @return array
	 */
	public function field_success_url() {
		// Validate back URL.
		if ( ! empty($this->success_url) && filter_var($this->success_url, FILTER_VALIDATE_URL) === false ) {
			$success_back_url_message = '<img width="14" height="14" src="' . plugins_url('assets/images/warning.png', plugin_dir_path(__FILE__)) . '"> ' .
				__('This seems to be an invalid URL.', 'woocommerce-mercadopago') . ' ';
		} else {
			$success_back_url_message = __('Choose the URL that we will show your customers when they finish their purchase.', 'woocommerce-mercadopago');
		}
		return array(
			'title'       => __('Success URL', 'woocommerce-mercadopago'),
			'type'        => 'text',
			'description' => $success_back_url_message,
			'default'     => '',
		);
	}

	/**
	 * Field failure url
	 *
	 * @return array
	 */
	public function field_failure_url() {
		if ( ! empty($this->failure_url) && filter_var($this->failure_url, FILTER_VALIDATE_URL) === false ) {
			$fail_back_url_message = '<img width="14" height="14" src="' . plugins_url('assets/images/warning.png', plugin_dir_path(__FILE__)) . '"> ' .
				__('This seems to be an invalid URL.', 'woocommerce-mercadopago') . ' ';
		} else {
			$fail_back_url_message = __('Choose the URL that we will show to your customers when we refuse their purchase. Make sure it includes a message appropriate to the situation and give them useful information so they can solve it.', 'woocommerce-mercadopago');
		}
		return array(
			'title'       => __('Payment URL rejected', 'woocommerce-mercadopago'),
			'type'        => 'text',
			'description' => $fail_back_url_message,
			'default'     => '',
		);
	}

	/**
	 * Field pending
	 *
	 * @return array
	 */
	public function field_pending_url() {
		// Validate back URL.
		if ( ! empty($this->pending_url) && filter_var($this->pending_url, FILTER_VALIDATE_URL) === false ) {
			$pending_back_url_message = '<img width="14" height="14" src="' . plugins_url('assets/images/warning.png', plugin_dir_path(__FILE__)) . '"> ' .
				__('This seems to be an invalid URL.', 'woocommerce-mercadopago') . ' ';
		} else {
			$pending_back_url_message = __('Choose the URL that we will show to your customers when they have a payment pending approval.', 'woocommerce-mercadopago');
		}
		return array(
			'title'       => __('Payment URL pending', 'woocommerce-mercadopago'),
			'type'        => 'text',
			'description' => $pending_back_url_message,
			'default'     => '',
		);
	}

	/**
	 * Field payments
	 *
	 * @return array
	 */
	public function field_ex_payments() {
		$payment_list = array(
			'description'          => __('Enable the payment methods available to your clients.', 'woocommerce-mercadopago'),
			'title'                => __('Choose the payment methods you accept in your store', 'woocommerce-mercadopago'),
			'type'                 => 'mp_checkbox_list',
			'payment_method_types' => array(
				'credit_card'      => array(
					'label'        => __('Credit Cards', 'woocommerce-mercadopago'),
					'list'         => array(),
				),
				'debit_card'       => array(
					'label'        => __('Debit Cards', 'woocommerce-mercadopago'),
					'list'         => array(),
				),
				'other'            => array(
					'label'        => __('Other Payment Methods', 'woocommerce-mercadopago'),
					'list'         => array(),
				),
			),
		);

		$all_payments = get_option('_checkout_payments_methods', '');

		if ( empty($all_payments) ) {
			return $payment_list;
		}

		foreach ( $all_payments as $payment_method ) {
			if ( 'credit_card' === $payment_method['type'] ) {
				$payment_list['payment_method_types']['credit_card']['list'][] = array(
				'id'        => 'ex_payments_' . $payment_method['id'],
				'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
				'label'     => $payment_method['name'],
				'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
				'type'      => 'checkbox',
				);
			} elseif ( 'debit_card' === $payment_method['type'] || 'prepaid_card' === $payment_method['type'] ) {
				$payment_list['payment_method_types']['debit_card']['list'][] = array(
				'id'        => 'ex_payments_' . $payment_method['id'],
				'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
				'label'     => $payment_method['name'],
				'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
				'type'      => 'checkbox',
				);
			} else {
				$payment_list['payment_method_types']['other']['list'][] = array(
				'id'        => 'ex_payments_' . $payment_method['id'],
				'field_key' => $this->get_field_key('ex_payments_' . $payment_method['id']),
				'label'     => $payment_method['name'],
				'value'     => $this->get_option('ex_payments_' . $payment_method['id'], 'yes'),
				'type'      => 'checkbox',
				);
			}
		}

		return $payment_list;
	}

	/**
	 * Field auto return
	 *
	 * @return array
	 */
	public function field_auto_return() {
		return array(
			'title'       => __('Return to the store', 'woocommerce-mercadopago'),
			'subtitle'    => __('Do you want your customer to automatically return to the store after payment?', 'woocommerce-mercadopago'),
			'type'        => 'mp_toggle_switch',
			'default'     => 'yes',
			'descriptions' => array(
				'enabled' => __('The buyer <b>will be automatically redirected to the store</b>.', 'woocommerce-mercadopago'),
				'disabled' => __('The buyer <b>will not be automatically redirected to the store</b>.', 'woocommerce-mercadopago'),
			),
		);
	}

	/**
	 * Payment Fields
	 */
	public function payment_fields() {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		// add css.
		wp_enqueue_style(
			'woocommerce-mercadopago-narciso-styles',
			plugins_url( '../assets/css/mp-plugins-components.css', plugin_dir_path( __FILE__ ) ),
			array(),
			WC_WooMercadoPago_Constants::VERSION
		);

		// validate active payments methods.
		$method         = $this->get_option_mp( 'method', 'redirect' );
		$test_mode_link = $this->get_mp_devsite_link( $this->checkout_country );
		$site           = strtoupper( $this->mp_options->get_site_id() );

		$payment_methods       = $this->get_payment_methods();
		$payment_methods_title = count($payment_methods) !== 0 ? __('Available payment methods', 'woocommerce-mercadopago') : '';

		$checkout_benefits_items = $this->get_benefits( $site );

		$parameters = [
			'method'                  => $method,
			'test_mode'               => ! $this->is_production_mode(),
			'test_mode_link'          => $test_mode_link,
			'plugin_version'          => WC_WooMercadoPago_Constants::VERSION,
			'checkout_redirect_src'   => plugins_url( '../assets/images/cho-pro-redirect-v2.png', plugin_dir_path( __FILE__ ) ),
			'payment_methods'         => wp_json_encode( $payment_methods ),
			'payment_methods_title'   => $payment_methods_title,
			'checkout_benefits_items' => wp_json_encode( $checkout_benefits_items )
		];

		$parameters = array_merge( $parameters, WC_WooMercadoPago_Helper_Links::mp_define_terms_and_conditions() );
		wc_get_template( 'checkout/basic-checkout.php', $parameters, 'woo/mercado/pago/module/', WC_WooMercadoPago_Module::get_templates_path() );
	}

	/**
	 * Process payment
	 *
	 * @param int $order_id Order Id.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order          = wc_get_order($order_id);
		$amount         = $this->get_order_total();
		$shipping_taxes = floatval($order->get_shipping_total());

		if ( method_exists($order, 'update_meta_data') ) {
			$order->update_meta_data('is_production_mode', 'no' === $this->mp_options->get_checkbox_checkout_test_mode() ? 'yes' : 'no');
			$order->update_meta_data('_used_gateway', get_class($this));

			if ( ! empty($this->gateway_discount) ) {
				$discount = ( $amount - $shipping_taxes ) * $this->gateway_discount / 100;
				$order->update_meta_data('Mercado Pago: discount', __('discount of', 'woocommerce-mercadopago') . ' ' . $this->gateway_discount . '% / ' . __('discount of', 'woocommerce-mercadopago') . ' = ' . $discount);
				$order->set_total($amount - $discount);
			}

			if ( ! empty($this->commission) ) {
				$comission = $amount * ( $this->commission / 100 );
				$order->update_meta_data('Mercado Pago: comission', __('fee of', 'woocommerce-mercadopago') . ' ' . $this->commission . '% / ' . __('fee of', 'woocommerce-mercadopago') . ' = ' . $comission);
			}
			$order->save();
		} else {
			update_post_meta($order_id, '_used_gateway', get_class($this));

			if ( ! empty($this->gateway_discount) ) {
				$discount = ( $amount - $shipping_taxes ) * $this->gateway_discount / 100;
				update_post_meta($order_id, 'Mercado Pago: discount', __('discount of', 'woocommerce-mercadopago') . ' ' . $this->gateway_discount . '% / ' . __('discount of', 'woocommerce-mercadopago') . ' = ' . $discount);
				$order->set_total($amount - $discount);
			}

			if ( ! empty($this->commission) ) {
				$comission = $amount * ( $this->commission / 100 );
				update_post_meta($order_id, 'Mercado Pago: comission', __('fee of', 'woocommerce-mercadopago') . ' ' . $this->commission . '% / ' . __('fee of', 'woocommerce-mercadopago') . ' = ' . $comission);
			}
		}

		if ( 'redirect' === $this->method || 'iframe' === $this->method ) {
			$this->log->write_log(__FUNCTION__, 'customer being redirected to Mercado Pago.');
			return array(
				'result'   => 'success',
				'redirect' => $this->create_preference($order),
			);
		} elseif ( 'modal' === $this->method ) {
			$this->log->write_log(__FUNCTION__, 'preparing to render Checkout Pro view.');
			return array(
				'result'   => 'success',
				'redirect' => $order->get_checkout_payment_url(true),
			);
		}
	}

	/**
	 * Create preference
	 *
	 * @param object $order Order.
	 * @return bool
	 */
	public function create_preference( $order ) {
		$preference_basic = new WC_WooMercadoPago_Preference_Basic( $this, $order );
		$preference       = $preference_basic->get_transaction( 'Preference' );

		try {
			$checkout_info = $preference->save();
			$this->log->write_log( __FUNCTION__, 'Created Preference: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			return ( $this->sandbox ) ? $checkout_info['sandbox_init_point'] : $checkout_info['init_point'];
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, 'preference creation failed with error: ' . $e->getMessage() );
			return false;
		}
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
		return apply_filters( 'woocommerce_mercadopago_icon', plugins_url( '../assets/images/icons/mercadopago.png', plugin_dir_path( __FILE__ ) ) );
	}

	/**
	 * Get payment methods
	 *
	 * @return array
	 */
	public function get_payment_methods() {
		$payment_methods_options = get_option( '_checkout_payments_methods', '' );
		$payment_methods         = [];

		if ( $this->credits_helper->is_credits() ) {
			$payment_methods[] = [
				'src' => plugins_url( '../assets/images/mercado-credito.png', plugin_dir_path(__FILE__) ),
				'alt' => 'Credits image'
			];
		}

		foreach ( $payment_methods_options as $payment_method_option ) {
			if ( 'yes' === $this->get_option_mp( $payment_method_option[ 'config' ], '' ) ) {
				$payment_methods[] = [
					'src' => $payment_method_option[ 'image' ],
					'alt' => $payment_method_option[ 'id' ]
				];
			}
		}

		return $payment_methods;
	}

	/**
	 * Get benefits items
	 *
	 * @param string $site
	 * @return array
	 */
	public function get_benefits( $site ) {
		$benefits = array(
			'MLB' => array(
				array(
					'title'    => __('Easy login', 'woocommerce-mercadopago'),
					'subtitle' => __('Log in with the same email and password you use in Mercado Libre.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-phone.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue phone image'
					)
				),
				array(
					'title'    => __('Quick payments', 'woocommerce-mercadopago'),
					'subtitle' => __('Use your saved cards, Pix or available balance.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-wallet.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue wallet image'
					)
				),
				array(
					'title'    => __('Protected purchases', 'woocommerce-mercadopago'),
					'subtitle' => __('Get your money back in case you don\'t receive your product.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-protection.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue protection image'
					)
				)
			),
			'MLM' => array(
				array(
					'title'    => __('Easy login', 'woocommerce-mercadopago'),
					'subtitle' => __('Log in with the same email and password you use in Mercado Libre.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-phone.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue phone image'
					)
				),
				array(
					'title'    => __('Quick payments', 'woocommerce-mercadopago'),
					'subtitle' => __('Use your available Mercado Pago Wallet balance or saved cards.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-wallet.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue wallet image'
					)
				),
				array(
					'title'    => __('Protected purchases', 'woocommerce-mercadopago'),
					'subtitle' => __('Get your money back in case you don\'t receive your product.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-protection.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue protection image'
					)
				)
			),
			'MLA' => array(
				array(
					'title'    => __('Quick payments', 'woocommerce-mercadopago'),
					'subtitle' => __('Use your available money or saved cards.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-wallet.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue wallet image'
					)
				),
				array(
					'title'    => __('Installments option', 'woocommerce-mercadopago'),
					'subtitle' => __('Pay with or without a credit card.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-phone-installments.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue phone installments image'
					)
				),
				array(
					'title'    => __('Reliable purchases', 'woocommerce-mercadopago'),
					'subtitle' => __('Get help if you have a problem with your purchase.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-protection.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue protection image'
					)
				)
			),
			'ROLA' => array(
				array(
					'title'    => __('Easy login', 'woocommerce-mercadopago'),
					'subtitle' => __('Log in with the same email and password you use in Mercado Libre.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-phone.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue phone image'
					)
				),
				array(
					'title'    => __('Quick payments', 'woocommerce-mercadopago'),
					'subtitle' => __('Use your available money or saved cards.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-wallet.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue wallet image'
					)
				),
				array(
					'title'    => __('Installments option', 'woocommerce-mercadopago'),
					'subtitle' => __('Interest-free installments with selected banks.', 'woocommerce-mercadopago'),
					'image'    => array(
						'src' => plugins_url( '../assets/images/blue-phone-installments.png', plugin_dir_path(__FILE__) ),
						'alt' => 'Blue phone installments image'
					)
				)
			),
		);

		return array_key_exists( $site, $benefits ) ? $benefits[ $site ] : $benefits[ 'ROLA' ];
	}
}
