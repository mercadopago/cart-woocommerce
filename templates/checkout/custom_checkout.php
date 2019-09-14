<?php
if (!defined('ABSPATH')) {
	exit;
}
?>
<div class="mp-panel-custom-checkout">
	<div class="mp-row-checkout">
		<div class="mp-frame-links">
			<a class="mp-checkout-link mp-pr-10" id="button-show-payments">
				<?= __('With what cards can I pay', 'woocommerce-mercadopago') ?> ⌵
			</a>
			<span id="mp_promotion_link"> | </span>
			<a href="https://www.mercadopago.com.ar/cuotas" id="mp_checkout_link" class="mp-checkout-link mp-pl-10" target="_blank">
				<?= __('See current promotions', 'woocommerce-mercadopago') ?>
			</a>
		</div>

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
				</div>

				<span class="mp-discount" id="mpCouponApplyed"></span>
				<span class="mp-error" id="mpCouponError"><?= __('The code you entered is incorrect', 'woocommerce-mercadopago') ?></span>
			</div>
		</div>

		<div class="mp-col-md-12">
			<div class="frame-tarjetas">
				<p class="mp-subtitle-custom-checkout"><?= __('Enter your card details', 'woocommerce-mercadopago') ?></p>

				<!-- saved cards -->
				<div id="mercadopago-form-customer-and-card">
					<div class="mp-row-checkout mp-pt-10">
						<div class="mp-col-md-12">
							<label for="paymentMethodIdSelector" class="mp-label-form"><?= esc_html__('Payment Method', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<select id="paymentMethodSelector" class="mp-form-control mp-pointer" name="mercadopago_custom[paymentMethodSelector]" data-checkout="cardId">
								<optgroup label="<?= esc_html__('Your card', 'woocommerce-mercadopago'); ?>" id="payment-methods-for-customer-and-cards">
									<?php foreach ($customer_cards as $card) : ?>
										<option value="<?= $card['id']; ?>
															first_six_digits=<?= $card['first_six_digits']; ?>
															last_four_digits=<?= $card['last_four_digits']; ?>
															security_code_length=<?= $card['security_code']['length']; ?>
															type_checkout='customer_and_card'
															payment_method_id=<?= $card['payment_method']['id']; ?>">
											<?= ucfirst($card['payment_method']['name']); ?>
											<?= esc_html__('finished in', 'woocommerce-mercadopago'); ?>
											<?= $card['last_four_digits']; ?>
										</option>
									<?php endforeach; ?>
								</optgroup>

								<optgroup label="<?= esc_html__('Other cards', 'woocommerce-mercadopago'); ?>" id="payment-methods-list-other-cards">
									<option value="-1"><?= esc_html__('Another card', 'woocommerce-mercadopago'); ?></option>
								</optgroup>
							</select>
						</div>

						<div class="mp-col-md-4">
							<div id="mp-securityCode-customer-and-card">
								<div class="mp-box-inputs mp-col-45">
									<label for="customer-and-card-securityCode" class="mp-label-form"><?= esc_html__('CVV', 'woocommerce-mercadopago'); ?> <em>*</em></label>
									<input type="text" onkeyup="maskDate(this, minteger);" class="mp-form-control" id="customer-and-card-securityCode" data-checkout="securityCode" autocomplete="off" maxlength="4" />

									<span class="mp-error" id="mp-error-224" data-main="#customer-and-card-securityCode"><?= esc_html__('Card number', 'woocommerce-mercadopago'); ?></span>
									<span class="mp-error" id="mp-error-E302" data-main="#customer-and-card-securityCode"><?= esc_html__('Invalid Card Number', 'woocommerce-mercadopago'); ?></span>
									<span class="mp-error" id="mp-error-E203" data-main="#customer-and-card-securityCode"><?= esc_html__('Invalid Card Number', 'woocommerce-mercadopago'); ?></span>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- end saved cards -->

				<!-- new card -->
				<div id="mercadopago-form">
					<div class="mp-row-checkout mp-pt-10">
						<div class="mp-col-md-12">
							<label for="cardNumber" class="mp-label-form"><?= esc_html__('Card number', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<input type="text" onkeyup="maskDate(this, mcc);" class="mp-form-control mp-mt-5" id="cardNumber" data-checkout="cardNumber" autocomplete="off" maxlength="23" />

							<span class="mp-error mp-mt-5" id="mp-error-205" data-main="#cardNumber"><?= esc_html__('Card number', 'woocommerce-mercadopago'); ?></span>
							<span class="mp-error mp-mt-5" id="mp-error-E301" data-main="#cardNumber"><?= esc_html__('Invalid Card Number', 'woocommerce-mercadopago'); ?></span>
						</div>
					</div>

					<div class="mp-row-checkout mp-pt-10">
						<div class="mp-col-md-12">
							<label for="cardholderName" class="mp-label-form"><?= esc_html__('Name and surname of the cardholder', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<input type="text" class="mp-form-control mp-mt-5" id="cardholderName" name="mercadopago_custom[cardholderName]" data-checkout="cardholderName" autocomplete="off" />

							<span class="mp-error mp-mt-5" id="mp-error-221" data-main="#cardholderName"><?= esc_html__('Card number', 'woocommerce-mercadopago'); ?></span>
							<span class="mp-error mp-mt-5" id="mp-error-316" data-main="#cardholderName"><?= esc_html__('Invalid cardholder name', 'woocommerce-mercadopago'); ?></span>
						</div>
					</div>

					<div class="mp-row-checkout mp-pt-10">
						<div class="mp-col-md-6 mp-pr-15">
							<label for="cardholderName" class="mp-label-form"><?= esc_html__('Expiration date', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<input type="text" onkeyup="maskDate(this, mdate);" onblur="validateMonthYear()" class="mp-form-control mp-mt-5" id="cardExpirationDate" data-checkout="cardExpirationDate" name="mercadopago_custom[cardExpirationDate]" autocomplete="off" placeholder="MM/AAAA" maxlength="7" />
							<input type="hidden" id="cardExpirationMonth" name="mercadopago_custom[cardExpirationMonth]" data-checkout="cardExpirationMonth">
							<input type="hidden" id="cardExpirationYear" name="mercadopago_custom[cardExpirationYear]" data-checkout="cardExpirationYear">

							<span class="mp-error mp-mt-5" id="mp-error-208" data-main="#cardExpirationDate"><?= esc_html__('Invalid Expiration Date', 'woocommerce-mercadopago'); ?></span>
						</div>

						<div class="mp-col-md-6">
							<label for="securityCode" class="mp-label-form"><?= esc_html__('Last 3 numbers on the back', 'woocommerce-mercadopago'); ?> <em>*</em></label>
							<input type="text" onkeyup="maskDate(this, minteger);" class="mp-form-control mp-mt-5" id="securityCode" data-checkout="securityCode" autocomplete="off" maxlength="4" />

							<p class="mp-desc mp-mt-5 mp-mb-0" data-main="#securityCode"><?= esc_html__('Last 3 numbers on the back', 'woocommerce-mercadopago'); ?></p>
							<span class="mp-error mp-mt-5" id="mp-error-224" data-main="#securityCode"><?= esc_html__('Card number', 'woocommerce-mercadopago'); ?></span>
							<span class="mp-error mp-mt-5" id="mp-error-E302" data-main="#securityCode"><?= esc_html__('Invalid Card Number', 'woocommerce-mercadopago'); ?></span>
						</div>
					</div>

					<div class="mp-col-md-12">
						<div class="frame-tarjetas">
							<p class="mp-subtitle-custom-checkout"><?= __('In how many installments do you want to pay?', 'woocommerce-mercadopago') ?></p>

							<div class="mp-row-checkout mp-pt-10">
								<div class="mp-col-md-4 mp-pr-15">
									<div class="mp-issuer">
										<label for="issuer" class="mp-label-form"><?= esc_html__('Issuer', 'woocommerce-mercadopago'); ?> </label>
										<select class="mp-form-control mp-pointer mp-mt-5" id="issuer" data-checkout="issuer" name="mercadopago_custom[issuer]"></select>
									</div>
								</div>

								<div id="installments-div" class="mp-col-md-8">
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
										<label for="installments" class="mp-label-form"><?= __('Select the number of installment', 'woocommerce-mercadopago') ?></label>
									<?php endif; ?>

									<select class="mp-form-control mp-pointer mp-mt-5" id="installments" data-checkout="installments" name="mercadopago_custom[installments]"></select>

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
							<p class="mp-subtitle-custom-checkout"><?= __('Enter your document number', 'woocommerce-mercadopago') ?></p>

							<div class="mp-row-checkout mp-pt-10">
								<div class="mp-col-md-4 mp-pr-15 mp-pt-5">
									<label for="docType" class="mp-label-form"><?= esc_html__('Type', 'woocommerce-mercadopago'); ?></label>
									<select id="docType" class="mp-form-control mp-pointer mp-mt-06rem" data-checkout="docType" name="mercadopago_custom[docType]"></select>
								</div>

								<div class="mp-col-md-8">
									<label for="docNumber" class="mp-label-form"><?= esc_html__('Document number', 'woocommerce-mercadopago'); ?> <em>*</em></label>
									<input type="text" class="mp-form-control mp-mt-5" id="docNumber" data-checkout="docNumber" name="mercadopago_custom[docNumber]" autocomplete="off" />
									<p class="mp-desc mp-mt-5 mp-mb-0" data-main="#securityCode"><?= esc_html__('Only numbers', 'woocommerce-mercadopago'); ?></p>

									<span class="mp-error mp-mt-5" id="mp-error-214" data-main="#docNumber"><?= esc_html__('Card number', 'woocommerce-mercadopago'); ?></span>
									<span class="mp-error mp-mt-5" id="mp-error-324" data-main="#docNumber"><?= esc_html__('Invalid Document Number', 'woocommerce-mercadopago'); ?></span>
								</div>
							</div>
						</div>
					</div>

					<div class="mp-col-md-12 mp-pt-10">
						<div class="frame-tarjetas">
							<div class="mp-row-checkout mp-pt-10">
								<label for="doNotSaveCard" class="mp-label-form-check mp-pointer" style="display: inline;">
									<input class="mp-form-control-check" type="checkbox" name="mercadopago_custom[doNotSaveCard]" id="doNotSaveCard" value="yes">
									<?= esc_html__('Do not save card', 'woocommerce-mercadopago'); ?>
								</label>
								<p class="mp-obrigatory"><em>*</em> <?= esc_html__('Obligatory field', 'woocommerce-mercadopago'); ?></p>
							</div>
						</div>
					</div>
				</div>
				<!-- end new card -->

				<!-- NOT DELETE LOADING-->
				<div id="mp-box-loading"></div>

			</div>
		</div>

		<div id="mercadopago-utilities">
			<input type="hidden" id="site_id" name="mercadopago_custom[site_id]" />
			<input type="hidden" id="amount" value='<?php echo $amount; ?>' name="mercadopago_custom[amount]" />
			<input type="hidden" id="currency_ratio" value='<?php echo $currency_ratio; ?>' name="mercadopago_custom[currency_ratio]" />
			<input type="hidden" id="campaign_id" name="mercadopago_custom[campaign_id]" />
			<input type="hidden" id="campaign" name="mercadopago_custom[campaign]" />
			<input type="hidden" id="discount" name="mercadopago_custom[discount]" />
			<input type="hidden" id="paymentMethodId" name="mercadopago_custom[paymentMethodId]" />
			<input type="hidden" id="token" name="mercadopago_custom[token]" />
			<input type="hidden" id="cardTruncated" name="mercadopago_custom[cardTruncated]" />
			<input type="hidden" id="CustomerAndCard" name="mercadopago_custom[CustomerAndCard]" />
			<input type="hidden" id="CustomerId" value='<?php echo $customerId; ?>' name="mercadopago_custom[CustomerId]" />
		</div>

	</div>
</div>

<script type="text/javascript">
	//collapsible payments
	var show_payments = document.querySelector("#button-show-payments")
	var frame_payments = document.querySelector("#mp-frame-payments");

	show_payments.onclick = function() {
		if (frame_payments.style.display == "inline-block") {
			frame_payments.style.display = "none";
		} else {
			frame_payments.style.display = "inline-block";
		}
	};

	//mask date input
	function maskDate(o, f) {
		v_obj = o
		v_fun = f
		setTimeout("execmascara()", 1)
	}

	function execmascara() {
		v_obj.value = v_fun(v_obj.value)
	}

	function mdate(v) {
		v = v.replace(/\D/g, "");
		v = v.replace(/(\d{2})(\d)/, "$1/$2");
		v = v.replace(/(\d{2})(\d{2})$/, "$1$2");
		return v;
	}

	function minteger(v) {
		return v.replace(/\D/g, "")
	}

	function mcc(v) {
		v = v.replace(/\D/g, "");
		v = v.replace(/^(\d{4})(\d)/g, "$1 $2");
		v = v.replace(/^(\d{4})\s(\d{4})(\d)/g, "$1 $2 $3");
		v = v.replace(/^(\d{4})\s(\d{4})\s(\d{4})(\d)/g, "$1 $2 $3 $4");
		return v;
	}

	//explode date to month and year
	function validateMonthYear() {
		var date = document.getElementById('cardExpirationDate').value.split('/');
		document.getElementById('cardExpirationMonth').value = date[0];
		document.getElementById('cardExpirationYear').value = date[1];
	}

	//mercadopago function
	(function($) {

		var mercado_pago = false;
		var MPv1 = {
			debug: true,
			add_truncated_card: true,
			site_id: "",
			public_key: "",
			coupon_of_discounts: {
				discount_action_url: "",
				payer_email: "",
				default: true,
				status: false
			},
			customer_and_card: {
				default: true,
				status: true
			},
			inputs_to_create_discount: [
				"couponCode",
				"applyCoupon"
			],
			inputs_to_create_token: [
				"cardNumber",
				"cardExpirationDate",
				"cardholderName",
				"securityCode",
				"docType",
				"docNumber"
			],
			inputs_to_create_token_customer_and_card: [
				"paymentMethodSelector",
				"securityCode"
			],
			selectors: {
				// others
				mp_doc_div: "#mp-doc-div",
				mpCheckoutLink: "#mp_checkout_link",
				mpPromotionLink: "#mp_promotion_link",
				// currency
				currency_ratio: "#currency_ratio",
				// coupom
				couponCode: "#couponCode",
				applyCoupon: "#applyCoupon",
				mpCouponApplyed: "#mpCouponApplyed",
				mpCouponError: "#mpCouponError",
				campaign_id: "#campaign_id",
				campaign: "#campaign",
				discount: "#discount",
				// customer cards
				paymentMethodSelector: "#paymentMethodSelector",
				pmCustomerAndCards: "#payment-methods-for-customer-and-cards",
				pmListOtherCards: "#payment-methods-list-other-cards",
				// card data
				mpSecurityCodeCustomerAndCard: "#mp-securityCode-customer-and-card",
				cardNumber: "#cardNumber",
				cardExpirationMonth: "#cardExpirationMonth",
				cardExpirationYear: "#cardExpirationYear",
				cardholderName: "#cardholderName",
				securityCode: "#securityCode",
				docType: "#docType",
				docNumber: "#docNumber",
				issuer: "#issuer",
				installments: "#installments",
				// document
				mpDoc: ".mp-doc",
				mpIssuer: ".mp-issuer",
				mpDocType: ".mp-docType",
				mpDocNumber: ".mp-docNumber",
				// payment method and checkout
				paymentMethodId: "#paymentMethodId",
				amount: "#amount",
				token: "#token",
				cardTruncated: "#cardTruncated",
				site_id: "#site_id",
				CustomerAndCard: "#CustomerAndCard",
				box_loading: "#mp-box-loading",
				submit: "#submit",
				// tax resolution AG 51/2017
				boxInstallments: "#mp-box-installments",
				boxInstallmentsSelector: "#mp-box-installments-selector",
				taxCFT: "#mp-box-input-tax-cft",
				taxTEA: "#mp-box-input-tax-tea",
				taxTextCFT: "#mp-tax-cft-text",
				taxTextTEA: "#mp-tax-tea-text",
				// form
				form: "#mercadopago-form",
				formCoupon: "#mercadopago-form-coupon",
				formCustomerAndCard: "#mercadopago-form-customer-and-card",
				utilities_fields: "#mercadopago-utilities"
			},
			text: {
				choose: "Choose",
				other_bank: "Other Bank",
				discount_info1: "You will save",
				discount_info2: "with discount from",
				discount_info3: "Total of your purchase:",
				discount_info4: "Total of your purchase with discount:",
				discount_info5: "*Uppon payment approval",
				discount_info6: "Terms and Conditions of Use",
				coupon_empty: "Please, inform your coupon code",
				apply: "Apply",
				remove: "Remove"
			},
			paths: {
				loading: "images/loading.gif",
				check: "images/check.png",
				error: "images/error.png"
			}
		}

		// === Coupon of Discounts
		MPv1.currencyIdToCurrency = function(currency_id) {
			if (currency_id == "ARS") {
				return "$";
			} else if (currency_id == "BRL") {
				return "R$";
			} else if (currency_id == "COP") {
				return "$";
			} else if (currency_id == "CLP") {
				return "$";
			} else if (currency_id == "MXN") {
				return "$";
			} else if (currency_id == "VEF") {
				return "Bs";
			} else if (currency_id == "PEN") {
				return "S/";
			} else if (currency_id == "UYU") {
				return "$U";
			} else {
				return "$";
			}
		}

		MPv1.checkCouponEligibility = function() {
			if (document.querySelector(MPv1.selectors.couponCode).value == "") {
				// Coupon code is empty.
				document.querySelector(MPv1.selectors.mpCouponApplyed).style.display = "none";
				document.querySelector(MPv1.selectors.mpCouponError).style.display = "block";
				document.querySelector(MPv1.selectors.mpCouponError).innerHTML = MPv1.text.coupon_empty;
				MPv1.coupon_of_discounts.status = false;
				document.querySelector(MPv1.selectors.couponCode).style.background = null;
				document.querySelector(MPv1.selectors.applyCoupon).value = MPv1.text.apply;
				document.querySelector(MPv1.selectors.discount).value = 0;
				MPv1.cardsHandler();
			} else if (MPv1.coupon_of_discounts.status) {
				// We already have a coupon set, so we remove it.
				document.querySelector(MPv1.selectors.mpCouponApplyed).style.display = "none";
				document.querySelector(MPv1.selectors.mpCouponError).style.display = "none";
				MPv1.coupon_of_discounts.status = false;
				document.querySelector(MPv1.selectors.applyCoupon).style.background = null;
				document.querySelector(MPv1.selectors.applyCoupon).value = MPv1.text.apply;
				document.querySelector(MPv1.selectors.couponCode).value = "";
				document.querySelector(MPv1.selectors.couponCode).style.background = null;
				document.querySelector(MPv1.selectors.discount).value = 0;
				MPv1.cardsHandler();
			} else {
				// Set loading.
				document.querySelector(MPv1.selectors.mpCouponApplyed).style.display = "none";
				document.querySelector(MPv1.selectors.mpCouponError).style.display = "none";
				document.querySelector(MPv1.selectors.couponCode).style.background = "url(" + MPv1.paths.loading + ") 98% 50% no-repeat #fff";
				document.querySelector(MPv1.selectors.couponCode).style.border = "1px solid #cecece";
				document.querySelector(MPv1.selectors.applyCoupon).disabled = true;

				// Check if there are params in the url.
				var url = MPv1.coupon_of_discounts.discount_action_url;
				var sp = "?";
				if (url.indexOf("?") >= 0) {
					sp = "&";
				}
				url += sp + "site_id=" + MPv1.site_id;
				url += "&coupon_id=" + document.querySelector(MPv1.selectors.couponCode).value;
				url += "&amount=" + document.querySelector(MPv1.selectors.amount).value;
				url += "&payer=" + MPv1.coupon_of_discounts.payer_email;
				//url += "&payer=" + document.getElementById( "billing_email" ).value;

				MPv1.AJAX({
					url: url,
					method: "GET",
					timeout: 5000,
					error: function() {
						// Request failed.
						document.querySelector(MPv1.selectors.mpCouponApplyed).style.display = "none";
						document.querySelector(MPv1.selectors.mpCouponError).style.display = "none";
						MPv1.coupon_of_discounts.status = false;
						document.querySelector(MPv1.selectors.applyCoupon).style.background = null;
						document.querySelector(MPv1.selectors.applyCoupon).value = MPv1.text.apply;
						document.querySelector(MPv1.selectors.couponCode).value = "";
						document.querySelector(MPv1.selectors.couponCode).style.background = null;
						document.querySelector(MPv1.selectors.discount).value = 0;
						MPv1.cardsHandler();
					},
					success: function(status, response) {
						if (response.status == 200) {
							document.querySelector(MPv1.selectors.mpCouponApplyed).style.display =
								"block";
							document.querySelector(MPv1.selectors.discount).value =
								response.response.coupon_amount;
							document.querySelector(MPv1.selectors.mpCouponApplyed).innerHTML =
								//"<div style='border-style: solid; border-width:thin; " +
								//"border-color: #009EE3; padding: 8px 8px 8px 8px; margin-top: 4px;'>" +
								MPv1.text.discount_info1 + " <strong>" +
								MPv1.currencyIdToCurrency(response.response.currency_id) + " " +
								Math.round(response.response.coupon_amount * 100) / 100 +
								"</strong> " + MPv1.text.discount_info2 + " " +
								response.response.name + ".<br>" + MPv1.text.discount_info3 + " <strong>" +
								MPv1.currencyIdToCurrency(response.response.currency_id) + " " +
								Math.round(MPv1.getAmountWithoutDiscount() * 100) / 100 +
								"</strong><br>" + MPv1.text.discount_info4 + " <strong>" +
								MPv1.currencyIdToCurrency(response.response.currency_id) + " " +
								Math.round(MPv1.getAmount() * 100) / 100 + "*</strong><br>" +
								"<i>" + MPv1.text.discount_info5 + "</i><br>" +
								"<a href='https://api.mercadolibre.com/campaigns/" +
								response.response.id +
								"/terms_and_conditions?format_type=html' target='_blank'>" +
								MPv1.text.discount_info6 + "</a>";
							document.querySelector(MPv1.selectors.mpCouponError).style.display = "none";
							MPv1.coupon_of_discounts.status = true;
							document.querySelector(MPv1.selectors.couponCode).style.background =
								null;
							document.querySelector(MPv1.selectors.couponCode).style.background =
								"url(" + MPv1.paths.check + ") 98% 50% no-repeat #fff";
							document.querySelector(MPv1.selectors.couponCode).style.border = "1px solid #cecece";
							document.querySelector(MPv1.selectors.applyCoupon).value =
								MPv1.text.remove;
							MPv1.cardsHandler();
							document.querySelector(MPv1.selectors.campaign_id).value =
								response.response.id;
							document.querySelector(MPv1.selectors.campaign).value =
								response.response.name;
						} else {
							document.querySelector(MPv1.selectors.mpCouponApplyed).style.display = "none";
							document.querySelector(MPv1.selectors.mpCouponError).style.display = "block";
							document.querySelector(MPv1.selectors.mpCouponError).innerHTML = response.response.message;
							MPv1.coupon_of_discounts.status = false;
							document.querySelector(MPv1.selectors.couponCode).style.background = null;
							document.querySelector(MPv1.selectors.couponCode).style.background = "url(" + MPv1.paths.error + ") 98% 50% no-repeat #fff";
							document.querySelector(MPv1.selectors.applyCoupon).value = MPv1.text.apply;
							document.querySelector(MPv1.selectors.discount).value = 0;
							MPv1.cardsHandler();
						}
						document.querySelector(MPv1.selectors.applyCoupon).disabled = false;
					}
				});
			}
		}

		MPv1.getBin = function() {

			var cardSelector = document.querySelector(MPv1.selectors.paymentMethodSelector);

			// 			if (cardSelector && cardSelector[cardSelector.options.selectedIndex].value != "-1") {
			// 				return cardSelector[cardSelector.options.selectedIndex]
			// 					.getAttribute("first_six_digits");
			// 			}

			var ccNumber = document.querySelector(MPv1.selectors.cardNumber);
			return ccNumber.value.replace(/[ .-]/g, "").slice(0, 6);

		}

		MPv1.clearOptions = function() {

			var bin = MPv1.getBin();

			if (bin.length == 0) {
				MPv1.resetBackgroundCard();

				MPv1.hideIssuer();

				var selectorInstallments = document.querySelector(MPv1.selectors.installments),
					fragment = document.createDocumentFragment(),
					option = new Option(MPv1.text.choose + "...", "-1");

				selectorInstallments.options.length = 0;
				fragment.appendChild(option);
				selectorInstallments.appendChild(fragment);
				selectorInstallments.setAttribute("disabled", "disabled");

			}

		}

		MPv1.guessingPaymentMethod = function(event) {
			var bin = MPv1.getBin();
			var amount = MPv1.getAmount();

			if (event.type == "keyup") {
				if (bin != null && bin.length == 6) {
					Mercadopago.getPaymentMethod({
						"bin": bin
					}, MPv1.setPaymentMethodInfo);
				}
			} else {
				setTimeout(function() {
					if (bin.length >= 6) {
						Mercadopago.getPaymentMethod({
							"bin": bin
						}, MPv1.setPaymentMethodInfo);
					}
				}, 100);
			}

		};

		MPv1.setPaymentMethodInfo = function(status, response) {

			if (status == 200) {

				if (MPv1.site_id != "MLM") {
					// Guessing...
					document.querySelector(MPv1.selectors.paymentMethodId).value = response[0].id;
					if (MPv1.customer_and_card.status) {
						document.querySelector(MPv1.selectors.paymentMethodSelector)
							.style.background = "url(" + response[0].secure_thumbnail + ") 90% 50% no-repeat #fff";
					} else {
						document.querySelector(MPv1.selectors.cardNumber).style.background = "url(" +
							response[0].secure_thumbnail + ") 98% 50% no-repeat #fff";
					}
				}

				// Check if the security code (ex: Tarshop) is required.
				var cardConfiguration = response[0].settings;
				var bin = MPv1.getBin();
				var amount = MPv1.getAmount();

				Mercadopago.getInstallments({
						"bin": bin,
						"amount": amount
					},
					MPv1.setInstallmentInfo
				);

				// Check if the issuer is necessary to pay.
				var issuerMandatory = false,
					additionalInfo = response[0].additional_info_needed;

				for (var i = 0; i < additionalInfo.length; i++) {
					if (additionalInfo[i] == "issuer_id") {
						issuerMandatory = true;
					}
				};

				if (issuerMandatory && MPv1.site_id != "MLM") {
					var payment_method_id = response[0].id;
					MPv1.getIssuersPaymentMethod(payment_method_id);
				} else {
					MPv1.hideIssuer();
				}

			}

		}

		MPv1.changePaymetMethodSelector = function() {
			var payment_method_id =
				document.querySelector(MPv1.selectors.paymentMethodSelector).value;
			MPv1.getIssuersPaymentMethod(payment_method_id);
		}

		// === Issuers
		MPv1.getIssuersPaymentMethod = function(payment_method_id) {

			var amount = MPv1.getAmount();

			// flow: MLM mercadopagocard
			if (payment_method_id == "mercadopagocard") {
				Mercadopago.getInstallments({
						"payment_method_id": payment_method_id,
						"amount": amount
					},
					MPv1.setInstallmentInfo
				);
			}

			Mercadopago.getIssuers(payment_method_id, MPv1.showCardIssuers);
			MPv1.addListenerEvent(
				document.querySelector(MPv1.selectors.issuer),
				"change",
				MPv1.setInstallmentsByIssuerId
			);

		}

		MPv1.showCardIssuers = function(status, issuers) {

			// If the API does not return any bank.
			if (issuers.length > 0) {
				var issuersSelector = document.querySelector(MPv1.selectors.issuer);
				var fragment = document.createDocumentFragment();

				issuersSelector.options.length = 0;
				var option = new Option(MPv1.text.choose + "...", "-1");
				fragment.appendChild(option);

				for (var i = 0; i < issuers.length; i++) {
					if (issuers[i].name != "default") {
						option = new Option(issuers[i].name, issuers[i].id);
					} else {
						option = new Option("Otro", issuers[i].id);
					}
					fragment.appendChild(option);
				}

				issuersSelector.appendChild(fragment);
				issuersSelector.removeAttribute("disabled");
			} else {
				MPv1.hideIssuer();
			}

		}

		MPv1.setInstallmentsByIssuerId = function(status, response) {

			var issuerId = document.querySelector(MPv1.selectors.issuer).value;
			var amount = MPv1.getAmount();

			if (issuerId === "-1") {
				return;
			}

			var params_installments = {
				"bin": MPv1.getBin(),
				"amount": amount,
				"issuer_id": issuerId
			}

			if (MPv1.site_id == "MLM") {
				params_installments = {
					"payment_method_id": document.querySelector(
						MPv1.selectors.paymentMethodSelector
					).value,
					"amount": amount,
					"issuer_id": issuerId
				}
			}
			Mercadopago.getInstallments(params_installments, MPv1.setInstallmentInfo);

		}

		MPv1.hideIssuer = function() {
			var $issuer = document.querySelector(MPv1.selectors.issuer);
			var opt = document.createElement("option");
			opt.value = "-1";
			opt.innerHTML = MPv1.text.other_bank;
			opt.style = "font-size: 12px;";

			$issuer.innerHTML = "";
			$issuer.appendChild(opt);
			$issuer.setAttribute("disabled", "disabled");
		}

		// === Installments
		MPv1.setInstallmentInfo = function(status, response) {

			var selectorInstallments = document.querySelector(MPv1.selectors.installments);

			if (response.length > 0) {

				var html_option = "<option value='-1'>" + MPv1.text.choose + "...</option>";
				payerCosts = response[0].payer_costs;

				// fragment.appendChild(option);
				for (var i = 0; i < payerCosts.length; i++) {
					// Resolution 51/2017
					var dataInput = "";
					if (MPv1.site_id == "MLA") {
						var tax = payerCosts[i].labels;
						if (tax.length > 0) {
							for (var l = 0; l < tax.length; l++) {
								if (tax[l].indexOf("CFT_") !== -1) {
									dataInput = "data-tax='" + tax[l] + "'";
								}
							}
						}
					}
					html_option += "<option value='" + payerCosts[i].installments + "' " + dataInput + ">" +
						(payerCosts[i].recommended_message || payerCosts[i].installments) +
						"</option>";
				}

				// Not take the user's selection if equal.
				if (selectorInstallments.innerHTML != html_option) {
					selectorInstallments.innerHTML = html_option;
				}

				selectorInstallments.removeAttribute("disabled");
				MPv1.showTaxes();

			}

		}

		MPv1.showTaxes = function() {
			var selectorIsntallments = document.querySelector(MPv1.selectors.installments);
			var tax = selectorIsntallments.options[selectorIsntallments.selectedIndex].getAttribute("data-tax");
			var cft = "";
			var tea = "";
			if (tax != null) {
				var tax_split = tax.split("|");
				cft = tax_split[0].replace("_", " ");
				tea = tax_split[1].replace("_", " ");
				if (cft == "CFT 0,00%" && tea == "TEA 0,00%") {
					cft = "";
					tea = "";
				}
			}
			document.querySelector(MPv1.selectors.taxTextCFT).innerHTML = cft;
			document.querySelector(MPv1.selectors.taxTextTEA).innerHTML = tea;
		}

		// === Customer & Cards
		MPv1.cardsHandler = function() {

			var cardSelector = document.querySelector(MPv1.selectors.paymentMethodSelector);
			var type_checkout =
				cardSelector[cardSelector.options.selectedIndex].getAttribute("type_checkout");
			var amount = MPv1.getAmount();

			if (MPv1.customer_and_card.default) {

				if (cardSelector &&
					cardSelector[cardSelector.options.selectedIndex].value != "-1" &&
					type_checkout == "customer_and_card") {

					document.querySelector(MPv1.selectors.paymentMethodId)
						.value = cardSelector[cardSelector.options.selectedIndex]
						.getAttribute("payment_method_id");

					MPv1.clearOptions();

					MPv1.customer_and_card.status = true;

					var _bin = cardSelector[cardSelector.options.selectedIndex]
						.getAttribute("first_six_digits");

					Mercadopago.getPaymentMethod({
							"bin": _bin
						},
						MPv1.setPaymentMethodInfo
					);

				} else {

					document.querySelector(MPv1.selectors.paymentMethodId)
						.value = cardSelector.value != -1 ? cardSelector.value : "";
					MPv1.customer_and_card.status = false;
					MPv1.resetBackgroundCard();
					MPv1.guessingPaymentMethod({
						type: "keyup"
					});

				}

				MPv1.setForm();

			}

		}

		// === Payment Methods
		MPv1.getPaymentMethods = function() {

			var fragment = document.createDocumentFragment();
			var paymentMethodsSelector =
				document.querySelector(MPv1.selectors.paymentMethodSelector)
			var mainPaymentMethodSelector =
				document.querySelector(MPv1.selectors.paymentMethodSelector)

			// Set loading.
			mainPaymentMethodSelector.style.background =
				"url(" + MPv1.paths.loading + ") 95% 50% no-repeat #fff";
			mainPaymentMethodSelector.style.border = "1px solid #cecece";

			// If customer and card.
			if (MPv1.customer_and_card.status) {
				paymentMethodsSelector = document.querySelector(MPv1.selectors.pmListOtherCards)
				// Clean payment methods.
				paymentMethodsSelector.innerHTML = "";
			} else {
				paymentMethodsSelector.innerHTML = "";
				option = new Option(MPv1.text.choose + "...", "-1");
				fragment.appendChild(option);
			}

			Mercadopago.getAllPaymentMethods(function(code, payment_methods) {

				for (var x = 0; x < payment_methods.length; x++) {

					var pm = payment_methods[x];

					if ((pm.payment_type_id == "credit_card" || pm.payment_type_id == "debit_card" ||
							pm.payment_type_id == "prepaid_card") && pm.status == "active") {

						option = new Option(pm.name, pm.id);
						option.setAttribute("type_checkout", "custom");
						fragment.appendChild(option);

					}

				}

				paymentMethodsSelector.appendChild(fragment);
				mainPaymentMethodSelector.style.background = "#fff";

			});

		}

		MPv1.validateInputsCreateToken = function() {
			var $inputs = MPv1.getForm().querySelectorAll("[data-checkout]");
			var $inputs_to_create_token = MPv1.getInputsToCreateToken();

			for (var x = 0; x < $inputs.length; x++) {

				var element = $inputs[x];

				// Check is a input to create token.
				if ($inputs_to_create_token
					.indexOf(element.getAttribute("data-checkout")) > -1) {

					if (element.value == -1 || element.value == "") {
						element.focus();
						return false;
					}
				}
			}
			return true;
		}

		MPv1.createToken = function() {
			MPv1.hideErrors();

			// Show loading.
			document.querySelector(MPv1.selectors.box_loading).style.background =
				"url(" + MPv1.paths.loading + ") 0 50% no-repeat #fff";

			// Form.
			var $form = MPv1.getForm();

			Mercadopago.createToken($form, MPv1.sdkResponseHandler);

			return false;
		}

		MPv1.sdkResponseHandler = function(status, response) {
			// Hide loading.
			document.querySelector(MPv1.selectors.box_loading).style.background = "";

			if (status != 200 && status != 201) {
				MPv1.showErrors(response);
			} else {
				var token = document.querySelector(MPv1.selectors.token);
				token.value = response.id;

				if (MPv1.add_truncated_card) {
					var card = MPv1.truncateCard(response);
					document.querySelector(MPv1.selectors.cardTruncated).value = card;
				}

				mercado_pago = true;
				$('form.checkout, form#order_review').submit();
			}
		}

		// === Useful functions
		MPv1.resetBackgroundCard = function() {
			document.querySelector(MPv1.selectors.paymentMethodSelector).style.background =
				"no-repeat #fff";
			document.querySelector(MPv1.selectors.paymentMethodSelector).style.border =
				"1px solid #cecece";
			document.querySelector(MPv1.selectors.cardNumber).style.background =
				"no-repeat #fff";
			document.querySelector(MPv1.selectors.cardNumber).style.border =
				"1px solid #cecece";
		}

		MPv1.setForm = function() {
			if (MPv1.customer_and_card.status) {
				document.querySelector(MPv1.selectors.formDiv).style.display = "none";
				document.querySelector(MPv1.selectors.mpSecurityCodeCustomerAndCard).removeAttribute("style");
			} else {
				document.querySelector(MPv1.selectors.mpSecurityCodeCustomerAndCard).style.display = "none";
				document.querySelector(MPv1.selectors.formDiv).removeAttribute("style");
			}

			Mercadopago.clearSession();

			document.querySelector(MPv1.selectors.CustomerAndCard).value =
				MPv1.customer_and_card.status;
		}

		MPv1.getForm = function() {
			if (MPv1.customer_and_card.status) {
				return document.querySelector(MPv1.selectors.formCustomerAndCard);
			} else {
				return document.querySelector(MPv1.selectors.form);
			}
		}

		MPv1.getInputsToCreateToken = function() {
			if (MPv1.customer_and_card.status) {
				return MPv1.inputs_to_create_token_customer_and_card;
			} else {
				return MPv1.inputs_to_create_token;
			}
		}

		MPv1.truncateCard = function(response_card_token) {

			var first_six_digits;
			var last_four_digits;

			if (MPv1.customer_and_card.status) {
				var cardSelector = document.querySelector(MPv1.selectors.paymentMethodSelector);
				first_six_digits = cardSelector[cardSelector.options.selectedIndex]
					.getAttribute("first_six_digits").match(/.{1,4}/g)
				last_four_digits = cardSelector[cardSelector.options.selectedIndex]
					.getAttribute("last_four_digits")
			} else {
				first_six_digits = response_card_token.first_six_digits.match(/.{1,4}/g)
				last_four_digits = response_card_token.last_four_digits
			}

			var card = first_six_digits[0] + " " +
				first_six_digits[1] + "** **** " + last_four_digits;

			return card;

		}

		MPv1.getAmount = function() {
			return document.querySelector(MPv1.selectors.amount).value;
		}

		MPv1.hideErrors = function() {

			for (var x = 0; x < document.querySelectorAll("[data-checkout]").length; x++) {
				var $field = document.querySelectorAll("[data-checkout]")[x];
				$field.classList.remove("mp-error-input");
			} // end for

			for (var x = 0; x < document.querySelectorAll(".mp-error").length; x++) {
				var $span = document.querySelectorAll(".mp-error")[x];
				$span.style.display = "none";
			}

			return;

		}

		// === Add events to guessing
		MPv1.addListenerEvent = function(el, eventName, handler) {
			if (el.addEventListener) {
				el.addEventListener(eventName, handler);
			} else {
				el.attachEvent("on" + eventName, function() {
					handler.call(el);
				});
			}
		};

		$('body').on('focusout', '#cardNumber', MPv1.guessingPaymentMethod);


		MPv1.addListenerEvent(
			document.querySelector(MPv1.selectors.cardNumber),
			"keyup", MPv1.clearOptions
		);

		MPv1.referer = (function() {
			var referer = window.location.protocol + "//" +
				window.location.hostname + (window.location.port ? ":" + window.location.port : "");
			return referer;
		})();

		MPv1.AJAX = function(options) {
			var useXDomain = !!window.XDomainRequest;
			var req = useXDomain ? new XDomainRequest() : new XMLHttpRequest()
			var data;
			options.url += (options.url.indexOf("?") >= 0 ? "&" : "?") + "referer=" + escape(MPv1.referer);
			options.requestedMethod = options.method;
			if (useXDomain && options.method == "PUT") {
				options.method = "POST";
				options.url += "&_method=PUT";
			}
			req.open(options.method, options.url, true);
			req.timeout = options.timeout || 1000;
			if (window.XDomainRequest) {
				req.onload = function() {
					data = JSON.parse(req.responseText);
					if (typeof options.success === "function") {
						options.success(options.requestedMethod === "POST" ? 201 : 200, data);
					}
				};
				req.onerror = req.ontimeout = function() {
					if (typeof options.error === "function") {
						options.error(400, {
							user_agent: window.navigator.userAgent,
							error: "bad_request",
							cause: []
						});
					}
				};
				req.onprogress = function() {};
			} else {
				req.setRequestHeader("Accept", "application/json");
				if (options.contentType) {
					req.setRequestHeader("Content-Type", options.contentType);
				} else {
					req.setRequestHeader("Content-Type", "application/json");
				}
				req.onreadystatechange = function() {
					if (this.readyState === 4) {
						try {
							if (this.status >= 200 && this.status < 400) {
								// Success!
								data = JSON.parse(this.responseText);
								if (typeof options.success === "function") {
									options.success(this.status, data);
								}
							} else if (this.status >= 400) {
								data = JSON.parse(this.responseText);
								if (typeof options.error === "function") {
									options.error(this.status, data);
								}
							} else if (typeof options.error === "function") {
								options.error(503, {});
							}
						} catch (e) {
							options.error(503, {});
						}
					}
				};
			}
			if (options.method === "GET" || options.data == null || options.data == undefined) {
				req.send();
			} else {
				req.send(JSON.stringify(options.data));
			}
		}

		// === Initialization function
		MPv1.Initialize = function(site_id, public_key, coupon_mode, discount_action_url, payer_email) {

			// Sets
			MPv1.site_id = site_id;
			MPv1.public_key = public_key;
			MPv1.coupon_of_discounts.default = coupon_mode;
			MPv1.coupon_of_discounts.discount_action_url = discount_action_url;
			MPv1.coupon_of_discounts.payer_email = payer_email;

			//hide errors
			MPv1.hideErrors();

			//promotion link
			if (MPv1.site_id != "MLA") {
				document.querySelector(MPv1.selectors.mpCheckoutLink).style.display = "none";
				document.querySelector(MPv1.selectors.mpPromotionLink).style.display = "none";
			}

			Mercadopago.setPublishableKey(MPv1.public_key);

			// flow coupon of discounts
			if (MPv1.coupon_of_discounts.default) {
				MPv1.addListenerEvent(
					document.querySelector(MPv1.selectors.applyCoupon),
					"click",
					MPv1.checkCouponEligibility
				);
				document.querySelector(MPv1.selectors.formCoupon).style.marginBottom = "20px";
			} else {
				document.querySelector(MPv1.selectors.formCoupon).style.display = "none";
			}

			// Flow: customer & cards.
			var selectorPmCustomerAndCards = document.querySelector(MPv1.selectors.pmCustomerAndCards);
			if (MPv1.customer_and_card.default && selectorPmCustomerAndCards.childElementCount > 0) {
				MPv1.addListenerEvent(
					document.querySelector(MPv1.selectors.paymentMethodSelector),
					"change", MPv1.cardsHandler
				);
				MPv1.cardsHandler();
			} else {
				// If customer & cards is disabled or customer does not have cards.
				MPv1.customer_and_card.status = false;
				document.querySelector(MPv1.selectors.formCustomerAndCard).style.display = "none";
			}

			// flow: MLM
			if (MPv1.site_id != "MLM") {
				Mercadopago.getIdentificationTypes();
			}

			if (MPv1.site_id == "MLM") {
				document.querySelector(MPv1.selectors.mpDoc).style.display = "none";
				document.querySelector(MPv1.selectors.formCustomerAndCard).removeAttribute("style");
				document.querySelector(MPv1.selectors.mpSecurityCodeCustomerAndCard).style.display = "none";

				// Removing not used fields for this country.
				MPv1.inputs_to_create_token.splice(
					MPv1.inputs_to_create_token.indexOf("docType"),
					1);
				MPv1.inputs_to_create_token.splice(
					MPv1.inputs_to_create_token.indexOf("docNumber"),
					1);

				MPv1.addListenerEvent(
					document.querySelector(MPv1.selectors.paymentMethodSelector),
					"change",
					MPv1.changePaymetMethodSelector
				);

				// Get payment methods and populate selector.
				MPv1.getPaymentMethods();

			}

			// flow: MLB AND MCO
			if (MPv1.site_id == "MLB") {
				document.querySelector(MPv1.selectors.mpIssuer).style.display = "none";
				document.getElementById('installments-div').classList.remove('mp-col-md-8');
				document.getElementById('installments-div').classList.add('mp-col-md-12');
			} else if (MPv1.site_id == "MCO") {
				document.querySelector(MPv1.selectors.mpIssuer).style.display = "none";
				document.getElementById('installments-div').classList.remove('mp-col-md-8');
				document.getElementById('installments-div').classList.add('mp-col-md-12');
			} else if (MPv1.site_id == "MLA") {
				document.querySelector(MPv1.selectors.mpIssuer).style.display = "block";
				document.querySelector(MPv1.selectors.taxCFT).style.display = "block";
				document.querySelector(MPv1.selectors.taxTEA).style.display = "block";
				MPv1.addListenerEvent(document.querySelector(MPv1.selectors.installments), "change", MPv1.showTaxes);
			} else if (MPv1.site_id == "MLC") {
				document.querySelector(MPv1.selectors.mpIssuer).style.display = "none";
				document.getElementById('installments-div').classList.remove('mp-col-md-8');
				document.getElementById('installments-div').classList.add('mp-col-md-12');
			}

			if (MPv1.debug) {
				document.querySelector(MPv1.selectors.utilities_fields).style.display = "inline-block";
			}

			document.querySelector(MPv1.selectors.site_id).value = MPv1.site_id;

			return;

		}

		this.MPv1 = MPv1;



		$('body').on('updated_checkout', function() {
			var field = $('body #cardNumber');

			if (0 < field.length) {
				field.focusout();
			}
		});

		// get action button submit
		$('form.checkout').on('checkout_place_order_woo-mercado-pago-custom', function() {
			if (mercado_pago) {
				mercado_pago = false;

				return true;
			}

			if (!document.getElementById('payment_method_woo-mercado-pago-custom').checked) {
				return true;
			}

			if (MPv1.validateInputsCreateToken()) {
				return MPv1.createToken();
			}

			return false;
		});

		$('form#order_review').submit(function() {
			if (mercado_pago) {
				mercado_pago = false;

				return true;
			}

			if (!document.getElementById('payment_method_woo-mercado-pago-custom').checked) {
				return true;
			}

			if (MPv1.validateInputsCreateToken()) {
				return MPv1.createToken();
			}

			return false;
		});

	}(jQuery));

	// Overriding this function to give form padding attribute.
	MPv1.setForm = function() {
		if (MPv1.customer_and_card.status) {
			document.querySelector(MPv1.selectors.form).style.display = "none";
			document.querySelector(MPv1.selectors.mpSecurityCodeCustomerAndCard).removeAttribute("style");
		} else {
			document.querySelector(MPv1.selectors.mpSecurityCodeCustomerAndCard).style.display = "none";
			document.querySelector(MPv1.selectors.form).removeAttribute("style");
			document.querySelector(MPv1.selectors.form).style.padding = "0px 12px 0px 12px";
		}
		Mercadopago.clearSession();
		document.querySelector(MPv1.selectors.CustomerAndCard).value = MPv1.customer_and_card.status;
	}

	MPv1.getAmount = function() {
		return document.querySelector(MPv1.selectors.amount).value - document.querySelector(MPv1.selectors.discount).value;
	}

	MPv1.getAmountWithoutDiscount = function() {
		return document.querySelector(MPv1.selectors.amount).value;
	}

	MPv1.showErrors = function(response) {
		var $form = MPv1.getForm();
		for (var x = 0; x < response.cause.length; x++) {
			var error = response.cause[x];

			if (error.code == 208 || error.code == 209 || error.code == 325 || error.code == 326) {
				var $span = $form.querySelector("#mp-error-208");
			} else {
				var $span = $form.querySelector("#mp-error-" + error.code);
			}

			if ($span != undefined) {
				var $input = $form.querySelector($span.getAttribute("data-main"));
				$span.style.display = "inline-block";
				$input.classList.add("mp-form-control-error");
			}
		}
		return;
	}

	MPv1.hideErrors = function() {

		for (var x = 0; x < document.querySelectorAll("[data-checkout]").length; x++) {
			var $field = document.querySelectorAll("[data-checkout]")[x];
			$field.classList.remove("mp-error-input");
			$field.classList.remove("mp-form-control-error");
		}

		for (var x = 0; x < document.querySelectorAll(".mp-error").length; x++) {
			var $span = document.querySelectorAll(".mp-error")[x];
			$span.style.display = "none";
		}

		return;

	}

	/*
	 *  END Customization
	 */

	MPv1.text.apply = "<?php echo __('Apply', 'woocommerce-mercadopago'); ?>";
	MPv1.text.remove = "<?php echo __('Remove', 'woocommerce-mercadopago'); ?>";
	MPv1.text.coupon_empty = "<?php echo __('Please, inform your coupon code', 'woocommerce-mercadopago'); ?>";
	MPv1.text.choose = "<?php echo __('To choose', 'woocommerce-mercadopago'); ?>";
	MPv1.text.other_bank = "<?php echo __('Other bank', 'woocommerce-mercadopago'); ?>";
	MPv1.text.discount_info1 = "<?php echo __('You will save', 'woocommerce-mercadopago'); ?>";
	MPv1.text.discount_info2 = "<?php echo __('with discount of', 'woocommerce-mercadopago'); ?>";
	MPv1.text.discount_info3 = "<?php echo __('Total of your purchase:', 'woocommerce-mercadopago'); ?>";
	MPv1.text.discount_info4 = "<?php echo __('Total of your purchase with discount:', 'woocommerce-mercadopago'); ?>";
	MPv1.text.discount_info5 = "<?php echo __('*After payment approval', 'woocommerce-mercadopago'); ?>";
	MPv1.text.discount_info6 = "<?php echo __('Terms and conditions of use', 'woocommerce-mercadopago'); ?>";

	MPv1.paths.loading = "<?php echo ($images_path . 'loading.gif'); ?>";
	MPv1.paths.check = "<?php echo ($images_path . 'check.png'); ?>";
	MPv1.paths.error = "<?php echo ($images_path . 'error.png'); ?>";

	MPv1.Initialize(
		"<?php echo $site_id; ?>",
		"<?php echo $public_key; ?>",
		"<?php echo $coupon_mode; ?>" == "yes",
		"<?php echo $discount_action_url; ?>",
		"<?php echo $payer_email; ?>"
	);
</script>