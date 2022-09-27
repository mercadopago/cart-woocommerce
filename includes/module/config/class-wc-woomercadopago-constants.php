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
 * Class WC_WooMercadoPago_Constants
 */
class WC_WooMercadoPago_Constants {

	const PRODUCT_ID_DESKTOP = 'BT7OF5FEOO6G01NJK3QG';
	const PRODUCT_ID_MOBILE  = 'BT7OFH09QS3001K5A0H0';
	const PLATAFORM_ID       = 'bo2hnr2ic4p001kbgpt0';
	const VERSION            = '6.3.0';
	const MIN_PHP            = 5.6;
	const API_MP_BASE_URL    = 'https://api.mercadopago.com';
	const DATE_EXPIRATION    = 3;
	const PAYMENT_GATEWAYS   = array(
		'WC_WooMercadoPago_Basic_Gateway',
		'WC_WooMercadoPago_Custom_Gateway',
		'WC_WooMercadoPago_Ticket_Gateway',
		'WC_WooMercadoPago_Pix_Gateway',
	);
	const GATEWAYS_IDS       = array(
		'woo-mercado-pago-ticket',
		'woo-mercado-pago-custom',
		'woo-mercado-pago-basic',
		'woo-mercado-pago-pix',
	);
}
