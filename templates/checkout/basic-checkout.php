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
						title="<?php echo esc_html_e('Checkout Pro in Test Mode', 'woocommerce-mercadopago'); ?>"
						description="<?php echo esc_html_e('Use Mercado Pago\'s payment methods without real charges. ', 'woocommerce-mercadopago'); ?>"
						link-text="<?php echo esc_html_e('See the rules for the test mode.', 'woocommerce-mercadopago'); ?>"
						link-src="<?php echo esc_html($test_mode_link); ?>"
					>
					</test-mode>
				</div>
			<?php endif; ?>

			<div class="mp-checkout-pro-checkout-benefits">
				<checkout-benefits
					title="<?php echo esc_html_e('Log in to Mercado Pago and earn benefits', 'woocommerce-mercadopago'); ?>"
					title-align="center"
					items="<?php echo esc_html($checkout_benefits_items); ?>"
					list-mode="image"
				>
				</checkout-benefits>
			</div>

			<div class="mp-checkout-pro-payment-methods">
				<payment-methods-v2
					title="<?php echo esc_html($payment_methods_title); ?>"
					methods="<?php echo esc_html($payment_methods); ?>"
				>
				</payment-methods-v2>
			</div>

			<?php if ( 'redirect' === $method ) : ?>
				<div class="mp-checkout-pro-redirect">
					<checkout-redirect-v2
						text="<?php echo esc_html_e('By continuing, you will be taken to Mercado Pago to safely complete your purchase.', 'woocommerce-mercadopago'); ?>"
						src="<?php echo esc_html($checkout_redirect_src); ?>"
						alt="<?php echo esc_html_e('Checkout Pro redirect info image', 'woocommerce-mercadopago'); ?>"
					>
					</checkout-redirect-v2>
				</div>
			<?php endif; ?>
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
