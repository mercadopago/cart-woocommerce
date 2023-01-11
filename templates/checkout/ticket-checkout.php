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
	<div class="mp-checkout-ticket-container">
		<div class="mp-checkout-ticket-content">
			<?php if ( true === $test_mode ) : ?>
				<div class="mp-checkout-ticket-test-mode">
					<test-mode
						title="<?php echo esc_html_e('Offline Methods in Test Mode', 'woocommerce-mercadopago'); ?>"
						description="<?php echo esc_html_e('You can test the flow to generate an invoice, but you cannot finalize the payment. ', 'woocommerce-mercadopago'); ?>"
						link-text="<?php echo esc_html_e('See the rules for the test mode.', 'woocommerce-mercadopago'); ?>"
						link-src="<?php echo esc_html($test_mode_link); ?>"
					>
					</test-mode>
				</div>
			<?php endif; ?>

			<?php if ( 'mlu' === $site_id ) : ?>
				<div class="mp-checkout-ticket-input-document">
					<input-document
						label-message="<?php echo esc_html_e('Holder document', 'woocommerce-mercadopago'); ?>"
						helper-message="<?php echo esc_html_e('Invalid document', 'woocommerce-mercadopago'); ?>"
						input-name='mercadopago_ticket[docNumber]'
						hidden-id="docNumberValue"
						select-id='mercadopago_ticket[docType]'
						select-name='mercadopago_ticket[docType]'
						flag-error='mercadopago_ticket[docNumberError]'
						documents='["CI","OTRO"]'
						validate=true
					>
					</input-document>
				</div>
			<?php endif; ?>

			<?php if ( 'mlb' === $site_id ) : ?>
				<div class="mp-checkout-ticket-input-document">
					<input-document
						label-message="<?php echo esc_html_e('Holder document', 'woocommerce-mercadopago'); ?>"
						helper-message="<?php echo esc_html_e('Invalid document', 'woocommerce-mercadopago'); ?>"
						input-name='mercadopago_ticket[docNumber]'
						hidden-id="docNumberValue"
						select-id='mercadopago_ticket[docType]'
						select-name='mercadopago_ticket[docType]'
						flag-error='mercadopago_ticket[docNumberError]'
						documents='["CPF","CNPJ"]'
						validate=true
					>
					</input-document>
				</div>
			<?php endif; ?>

			<p class="mp-checkout-ticket-text" data-cy="checkout-ticket-text">
				<?php echo esc_html_e('Select where you want to pay', 'woocommerce-mercadopago'); ?>
			</p>

			<input-table
				name="mercadopago_ticket[paymentMethodId]"
				button-name=<?php echo esc_html_e('more options', 'woocommerce-mercadopago'); ?>
				columns='<?php echo esc_attr(wp_json_encode($payment_methods)); ?>'
			>
			</input-table>

			<input-helper isVisible=false message="<?php echo esc_html_e('Select a payment method', 'woocommerce-mercadopago'); ?>" input-id="mp-payment-method-helper" id="payment-method-helper"></input-helper>

			<!-- NOT DELETE LOADING-->
			<div id="mp-box-loading"></div>

			<!-- utilities -->
			<div id="mercadopago-utilities">
				<input type="hidden" id="site_id" value="<?php echo esc_textarea($site_id); ?>" name="mercadopago_ticket[site_id]" />
				<input type="hidden" id="amountTicket" value="<?php echo esc_textarea($amount); ?>" name="mercadopago_ticket[amount]" />
				<input type="hidden" id="currency_ratioTicket" value="<?php echo esc_textarea($currency_ratio); ?>" name="mercadopago_ticket[currency_ratio]" />
				<input type="hidden" id="campaign_idTicket" name="mercadopago_ticket[campaign_id]" />
				<input type="hidden" id="campaignTicket" name="mercadopago_ticket[campaign]" />
				<input type="hidden" id="discountTicket" name="mercadopago_ticket[discount]" />
			</div>
		</div>

		<div class="mp-checkout-ticket-terms-and-conditions">
			<terms-and-conditions
				description="<?php echo esc_html_e('By continuing, you agree with our', 'woocommerce-mercadopago'); ?>"
				link-text="<?php echo esc_html_e('Terms and conditions', 'woocommerce-mercadopago'); ?>"
				link-src="<?php echo esc_html($link_terms_and_conditions); ?>"
			>
			</terms-and-conditions>
		</div>
	</div>
</div>

<script type="text/javascript">
	if (document.getElementById("payment_method_woo-mercado-pago-custom")) {
		jQuery("form.checkout").on(
			"checkout_place_order_woo-mercado-pago-ticket",
			function() {
				cardFormLoad();
			}
		);
	}
</script>
