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
 * Class WC_WooMercadoPago_Configs
 */
class WC_WooMercadoPago_Configs {

	/**
	 * WC_WooMercadoPago_Configs constructor.
	 *
	 * @throws WC_WooMercadoPago_Exception Load configs exception.
	 */
	public function __construct() {
		$this->update_token_new_version();
		$this->show_notices();
	}

	/**
	 *  Show Notices in ADMIN
	 */
	private function show_notices() {
		add_action( 'admin_notices', array( $this, 'plugin_review' ) );
		add_action( 'admin_notices', array( $this, 'saved_cards_notice' ) );

		if ( empty( get_option( '_mp_public_key_prod' ) ) && empty( get_option( '_mp_access_token_prod' ) ) ) {
			if ( ! empty( get_option( '_mp_client_id' ) ) && ! empty( get_option( '_mp_client_secret' ) ) ) {
				add_action( 'admin_notices', array( $this, 'notice_update_access_token' ) );
			}
		}

		if ( ( empty( $_SERVER['HTTPS'] ) || 'off' === $_SERVER['HTTPS'] ) ) {
			add_action( 'admin_notices', array( $this, 'notice_https' ) );
		}
	}

	/**
	 * Update token new version
	 *
	 * @throws WC_WooMercadoPago_Exception Update token new version exception.
	 */
	private function update_token_new_version() {
		if ( empty( get_option( '_mp_public_key_prod', '' ) ) || empty( get_option( '_mp_access_token_prod', '' ) ) ) {
			if ( ! empty( get_option( '_mp_public_key' ) ) && ! empty( get_option( '_mp_access_token' ) ) ) {
				$this->update_token();
			}
		}
		if ( empty( strtolower( get_option( '_site_id_v1' ) ) ) || empty( get_option( '_collector_id_v1' ) ) ) {
			WC_WooMercadoPago_Credentials::validate_credentials_v1();
		}

		$ticket_methods = get_option( '_all_payment_methods_ticket', '' );
		if ( empty( $ticket_methods ) || ! is_array( $ticket_methods ) ) {
			$this->update_ticket_methods();
		}

		$all_payments = get_option( '_checkout_payments_methods', '' );
		if ( empty( $all_payments ) ) {
			$this->update_payments();
		}

		if ( ! empty( $all_payments ) ) {
			foreach ( $all_payments as $payment ) {
				if ( ! isset( $payment['name'] ) ) {
					$this->update_payments();
					break;
				}
			}
		}
	}

	/**
	 * Update payments
	 *
	 * @throws WC_WooMercadoPago_Exception Update payment exception.
	 */
	private function update_payments() {
		$mp_instance = WC_WooMercadoPago_Module::get_mp_instance_singleton();
		if ( $mp_instance ) {
			WC_WooMercadoPago_Credentials::update_payment_methods( $mp_instance, $mp_instance->get_access_token() );
		}
	}

	/**
	 * Update ticket methods
	 *
	 * @throws WC_WooMercadoPago_Exception Update ticket exception.
	 */
	private function update_ticket_methods() {
		$mp_instance = WC_WooMercadoPago_Module::get_mp_instance_singleton();
		if ( $mp_instance ) {
			WC_WooMercadoPago_Credentials::update_ticket_method( $mp_instance, $mp_instance->get_access_token() );
		}
	}

	/**
	 * Update pix methods
	 *
	 * @throws WC_WooMercadoPago_Exception Update ticket exception.
	 */
	private function update_pix_methods() {
		$mp_instance = WC_WooMercadoPago_Module::get_mp_instance_singleton();
		if ( $mp_instance ) {
			WC_WooMercadoPago_Credentials::update_pix_method( $mp_instance, $mp_instance->get_access_token() );
		}
	}

