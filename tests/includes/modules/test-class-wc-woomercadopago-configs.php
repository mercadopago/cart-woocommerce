<?php

/**
 * Class WC_WooMercadoPago_ConfigsTest
 *
 * @package woocommerce_mercadopago
 */

/**
 * WC_WooMercadoPago_ConfigsTest
 */
class WC_WooMercadoPago_ConfigsTest extends WP_UnitTestCase {

	function setUp() {
		require_once dirname( dirname( __FILE__ ) ) . '/../../includes/module/class-wc-woomercadopago-configs.php';
		require_once dirname( dirname( __FILE__ ) ) . '/../../includes/module/class-wc-woomercadopago-credentials.php';
		require_once dirname( dirname( __FILE__ ) ) . '/../../includes/module/class-wc-woomercadopago-module.php';
		require_once dirname( dirname( __FILE__ ) ) . '/../../includes/module/class-wc-woomercadopago-options.php';
		require_once dirname( dirname( __FILE__ ) ) . '/../../includes/helpers/class-wc-woomercadopago-helper-credits.php';

		update_option( 'woocommerce_default_country', 'BR:SP', true  );
	}

	/**
	 * Get Country Configs.
	 */
	function test_get_country_configs() {
		$country_configs = WC_WooMercadoPago_Configs::get_country_configs();

		$url_mco_standard_mco = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MCO/standard_mco.jpg', plugin_dir_path( __FILE__ ) ) );
		$url_mco_credit_card = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MCO/credit_card.png', plugin_dir_path( __FILE__ ) ) );

		$url_mla_standard_mla = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLA/standard_mla.jpg', plugin_dir_path( __FILE__ ) ) );
		$url_mla_credit_card = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLA/credit_card.png', plugin_dir_path( __FILE__ ) ) );

		$url_mlb_standard_mlb = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLB/standard_mlb.jpg', plugin_dir_path( __FILE__ ) ) );
		$url_mlb_credit_card = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLB/credit_card.png', plugin_dir_path( __FILE__ ) ) );

		$url_mlc_standard_mlc = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLC/standard_mlc.gif', plugin_dir_path( __FILE__ ) ) );
		$url_mlc_credit_card = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLC/credit_card.png', plugin_dir_path( __FILE__ ) ) );

		$url_mlm_standard_mlm = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLM/standard_mlm.jpg', plugin_dir_path( __FILE__ ) ) );
		$url_mlm_credit_card = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLM/credit_card.png', plugin_dir_path( __FILE__ ) ) );

		$url_mlu_standard_mlu = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLU/standard_mlu.png', plugin_dir_path( __FILE__ ) ) );
		$url_mlu_credit_card = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLU/credit_card.png', plugin_dir_path( __FILE__ ) ) );

		$url_mlv_standard_mlv = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLV/standard_mlv.jpg', plugin_dir_path( __FILE__ ) ) );
		$url_mlv_credit_card = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MLV/credit_card.png', plugin_dir_path( __FILE__ ) ) );

		$url_mpe_standard_mpe = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MPE/standard_mpe.png', plugin_dir_path( __FILE__ ) ) );
		$url_mpe_credit_card = str_replace( '/tests', '', plugins_url( 'module/../../assets/images/MPE/credit_card.png', plugin_dir_path( __FILE__ ) ) );

		$country_configs_mock = array(
			'mco' => array(
				'site_id'                => 'mco',
				'sponsor_id'             => 208687643,
				'checkout_banner'        => $url_mco_standard_mco,
				'checkout_banner_custom' => $url_mco_credit_card,
				'currency'               => 'COP',
				'zip_code'               => '110111',
				'currency_symbol'        => '$',
				'intl'                   => 'es-CO',
			),
			'mla' => array(
				'site_id'                => 'mla',
				'sponsor_id'             => 208682286,
				'checkout_banner'        => $url_mla_standard_mla,
				'checkout_banner_custom' => $url_mla_credit_card,
				'currency'               => 'ARS',
				'zip_code'               => '3039',
				'currency_symbol'        => '$',
				'intl'                   => 'es-AR',
			),
			'mlb' => array(
				'site_id'                => 'mlb',
				'sponsor_id'             => 208686191,
				'checkout_banner'        => $url_mlb_standard_mlb,
				'checkout_banner_custom' => $url_mlb_credit_card,
				'currency'               => 'BRL',
				'zip_code'               => '01310924',
				'currency_symbol'        => 'R$',
				'intl'                   => 'pt-BR',
			),
			'mlc' => array(
				'site_id'                => 'mlc',
				'sponsor_id'             => 208690789,
				'checkout_banner'        => $url_mlc_standard_mlc,
				'checkout_banner_custom' => $url_mlc_credit_card,
				'currency'               => 'CLP',
				'zip_code'               => '7591538',
				'currency_symbol'        => '$',
				'intl'                   => 'es-CL',
			),
			'mlm' => array(
				'site_id'                => 'mlm',
				'sponsor_id'             => 208692380,
				'checkout_banner'        => $url_mlm_standard_mlm,
				'checkout_banner_custom' => $url_mlm_credit_card,
				'currency'               => 'MXN',
				'zip_code'               => '11250',
				'currency_symbol'        => '$',
				'intl'                   => 'es-MX',
			),
			'mlu' => array(
				'site_id'                => 'mlu',
				'sponsor_id'             => 243692679,
				'checkout_banner'        => $url_mlu_standard_mlu,
				'checkout_banner_custom' => $url_mlu_credit_card,
				'currency'               => 'UYU',
				'zip_code'               => '11800',
				'currency_symbol'        => '$',
				'intl'                   => 'es-UY',
			),
			'mlv' => array(
				'site_id'                => 'mlv',
				'sponsor_id'             => 208692735,
				'checkout_banner'        => $url_mlv_standard_mlv,
				'checkout_banner_custom' => $url_mlv_credit_card,
				'currency'               => 'VEF',
				'zip_code'               => '1160',
				'currency_symbol'        => '$',
				'intl'                   => 'es-VE',
			),
			'mpe' => array(
				'site_id'                => 'mpe',
				'sponsor_id'             => 216998692,
				'checkout_banner'        => $url_mpe_standard_mpe,
				'checkout_banner_custom' => $url_mpe_credit_card,
				'currency'               => 'PEN',
				'zip_code'               => '15074',
				'currency_symbol'        => '$',
				'intl'                   => 'es-PE',
			),
		);

		$this->assertEqualSets( $country_configs_mock , $country_configs );
	}

