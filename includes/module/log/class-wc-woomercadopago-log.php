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
 * Class WC_WooMercadoPago_Log
 */
class WC_WooMercadoPago_Log {

	/**
	 * Log
	 *
	 * @var WC_WooMercadoPago_Log
	 */
	public $log;

	/**
	 * Id
	 *
	 * @var WC_WooMercadoPago_Log::$id
	 */
	public $id;

	/**
	 * DebugLog
	 *
	 * @var WC_WooMercadoPago_Log::$debug_mode
	 */
	public $debug_mode;

	/**
	 * WC_WooMercadoPago_Log constructor.
	 *
	 * @param null $payment .
	 * @param bool $debug_mode .
	 */
	public function __construct( $payment = null, $debug_mode = false ) {
		$this->get_debug_mode( $payment, $debug_mode );
		if ( ! empty( $payment ) ) {
			$this->id = get_class( $payment );
		}
		return $this->init_log();
	}

	/**
	 * Get_debug_mode function
	 *
	 * @param [type] $payment .
	 * @param [type] $debug_mode .
	 * @return void
	 */
	public function get_debug_mode( $payment, $debug_mode ) {
		if ( ! empty( $payment ) ) {
			$debug_mode = $payment->debug_mode;
			if ( 'no' === $debug_mode ) {
				$debug_mode = false;
			} else {
				$debug_mode = true;
			}
		}

		if ( empty( $payment ) && empty( $debug_mode ) ) {
			$debug_mode = true;
		}

		$this->debug_mode = $debug_mode;
	}

	/**
	 * Init_log function
	 *
	 * @return WC_Logger|null
	 */
	public function init_log() {
		if ( ! empty( $this->debug_mode ) ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = WC_WooMercadoPago_Module::woocommerce_instance()->logger();
			}
			return $this->log;
		}
		return null;
	}

	/**
	 * Init_mercado_pago_log function
	 *
	 * @param null $id .
	 * @return WC_WooMercadoPago_Log|null
	 */
	public static function init_mercado_pago_log( $id = null ) {
		$log = new self( null, true );
		if ( ! empty( $log ) && ! empty( $id ) ) {
			$log->set_id( $id );
		}
		return $log;
	}

	/**
	 * Write_log function
	 *
	 * @param [type] $function .
	 * @param [type] $message .
	 * @return void
	 */
	public function write_log( $function, $message ) {
		if ( ! empty( $this->debug_mode ) ) {
			$this->log->add( $this->id, '[' . $function . ']: ' . $message );
		}
	}

	/**
	 * Set_id function
	 *
	 * @param [type] $id .
	 * @return void
	 */
	public function set_id( $id ) {
		$this->id = $id;
	}
}
