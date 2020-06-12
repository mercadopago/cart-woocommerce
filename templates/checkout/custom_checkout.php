<?php
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="mp-panel-custom-checkout">
	<div class="mp-row-checkout">

		<!-- Links cards can pay | current promotions only Argentina -->
		<div class="mp-frame-links">
			<a class="mp-checkout-link mp-pr-10" id="button-show-payments">
				<?= __('With what cards can I pay', 'woocommerce-mercadopago') ?> ⌵
			</a>
			<?php if ($site_id == 'MLA') : ?>
				<span id="mp_promotion_link"> | </span>
				<a href="https://www.mercadopago.com.ar/cuotas" id="mp_checkout_link" class="mp-checkout-link mp-pl-10" target="_blank">
					<?= __('See current promotions', 'woocommerce-mercadopago') ?>
				</a>
			<?php endif; ?>
		</div>

		<!-- Frame with cards acepteds -->
		<div class="mp-frame-payments" id="mp-frame-payments">
			<div class="mp-col-md-12">
				<div class="frame-tarjetas">
					<?php if (count($credit_card) != 0) : ?>
						<p class="submp-title-checkout-custom"><?= __('Credit cards', 'woocommerce-mercadopago') ?></p>
						<?php foreach ($credit_card as $credit_image) : ?>
							<img src="<?= $credit_image ?>" class="mp-img-fluid mp-img-tarjetas" alt="" />
						<?php endforeach; ?>
					<?php endif; ?>

					<?php if (count($debit_card) != 0) : ?>
						<p class="submp-title-checkout-custom mp-pt-10"><?= __('Debit card', 'woocommerce-mercadopago') ?></p>
						<?php foreach ($debit_card as $debit_image) : ?>
							<img src="<?= $debit_image ?>" class="mp-img-fluid mp-img-tarjetas" alt="" />
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- Cupom mode, creat a campaign on mercado pago -->
		<?php if ($coupon_mode == 'yes') : ?>
			<div class="mp-col-md-12" id="mercadopago-form-coupon">
				<div class="frame-tarjetas mp-text-justify">
					<p class="mp-subtitle-custom-checkout"><?= __('Enter your discount coupon', 'woocommerce-mercadopago') ?></p>

					<div class="mp-row-checkout mp-pt-10">
						<div class="mp-col-md-9 mp-pr-15">
							<input type="text" class="mp-form-control" id="couponCode" name="mercadopago_custom[coupon_code]" autocomplete="off" maxlength="24" placeholder="<?= __('Enter your coupon', 'woocommerce-mercadopago') ?>" />
						</div>

						<div class="mp-col-md-3">
							<input type="button" class="mp-button mp-pointer" id="applyCoupon" value="<?= esc_html__('Apply', 'woocommerce-mercadopago'); ?>">
						</div>
						<div class="mp-discount mp-col-md-9 mp-pr-15" id="mpCouponApplyed"></div>
						<span class="mp-error" id="mpCouponError"><?= __('The code you entered is incorrect', 'woocommerce-mercadopago') ?></span>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<div class="mp-col-md-12">
			<div class="frame-tarjetas">
				<!-- Title enter your card details -->
				<p class="mp-subtitle-custom-checkout"><?= __('Enter your card details', 'woocommerce-mercadopago') ?></p>

				<div id="mercadopago-form">
					<!-- Input Card number -->
					<div class="mp-row-checkout mp-pt-10">
						<div class="mp-col-md-12">
							<label for="mp-card-number" class="mp-label-form"><?= esc_html__('Card number', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<input type="text" onkeyup="mpCreditMaskDate(this, mpMcc);" class="mp-form-control mp-mt-5" id="mp-card-number" data-checkout="cardNumber" autocomplete="off" maxlength="23" />

							<span class="mp-error mp-mt-5" id="mp-error-205" data-main="#mp-card-number"><?= esc_html__('Card number', 'woocommerce-mercadopago'); ?></span>
							<span class="mp-error mp-mt-5" id="mp-error-E301" data-main="#mp-card-number"><?= esc_html__('Invalid Card Number', 'woocommerce-mercadopago'); ?></span>
						</div>
					</div>
					<!-- Input Name and Surname -->
					<div class="mp-row-checkout mp-pt-10" id="mp-card-holder-div">
						<div class="mp-col-md-12">
							<label for="mp-card-holder-name" class="mp-label-form"><?= esc_html__('Name and surname of the cardholder', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<input type="text" class="mp-form-control mp-mt-5" id="mp-card-holder-name" data-checkout="cardholderName" autocomplete="off" />

							<span class="mp-error mp-mt-5" id="mp-error-221" data-main="#mp-card-holder-name"><?= esc_html__('Invalid Card Number', 'woocommerce-mercadopago'); ?></span>
							<span class="mp-error mp-mt-5" id="mp-error-E301" data-main="#mp-card-holder-name"><?= esc_html__('Invalid Card Number', 'woocommerce-mercadopago'); ?></span>
						</div>
					</div>

					<div class="mp-row-checkout mp-pt-10">
						<!-- Input expiration date -->
						<div class="mp-col-md-6 mp-pr-15">
							<label for="mp-card-expiration-date" class="mp-label-form"><?= esc_html__('Expiration date', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<input type="text" onkeyup="mpCreditMaskDate(this, mpDate);" onblur="mpValidateMonthYear()" class="mp-form-control mp-mt-5" id="mp-card-expiration-date" data-checkout="cardExpirationDate" autocomplete="off" placeholder="MM/AAAA" maxlength="7" />
							<input type="hidden" id="cardExpirationMonth" data-checkout="cardExpirationMonth">
							<input type="hidden" id="cardExpirationYear" data-checkout="cardExpirationYear">
							<span class="mp-error mp-mt-5" id="mp-error-208" data-main="#mp-card-expiration-date"><?= esc_html__('Invalid Expiration Date', 'woocommerce-mercadopago'); ?></span>
						</div>
						<!-- Input Security Code -->
						<div class="mp-col-md-6">
							<label for="mp-security-code" class="mp-label-form"><?= esc_html__('Security code', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<input type="text" onkeyup="mpCreditMaskDate(this, mpInteger);" class="mp-form-control mp-mt-5" id="mp-security-code" data-checkout="securityCode" autocomplete="off" maxlength="4" />
							<p class="mp-desc mp-mt-5 mp-mb-0" data-main="#mp-security-code"><?= esc_html__('Last 3 numbers on the back', 'woocommerce-mercadopago'); ?></p>
							<span class="mp-error mp-mt-5" id="mp-error-224" data-main="#mp-security-code"><?= esc_html__('Check the informed security code.', 'woocommerce-mercadopago'); ?></span>
							<span class="mp-error mp-mt-5" id="mp-error-E302" data-main="#mp-security-code"><?= esc_html__('Check the informed security code.', 'woocommerce-mercadopago'); ?></span>
						</div>
					</div>

					<div class="mp-col-md-12">
						<div class="frame-tarjetas">
							<!-- Title installments -->
							<p class="mp-subtitle-custom-checkout"><?= __('In how many installments do you want to pay', 'woocommerce-mercadopago') ?></p>

							<!-- Select issuer -->
							<div class="mp-row-checkout mp-pt-10">
								<div id="mp-issuer-div" class="mp-col-md-4 mp-pr-15">
									<div class="mp-issuer">
										<label for="mp-issuer" class="mp-label-form"><?= esc_html__('Issuer', 'woocommerce-mercadopago'); ?> </label>
										<select class="mp-form-control mp-pointer mp-mt-5" id="mp-issuer" data-checkout="issuer" name="mercadopago_custom[issuer]"></select>
									</div>
								</div>

								<!-- Select installments -->
								<div id="installments-div" class="mp-col-md-12">
									<?php if ($currency_ratio != 1) : ?>
										<label for="installments" class="mp-label-form">
											<div class="mp-tooltip">
												<?= esc_html__('', 'woocommerce-mercadopago'); ?>
												<span class="mp-tooltiptext">
													<?=
															esc_html__('Converted payment of', 'woocommerce-mercadopago') . " " .
																$woocommerce_currency . " " . esc_html__('for', 'woocommerce-mercadopago') . " " .
																$account_currency;
														?>
												</span>
											</div>
											<em>*</em>
										</label>
									<?php else : ?>
										<label for="mp-installments" class="mp-label-form"><?= __('Select the number of installment', 'woocommerce-mercadopago') ?></label>
									<?php endif; ?>

									<select class="mp-form-control mp-pointer mp-mt-5" id="mp-installments" data-checkout="installments" name="mercadopago_custom[installments]"></select>

									<div id="mp-box-input-tax-cft">
										<div id="mp-box-input-tax-tea">
											<div id="mp-tax-tea-text"></div>
										</div>
										<div id="mp-tax-cft-text"></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div id="mp-doc-div" class="mp-col-md-12 mp-doc">
						<div class="frame-tarjetas">
							<!-- Title document -->
							<p class="mp-subtitle-custom-checkout"><?= __('Enter your document number', 'woocommerce-mercadopago') ?></p>

							<div id="mp-doc-type-div" class="mp-row-checkout mp-pt-10">
								<!-- Select Doc Type -->
								<div class="mp-col-md-4 mp-pr-15">
									<label for="docType" class="mp-label-form">
                                        <?= esc_html__('Type', 'woocommerce-mercadopago'); ?>
                                    </label>
									<select id="docType" class="mp-form-control mp-pointer mp-mt-04rem" data-checkout="docType"></select>
								</div>

								<!-- Input Doc Number -->
								<div id="mp-doc-number-div" class="mp-col-md-8">
									<label for="docNumber" class="mp-label-form"><?= esc_html__('Document number', 'woocommerce-mercadopago'); ?> <em>*</em></label>
									<input type="text" class="mp-form-control mp-mt-04rem" id="docNumber" data-checkout="docNumber" autocomplete="off" />
									<p class="mp-desc mp-mt-5 mp-mb-0" data-main="#mp-security-code"><?= esc_html__('Only numbers', 'woocommerce-mercadopago'); ?></p>
									<span class="mp-error mp-mt-5" id="mp-error-324" data-main="#docNumber"><?= esc_html__('Invalid Document Number', 'woocommerce-mercadopago'); ?></span>
									<span class="mp-error mp-mt-5" id="mp-error-E301" data-main="#docNumber"><?= esc_html__('Invalid Document Number', 'woocommerce-mercadopago'); ?></span>
								</div>
							</div>
						</div>
					</div>

					<div class="mp-col-md-12 mp-pt-10">
						<div class="frame-tarjetas">
							<div class="mp-row-checkout">
								<p class="mp-obrigatory">
									<em>*</em> <?= esc_html__('Obligatory field', 'woocommerce-mercadopago'); ?>
								</p>
							</div>
						</div>
					</div>
				</div>

				<!-- NOT DELETE LOADING-->
				<div id="mp-box-loading"></div>

			</div>
		</div>

		<div id="mercadopago-utilities">
			<input type="hidden" id="mp-amount" value='<?php echo $amount; ?>' name="mercadopago_custom[amount]" />
			<input type="hidden" id="currency_ratio" value='<?php echo $currency_ratio; ?>' name="mercadopago_custom[currency_ratio]" />
			<input type="hidden" id="campaign_id" name="mercadopago_custom[campaign_id]" />
			<input type="hidden" id="campaign" name="mercadopago_custom[campaign]" />
			<input type="hidden" id="mp-discount" name="mercadopago_custom[discount]" />
			<input type="hidden" id="paymentMethodId" name="mercadopago_custom[paymentMethodId]" />
			<input type="hidden" id="token" name="mercadopago_custom[token]" />
		</div>

	</div>
</div>

<script type="text/javascript">
	function mpCreditExecmascara() {
		v_obj.value = v_fun(v_obj.value)
	}

	//Card mask date input
	function mpCreditMaskDate(o, f) {
		v_obj = o
		v_fun = f
		setTimeout("mpCreditExecmascara()", 1);
	}

	function mpMcc(value) {
        if(mpIsMobile()){
            return value;
        }
		value = value.replace(/\D/g, "");
		value = value.replace(/^(\d{4})(\d)/g, "$1 $2");
		value = value.replace(/^(\d{4})\s(\d{4})(\d)/g, "$1 $2 $3");
		value = value.replace(/^(\d{4})\s(\d{4})\s(\d{4})(\d)/g, "$1 $2 $3 $4");
		return value;
	}

	function mpDate(v) {
		v = v.replace(/\D/g, "");
		v = v.replace(/(\d{2})(\d)/, "$1/$2");
		v = v.replace(/(\d{2})(\d{2})$/, "$1$2");
		return v;
	}

	// Explode date to month and year
	function mpValidateMonthYear() {
		var date = document.getElementById('mp-card-expiration-date').value.split('/');
		document.getElementById('cardExpirationMonth').value = date[0];
		document.getElementById('cardExpirationYear').value = date[1];
	}

	function mpInteger(v) {
		return v.replace(/\D/g, "");
	}

    function mpIsMobile() {
        try{
            document.createEvent("TouchEvent");
            return true;
        }catch(e){
            return false;
        }
    }
</script>
