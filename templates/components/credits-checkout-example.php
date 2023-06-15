<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * @var array $settings
 *
 * @package MercadoPago
 */

if ( ! defined('ABSPATH') ) {
	exit;
}

?>

<div class="credits-info-example-text">
	<label><?php echo esc_html( $settings['value']['title'] ); ?></label>
	<p><?php echo esc_html( $settings['value']['subtitle'] ); ?></p>
</div>
<div class="credits-info-preview-container">
	<div class="credits-info-example-image-container">
		<p class="credits-info-example-preview-pill"><?php echo esc_html( $settings['value']['pill_text'] ); ?></p>
		<div class="credits-info-example-image">
			<img alt='example' src="<?php echo esc_html( $settings['value']['image'] ); ?>">
		</div>
		<p class="credits-info-example-preview-footer"><?php echo esc_html( $settings['value']['footer'] ); ?></p>
	</div>
</div>
