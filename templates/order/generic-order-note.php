<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago Developers
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * @package MercadoPago
 *
 * @var string $tip_text
 * @var string $title
 * @var string $value
 *
 * @see WC_WooMercadoPago_Custom_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<tr>
	<td class="label">
		<?php echo wc_help_tip( $tip_text ); ?>
		<?php echo esc_html( $title ); ?>
	</td>
	<td width="1%"></td>
	<td class="total">
		<?php echo wp_kses_post( $value ); ?>
	</td>
</tr>
