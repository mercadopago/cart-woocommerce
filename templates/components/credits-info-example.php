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
<div class="credits-info-example-container">
	<div class="credits-info-example-buttons-container">
		<div class="credits-info-example-buttons-child credits-info-button-selected">
			<div id="btn-first" class="credits-info-example-blue-badge"></div>
			<div class="credits-info-example-buttons-content">
				<div>
					<img class="icon-image" alt="computer" src="<?php echo esc_html(plugins_url('../assets/images/credits/desktop-gray-icon.png', plugin_dir_path(__FILE__))); ?>">
				</div>
				<div>
					<p><?php echo esc_html( $settings['value']['desktop'] ); ?>
				</div>
			</div>

		</div>
		<div class="credits-info-example-buttons-child">
			<div id="btn-second" class="credits-info-example-blue-badge"></div>
			<div class="credits-info-example-buttons-content">
				<div>
					<img class="icon-image" alt="cellphone" src="<?php echo esc_html(plugins_url('../assets/images/credits/cellphone-gray-icon.png', plugin_dir_path(__FILE__))); ?>">
				</div>

				<div>
					<p><?php echo esc_html( $settings['value']['cellphone'] ); ?></p>
				</div>
			</div>
		</div>
	</div>
	<div class="credits-info-example-gif-container">
		<div class="credits-info-example-gif">
			<img id="gif-image" alt='example' src="<?php echo esc_html(plugins_url('../assets/images/credits/view_desktop.gif', plugin_dir_path(__FILE__))); ?>">
		</div>
		<p id="credits-info-example-gif-footer">
		<?php echo esc_html( $settings['value']['footer'] ); ?>
		</p>
	</div>
</div>