	/**
	 *  Notice Access Token
	 */
	public function notice_update_access_token() {
		$type    = 'error';
		$message = __( 'Update your credentials with the Access Token and Public Key, you need them to continue receiving payments!', 'woocommerce-mercadopago');
		echo wp_kses_post( WC_WooMercadoPago_Notices::get_alert_frame($message, $type ));
	}

	/**
	 * Notice HTTPS
	 */
	public function notice_https() {
		$type    = 'notice-warning';
		$message = __( 'The store should have HTTPS in order to activate both Checkout Personalizado and Ticket Checkout.', 'woocommerce-mercadopago');
		echo wp_kses_post(WC_WooMercadoPago_Notices::get_alert_frame($message, $type ));
	}

	private function must_not_show_notice() {
		$pages_to_show    = array( 'dashboard', 'plugins', 'woocommerce_page_wc-settings' );
		$dismissed_review = (int) get_option( '_mp_dismiss_review', 0 );

		return ! in_array( get_current_screen()->id, $pages_to_show, true ) || 0 !== $dismissed_review;
	}

	private function must_not_show_saved_cards_notice() {
		$pages_to_show    = array( 'dashboard', 'plugins', 'woocommerce_page_wc-settings' );
		$dismissed_review = (int) get_option( '_mp_dismiss_saved_cards_notice', 0 );

		return ! in_array( get_current_screen()->id, $pages_to_show, true ) || 0 !== $dismissed_review;
	}

	/**
	 * Plugin review
	 *
	 * @return false
	 */
	public function plugin_review() {
		if ( $this->must_not_show_notice() ) {
			return false;
		}
		echo wp_kses_post( WC_WooMercadoPago_Review_Notice::get_plugin_review_banner());
	}

	/**
	 * Saved Cards Notice
	 *
	 * @return false
	 */
	public function saved_cards_notice() {
		if ( $this->must_not_show_saved_cards_notice() ) {
			return false;
		}
		echo wp_kses_post( WC_WooMercadoPago_Saved_Cards::get_plugin_review_banner());
	}

	/**
	 *  UpdateToken
	 */
	private function update_token() {
		$mp_instance = WC_WooMercadoPago_Module::get_mp_instance_singleton();

		if ( $mp_instance ) {
			if (
				true === WC_WooMercadoPago_Credentials::validate_credentials_test( $mp_instance, null, get_option( '_mp_public_key' ) )
				&& true === WC_WooMercadoPago_Credentials::validate_credentials_test( $mp_instance, get_option( '_mp_access_token' ) )
			) {
				update_option( '_mp_public_key_test', get_option( '_mp_public_key' ), true );
				update_option( '_mp_access_token_test', get_option( '_mp_access_token' ), true );
				update_option( 'checkbox_checkout_test_mode', 'yes', true );
			}

			if (
				true === WC_WooMercadoPago_Credentials::validate_credentials_prod( $mp_instance, null, get_option( '_mp_public_key' ) )
				&& true === WC_WooMercadoPago_Credentials::validate_credentials_prod( $mp_instance, get_option( '_mp_access_token' ) )
			) {
				update_option( '_mp_public_key_prod', get_option( '_mp_public_key' ), true );
				update_option( '_mp_access_token_prod', get_option( '_mp_access_token' ), true );
				if ( ! empty( get_option( '_mp_public_key_prod', '' ) ) && ! empty( get_option( '_mp_access_token_prod', '' ) ) ) {
					update_option( '_mp_public_key', '' );
					update_option( '_mp_access_token', '' );
				}
				update_option( 'checkbox_checkout_test_mode', 'no', true );
			}
		}
	}

