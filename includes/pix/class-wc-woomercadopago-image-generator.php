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
if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Class WC_WooMercadoPago_Image_Generator
 */
class WC_WooMercadoPago_Image_Generator {

	/**
	 * Static Instance
	 */
	public static $instance = null;

	/**
	 * WC_WooMercadoPago_Image_Generator constructor.
	 */
	public function __construct() {

		add_action('woocommerce_api_wc_mp_pix_image', array($this, 'get_image_qr'));
	}

	/**
	 * Get Pix Payment Data
	 *
	 * @return array
	 */
	public static function get_payment_data() {
		$data         = self::get_access_data();
		$payment_id   = $data['payment_id'];
		$access_token = $data['access_token'];
		$request      = array(
			'uri'    => '/v1/payments/' . $payment_id[0],
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
			)
		);

		return MP_Rest_Client::get($request);
	}

	/**
	 * Get qr code image
	 */
	public function get_image_qr() {
		$payment_data = self::get_payment_data();

		$pix = $payment_data['response']['point_of_interaction']['transaction_data'];

		if ( is_null($pix) || empty($pix) || ! array_key_exists('qr_code_base64', $pix ) ) {
			self::get_error_image();
		}

		$pix_base64 = $payment_data['response']['point_of_interaction']['transaction_data']['qr_code_base64'];

		header('Content-type: image/png');

		// @codingStandardsIgnoreLine
		$pix_qr_image = base64_decode($pix_base64);
		$pix_qr_image = imagecreatefromstring($pix_qr_image);

		$pix_qr_image = imagescale($pix_qr_image, 447);

		imagepng($pix_qr_image);

		imagedestroy($pix_qr_image);

		exit();
	}


	/**
	 * Get Access Data
	 *
	 * @return array
	 */
	public static function get_access_data() {

		$id_payment = sanitize_key( isset($_GET['id']) ? $_GET['id'] : null); // phpcs:disable WordPress.Security.NonceVerification

		if ( is_null($id_payment) || empty($id_payment) || ! is_numeric($id_payment) ) {
			self::get_error_image();
			exit();
		}

		$order = wc_get_order($id_payment);

		if ( is_null($order) || empty($order) ) {
			self::get_error_image();
			exit();
		}

		$payment_method                = $order->get_payment_method();
		$is_mercadopago_payment_method = in_array($payment_method, WC_WooMercadoPago_Constants::GATEWAYS_IDS, true);
		$payment_ids                   = explode(',', $order->get_meta('_Mercado_Pago_Payment_IDs'));

		if ( ! $is_mercadopago_payment_method || empty($payment_ids) ) {
			return;
		}

		$is_production_mode = $order->get_meta('is_production_mode');
		$access_token       = 'no' === $is_production_mode || ! $is_production_mode
			? get_option('_mp_access_token_test')
			: get_option('_mp_access_token_prod');

		$data = array(
			'payment_id'     => $payment_ids,
			'access_token' => $access_token,
		);

		return $data;
	}

	/**
	 * Init Mercado Pago Image Generator Class
	 *
	 * @return WC_WooMercadoPago_Image_Generator|null
	 * Singleton
	 */
	public static function init_image_generator_class() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get Error Image
	 */
	public static function get_error_image() {
		header('Content-type: image/png');
		$png_image = dirname(__FILE__) . '/../../assets/images/pix_has_expired.png';
		$png_image = imagecreatefrompng($png_image);
		$png_image = imagescale($png_image, 447);
		imagepng($png_image);
		imagedestroy($png_image);
		exit();
	}
}
