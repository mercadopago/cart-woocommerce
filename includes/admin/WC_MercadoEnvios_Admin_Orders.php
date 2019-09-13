<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MercadoEnvios orders.
 */
class WC_MercadoEnvios_Admin_Orders {

	/**
	 * Initialize the order actions.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
	}

	/**
	 * Register tracking code metabox.
	 */
	public function register_metabox() {
		global $post;
		$order = wc_get_order( $post->ID );
		$shipment_id = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_mercadoenvios_shipment_id' ) :
			get_post_meta( $post->ID, '_mercadoenvios_shipment_id', true );
		if ( isset( $shipment_id ) && ! empty( $shipment_id ) ) {
			add_meta_box(
				'wc_mercadoenvios',
				'Mercado Envios',
				array( $this, 'metabox_content' ),
				'shop_order',
				'side',
				'high'
			);
		}
	}

    /**
     * @param $post
     * @throws WC_WooMercadoPago_Exception
     */
	public function metabox_content( $post ) {
		$order = wc_get_order( $post->ID );
		$shipment_id = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_mercadoenvios_shipment_id' ) :
			get_post_meta( $post->ID, '_mercadoenvios_shipment_id', true );
		$status = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_mercadoenvios_status' ) :
			get_post_meta( $post->ID, '_mercadoenvios_status', true );
		$tracking_number = ( method_exists( $order, 'get_meta' ) ) ?
			$order->get_meta( '_mercadoenvios_tracking_number' ) :
			get_post_meta( $post->ID, '_mercadoenvios_tracking_number', true );

		if ( isset( $status ) && $status != '' && $status != 'pending' ) {
			echo '<label for="mercadoenvios_tracking_code">' . esc_html__( 'Tracking code:', 'woocommerce-mercadopago' ) . '</label><br />';
			echo '<input type="text" id="mercadoenvios_tracking_code" name="mercadoenvios_tracking_code" value="' .
				esc_attr( $tracking_number ) . '" style="width:100%; text-align:center;" />';
			// Check exist shipment_id
			if ( isset( $shipment_id ) && $shipment_id != '' ) {
				$mp = WC_WooMercadoPago_Module::getMpInstanceSingleton();
				$email = ( wp_get_current_user()->ID != 0 ) ? wp_get_current_user()->user_email : null;
				$mp->set_email( $email );
				echo '<br /><label for="mercadoenvios_tracking_number">' . esc_html__( 'Tag:', 'woocommerce-mercadopago' ) . '</label><br />';
				echo '<a href="https://api.mercadolibre.com/shipment_labels?shipment_ids=' . esc_attr( $shipment_id ) .
					'&savePdf=Y&access_token=' . $mp->get_access_token() .
					'" style="width:100%; text-align:center;" class="button" target="_blank">' . esc_html__( 'Print', 'woocommerce-mercadopago' ) . '</a>';
			}
		} else {
			echo '<label for="mercadoenvios_tracking_number">' . esc_html__( 'Shipping is pending', 'woocommerce-mercadopago' ) . '</label><br />';
		}
	}
}

new WC_MercadoEnvios_Admin_Orders();