	/**
	 *  Country Configs
	 */
	public static function get_country_configs() {
		return array(
			'mco' => array(
				'site_id'                => 'mco',
				'sponsor_id'             => 208687643,
				'checkout_banner'        => plugins_url( '../../assets/images/MCO/standard_mco.jpg', __FILE__ ),
				'checkout_banner_custom' => plugins_url( '../../assets/images/MCO/credit_card.png', __FILE__ ),
				'currency'               => 'COP',
				'zip_code'               => '110111',
				'currency_symbol'        => '$',
				'intl'                   => 'es-CO',
			),
			'mla' => array(
				'site_id'                => 'mla',
				'sponsor_id'             => 208682286,
				'checkout_banner'        => plugins_url( '../../assets/images/MLA/standard_mla.jpg', __FILE__ ),
				'checkout_banner_custom' => plugins_url( '../../assets/images/MLA/credit_card.png', __FILE__ ),
				'currency'               => 'ARS',
				'zip_code'               => '3039',
				'currency_symbol'        => '$',
				'intl'                   => 'es-AR',
			),
			'mlb' => array(
				'site_id'                => 'mlb',
				'sponsor_id'             => 208686191,
				'checkout_banner'        => plugins_url( '../../assets/images/MLB/standard_mlb.jpg', __FILE__ ),
				'checkout_banner_custom' => plugins_url( '../../assets/images/MLB/credit_card.png', __FILE__ ),
				'currency'               => 'BRL',
				'zip_code'               => '01310924',
				'currency_symbol'        => 'R$',
				'intl'                   => 'pt-BR',
			),
			'mlc' => array(
				'site_id'                => 'mlc',
				'sponsor_id'             => 208690789,
				'checkout_banner'        => plugins_url( '../../assets/images/MLC/standard_mlc.gif', __FILE__ ),
				'checkout_banner_custom' => plugins_url( '../../assets/images/MLC/credit_card.png', __FILE__ ),
				'currency'               => 'CLP',
				'zip_code'               => '7591538',
				'currency_symbol'        => '$',
				'intl'                   => 'es-CL',
			),
			'mlm' => array(
				'site_id'                => 'mlm',
				'sponsor_id'             => 208692380,
				'checkout_banner'        => plugins_url( '../../assets/images/MLM/standard_mlm.jpg', __FILE__ ),
				'checkout_banner_custom' => plugins_url( '../../assets/images/MLM/credit_card.png', __FILE__ ),
				'currency'               => 'MXN',
				'zip_code'               => '11250',
				'currency_symbol'        => '$',
				'intl'                   => 'es-MX',
			),
			'mlu' => array(
				'site_id'                => 'mlu',
				'sponsor_id'             => 243692679,
				'checkout_banner'        => plugins_url( '../../assets/images/MLU/standard_mlu.png', __FILE__ ),
				'checkout_banner_custom' => plugins_url( '../../assets/images/MLU/credit_card.png', __FILE__ ),
				'currency'               => 'UYU',
				'zip_code'               => '11800',
				'currency_symbol'        => '$',
				'intl'                   => 'es-UY',
			),
			'mlv' => array(
				'site_id'                => 'mlv',
				'sponsor_id'             => 208692735,
				'checkout_banner'        => plugins_url( '../../assets/images/MLV/standard_mlv.jpg', __FILE__ ),
				'checkout_banner_custom' => plugins_url( '../../assets/images/MLV/credit_card.png', __FILE__ ),
				'currency'               => 'VEF',
				'zip_code'               => '1160',
				'currency_symbol'        => '$',
				'intl'                   => 'es-VE',
			),
			'mpe' => array(
				'site_id'                => 'mpe',
				'sponsor_id'             => 216998692,
				'checkout_banner'        => plugins_url( '../../assets/images/MPE/standard_mpe.png', __FILE__ ),
				'checkout_banner_custom' => plugins_url( '../../assets/images/MPE/credit_card.png', __FILE__ ),
				'currency'               => 'PEN',
				'zip_code'               => '15074',
				'currency_symbol'        => '$',
				'intl'                   => 'es-PE',
			),
		);
	}

