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
?>

<p>
<p>
	<?php echo esc_html__( 'Great, we processed your purchase order. Complete the payment with ticket so that we finish approving it.', 'woocommerce-mercadopago' ); ?>
</p>
<p><iframe src="<?php echo esc_attr( $transaction_details ); ?>" style="width:100%; height:1000px;"></iframe></p>
<a id="submit-payment" target="_blank" href="<?php echo esc_attr( $transaction_details ); ?>" class="button alt" style="font-size:1.25rem; width:75%; height:48px; line-height:24px; text-align:center;">
	<?php echo esc_html__( 'Print ticket', 'woocommerce-mercadopago' ); ?>
</a>
</p>
