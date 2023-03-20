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
	<div class='mp-checkout-custom-container'>
		<?php if ( true === $test_mode ) : ?>
			<div class="mp-checkout-pro-test-mode">
				<test-mode
					title="<?php echo esc_html_e('Checkout Custom in Test Mode', 'woocommerce-mercadopago'); ?>"
					description="<?php echo esc_html_e('Use Mercado Pago means without real charges.', 'woocommerce-mercadopago'); ?>"
					link-text="<?php echo esc_html_e('See test mode rules.', 'woocommerce-mercadopago'); ?>"
					link-src="<?php echo esc_html($test_mode_link); ?>"
				>
				</test-mode>
			</div>
		<?php endif; ?>

		<?php if ( 'yes' === $wallet_button ) : ?>
			<div class='mp-wallet-button-container'>
				<img src="<?php echo esc_url(plugins_url('../assets/images/ml_mp_logo.png', plugin_dir_path(__FILE__))); ?>">
				<div class='mp-wallet-button-title'>
					<span><?php echo esc_html_e('Pay with saved cards', 'woocommerce-mercadopago'); ?></span>
				</div>

				<div class='mp-wallet-button-description'>
					<?php echo esc_html_e('Do you have a Mercado Libre account? Then use the same email and password to pay faster with Mercado Pago.', 'woocommerce-mercadopago'); ?>
				</div>

				<div class='mp-wallet-button-button'>
					<button id="mp-wallet-button" onclick="submitWalletButton(event)">
						<?php echo esc_html_e('Pay with Mercado Pago', 'woocommerce-mercadopago'); ?>
					</button>
				</div>
			</div>
		<?php endif; ?>

		<div id="mp-custom-checkout-form-container">
			<div class='mp-checkout-custom-available-payments'>
				<div class='mp-checkout-custom-available-payments-header'>
					<div class="mp-checkout-custom-available-payments-title">
						<img src="<?php echo esc_url(plugins_url('../assets/images/purple_card.png', plugin_dir_path(__FILE__))); ?>" class='mp-icon'>
						<p class="mp-checkout-custom-available-payments-text">
							<?php echo esc_html_e('With which card can you pay?', 'woocommerce-mercadopago'); ?>
						</p>
					</div>

					<img src="<?php echo esc_url(plugins_url('../assets/images/chefron-down.png', plugin_dir_path(__FILE__))); ?>" class='mp-checkout-custom-available-payments-collapsible' />
				</div>

				<div class='mp-checkout-custom-available-payments-content'>
					<payment-methods methods='<?php echo wp_json_encode($payment_methods); ?>'></payment-methods>

					<?php if ( 'mla' === $site_id ) : ?>
						<span id="mp_promotion_link"> | </span>
						<a href="https://www.mercadopago.com.ar/cuotas" id="mp_checkout_link" class="mp-checkout-link mp-pl-10" target="_blank">
							<?php echo esc_html__('See current promotions', 'woocommerce-mercadopago'); ?>
						</a>
					<?php endif; ?>
					<hr>
				</div>
			</div>

			<div class='mp-checkout-custom-card-form'>
				<p class='mp-checkout-custom-card-form-title'><?php echo esc_html_e('Fill in your card details', 'woocommerce-mercadopago'); ?></p>
				<div class='mp-checkout-custom-card-row'>
					<input-label isOptinal=false message="<?php echo esc_html_e('Card number', 'woocommerce-mercadopago'); ?>" for='mp-card-number'></input-label>
					<div class="mp-checkout-custom-card-input" id="form-checkout__cardNumber-container"></div>
					<input-helper isVisible=false message="<?php echo esc_html_e('Required data', 'woocommerce-mercadopago'); ?>" input-id="mp-card-number-helper">
					</input-helper>
				</div>

				<div class='mp-checkout-custom-card-row' id="mp-card-holder-div">
					<input-label message="<?php echo esc_html_e('Holder name as it appears on the card', 'woocommerce-mercadopago'); ?>" isOptinal=false></input-label>
					<input class="mp-checkout-custom-card-input mp-card-holder-name" placeholder="Ex.: María López" id="form-checkout__cardholderName" name="mp-card-holder-name" data-checkout="cardholderName" />
					<input-helper isVisible=false message="<?php echo esc_html_e('Required data', 'woocommerce-mercadopago'); ?>" input-id="mp-card-holder-name-helper" data-main="mp-card-holder-name">
					</input-helper>
				</div>

				<div class='mp-checkout-custom-card-row mp-checkout-custom-dual-column-row'>
					<div class='mp-checkout-custom-card-column'>
						<input-label message="<?php echo esc_html_e('Expiration', 'woocommerce-mercadopago'); ?>" isOptinal=false></input-label>
						<div id="form-checkout__expirationDate-container" class="mp-checkout-custom-card-input mp-checkout-custom-left-card-input">
						</div>
						<input-helper isVisible=false message="<?php echo esc_html_e('Required data', 'woocommerce-mercadopago'); ?>" input-id="mp-expiration-date-helper">
						</input-helper>
					</div>

					<div class='mp-checkout-custom-card-column'>
						<input-label message="<?php echo esc_html_e('Security Code', 'woocommerce-mercadopago'); ?>" isOptinal=false></input-label>
						<div id="form-checkout__securityCode-container" class="mp-checkout-custom-card-input"></div>
						<p id="mp-security-code-info" class="mp-checkout-custom-info-text"></p>
						<input-helper isVisible=false message="<?php echo esc_html_e('Required data', 'woocommerce-mercadopago'); ?>" input-id="mp-security-code-helper">
						</input-helper>
					</div>
				</div>

				<div id="mp-doc-div" class="mp-checkout-custom-input-document" style="display: none;">
					<input-document
						label-message="<?php echo esc_html_e('Holder document', 'woocommerce-mercadopago'); ?>"
						helper-message="<?php echo esc_html_e('Invalid document', 'woocommerce-mercadopago'); ?>"
						input-name="identificationNumber"
						hidden-id="form-checkout__identificationNumber"
						input-data-checkout="docNumber"
						select-id="form-checkout__identificationType"
						select-name="identificationType"
						select-data-checkout="docType"
						flag-error="docNumberError"
					>
					</input-document>
				</div>
			</div>

			<div id="mp-checkout-custom-installments" class="mp-checkout-custom-installments-display-none">
				<p class='mp-checkout-custom-card-form-title'>
					<?php echo esc_html_e('Select the number of installments', 'woocommerce-mercadopago'); ?>
				</p>

				<div id="mp-checkout-custom-issuers-container" class="mp-checkout-custom-issuers-container">
					<div class='mp-checkout-custom-card-row'>
						<input-label isOptinal=false message="<?php echo esc_html_e('Issuer', 'woocommerce-mercadopago'); ?>" for='mp-issuer'></input-label>
					</div>
					<div class="mp-input-select-input">
						<select name="issuer" id="form-checkout__issuer" class="mp-input-select-select"></select>
					</div>
				</div>

				<div id="mp-checkout-custom-installments-container" class="mp-checkout-custom-installments-container"></div>

				<input-helper
					isVisible=false
					message="<?php echo esc_html_e('Select the number of installments', 'woocommerce-mercadopago'); ?>"
					input-id="mp-installments-helper"
				>
				</input-helper>

				<select
					style="display: none;"
					data-checkout="installments"
					name="installments"
					id="form-checkout__installments"
					class="mp-input-select-select"
				>
				</select>

				<div id="mp-checkout-custom-box-input-tax-cft">
					<div id="mp-checkout-custom-box-input-tax-tea">
						<div id="mp-checkout-custom-tax-tea-text"></div>
					</div>
					<div id="mp-checkout-custom-tax-cft-text"></div>
				</div>
			</div>

			<div class="mp-checkout-custom-terms-and-conditions">
				<terms-and-conditions
					description="<?php echo esc_html_e('By continuing, you agree with our', 'woocommerce-mercadopago'); ?>"
					link-text="<?php echo esc_html_e('Terms and conditions', 'woocommerce-mercadopago'); ?>"
					link-src="<?php echo esc_html($link_terms_and_conditions); ?>"
				>
				</terms-and-conditions>
			</div>
		</div>
	</div>
