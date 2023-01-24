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

<div class='mp-checkout-container'>
	<div class="mp-checkout-pro-container">
		<div class="mp-checkout-pro-content">
			<?php if ( true === $test_mode ) : ?>
				<div class="mp-checkout-pro-test-mode">
					<test-mode
						title="<?php echo esc_html_e('No card installments in Test Mode', 'woocommerce-mercadopago'); ?>"
						description="<?php echo esc_html_e('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'); ?>"
						link-text="<?php echo esc_html_e('See the rules for the test mode.', 'woocommerce-mercadopago'); ?>"
						link-src="<?php echo esc_html($test_mode_link); ?>"
					>
					</test-mode>
				</div>
			<?php endif; ?>

			<checkout-benefits
				title="<?php echo esc_html_e('How to use it?', 'woocommerce-mercadopago'); ?>"
				title-align="left"
				items='[
					"<?php echo esc_html_e('<b>Log in</b> or create an account in Mercado Pago. If you use Mercado Libre, you already have one!', 'woocommerce-mercadopago'); ?>",
					"<?php echo esc_html_e('Know your available limit in Mercado Crédito and <b>choose how many installments</b> you want to pay.', 'woocommerce-mercadopago'); ?>",
					"<?php echo esc_html_e('Pay the installments as you prefer: <b>with money in your account, card of from the Mercado Pago app.</b>', 'woocommerce-mercadopago'); ?>"
				]'
				list-mode='count'
			>
			</checkout-benefits>
			<div class="mp-checkout-pro-redirect">
				<checkout-redirect-v2
					text="<?php echo esc_html_e('By confirming your purchase, you will be redirected to your Mercado Pago account.', 'woocommerce-mercadopago'); ?>"
					alt="<?php echo esc_html_e('Checkout Pro redirect info image', 'woocommerce-mercadopago'); ?>"
					src="<?php echo esc_html($redirect_image); ?>"
				>
				</checkout-redirect-v2>
			</div>
		</div>
	</div>
	<div class="mp-checkout-pro-terms-and-conditions">
		<terms-and-conditions
			description="<?php echo esc_html_e('By continuing, you agree with our', 'woocommerce-mercadopago'); ?>"
			link-text="<?php echo esc_html_e('Terms and conditions', 'woocommerce-mercadopago'); ?>"
			link-src="<?php echo esc_html($link_terms_and_conditions); ?>"
		>
		</terms-and-conditions>
	</div>
</div>

<script type="text/javascript">
	if ( document.getElementById("payment_method_woo-mercado-pago-custom") ) {
		jQuery("form.checkout").on(
			"checkout_place_order_woo-mercado-pago-basic",
			function() {
				cardFormLoad();
			}
		);
	}
</script>
