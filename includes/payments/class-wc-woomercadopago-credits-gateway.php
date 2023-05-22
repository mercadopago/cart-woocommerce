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
 * Class WC_WooMercadoPago_Credits_Gateway
 */
class WC_WooMercadoPago_Credits_Gateway extends WC_WooMercadoPago_Payment_Abstract {

	const ID = 'woo-mercado-pago-credits';

	/**
	 * WC_WooMercadoPago_CreditsGateway constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception On load payment exception.
	 */
	public function __construct() {
		$this->id            = self::ID;
		$this->title         = __('Installments without card', 'woocommerce-mercadopago');
		$this->title_gateway = __('Installments without card', 'woocommerce-mercadopago');
		$this->description   = __('Customers who buy on spot and pay later in up to 12 installments', 'woocommerce-mercadopago');
		$this->mp_options    = $this->get_mp_options();

		if ( ! $this->validate_section() ) {
			return;
		}

		$this->form_fields          = array();
		$this->method_title         = __( 'Mercado Pago - Installments without card', 'woocommerce-mercadopago' );
		$this->method               = $this->get_option_mp( 'method', 'redirect' );
		$this->title                = $this->get_option_mp( 'title', __( 'Installments without card', 'woocommerce-mercadopago' ) );
		$this->method_description   = $this->description;
		$this->credits_banner       = $this->get_option('credits_banner', 'no');
		$this->gateway_discount     = $this->get_option('gateway_discount', 0);
		$this->clientid_old_version = $this->get_client_id();
		$this->field_forms_order    = $this->get_fields_sequence();

		parent::__construct();
		$this->form_fields         = $this->get_form_mp_fields();
		$this->hook                = new WC_WooMercadoPago_Hook_Credits($this);
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
				'woocommerce-mercadopago-credits-config-script',
				plugins_url( '../assets/js/credits_config_mercadopago' . $suffix . '.js', plugin_dir_path( __FILE__ ) ),
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
			$form_fields['checkout_payments_advanced_title'] = $this->field_checkout_payments_advanced_title();
			$form_fields['credits_banner']                   = $this->field_credits_banner_mode();
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
			'credits_banner',

			// Advanced settings.
			'checkout_payments_advanced_title',
			'checkout_payments_advanced_description',
			'method',
			'gateway_discount',
			'commission',
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
	 * Field enabled
	 *
	 * @return array
	 */
	public function field_enabled() {
		$site = strtolower($this->mp_options->get_site_id());

		return array(
			'title'        => __('Activate installments without card in your store checkout ', 'woocommerce-mercadopago'),
			'subtitle'     => __('Offer the option to pay in installments without card directly from your store\'s checkout.', 'woocommerce-mercadopago'),
			'type'         => 'mp_toggle_switch',
			'default'      => 'no',
			'descriptions' => array(
				'enabled'  => __('Payment in installments without card in the store checkout is <b>active</b>', 'woocommerce-mercadopago'),
				'disabled' => __('Payment in installments without card in the store checkout is <b>inactive</b>', 'woocommerce-mercadopago'),
			),
			'after_toggle' => $this->get_ckeckout_visualization($site),
		);
	}

	/**
	 * Example Banner Credits Admin
	 *
	 * @param $siteId
	 *
	 * @return string
	 */
	public function get_ckeckout_visualization( $siteId ) {
		return wc_get_template_html(
			'components/credits-checkout-example.php',
			array(
				'title'     => __('Checkout visualization', 'woocommerce-mercadopago'),
				'subtitle'  => __('Check below how this feature will be displayed to your customers:', 'woocommerce-mercadopago'),
				'footer'    => __('Checkout Preview', 'woocommerce-mercadopago'),
				'pill_text' => __('PREVIEW', 'woocommerce-mercadopago'),
				'image'     => plugins_url($this->get_mercado_credits_preview_image($siteId), plugin_dir_path(__FILE__)),
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Get image path for mercado credits checkout preview
	 *
	 * @param $siteId
	 *
	 * @return string
	 */
	protected function get_mercado_credits_preview_image( $siteId ) {
		$siteIds = [
			'mla' => 'HISPANO_',
			'mlb' => 'MLB_',
			'mlm' => 'HISPANO_',
		];

		$prefix = isset($siteIds[$siteId]) ? $siteIds[$siteId] : '';

		return sprintf('../assets/images/credits/%scheckout_preview.jpg', $prefix);
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
								' . __('Installments without card', 'woocommerce-mercadopago') . '
								 </div>
							<div class="mp-col-md-12">
								<p class="mp-text-checkout-body mp-mb-0">
									' . __('Reach millions of buyers by offering Mercado Credito as a payment method. Our flexible payment options <b>give your customers the possibility to buy today whatever they want in up to 12 installments without the need to use a credit card.</b>', 'woocommerce-mercadopago') . '
								</p>
								<p class="mp-text-checkout-body mp-mb-0">
									' . __('For your business, the approval of the purchase is immediate and guaranteed.', 'woocommerce-mercadopago') . '
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

		$test_mode_link = $this->get_mp_devsite_link( $this->checkout_country );

		$parameters = [
			'test_mode'           => ! $this->is_production_mode(),
			'test_mode_link'      => $test_mode_link,
			'plugin_version'      => WC_WooMercadoPago_Constants::VERSION,
			'redirect_image'      => plugins_url( '../assets/images/cho-pro-redirect-v2.png', plugin_dir_path( __FILE__ ) ),
		];

		$parameters = array_merge($parameters, WC_WooMercadoPago_Helper_Links::mp_define_terms_and_conditions());
		wc_get_template('checkout/credits-checkout.php', $parameters, 'woo/mercado/pago/module/', WC_WooMercadoPago_Module::get_templates_path());
	}

	/**
	 * Field Banner Credits
	 *
	 * @return array
	 */
	public function field_credits_banner_mode() {
		$site = strtolower($this->mp_options->get_site_id());

		return array(
			'title'    => __('Inform your customers about the option of paying in installments without card', 'woocommerce-mercadopago'),
			'type'     => 'mp_toggle_switch',
			'default'  => 'no',
			'subtitle' => __('<b>By activating the installments without card component</b>, you increase your chances of selling.', 'woocommerce-mercadopago'),
			'descriptions' => array(
				'enabled'  => __('The installments without card component is <b>active</b>.', 'woocommerce-mercadopago'),
				'disabled' => __('The installments without card component is <b>inactive</b>.', 'woocommerce-mercadopago'),
			),
			'after_toggle' => $this->get_credits_info_template($site)
		);
	}

	/**
	 * Example Banner Credits Admin
	 *
	 * @param $siteId
	 *
	 * @return string
	 */
	public function get_credits_info_template( $siteId ) {
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script(
			'woocommerce-mercadopago-info-admin-credits-script',
			plugins_url('../assets/js/credits/example-info' . $suffix . '.js', plugin_dir_path(__FILE__)),
			array(),
			WC_WooMercadoPago_Constants::VERSION,
			true
		);
		wp_enqueue_style(
			'woocommerce-mercadopago-info-admin-credits-style',
			plugins_url('../assets/css/credits/example-info' . $suffix . '.css', plugin_dir_path(__FILE__)),
			array(),
			WC_WooMercadoPago_Constants::VERSION
		);
		wp_localize_script(
			'woocommerce-mercadopago-info-admin-credits-script',
			'wc_mp_icon_images',
			array(
				'computerBlueIcon'  => plugins_url('../assets/images/credits/desktop-blue-icon.png', plugin_dir_path(__FILE__)),
				'computerGrayIcon'  => plugins_url('../assets/images/credits/desktop-gray-icon.png', plugin_dir_path(__FILE__)),
				'cellphoneBlueIcon' => plugins_url('../assets/images/credits/cellphone-blue-icon.png', plugin_dir_path(__FILE__)),
				'cellphoneGrayIcon' => plugins_url('../assets/images/credits/cellphone-gray-icon.png', plugin_dir_path(__FILE__)),
				'viewMobile'        => plugins_url($this->get_mercado_credits_gif_path($siteId, 'mobile'), plugin_dir_path(__FILE__)),
				'viewDesktop'       => plugins_url($this->get_mercado_credits_gif_path($siteId, 'desktop'), plugin_dir_path(__FILE__)),
				'footerDesktop'     => __('Banner on the product page | Computer version', 'woocommerce-mercadopago'),
				'footerCellphone'   => __('Banner on the product page | Cellphone version', 'woocommerce-mercadopago'),
			)
		);

		return wc_get_template_html(
			'components/credits-info-example.php',
			array(
				'desktop'   => __('Computer', 'woocommerce-mercadopago'),
				'cellphone' => __('Mobile', 'woocommerce-mercadopago'),
				'footer'    => __('Banner on the product page | Computer version', 'woocommerce-mercadopago'),
				'title'     => __('Component visualization', 'woocommerce-mercadopago'),
				'subtitle'  => __('Check below how this feature will be displayed to your customers:', 'woocommerce-mercadopago'),
			),
			'',
			WC_WooMercadoPago_Module::get_templates_path()
		);
	}

	/**
	 * Get git image path for mercado credits demonstration
	 *
	 * @param $siteId
	 * @param $view
	 *
	 * @return string
	 */
	protected function get_mercado_credits_gif_path( $siteId, $view ) {
		$siteIds = [
			'mla' => 'HISPANO_',
			'mlb' => 'MLB_',
			'mlm' => 'HISPANO_',
		];

		$prefix = isset($siteIds[$siteId]) ? $siteIds[$siteId] : '';

		return sprintf('../assets/images/credits/%sview_%s.gif', $prefix, $view);
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

		$this->log->write_log(__FUNCTION__, 'customer being redirected to Mercado Pago.');
			return array(
				'result'   => 'success',
				'redirect' => $this->create_preference($order),
			);
	}

	/**
	 * Create preference
	 *
	 * @param object $order Order.
	 * @return bool
	 */
	public function create_preference( $order ) {
		$preference_credits = new WC_WooMercadoPago_Preference_Credits( $this, $order );
		$preference         = $preference_credits->get_transaction( 'Preference' );

		try {
			$checkout_info = $preference->save();
			$this->log->write_log( __FUNCTION__, 'Created Preference: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			$this->log->write_log( __FUNCTION__, 'payment link generated with success from mercado pago, with structure as follow: ' . wp_json_encode( $checkout_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
			return ( $this->sandbox ) ? $checkout_info['sandbox_init_point'] : $checkout_info['init_point'];
		} catch ( Exception $e ) {
			$this->log->write_log( __FUNCTION__, 'payment creation failed with exception: ' . wp_json_encode( $e, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
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
}