</div>

<div id="mercadopago-utilities">
	<input type="hidden" id="mp-amount" value='<?php echo esc_textarea($amount); ?>' name="mercadopago_custom[amount]" />
	<input type="hidden" id="currency_ratio" value='<?php echo esc_textarea($currency_ratio); ?>' name="mercadopago_custom[currency_ratio]" />
	<input type="hidden" id="paymentMethodId" name="mercadopago_custom[paymentMethodId]" />
	<input type="hidden" id="mp_checkout_type" name="mercadopago_custom[checkout_type]" value="custom" />
	<input type="hidden" id="cardExpirationMonth" data-checkout="cardExpirationMonth" />
	<input type="hidden" id="cardExpirationYear" data-checkout="cardExpirationYear" />
	<input type="hidden" id="cardTokenId" name="mercadopago_custom[token]" />
	<input type="hidden" id="cardInstallments" name="mercadopago_custom[installments]" />
	<input type="hidden" id="mpCardSessionId" name="mercadopago_custom[session_id]" />
</div>

<script type="text/javascript">
	function submitWalletButton(event) {
		event.preventDefault();
		jQuery('#mp_checkout_type').val('wallet_button');
		jQuery('form.checkout, form#order_review').submit();
	}

	var availablePayment = document.getElementsByClassName('mp-checkout-custom-available-payments')[0];
	var collapsible = availablePayment.getElementsByClassName('mp-checkout-custom-available-payments-header')[0];

	collapsible.addEventListener("click", function() {
		var icon = collapsible.getElementsByClassName('mp-checkout-custom-available-payments-collapsible')[0];
		var content = availablePayment.getElementsByClassName('mp-checkout-custom-available-payments-content')[0];

		if (content.style.maxHeight) {
			content.style.maxHeight = null;
			content.style.padding = "0px";
			icon.src = "<?php echo esc_url(plugins_url('../assets/images/chefron-down.png', plugin_dir_path(__FILE__))); ?>";
		} else {
			let hg = content.scrollHeight + 15 + "px";
			content.style.setProperty("max-height", hg, "important");
			content.style.setProperty("padding", "24px 0px 0px", "important");

			icon.src = "<?php echo esc_url(plugins_url('../assets/images/chefron-up.png', plugin_dir_path(__FILE__))); ?>";
		}
	});
</script>
