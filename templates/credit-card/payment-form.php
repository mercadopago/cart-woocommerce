<?php

/**
 * Part of Woo Mercado Pago Module
 * Author - Mercado Pago
 * Developer - Marcelo Tomio Hama / marcelo.hama@mercadolivre.com
 * Copyright - Copyright(c) MercadoPago [https://www.mercadopago.com]
 * License - https://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<?php
$theme = wp_get_theme(); // gets the current theme
if ( 'Twenty Seventeen' == $theme->name || 'Twenty Seventeen' == $theme->parent_theme ) {
	echo '<div class="mp-line twenty-seventeen-cc-header" >';
} else {
	echo '<div class="mp-line other-themes-cc-header" >';
}
?>
	<?php if ( ! empty( $banner_path ) ) : ?>
		<img class="mp-creditcard-banner" src="<?php echo $banner_path;?>" width="312" height="40"/>
	<?php endif; ?>
</div>

<fieldset id="custom_checkout_fieldset" style="margin:-1px; background:white; display: none;">

	<div class="mp-box-inputs mp-line" id="mercadopago-form-coupon" style="padding: 0px 12px 0px 12px;">
		<label for="couponCodeLabel">
			<?php echo esc_html__( 'Discount Coupon', 'woocommerce-mercadopago' ); ?>
		</label>
		<div class="mp-box-inputs mp-col-55">
			<input type="text" id="couponCode" name="mercadopago_custom[coupon_code]"
			autocomplete="off" maxlength="24" style="background: #fff; padding: 12px; border: 1px solid #cecece;"/>
		</div>
		<div class="mp-box-inputs mp-col-10">
			<div id="mp-separete-date"></div>
		</div>
		<div class="mp-box-inputs mp-col-35">
			<input type="button" class="button" id="applyCoupon"
			value="<?php echo esc_html__( 'Apply', 'woocommerce-mercadopago' ); ?>">
		</div>
		<div class="mp-box-inputs mp-col-65 mp-box-message" style="margin-top:2px;">
			<span class="mp-discount" id="mpCouponApplyed" ></span>
			<span class="mp-error" id="mpCouponError" ></span>
		</div>
	</div>

	<!-- payment method -->
	<div id="mercadopago-form-customer-and-card" style="padding:0px 12px 0px 12px;">
		<div class="mp-box-inputs mp-line">
			<label for="paymentMethodIdSelector">
				<?php echo esc_html__( 'Payment Method', 'woocommerce-mercadopago' ); ?> <em>*</em>
			</label>
			<select id="paymentMethodSelector" name="mercadopago_custom[paymentMethodSelector]"
			data-checkout="cardId">
				<optgroup label=<?php echo esc_html__( 'Your Card', 'woocommerce-mercadopago' ); ?>
				id="payment-methods-for-customer-and-cards">
				<?php foreach ($customer_cards as $card) : ?>
					<option value=<?php echo $card['id']; ?>
					first_six_digits=<?php echo $card['first_six_digits']; ?>
					last_four_digits=<?php echo $card['last_four_digits']; ?>
					security_code_length=<?php echo $card['security_code']['length']; ?>
					type_checkout='customer_and_card'
					payment_method_id=<?php echo $card['payment_method']['id']; ?>>
						<?php echo ucfirst( $card['payment_method']['name'] ); ?>
						<?php echo esc_html__( 'ended in', 'woocommerce-mercadopago' ); ?>
						<?php echo $card['last_four_digits']; ?>
					</option>
				<?php endforeach; ?>
				</optgroup>
				<optgroup label="<?php echo esc_html__( 'Other Cards', 'woocommerce-mercadopago' ); ?>"
				id="payment-methods-list-other-cards">
					<option value="-1"><?php echo esc_html__( 'Other Card', 'woocommerce-mercadopago' ); ?></option>
				</optgroup>
			</select>
		</div>
		<div class="mp-box-inputs mp-line" id="mp-securityCode-customer-and-card">
			<div class="mp-box-inputs mp-col-45">
				<label for="customer-and-card-securityCode">
					<?php echo esc_html__( 'CVC', 'woocommerce-mercadopago' ); ?> <em>*</em>
				</label>
				<input type="text" id="customer-and-card-securityCode" data-checkout="securityCode"
				autocomplete="off" maxlength="4" style="padding: 8px; border: 1px solid #cecece;
				background: url( <?php echo ( $images_path . 'cvv.png' ); ?> ) 94% 50% no-repeat;"/>
				<span class="mp-error" id="mp-error-224" data-main="#customer-and-card-securityCode">
					<?php echo esc_html__( 'Parameter securityCode can not be null/empty', 'woocommerce-mercadopago' ); ?>
				</span>
				<span class="mp-error" id="mp-error-E302" data-main="#customer-and-card-securityCode">
					<?php echo esc_html__( 'Invalid Security Code', 'woocommerce-mercadopago' ); ?>
				</span>
				<span class="mp-error" id="mp-error-E203" data-main="#customer-and-card-securityCode">
					<?php echo esc_html__( 'Invalid Security Code', 'woocommerce-mercadopago' ); ?>
				</span>
			</div>
		</div>
	</div> <!--  end mercadopago-form-osc -->

	<div id="mercadopago-form" style="padding:0px 12px 0px 12px;">
		<!-- Card Number -->
		<div class="mp-box-inputs mp-col-100">
			<label for="cardNumber">
				<?php echo esc_html__( 'Credit card number', 'woocommerce-mercadopago' ); ?> <em>*</em>
			</label>
			<input type="text" id="cardNumber" data-checkout="cardNumber" autocomplete="off"
			maxlength="19" style="background: #fff; padding: 8px; border: 1px solid #cecece;"/>
			<span class="mp-error" id="mp-error-205" data-main="#cardNumber">
				<?php echo esc_html__( 'Parameter cardNumber can not be null/empty', 'woocommerce-mercadopago' ); ?>
			</span>
			<span class="mp-error" id="mp-error-E301" data-main="#cardNumber">
				<?php echo esc_html__( 'Invalid Card Number', 'woocommerce-mercadopago' ); ?>
			</span>
		</div>
		<!-- Expiry Date -->
		<div class="mp-box-inputs mp-line">
			<div class="mp-box-inputs mp-col-45">
				<label for="cardExpirationMonth">
					<?php echo esc_html__( 'Expiration month', 'woocommerce-mercadopago' ); ?> <em>*</em>
				</label>
				<select id="cardExpirationMonth" data-checkout="cardExpirationMonth"
				name="mercadopago_custom[cardExpirationMonth]">
					<option value="-1"> <?php echo esc_html__( 'Month', 'woocommerce-mercadopago' ); ?> </option>
					<?php for ($x=1; $x<=12; $x++) : ?>
						<option value="<?php echo $x; ?>"> <?php echo $x; ?></option>
					<?php endfor; ?>
				</select>
			</div>
			<div class="mp-box-inputs mp-col-10">
				<div id="mp-separete-date"> </div>
			</div>
			<div class="mp-box-inputs mp-col-45">
				<label for="cardExpirationYear">
					<?php echo esc_html__( 'Expiration year', 'woocommerce-mercadopago' ); ?> <em>*</em>
				</label>
				<select id="cardExpirationYear" data-checkout="cardExpirationYear"
					name="mercadopago_custom[cardExpirationYear]">
					<option value="-1"> <?php echo esc_html__( 'Year', 'woocommerce-mercadopago' ); ?> </option>
					<?php for ( $x=date("Y"); $x<= date("Y") + 10; $x++ ) : ?>
						<option value="<?php echo $x; ?>"> <?php echo $x; ?> </option>
					<?php endfor; ?>
				</select>
			</div>
			<span class="mp-error" id="mp-error-208" data-main="#cardExpirationMonth">
				<?php echo esc_html__( 'Invalid Expiration Date', 'woocommerce-mercadopago' ); ?>
			</span>
			<span class="mp-error" id="mp-error-209" data-main="#cardExpirationYear"> </span>
			<span class="mp-error" id="mp-error-325" data-main="#cardExpirationMonth">
				<?php echo esc_html__( 'Invalid Expiration Date', 'woocommerce-mercadopago' ); ?>
			</span>
			<span class="mp-error" id="mp-error-326" data-main="#cardExpirationYear"> </span>
		</div>
		<!-- Card Holder Name -->
		<div class="mp-box-inputs mp-col-100">
			<label for="cardholderName">
				<?php echo esc_html__( 'Card holder name', 'woocommerce-mercadopago' ); ?> <em>*</em>
			</label>
			<input type="text" id="cardholderName" name="mercadopago_custom[cardholderName]"
			data-checkout="cardholderName" autocomplete="off" style="background-color: #fff; border: 1px solid #cecece;" />
			<span class="mp-error" id="mp-error-221" data-main="#cardholderName">
				<?php echo esc_html__( 'Parameter cardholderName can not be null/empty', 'woocommerce-mercadopago' ); ?>
			</span>
			<span class="mp-error" id="mp-error-316" data-main="#cardholderName">
				<?php echo esc_html__( 'Invalid Card Holder Name', 'woocommerce-mercadopago' ); ?>
			</span>
		</div>
		<!-- CVV -->
		<div class="mp-box-inputs mp-line">
			<div class="mp-box-inputs mp-col-45">
				<label for="securityCode">
					<?php echo esc_html__( 'CVC', 'woocommerce-mercadopago' ); ?> <em>*</em>
				</label>
				<input type="text" id="securityCode" data-checkout="securityCode"
				autocomplete="off" maxlength="4" style="padding: 12px; border: 1px solid #cecece;
				background: url(<?php echo ($images_path . 'cvv.png'); ?>) 94% 50% no-repeat;" />
				<span class="mp-error" id="mp-error-224" data-main="#securityCode">
					<?php echo esc_html__( 'Parameter securityCode can not be null/empty', 'woocommerce-mercadopago' ); ?>
				</span>
				<span class="mp-error" id="mp-error-E302" data-main="#securityCode">
					<?php echo esc_html__( 'Invalid Security Code', 'woocommerce-mercadopago' ); ?>
				</span>
			</div>
			<div class="mp-box-inputs mp-col-10">
				<div id="mp-separete-date"> </div>
			</div>
			<!-- Issuer -->
			<div class="mp-box-inputs mp-col-45 mp-issuer">
				<label for="issuer">
					<?php echo esc_html__( 'Issuer', 'woocommerce-mercadopago' ); ?> <em>*</em>
				</label>
				<select id="issuer" data-checkout="issuer" name="mercadopago_custom[issuer]"></select>
				<span class="mp-error" id="mp-error-220" data-main="#issuer">
					<?php echo esc_html__( 'Parameter cardIssuerId can not be null/empty', 'woocommerce-mercadopago' ); ?>
				</span>
			</div>
			<?php if ($site_id == 'MLB') : ?>
				<div class="mp-box-inputs mp-col-45 mp-docNumber">
					<label for="docNumber">
						<?php echo esc_html__( 'Document number', 'woocommerce-mercadopago' ); ?> <em>*</em>
					</label>
					<input type="text" id="docNumber" data-checkout="docNumber"
					name="mercadopago_custom[docNumber]" autocomplete="off"
					style="background-color: #fff; border: 1px solid #cecece;" />
					<span class="mp-error" id="mp-error-214" data-main="#docNumber">
						<?php echo esc_html__( 'Parameter docNumber can not be null/empty', 'woocommerce-mercadopago' ); ?>
					</span>
					<span class="mp-error" id="mp-error-324" data-main="#docNumber">
						<?php echo esc_html__( 'Invalid Document Number', 'woocommerce-mercadopago' ); ?>
					</span>
				</div>
			<?php endif; ?>
		</div>
		<!-- Document Type -->
		<div id="mp-doc-div" class="mp-box-inputs mp-col-100 mp-doc">
			<?php if ($site_id != 'MLB') : ?>
				<div class="mp-box-inputs mp-col-45 mp-docNumber">
					<label for="docNumber">
						<?php echo esc_html__( 'Document number', 'woocommerce-mercadopago' ); ?> <em>*</em>
					</label>
					<input type="text" id="docNumber" data-checkout="docNumber"
					name="mercadopago_custom[docNumber]" autocomplete="off"
					style="background-color: #fff; border: 1px solid #cecece;" />
					<span class="mp-error" id="mp-error-214" data-main="#docNumber">
						<?php echo esc_html__( 'Parameter docNumber can not be null/empty', 'woocommerce-mercadopago' ); ?>
					</span>
					<span class="mp-error" id="mp-error-324" data-main="#docNumber">
						<?php echo esc_html__( 'Invalid Document Number', 'woocommerce-mercadopago' ); ?>
					</span>
				</div>
			<?php endif; ?>
			<div class="mp-box-inputs mp-col-10">
				<div id="mp-separete-date"> </div>
			</div>
			<div class="mp-box-inputs mp-col-45 mp-docType">
				<label for="docType">
					<?php echo esc_html__( 'Document Type', 'woocommerce-mercadopago' ); ?> <em>*</em>
				</label>
				<select id="docType" data-checkout="docType"
				name="mercadopago_custom[docType]"></select>
				<span class="mp-error" id="mp-error-212" data-main="#docType">
					<?php echo esc_html__( 'Parameter docType can not be null/empty', 'woocommerce-mercadopago' ); ?>
				</span>
				<span class="mp-error" id="mp-error-322" data-main="#docType">
					<?php echo esc_html__( 'Invalid Document Type', 'woocommerce-mercadopago' ); ?>
				</span>
			</div>
		</div>
	</div> <!-- end #mercadopago-form -->

	<div id="mp-box-installments" class="mp-box-inputs mp-line">
		<div class="form-row" >
			<div id="mp-box-installments-selector" class="form-col-12" style="padding: 0px 12px 0px 12px;">
				<label for="installments">
					<span class="mensagem-credit-card">
						<?php if ( $currency_ratio != 1 ) : ?>
							<div class="tooltip">
								<?php echo esc_html__( 'Installments', 'woocommerce-mercadopago' ); ?>
								<span class="tooltiptext">
									<?php echo esc_html__( 'Payment converted from', 'woocommerce-mercadopago' ) . " " .
										$woocommerce_currency . " " . esc_html__( 'to', 'woocommerce-mercadopago' ) . " " .
										$account_currency; ?>
								</span>
							</div>
						<?php else :
							echo esc_html__( 'Installments', 'woocommerce-mercadopago' );
						endif; ?>
						<em class="obrigatorio">* </em>
					</span>
				</label>
				<select id="installments" data-checkout="installments" class="form-control-mine"
					name="mercadopago_custom[installments]" style="width: 100%;"></select>
			</div>
		</div>
		<div id="mp-box-input-tax-cft" class="form-col-12" style="padding: 0px 12px 0px 12px;">
			<div id="mp-box-input-tax-tea"><div id="mp-tax-tea-text"></div></div>
			<div id="mp-tax-cft-text"></div>
		</div>
	</div>
	
	<div style="padding:0px 12px 0px 12px;">
		<label for="saveCard" class="show_if_simple tips" style="display: inline;">
			<input type="checkbox" name="mercadopago_custom[doNotSaveCard]" id="doNotSaveCard" value="yes">
			<?php echo esc_html__( 'Do not save my card', 'woocommerce-mercadopago' ); ?>
		</label>
	</div>

	<div class="mp-box-inputs mp-line" style="padding:0px 12px 0px 12px;">
		<!-- NOT DELETE LOADING-->
		<div class="mp-box-inputs mp-col-25">
			<div id="mp-box-loading"></div>
		</div>
	</div>

	<div id="mercadopago-utilities" >
		<input type="hidden" id="site_id" name="mercadopago_custom[site_id]"/>
		<input type="hidden" id="amount" value='<?php echo $amount; ?>' name="mercadopago_custom[amount]"/>
		<input type="hidden" id="currency_ratio" value='<?php echo $currency_ratio; ?>' name="mercadopago_custom[currency_ratio]"/>
		<input type="hidden" id="campaign_id" name="mercadopago_custom[campaign_id]"/>
		<input type="hidden" id="campaign" name="mercadopago_custom[campaign]"/>
		<input type="hidden" id="discount" name="mercadopago_custom[discount]"/>
		<input type="hidden" id="paymentMethodId" name="mercadopago_custom[paymentMethodId]"/>
		<input type="hidden" id="token" name="mercadopago_custom[token]"/>
		<input type="hidden" id="cardTruncated" name="mercadopago_custom[cardTruncated]"/>
		<input type="hidden" id="CustomerAndCard" name="mercadopago_custom[CustomerAndCard]"/>
		<input type="hidden" id="CustomerId" value='<?php echo $customerId; ?>' name="mercadopago_custom[CustomerId]"/>
	</div>

</fieldset>

<script type="text/javascript">
	( function() {

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
			create_token_on: {
				event: true, //if true create token on event, if false create on click and ignore others
				keyup: false,
				paste: true
			},
			inputs_to_create_discount: [
				"couponCode",
				"applyCoupon"
			],
			inputs_to_create_token: [
				"cardNumber",
				"cardExpirationMonth",
				"cardExpirationYear",
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

		MPv1.currencyIdToCurrency = function ( currency_id ) {
			if ( currency_id == "ARS" ) {
				return "$";
			} else if ( currency_id == "BRL" ) {
				return "R$";
			} else if ( currency_id == "COP" ) {
				return "$";
			} else if ( currency_id == "CLP" ) {
				return "$";
			} else if ( currency_id == "MXN" ) {
				return "$";
			} else if ( currency_id == "VEF" ) {
				return "Bs";
			} else if ( currency_id == "PEN" ) {
				return "S/";
			} else if ( currency_id == "UYU" ) {
				return "$U";
			} else {
				return "$";
			}
		}

		MPv1.checkCouponEligibility = function () {
			if ( document.querySelector( MPv1.selectors.couponCode).value == "" ) {
				// Coupon code is empty.
				document.querySelector( MPv1.selectors.mpCouponApplyed ).style.display = "none";
				document.querySelector( MPv1.selectors.mpCouponError ).style.display = "block";
				document.querySelector( MPv1.selectors.mpCouponError ).innerHTML = MPv1.text.coupon_empty;
				MPv1.coupon_of_discounts.status = false;
				document.querySelector( MPv1.selectors.couponCode ).style.background = null;
				document.querySelector( MPv1.selectors.applyCoupon ).value = MPv1.text.apply;
				document.querySelector( MPv1.selectors.discount ).value = 0;
				MPv1.cardsHandler();
			} else if ( MPv1.coupon_of_discounts.status ) {
				// We already have a coupon set, so we remove it.
				document.querySelector( MPv1.selectors.mpCouponApplyed ).style.display = "none";
				document.querySelector( MPv1.selectors.mpCouponError ).style.display = "none";
				MPv1.coupon_of_discounts.status = false;
				document.querySelector( MPv1.selectors.applyCoupon ).style.background = null;
				document.querySelector( MPv1.selectors.applyCoupon ).value = MPv1.text.apply;
				document.querySelector( MPv1.selectors.couponCode ).value = "";
				document.querySelector( MPv1.selectors.couponCode ).style.background = null;
				document.querySelector( MPv1.selectors.discount ).value = 0;
				MPv1.cardsHandler();
			} else {
				// Set loading.
				document.querySelector( MPv1.selectors.mpCouponApplyed ).style.display = "none";
				document.querySelector( MPv1.selectors.mpCouponError ).style.display = "none";
				document.querySelector( MPv1.selectors.couponCode ).style.background = "url(" + MPv1.paths.loading + ") 98% 50% no-repeat #fff";
				document.querySelector( MPv1.selectors.couponCode ).style.border = "1px solid #cecece";
				document.querySelector( MPv1.selectors.applyCoupon ).disabled = true;

				// Check if there are params in the url.
				var url = MPv1.coupon_of_discounts.discount_action_url;
				var sp = "?";
				if ( url.indexOf( "?" ) >= 0 ) {
					sp = "&";
				}
				url += sp + "site_id=" + MPv1.site_id;
				url += "&coupon_id=" + document.querySelector( MPv1.selectors.couponCode ).value;
				url += "&amount=" + document.querySelector( MPv1.selectors.amount ).value;
				url += "&payer=" + MPv1.coupon_of_discounts.payer_email;
				//url += "&payer=" + document.getElementById( "billing_email" ).value;

				MPv1.AJAX({
					url: url,
					method : "GET",
					timeout : 5000,
					error: function() {
						// Request failed.
						document.querySelector( MPv1.selectors.mpCouponApplyed ).style.display = "none";
						document.querySelector( MPv1.selectors.mpCouponError ).style.display = "none";
						MPv1.coupon_of_discounts.status = false;
						document.querySelector( MPv1.selectors.applyCoupon ).style.background = null;
						document.querySelector( MPv1.selectors.applyCoupon ).value = MPv1.text.apply;
						document.querySelector( MPv1.selectors.couponCode ).value = "";
						document.querySelector( MPv1.selectors.couponCode ).style.background = null;
						document.querySelector( MPv1.selectors.discount ).value = 0;
						MPv1.cardsHandler();
					},
					success : function ( status, response ) {
						if ( response.status == 200 ) {
							document.querySelector( MPv1.selectors.mpCouponApplyed ).style.display =
								"block";
							document.querySelector( MPv1.selectors.discount ).value =
								response.response.coupon_amount;
							document.querySelector( MPv1.selectors.mpCouponApplyed ).innerHTML =
								//"<div style='border-style: solid; border-width:thin; " +
								//"border-color: #009EE3; padding: 8px 8px 8px 8px; margin-top: 4px;'>" +
								MPv1.text.discount_info1 + " <strong>" +
								MPv1.currencyIdToCurrency( response.response.currency_id ) + " " +
								Math.round( response.response.coupon_amount * 100 ) / 100 +
								"</strong> " + MPv1.text.discount_info2 + " " +
								response.response.name + ".<br>" + MPv1.text.discount_info3 + " <strong>" +
								MPv1.currencyIdToCurrency( response.response.currency_id ) + " " +
								Math.round( MPv1.getAmountWithoutDiscount() * 100 ) / 100 +
								"</strong><br>" + MPv1.text.discount_info4 + " <strong>" +
								MPv1.currencyIdToCurrency( response.response.currency_id ) + " " +
								Math.round( MPv1.getAmount() * 100 ) / 100 + "*</strong><br>" +
								"<i>" + MPv1.text.discount_info5 + "</i><br>" +
								"<a href='https://api.mercadolibre.com/campaigns/" +
								response.response.id +
								"/terms_and_conditions?format_type=html' target='_blank'>" +
								MPv1.text.discount_info6 + "</a>";
								document.querySelector( MPv1.selectors.mpCouponError ).style.display = "none";
							MPv1.coupon_of_discounts.status = true;
							document.querySelector( MPv1.selectors.couponCode ).style.background =
								null;
							document.querySelector( MPv1.selectors.couponCode ).style.background =
								"url(" + MPv1.paths.check + ") 94% 50% no-repeat #fff";
							document.querySelector( MPv1.selectors.couponCode ).style.border = "1px solid #cecece";
							document.querySelector( MPv1.selectors.applyCoupon ).value =
								MPv1.text.remove;
							MPv1.cardsHandler();
							document.querySelector( MPv1.selectors.campaign_id ).value =
								response.response.id;
							document.querySelector( MPv1.selectors.campaign ).value =
								response.response.name;
						} else {
							document.querySelector( MPv1.selectors.mpCouponApplyed ).style.display = "none";
							document.querySelector( MPv1.selectors.mpCouponError ).style.display = "block";
							document.querySelector( MPv1.selectors.mpCouponError ).innerHTML = response.response.message;
							MPv1.coupon_of_discounts.status = false;
							document.querySelector( MPv1.selectors.couponCode ).style.background = null;
							document.querySelector( MPv1.selectors.couponCode ).style.background = "url(" + MPv1.paths.error + ") 94% 50% no-repeat #fff";
							document.querySelector( MPv1.selectors.applyCoupon ).value = MPv1.text.apply;
							document.querySelector( MPv1.selectors.discount ).value = 0;
							MPv1.cardsHandler();
						}
						document.querySelector( MPv1.selectors.applyCoupon ).disabled = false;
					}
				});
			}
		}

		MPv1.getBin = function() {

			var cardSelector = document.querySelector( MPv1.selectors.paymentMethodSelector );

			if (cardSelector && cardSelector[cardSelector.options.selectedIndex].value != "-1") {
				return cardSelector[cardSelector.options.selectedIndex]
					.getAttribute( "first_six_digits" );
			}

			var ccNumber = document.querySelector( MPv1.selectors.cardNumber );
				return ccNumber.value.replace( /[ .-]/g, "" ).slice( 0, 6 );

		}

	  	MPv1.clearOptions = function() {

			var bin = MPv1.getBin();

	     	if ( bin.length == 0 ) {

				MPv1.hideIssuer();

				var selectorInstallments = document.querySelector( MPv1.selectors.installments ),
					fragment = document.createDocumentFragment(),
					option = new Option( MPv1.text.choose + "...", "-1" );

					selectorInstallments.options.length = 0;
					fragment.appendChild( option );
					selectorInstallments.appendChild( fragment );
					selectorInstallments.setAttribute( "disabled", "disabled" );

			}

		}

		MPv1.guessingPaymentMethod = function( event ) {

			var bin = MPv1.getBin();
			var amount = MPv1.getAmount();

			if ( event.type == "keyup" ) {
				if ( bin != null && bin.length == 6 ) {
					Mercadopago.getPaymentMethod( {
						"bin": bin
					}, MPv1.setPaymentMethodInfo );
				}
			} else {
				setTimeout( function() {
					if ( bin.length >= 6 ) {
						Mercadopago.getPaymentMethod( {
							"bin": bin
						}, MPv1.setPaymentMethodInfo );
					}
				}, 100 );
			}

		};

		MPv1.setPaymentMethodInfo = function( status, response ) {

			if ( status == 200 ) {

				if ( MPv1.site_id != "MLM" ) {
					// Guessing...
					document.querySelector( MPv1.selectors.paymentMethodId ).value = response[0].id;
					if ( MPv1.customer_and_card.status ) {
						document.querySelector( MPv1.selectors.paymentMethodSelector )
						.style.background = "url(" + response[0].secure_thumbnail + ") 90% 50% no-repeat #fff";
					} else {
						document.querySelector( MPv1.selectors.cardNumber ).style.background = "url(" +
						response[0].secure_thumbnail + ") 94% 50% no-repeat #fff";
					}
					document.querySelector( MPv1.selectors.cardNumber ).style.border = "1px solid #cecece";
				}

				// Check if the security code (ex: Tarshop) is required.
				var cardConfiguration = response[0].settings;
				var bin = MPv1.getBin();
				var amount = MPv1.getAmount();

				Mercadopago.getInstallments(
					{ "bin": bin, "amount": amount },
					MPv1.setInstallmentInfo
				);

				// Check if the issuer is necessary to pay.
				var issuerMandatory = false, additionalInfo = response[0].additional_info_needed;

				for ( var i=0; i<additionalInfo.length; i++ ) {
					if ( additionalInfo[i] == "issuer_id" ) {
						issuerMandatory = true;
					}
				};

				if ( issuerMandatory && MPv1.site_id != "MLM" ) {
					var payment_method_id = response[0].id;
					MPv1.getIssuersPaymentMethod( payment_method_id );
				} else {
					MPv1.hideIssuer();
				}

			}

		}

		MPv1.changePaymetMethodSelector = function() {
			var payment_method_id =
	     		document.querySelector( MPv1.selectors.paymentMethodSelector ).value;
				MPv1.getIssuersPaymentMethod( payment_method_id );
		}

		// === Issuers

		MPv1.getIssuersPaymentMethod = function( payment_method_id ) {

			var amount = MPv1.getAmount();

			// flow: MLM mercadopagocard
			if ( payment_method_id == "mercadopagocard" ) {
	        	Mercadopago.getInstallments(
	        		{ "payment_method_id": payment_method_id, "amount": amount },
	        		MPv1.setInstallmentInfo
	        	);
			}

			Mercadopago.getIssuers( payment_method_id, MPv1.showCardIssuers );
			MPv1.addListenerEvent(
	        	document.querySelector( MPv1.selectors.issuer ),
	        	"change",
				MPv1.setInstallmentsByIssuerId
			);

		}

		MPv1.showCardIssuers = function( status, issuers ) {

			// If the API does not return any bank.
			if ( issuers.length > 0 ) {
				var issuersSelector = document.querySelector( MPv1.selectors.issuer );
				var fragment = document.createDocumentFragment();

				issuersSelector.options.length = 0;
				var option = new Option( MPv1.text.choose + "...", "-1" );
				fragment.appendChild( option );

				for ( var i=0; i<issuers.length; i++ ) {
					if ( issuers[i].name != "default" ) {
						option = new Option( issuers[i].name, issuers[i].id );
					} else {
						option = new Option( "Otro", issuers[i].id );
					}
					fragment.appendChild( option );
				}

				issuersSelector.appendChild( fragment );
				issuersSelector.removeAttribute( "disabled" );
			} else {
				MPv1.hideIssuer();
			}

		}

		MPv1.setInstallmentsByIssuerId = function( status, response ) {

			var issuerId = document.querySelector( MPv1.selectors.issuer ).value;
			var amount = MPv1.getAmount();

			if ( issuerId === "-1" ) {
	        	return;
			}

			var params_installments = {
				"bin": MPv1.getBin(),
				"amount": amount,
				"issuer_id": issuerId
			}

			if ( MPv1.site_id == "MLM" ) {
	        	params_installments = {
					"payment_method_id": document.querySelector(
						MPv1.selectors.paymentMethodSelector
					).value,
					"amount": amount,
					"issuer_id": issuerId
				}
			}
			Mercadopago.getInstallments( params_installments, MPv1.setInstallmentInfo );

		}

		MPv1.hideIssuer = function() {
			var $issuer = document.querySelector( MPv1.selectors.issuer );
			var opt = document.createElement( "option" );
			opt.value = "-1";
			opt.innerHTML = MPv1.text.other_bank;
			opt.style = "font-size: 12px;";

			$issuer.innerHTML = "";
			$issuer.appendChild( opt );
			$issuer.setAttribute( "disabled", "disabled" );
		}

		// === Installments

		MPv1.setInstallmentInfo = function( status, response ) {

			var selectorInstallments = document.querySelector( MPv1.selectors.installments );

			if ( response.length > 0 ) {

				var html_option = "<option value='-1'>" + MPv1.text.choose + "...</option>";
				payerCosts = response[0].payer_costs;

				// fragment.appendChild(option);
				for ( var i=0; i<payerCosts.length; i++) {
					// Resolution 51/2017
					var dataInput = "";
					if ( MPv1.site_id == "MLA" ) {
						var tax = payerCosts[i].labels;
						if ( tax.length > 0 ) {
							for ( var l=0; l<tax.length; l++ ) {
								if ( tax[l].indexOf( "CFT_" ) !== -1 ) {
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
				if ( selectorInstallments.innerHTML != html_option ) {
					selectorInstallments.innerHTML = html_option;
				}

				selectorInstallments.removeAttribute( "disabled" );
				MPv1.showTaxes();

			}

		}

		MPv1.showTaxes = function() {
			var selectorIsntallments = document.querySelector( MPv1.selectors.installments );
			var tax = selectorIsntallments.options[selectorIsntallments.selectedIndex].getAttribute( "data-tax" );
			var cft = "";
			var tea = "";
			if ( tax != null ) {
				var tax_split = tax.split( "|" );
				cft = tax_split[0].replace( "_", " ");
				tea = tax_split[1].replace( "_", " ");
				if ( cft == "CFT 0,00%" && tea == "TEA 0,00%" ) {
					cft = "";
					tea = "";
				}
			}
			document.querySelector( MPv1.selectors.taxTextCFT ).innerHTML = cft;
			document.querySelector( MPv1.selectors.taxTextTEA ).innerHTML = tea;
		}

		// === Customer & Cards

		MPv1.cardsHandler = function() {

			var cardSelector = document.querySelector( MPv1.selectors.paymentMethodSelector );
			var type_checkout =
				cardSelector[cardSelector.options.selectedIndex].getAttribute( "type_checkout" );
			var amount = MPv1.getAmount();

	     	if ( MPv1.customer_and_card.default ) {

	            if ( cardSelector &&
	        	cardSelector[cardSelector.options.selectedIndex].value != "-1" &&
	        	type_checkout == "customer_and_card" ) {

					document.querySelector( MPv1.selectors.paymentMethodId )
					.value = cardSelector[cardSelector.options.selectedIndex]
					.getAttribute( "payment_method_id" );

					MPv1.clearOptions();

					MPv1.customer_and_card.status = true;

					var _bin = cardSelector[cardSelector.options.selectedIndex]
					.getAttribute( "first_six_digits" );

					Mercadopago.getPaymentMethod(
						{ "bin": _bin },
						MPv1.setPaymentMethodInfo
					);

				} else {

					document.querySelector( MPv1.selectors.paymentMethodId )
					.value = cardSelector.value != -1 ? cardSelector.value : "";
					MPv1.customer_and_card.status = false;
					MPv1.resetBackgroundCard();
					MPv1.guessingPaymentMethod(
						{ type: "keyup" }
					);

				}

				MPv1.setForm();

			}

		}

		// === Payment Methods

		MPv1.getPaymentMethods = function() {

			var fragment = document.createDocumentFragment();
			var paymentMethodsSelector =
				document.querySelector( MPv1.selectors.paymentMethodSelector )
			var mainPaymentMethodSelector =
				document.querySelector( MPv1.selectors.paymentMethodSelector )

			// Set loading.
			mainPaymentMethodSelector.style.background =
			"url(" + MPv1.paths.loading + ") 95% 50% no-repeat #fff";
			mainPaymentMethodSelector.style.border = "1px solid #cecece";

			// If customer and card.
			if ( MPv1.customer_and_card.status ) {
				paymentMethodsSelector = document.querySelector( MPv1.selectors.pmListOtherCards )
	            // Clean payment methods.
				paymentMethodsSelector.innerHTML = "";
			} else {
				paymentMethodsSelector.innerHTML = "";
				option = new Option( MPv1.text.choose + "...", "-1" );
				fragment.appendChild( option );
			}

			Mercadopago.getAllPaymentMethods( function( code, payment_methods ) {

				for ( var x=0; x < payment_methods.length; x++ ) {

					var pm = payment_methods[x];

					if ( ( pm.payment_type_id == "credit_card" || pm.payment_type_id == "debit_card" ||
					pm.payment_type_id == "prepaid_card" ) && pm.status == "active" ) {

						option = new Option( pm.name, pm.id );
						option.setAttribute( "type_checkout", "custom" );
						fragment.appendChild( option );

					} // end if

				} // end for

				paymentMethodsSelector.appendChild( fragment );
				mainPaymentMethodSelector.style.background = "#fff";

			} );

		}

		// === Functions related to Create Tokens

		MPv1.createTokenByEvent = function() {

			var $inputs = MPv1.getForm().querySelectorAll( "[data-checkout]" );
			var $inputs_to_create_token = MPv1.getInputsToCreateToken();

			for (var x=0; x<$inputs.length; x++) {

				var element = $inputs[x];

				// Add events only in the required fields.
				if ( $inputs_to_create_token
				.indexOf( element.getAttribute( "data-checkout" ) ) > -1 ) {

					var event = "focusout";

					if ( element.nodeName == "SELECT" ) {
						event = "change";
					}

					MPv1.addListenerEvent( element, event, MPv1.validateInputsCreateToken );

					// For firefox.
					MPv1.addListenerEvent( element, "blur", MPv1.validateInputsCreateToken );

					if ( MPv1.create_token_on.keyup ) {
						MPv1.addListenerEvent(element, "keyup", MPv1.validateInputsCreateToken );
					}

					if ( MPv1.create_token_on.paste ) {
						MPv1.addListenerEvent(element, "paste", MPv1.validateInputsCreateToken );
					}

				}

			}

		}

		MPv1.createTokenBySubmit = function() {
			MPv1.addListenerEvent( document.querySelector( MPv1.selectors.form ), "submit", MPv1.doPay );
		}

		var doSubmit = false;

		MPv1.doPay = function( event ) {
			event.preventDefault();
			if ( ! doSubmit ) {
				MPv1.createToken();
				return false;
			}
		}

		MPv1.validateInputsCreateToken = function() {

			var valid_to_create_token = true;
			var $inputs = MPv1.getForm().querySelectorAll( "[data-checkout]" );
			var $inputs_to_create_token = MPv1.getInputsToCreateToken();

			for (var x=0; x<$inputs.length; x++) {

				var element = $inputs[x];

				// Check is a input to create token.
				if ( $inputs_to_create_token
				.indexOf( element.getAttribute( "data-checkout" ) ) > -1 ) {

					if ( element.value == -1 || element.value == "" ) {
						valid_to_create_token = false;
					} // end if check values
				} // end if check data-checkout
			} // end for

	 		if ( valid_to_create_token ) {
				MPv1.createToken();
			}

		}

		MPv1.createToken = function() {
			MPv1.hideErrors();

			// Show loading.
			document.querySelector( MPv1.selectors.box_loading ).style.background =
				"url(" + MPv1.paths.loading + ") 0 50% no-repeat #fff";

			// Form.
			var $form = MPv1.getForm();

			Mercadopago.createToken( $form, MPv1.sdkResponseHandler );

			return false;
		}

		MPv1.sdkResponseHandler = function( status, response ) {

			// Hide loading.
			document.querySelector( MPv1.selectors.box_loading ).style.background = "";

			if ( status != 200 && status != 201 ) {
				MPv1.showErrors( response );
			} else {
				var token = document.querySelector( MPv1.selectors.token );
				token.value = response.id;

				if ( MPv1.add_truncated_card ) {
					var card = MPv1.truncateCard( response );
	           		document.querySelector( MPv1.selectors.cardTruncated ).value = card;
				}

				if ( ! MPv1.create_token_on.event ) {
					doSubmit = true;
					btn = document.querySelector( MPv1.selectors.form );
					btn.submit();
				}
			}

		}

		// === Useful functions

		MPv1.resetBackgroundCard = function() {
			document.querySelector( MPv1.selectors.paymentMethodSelector ).style.background =
				"no-repeat #fff";
			document.querySelector( MPv1.selectors.paymentMethodSelector ).style.border =
				"1px solid #cecece";
			document.querySelector( MPv1.selectors.cardNumber ).style.background =
				"no-repeat #fff";
			document.querySelector( MPv1.selectors.cardNumber ).style.border =
				"1px solid #cecece";
		}

		MPv1.setForm = function() {
			if ( MPv1.customer_and_card.status ) {
				document.querySelector( MPv1.selectors.formDiv ).style.display = "none";
				document.querySelector( MPv1.selectors.mpSecurityCodeCustomerAndCard ).removeAttribute( "style" );
			} else {
				document.querySelector( MPv1.selectors.mpSecurityCodeCustomerAndCard ).style.display = "none";
				document.querySelector( MPv1.selectors.formDiv ).removeAttribute( "style" );
			}

			Mercadopago.clearSession();

			if ( MPv1.create_token_on.event ) {
	            MPv1.createTokenByEvent();
	            MPv1.validateInputsCreateToken();
			}

			document.querySelector( MPv1.selectors.CustomerAndCard ).value =
			MPv1.customer_and_card.status;
		}

		MPv1.getForm = function() {
			if ( MPv1.customer_and_card.status ) {
				return document.querySelector( MPv1.selectors.formCustomerAndCard );
			} else {
				return document.querySelector( MPv1.selectors.form );
			}
		}

		MPv1.getInputsToCreateToken = function() {
			if ( MPv1.customer_and_card.status ) {
				return MPv1.inputs_to_create_token_customer_and_card;
			} else {
				return MPv1.inputs_to_create_token;
			}
		}

		MPv1.truncateCard = function( response_card_token ) {

			var first_six_digits;
			var last_four_digits;

			if ( MPv1.customer_and_card.status ) {
				var cardSelector = document.querySelector( MPv1.selectors.paymentMethodSelector );
				first_six_digits = cardSelector[cardSelector.options.selectedIndex]
				.getAttribute( "first_six_digits" ).match( /.{1,4}/g )
				last_four_digits = cardSelector[cardSelector.options.selectedIndex]
				.getAttribute( "last_four_digits" )
			} else {
	            first_six_digits = response_card_token.first_six_digits.match( /.{1,4}/g )
	            last_four_digits = response_card_token.last_four_digits
			}

			var card = first_six_digits[0] + " " +
			first_six_digits[1] + "** **** " + last_four_digits;

			return card;

		}

		MPv1.getAmount = function() {
			return document.querySelector( MPv1.selectors.amount ).value;
		}

		// === Show errors

		MPv1.showErrors = function( response ) {
			var $form = MPv1.getForm();

			for ( var x=0; x<response.cause.length; x++ ) {
				var error = response.cause[x];
				var $span = $form.querySelector( "#mp-error-" + error.code );
				var $input = $form.querySelector( $span.getAttribute( "data-main" ) );

				$span.style.display = "inline-block";
				$input.classList.add( "mp-error-input" );
			}

			return;
		}

		MPv1.hideErrors = function() {

			for ( var x = 0; x < document.querySelectorAll( "[data-checkout]" ).length; x++ ) {
				var $field = document.querySelectorAll( "[data-checkout]" )[x];
				$field.classList.remove( "mp-error-input" );
			} // end for

			for ( var x = 0; x < document.querySelectorAll( ".mp-error" ).length; x++ ) {
				var $span = document.querySelectorAll( ".mp-error" )[x];
				$span.style.display = "none";
			}

			return;

		}

		// === Add events to guessing

		MPv1.addListenerEvent = function( el, eventName, handler ) {
			if ( el.addEventListener ) {
				el.addEventListener( eventName, handler );
			} else {
				el.attachEvent( "on" + eventName, function() {
					handler.call( el );
				});
			}
		};

		MPv1.addListenerEvent(
			document.querySelector( MPv1.selectors.cardNumber ),
			"keyup", MPv1.guessingPaymentMethod
		);
		MPv1.addListenerEvent(
			document.querySelector( MPv1.selectors.cardNumber ),
			"keyup", MPv1.clearOptions
		);
		MPv1.addListenerEvent(
			document.querySelector( MPv1.selectors.cardNumber),
			"change", MPv1.guessingPaymentMethod
		);

		MPv1.referer = (function () {
			var referer = window.location.protocol + "//" +
				window.location.hostname + ( window.location.port ? ":" + window.location.port: "" );
			return referer;
		})();

		MPv1.AJAX = function( options ) {
			var useXDomain = !!window.XDomainRequest;
			var req = useXDomain ? new XDomainRequest() : new XMLHttpRequest()
			var data;
			options.url += ( options.url.indexOf( "?" ) >= 0 ? "&" : "?" ) + "referer=" + escape( MPv1.referer );
			options.requestedMethod = options.method;
			if ( useXDomain && options.method == "PUT" ) {
				options.method = "POST";
				options.url += "&_method=PUT";
			}
			req.open( options.method, options.url, true );
			req.timeout = options.timeout || 1000;
			if ( window.XDomainRequest ) {
				req.onload = function() {
					data = JSON.parse( req.responseText );
					if ( typeof options.success === "function" ) {
						options.success( options.requestedMethod === "POST" ? 201 : 200, data );
					}
				};
				req.onerror = req.ontimeout = function() {
					if ( typeof options.error === "function" ) {
						options.error( 400, {
							user_agent:window.navigator.userAgent, error : "bad_request", cause:[]
						});
					}
				};
				req.onprogress = function() {};
			} else {
				req.setRequestHeader( "Accept", "application/json" );
				if ( options.contentType ) {
					req.setRequestHeader( "Content-Type", options.contentType );
				} else {
					req.setRequestHeader( "Content-Type", "application/json" );
				}
				req.onreadystatechange = function() {
					if ( this.readyState === 4 ) {
						try {
							if ( this.status >= 200 && this.status < 400 ) {
								// Success!
								data = JSON.parse( this.responseText );
								if ( typeof options.success === "function" ) {
									options.success( this.status, data );
								}
							} else if ( this.status >= 400 ) {
								data = JSON.parse( this.responseText );
								if ( typeof options.error === "function" ) {
									options.error( this.status, data );
								}
							} else if ( typeof options.error === "function" ) {
								options.error( 503, {} );
							}
						} catch (e) {
							options.error( 503, {} );
						}
					}
				};
			}
			if ( options.method === "GET" || options.data == null || options.data == undefined ) {
				req.send();
			} else {
				req.send( JSON.stringify( options.data ) );
			}
		}

		// === Initialization function

		MPv1.Initialize = function( site_id, public_key, coupon_mode, discount_action_url, payer_email ) {

			// Sets
			MPv1.site_id = site_id;
			MPv1.public_key = public_key;
			MPv1.coupon_of_discounts.default = coupon_mode;
			MPv1.coupon_of_discounts.discount_action_url = discount_action_url;
			MPv1.coupon_of_discounts.payer_email = payer_email;

			Mercadopago.setPublishableKey( MPv1.public_key );

			// flow coupon of discounts
			if ( MPv1.coupon_of_discounts.default ) {
				MPv1.addListenerEvent(
					document.querySelector( MPv1.selectors.applyCoupon ),
					"click",
					MPv1.checkCouponEligibility
				);
			} else {
				document.querySelector( MPv1.selectors.formCoupon ).style.display = "none";
			}

			// Flow: customer & cards.
			var selectorPmCustomerAndCards = document.querySelector( MPv1.selectors.pmCustomerAndCards );
			if ( MPv1.customer_and_card.default && selectorPmCustomerAndCards.childElementCount > 0 ) {
				MPv1.addListenerEvent(
					document.querySelector( MPv1.selectors.paymentMethodSelector ),
					"change", MPv1.cardsHandler
				);
				MPv1.cardsHandler();
			} else {
				// If customer & cards is disabled or customer does not have cards.
				MPv1.customer_and_card.status = false;
				document.querySelector( MPv1.selectors.formCustomerAndCard ).style.display = "none";
	         }

			if ( MPv1.create_token_on.event ) {
				MPv1.createTokenByEvent();
			} else {
				MPv1.createTokenBySubmit()
			}

			// flow: MLM
			if ( MPv1.site_id != "MLM" ) {
				Mercadopago.getIdentificationTypes();
			}

			if ( MPv1.site_id == "MLM" ) {

				// Hide documento for mex.
				document.querySelector( MPv1.selectors.mpDoc ).style.display = "none";

				document.querySelector( MPv1.selectors.formCustomerAndCard ).removeAttribute( "style" );
				document.querySelector( MPv1.selectors.formCustomerAndCard ).style.padding = "0px 12px 0px 12px";
				document.querySelector( MPv1.selectors.mpSecurityCodeCustomerAndCard ).style.display = "none";

				// Removing not used fields for this country.
				MPv1.inputs_to_create_token.splice(
					MPv1.inputs_to_create_token.indexOf( "docType" ),
				1 );
				MPv1.inputs_to_create_token.splice(
					MPv1.inputs_to_create_token.indexOf( "docNumber" ),
				1 );

				MPv1.addListenerEvent(
					document.querySelector( MPv1.selectors.paymentMethodSelector ),
					"change",
					MPv1.changePaymetMethodSelector
				);

				// Get payment methods and populate selector.
				MPv1.getPaymentMethods();

			}

			// flow: MLB AND MCO
			if ( MPv1.site_id == "MLB" ) {

				document.querySelector( MPv1.selectors.mpDocType ).style.display = "none";
				document.querySelector( MPv1.selectors.mpIssuer ).style.display = "none";
				// Adjust css.
				document.querySelector( MPv1.selectors.docNumber ).classList.remove( "mp-col-75" );
				//document.querySelector( MPv1.selectors.docNumber ).classList.add( "mp-col-100" );
				document.querySelector( MPv1.selectors.mp_doc_div ).style.display = "none";

			} else if ( MPv1.site_id == "MCO" ) {
				document.querySelector( MPv1.selectors.mpIssuer ).style.display = "none";
			} else if ( MPv1.site_id == "MLA" ) {
				document.querySelector( MPv1.selectors.boxInstallmentsSelector ).classList.remove( "mp-col-100" );
				document.querySelector( MPv1.selectors.boxInstallmentsSelector ).classList.add( "mp-col-70" );
				document.querySelector( MPv1.selectors.taxCFT ).style.display = "block";
				document.querySelector( MPv1.selectors.taxTEA ).style.display = "block";
				MPv1.addListenerEvent( document.querySelector( MPv1.selectors.installments ), "change", MPv1.showTaxes );
			} else if ( MPv1.site_id == "MLC" ) {
				document.querySelector(MPv1.selectors.mpIssuer).style.display = "none";
			}

			if ( MPv1.debug ) {
				document.querySelector( MPv1.selectors.utilities_fields ).style.display = "inline-block";
			}

			document.querySelector( MPv1.selectors.site_id ).value = MPv1.site_id;

			return;

		}

		this.MPv1 = MPv1;

	} ).call();

	// Overriding this function to give form padding attribute.
	MPv1.setForm = function() {
		if ( MPv1.customer_and_card.status ) {
			document.querySelector( MPv1.selectors.form ).style.display = "none";
			document.querySelector( MPv1.selectors.mpSecurityCodeCustomerAndCard ).removeAttribute( "style" );
		} else {
			document.querySelector( MPv1.selectors.mpSecurityCodeCustomerAndCard ).style.display = "none";
			document.querySelector( MPv1.selectors.form ).removeAttribute( "style" );
			document.querySelector( MPv1.selectors.form ).style.padding = "0px 12px 0px 12px";
		}
		Mercadopago.clearSession();
		if ( MPv1.create_token_on.event ) {
			MPv1.createTokenByEvent();
			MPv1.validateInputsCreateToken();
		}
		document.querySelector( MPv1.selectors.CustomerAndCard ).value = MPv1.customer_and_card.status;
	}

	MPv1.getAmount = function() {
		return document.querySelector( MPv1.selectors.amount ).value - document.querySelector( MPv1.selectors.discount ).value;
	}

	MPv1.getAmountWithoutDiscount = function() {
		return document.querySelector( MPv1.selectors.amount ).value;
	}

	MPv1.showErrors = function( response ) {
		var $form = MPv1.getForm();
		for ( var x=0; x<response.cause.length; x++ ) {
			var error = response.cause[x];
			var $span = $form.querySelector( "#mp-error-" + error.code );
			var $input = $form.querySelector( $span.getAttribute( "data-main" ) );
			$span.style.display = "inline-block";
			$input.classList.add( "mp-error-input" );
		}
		return;
	}
	
	MPv1.text.apply = "<?php echo __( 'Apply', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.remove = "<?php echo __( 'Remove', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.coupon_empty = "<?php echo __( 'Please, inform your coupon code', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.choose = "<?php echo __( 'Choose', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.other_bank = "<?php echo __( 'Other Bank', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.discount_info1 = "<?php echo __( 'You will save', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.discount_info2 = "<?php echo __( 'with discount from', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.discount_info3 = "<?php echo __( 'Total of your purchase:', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.discount_info4 = "<?php echo __( 'Total of your purchase with discount:', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.discount_info5 = "<?php echo __( '*Uppon payment approval', 'woocommerce-mercadopago' ); ?>";
	MPv1.text.discount_info6 = "<?php echo __( 'Terms and Conditions of Use', 'woocommerce-mercadopago' ); ?>";

	MPv1.paths.loading = "<?php echo ( $images_path . 'loading.gif' ); ?>";
	MPv1.paths.check = "<?php echo ( $images_path . 'check.png' ); ?>";
	MPv1.paths.error = "<?php echo ( $images_path . 'error.png' ); ?>";

	MPv1.Initialize(
		"<?php echo $site_id; ?>",
		"<?php echo $public_key; ?>",
		"<?php echo $coupon_mode; ?>" == "yes",
		"<?php echo $discount_action_url; ?>",
		"<?php echo $payer_email; ?>"
	);

	document.querySelector( "#custom_checkout_fieldset" ).style.display = "block";
</script>
