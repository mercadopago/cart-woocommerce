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
 * Class WC_WooMercadoPago_Helper_Links
 */
class WC_WooMercadoPago_Helper_Links {
	/**
	 * Links by country configured in woocommerce.
	 */
	public static function woomercadopago_settings_links() {
		$link_settings    = WC_WooMercadoPago_Module::define_link_country();
		$link_prefix_mp   = 'https://www.mercadopago.';
		$link_costs_mp    = 'costs-section';
		$link_developers  = 'developers/';
		$link_guides      = '/docs/woocommerce/integration-configuration';
		$link_credentials = 'settings/account/credentials';

		return array (
			'link_costs' => $link_prefix_mp . $link_settings ['sufix_url'] . $link_costs_mp,
			'link_guides_plugin' => $link_prefix_mp . $link_settings ['sufix_url'] . $link_developers . $link_settings ['translate'] . $link_guides,
			'link_credentials' => $link_prefix_mp . $link_settings ['sufix_url'] . $link_credentials
		);
	}

	public static function get_mp_devsite_links() {
		$link      = WC_WooMercadoPago_Module::define_link_country();
		$base_link = 'https://www.mercadopago.com/developers/' . $link['translate'];

		return array(
			'dev_program'       => $base_link . '/developer-program',
			'notifications_ipn' => $base_link . '/guides/notifications/ipn',
			'shopping_testing'  => $base_link . '/docs/woocommerce/integration-test',
			'test_cards'        => $base_link . '/docs/checkout-api/integration-test/test-cards'
		);
	}

	/**
	 * Get Mercado Pago Devsite Page Link
	 *
	 * @param String $country Country Acronym
	 *
	 * @return String
	 */
	public static function get_mp_devsite_link( $country ) {
		$country_links = [
			'mla' => 'https://www.mercadopago.com.ar/developers/es/guides/plugins/woocommerce/testing',
			'mlb' => 'https://www.mercadopago.com.br/developers/pt/guides/plugins/woocommerce/testing',
			'mlc' => 'https://www.mercadopago.cl/developers/es/guides/plugins/woocommerce/testing',
			'mco' => 'https://www.mercadopago.com.co/developers/es/guides/plugins/woocommerce/testing',
			'mlm' => 'https://www.mercadopago.com.mx/developers/es/guides/plugins/woocommerce/testing',
			'mpe' => 'https://www.mercadopago.com.pe/developers/es/guides/plugins/woocommerce/testing',
			'mlu' => 'https://www.mercadopago.com.uy/developers/es/guides/plugins/woocommerce/testing',
		];

		return array_key_exists($country, $country_links) ? $country_links[$country] : $country_links['mla'];
	}

	/**
	 * Get Country Link to Mercado Pago
	 *
	 * @param string $checkout Checkout by country.
	 * @return string
	 */
	public static function get_country_link_mp_terms() {
		$country_link = [
			'mla' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.ar/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',  // Argentinian.
			],
			'mlb' => [
				'help'      => 'ajuda',
				'sufix_url' => 'com.br/',
				'translate' => 'pt',
				'term_conditition' => '/termos-e-politicas_194',   //Brasil
			],
			'mlc' => [
				'help'      => 'ayuda',
				'sufix_url' => 'cl/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Chile.
			],
			'mco' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.co/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Colombia.
			],
			'mlm' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.mx/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Mexico.
			],
			'mpe' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.pe/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Peru.
			],
			'mlu' => [
				'help'      => 'ayuda',
				'sufix_url' => 'com.uy/',
				'translate' => 'es',
				'term_conditition' => '/terminos-y-politicas_194',   // Uruguay.
			],
		];

		$option_country   = WC_WooMercadoPago_Options::get_instance();
		$checkout_country = strtolower($option_country->get_checkout_country());

		return $country_link[ $checkout_country ];
	}

	/**
	 *
	 * Define terms and conditions link
	 *
	 * @return array
	 */
	public static function mp_define_terms_and_conditions() {

		$links_mp       = self::get_country_link_mp_terms();
		$link_prefix_mp = 'https://www.mercadopago.';
		return array (
			'text_prefix'                           => __( 'By continuing, you agree to our ', 'woocommerce-mercadopago' ),
			'link_terms_and_conditions' => $link_prefix_mp . $links_mp['sufix_url'] . $links_mp['help'] . $links_mp['term_conditition'],
			'text_suffix'                               => __( 'Terms and Conditions', 'woocommerce-mercadopago' ),
		);
	}

	/**
	 * Get Mercado Pago Devsite Page Link
	 *
	 * @param String $country Country Acronym
	 *
	 * @return String
	 */
	public static function get_mc_blog_link( $country ) {
		$country_links = [
			'mla' => array(
				'blog_link' => 'https://vendedores.mercadolibre.com.ar/nota/impulsa-tus-ventas-y-alcanza-mas-publico-con-mercado-credito',
				'FAQ_link' => 'https://www.mercadopago.com.ar/help/19040'
			),
			'mlm' => array(
				'blog_link' => 'https://vendedores.mercadolibre.com.mx/nota/impulsa-tus-ventas-y-alcanza-a-mas-clientes-con-mercado-credito',
				'FAQ_link' => 'https://www.mercadopago.com.mx/help/19040'
			),
			'mlb' => array(
				'blog_link' => 'https://conteudo.mercadopago.com.br/parcelamento-via-boleto-bancario-no-mercado-pago-seus-clientes-ja-podem-solicitar',
				'FAQ_link' => 'https://www.mercadopago.com.br/help/19040'
			),
		];

		$link = array_key_exists($country, $country_links) ? $country_links[$country] : $country_links['mla'];

		return $link;
	}
}
