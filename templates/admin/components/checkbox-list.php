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

?><tr valign="top">
	<th scope="row" class="titledesc">
		<label><?php echo esc_html( $settings['title'] ); ?>
		<?php if ( isset($settings['desc_tip']) ) { ?>
			<span class="woocommerce-help-tip" data-tip="<?php echo esc_html( $settings['desc_tip'] ); ?>"></span>
		<?php } ?>
		</label>
	</th>
	<td class="forminp">
		<div class="mp-mw-100 mp-component-card">
			<p class="mp-checkbox-list-description"><?php echo esc_html($settings['description']); ?></p>
			<?php foreach ( $settings['payment_method_types'] as $key => $payment_method_type ) { ?>
			<ul class="mp-list-group">
				<li class="mp-list-group-item">
					<div class="mp-custom-checkbox">
						<input class="mp-custom-checkbox-input mp-selectall" id="<?php echo esc_attr($key); ?>_payments" type="checkbox" data-group="<?php echo esc_attr($key); ?>">
						<label class="mp-custom-checkbox-label" for="<?php echo esc_attr($key); ?>_payments"><b><?php echo esc_html($payment_method_type['label']); ?></b></label>
					</div>
				</li>
				<?php foreach ( $payment_method_type['list'] as $payment_method ) { ?>
				<li class="mp-list-group-item">
					<div class="mp-custom-checkbox">
						<fieldset>
							<input class="mp-custom-checkbox-input mp-child" id="<?php echo esc_attr($payment_method['field_key']); ?>" name="<?php echo esc_attr($payment_method['field_key']); ?>" type="checkbox" value="1" data-group="<?php echo esc_attr($key); ?>" <?php echo checked($payment_method['value'], 'yes'); ?>>
							<label class="mp-custom-checkbox-label" for="<?php echo esc_attr($payment_method['field_key']); ?>"><?php echo esc_html($payment_method['label']); ?></label>
						</fieldset>
					</div>
				</li>
				<?php } ?>
			</ul>
			<?php } ?>
		</div>
	</td>
</tr>