	/**
	 * Get categories
	 *
	 * @return array
	 */
	public function test_get_categories() {
		$woomercadoPago_configs = new WC_WooMercadoPago_Configs;
		$categories = $woomercadoPago_configs->get_categories();
		$categories_mock = array(
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

		$this->assertEqualSets( $categories_mock , $categories );
	}

	/**
	 * Set payment for Brazil
	 */
	public function test_set_payment_gateway() {
		$credits_helper_mock = $this->getMockBuilder(WC_WooMercadoPago_Helper_Credits::class)
			->setMethods(['is_credits'])
			->getMock();
		$credits_helper_mock->expects($this->any())
			->method('is_credits')
			->willReturn(1);
			
		$woomercadoPago_configs = $this->getMockBuilder(WC_WooMercadoPago_Configs::class)
			->setMethods(['get_credits_helper_instance'])
			->getMock();
		$woomercadoPago_configs->expects($this->any())
			->method('get_credits_helper_instance')
			->willReturn($credits_helper_mock);

		$payment_gateway = $woomercadoPago_configs->set_payment_gateway( [] );
		$methods_returned = array (
			'WC_WooMercadoPago_Basic_Gateway',
			'WC_WooMercadoPago_Custom_Gateway',
			'WC_WooMercadoPago_Ticket_Gateway',
			'WC_WooMercadoPago_Pix_Gateway',
			'WC_WooMercadoPago_Credits_Gateway',
		);

		$this->assertEqualSets( $methods_returned , $payment_gateway );
	}

	/**
	 * Get available payment methods for Brazil
	 */
	public function test_get_available_payment_methods() {
		$credits_helper_mock = $this->getMockBuilder(WC_WooMercadoPago_Helper_Credits::class)
			->setMethods(['is_credits'])
			->getMock();
		$credits_helper_mock->expects($this->any())
			->method('is_credits')
			->willReturn(1);
			
		$woomercadoPago_configs = $this->getMockBuilder(WC_WooMercadoPago_Configs::class)
			->setMethods(['get_credits_helper_instance'])
			->getMock();
		$woomercadoPago_configs->expects($this->any())
			->method('get_credits_helper_instance')
			->willReturn($credits_helper_mock);

		$payment_gateway = $woomercadoPago_configs->get_available_payment_methods( [] );
		$methods_returned = array (
			'WC_WooMercadoPago_Basic_Gateway',
			'WC_WooMercadoPago_Custom_Gateway',
			'WC_WooMercadoPago_Ticket_Gateway',
			'WC_WooMercadoPago_Pix_Gateway',
			'WC_WooMercadoPago_Credits_Gateway',
		);

		$this->assertEqualSets( $methods_returned , $payment_gateway );
	}
}