	/**
	 * Get categories
	 *
	 * @return array
	 */
	public function get_categories() {
		return array(
			'store_categories_id'          =>
				array(
					'art',
					'baby',
					'coupons',
					'donations',
					'computing',
					'cameras',
					'video games',
					'television',
					'car electronics',
					'electronics',
					'automotive',
					'entertainment',
					'fashion',
					'games',
					'home',
					'musical',
					'phones',
					'services',
					'learnings',
					'tickets',
					'travels',
					'virtual goods',
					'others',
				),
			'store_categories_description' =>
				array(
					'Collectibles & Art',
					'Toys for Baby, Stroller, Stroller Accessories, Car Safety Seats',
					'Coupons',
					'Donations',
					'Computers & Tablets',
					'Cameras & Photography',
					'Video Games & Consoles',
					'LCD, LED, Smart TV, Plasmas, TVs',
					'Car Audio, Car Alarm Systems & Security, Car DVRs, Car Video Players, Car PC',
					'Audio & Surveillance, Video & GPS, Others',
					'Parts & Accessories',
					'Music, Movies & Series, Books, Magazines & Comics, Board Games & Toys',
					"Men's, Women's, Kids & baby, Handbags & Accessories, Health & Beauty, Shoes, Jewelry & Watches",
					'Online Games & Credits',
					'Home appliances. Home & Garden',
					'Instruments & Gear',
					'Cell Phones & Accessories',
					'General services',
					'Trainings, Conferences, Workshops',
					'Tickets for Concerts, Sports, Arts, Theater, Family, Excursions tickets, Events & more',
					'Plane tickets, Hotel vouchers, Travel vouchers',
					'E-books, Music Files, Software, Digital Images,  PDF Files and any item which can be electronically stored in a file, Mobile Recharge, DTH Recharge and any Online Recharge',
					'Other categories',
				),
		);
	}

	/**
	 * Set payment
	 *
	 * @param array|null $methods Methods.
	 *
	 * @return array
	 */
	public function set_payment_gateway( $methods = null ) {
		global $wp;
		if ( ! empty( $wp ) && isset( $wp->query_vars['wc-api'] ) ) {
			$api_request = wc_clean( $wp->query_vars['wc-api'] );
			if ( ! empty( $api_request ) && in_array(
					strtolower( $api_request ),
					array(
						'wc_woomercadopago_basic_gateway',
						'wc_woomercadopago_custom_gateway',
						'wc_woomercadopago_ticket_gateway',
						'wc_woomercadopago_pix_gateway',
						'wc_woomercadopago_credits_gateway',
						'wc_woomercadopago_basicgateway',
						'wc_woomercadopago_customgateway',
						'wc_woomercadopago_ticketgateway',
					),
					true
				) ) {
				if ( ! preg_match( '/(_gateway)/i', $api_request ) ) {
					$api_request = preg_replace( '/gateway/i', '_Gateway', $api_request );
				}
				$methods[] = $api_request;
			}

			return $methods;
		}

		return $this->get_available_payment_methods( $methods );
	}

	/**
	 * Get available payment methods
	 *
	 * @param array|null $methods
	 *
	 * @return array
	 */
	public function get_available_payment_methods( $methods = [] ) {
		$wc_country = WC_WooMercadoPago_Module::get_woocommerce_default_country();
		$site_id    = strtolower( get_option( '_site_id_v1', '' ) );

		$methods[] = 'WC_WooMercadoPago_Basic_Gateway';

		$credits_helper = $this->get_credits_helper_instance();
		if ( $credits_helper->is_credits() ) {
			$methods[] = 'WC_WooMercadoPago_Credits_Gateway';
		}

		$methods[] = 'WC_WooMercadoPago_Custom_Gateway';
		$methods[] = 'WC_WooMercadoPago_Ticket_Gateway';

		if ( ( 'BR' === $wc_country && '' === $site_id ) || ( 'mlb' === $site_id ) ) {
			$methods[] = 'WC_WooMercadoPago_Pix_Gateway';
		}

		return $methods;
	}

	public function get_credits_helper_instance() {
		return new WC_WooMercadoPago_Helper_Credits();
	}

}
