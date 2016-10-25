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

<div width="100%" style="margin:1px; padding:36px 36px 16px 36px; background:white; ">
	<img class="logo" src="<?php echo ( $images_path . 'mplogo.png' ); ?>" width="156" height="40" />
	<?php if ( count( $payment_methods ) > 1 ) { ?>
		<img class="logo" src="<?php echo ( $images_path . 'boleto.png' ); ?>" width="90" height="40" style="float:right;"/>
	<?php } else { ?>
		<?php foreach ( $payment_methods as $payment ) { ?>
			<img class="logo" src="<?php echo $payment[ 'thumbnail' ]; ?>" width="90" height="40" style="float:right;"/>
		<?php } ?>
	<?php } ?>
</div>
<fieldset id="mercadopago-form" style="background:white; ">

	<div class="mp-box-inputs mp-line" id="mercadopago-form-coupon-ticket" style="padding:0px 36px 16px 36px;">
		<label for="couponCodeLabel"><?php echo $form_labels['form']['coupon_of_discounts']; ?></label>
      	<div class="mp-box-inputs mp-col-65">
	        <input type="text" id="couponCodeTicket" name="mercadopago_ticket[coupon_code]" autocomplete="off" maxlength="24" />
	        <span class="mp-discount" id="mpCouponApplyedTicket" ></span>
	        <span class="mp-error" id="mpCouponErrorTicket" ></span>
      	</div>
      	<div class="mp-box-inputs mp-col-10">
        	<div id="mp-separete-date"></div>
      	</div>
      	<div class="mp-box-inputs mp-col-25">
        	<input type="button" class="button" id="applyCouponTicket" value="<?php echo $form_labels['form']['apply']; ?>" >
      	</div>
    </div>

	<div style="padding:0px 36px 0px 36px;">
		<p>
			<?php if ( count( $payment_methods ) > 1 ) { ?>
				<?php echo $form_labels[ 'form' ][ 'issuer_selection' ] ?>
			<?php } ?>
			<?php echo $form_labels[ 'form' ][ 'payment_instructions' ] ?>
			<br />
			<?php echo $form_labels[ 'form' ][ 'ticket_note' ] ?>
			<?php if ($is_currency_conversion > 0) { ?>
    			<?php echo " (" . $form_labels['form']['payment_converted'] . " " . $woocommerce_currency . " " . $form_labels['form']['to'] . " " . $account_currency . ")"; ?>
    		<?php } ?>
		</p>
		<?php if ( count( $payment_methods ) > 1 ) { ?>
			<div class="mp-box-inputs mp-col-100">
				<!--<select id="paymentMethodId" name="mercadopago_ticket[paymentMethodId]">
					<option value="-1"> <?php /*echo $form_labels[ 'form' ][ 'label_choose' ] . " ...";*/ ?> </option>-->
				<?php $atFirst = true; ?>
				<?php foreach ( $payment_methods as $payment ) { ?>
		  			<!--<option value="<?php /*echo $payment[ 'id' ];*/ ?>"> <?php /*echo $payment[ 'name' ];*/ ?></option>-->
	  				<div class="mp-box-inputs mp-line">
						<div id="paymentMethodId" class="mp-box-inputs mp-col-5">
	  						<input type="radio" class="input-radio" name="mercadopago_ticket[paymentMethodId]"
		  						style="height:16px; width:16px;" value="<?php echo $payment[ 'id' ]; ?>"
		  						<?php if ( $atFirst ) { ?> checked="checked" } <?php } ?> />
			  			</div>
			  			<div class="mp-box-inputs mp-col-45">
							<label>
			  					<img src="<?php echo $payment[ 'thumbnail' ]; ?>" alt="<?php echo $payment[ 'name' ]; ?>" />
			  					&nbsp;(<?php echo $payment[ 'name' ]; ?>)
			  				</label>
			  			</div>
	  				</div>
	  				<?php $atFirst = false; ?>
				<?php } ?>
				<!--</select>-->
			</div>
		<?php } else { ?>
			<div class="mp-box-inputs mp-col-100" style="display:none;">
				<select id="paymentMethodId" name="mercadopago_ticket[paymentMethodId]">
					<?php foreach ( $payment_methods as $payment ) { ?>
			  			<option value="<?php echo $payment[ 'id' ]; ?>"
			  				style="padding: 8px; background: url( 'https://img.mlstatic.com/org-img/MP3/API/logos/bapropagos.gif' ); ?> ) 98% 50% no-repeat;"> <?php echo $payment[ 'name' ]; ?></option>
					<?php } ?>
				</select>
			</div>
		<?php } ?>

		<div class="mp-box-inputs mp-line">
	    	<!-- <div class="mp-box-inputs mp-col-50">
	    		<input type="submit" value="Pay" id="submit"/>
	    	</div> -->
			<div class="mp-box-inputs mp-col-25">
	    		<div id="mp-box-loading">
	        	</div>
			</div>
		</div>

		<!-- utilities -->
		<div class="mp-box-inputs mp-col-100" id="mercadopago-utilities">
			<input type="hidden" id="site_id"  value="<?php echo $site_id; ?>" name="mercadopago_ticket[site_id]"/>
			<input type="hidden" id="amountTicket" value="<?php echo $amount; ?>" name="mercadopago_ticket[amount]"/>
			<input type="hidden" id="campaign_idTicket" name="mercadopago_ticket[campaign_id]"/>
			<input type="hidden" id="campaignTicket" name="mercadopago_ticket[campaign]"/>
			<input type="hidden" id="discountTicket" name="mercadopago_ticket[discount]"/>
		</div>

	</div>
</fieldset>

<script type="text/javascript">

    (function() {

	    var MPv1Ticket = {
	    	site_id: '',
	        coupon_of_discounts: {
				discount_action_url: '',
				default: true,
				status: false
    		},

	        inputs_to_create_discount: [
				"couponCodeTicket",
				"applyCouponTicket"
			],

	        selectors: {
	        	couponCode: "#couponCodeTicket",
				applyCoupon: "#applyCouponTicket",
				mpCouponApplyed: "#mpCouponApplyedTicket",
				mpCouponError: "#mpCouponErrorTicket",

				paymentMethodId: "#paymentMethodId",
	            amount: "#amountTicket",
				campaign_id: "#campaign_idTicket",
	            campaign: "#campaignTicket",
	            discount: "#discountTicket",

				formCoupon: '#mercadopago-form-coupon-ticket'
	        },
	        text: {
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

	    /*
    	 * Coupon of Discounts
    	 */

	    MPv1Ticket.currencyIdToCurrency = function (currency_id) {
	    	if ( currency_id == 'ARS' ) {
				return '$';
	    	} else if ( currency_id == 'BRL' ) {
	    		return 'R$';
	    	} else if ( currency_id == 'COP' ) {
	    		return '$';
	    	} else if ( currency_id == 'CLP' ) {
	    		return '$';
	    	} else if ( currency_id == 'MXN' ) {
	    		return '$';
	    	} else if ( currency_id == 'VEF' ) {
	    		return 'Bs';
	    	} else if ( currency_id == 'PEN' ) {
	    		return 'S/';
	    	} else {
	    		return '$';
	    	}
	    }

	    MPv1Ticket.checkCouponEligibility = function () {
	    	if ( document.querySelector(MPv1Ticket.selectors.couponCode).value == "" ) {
	    		// coupon code is empty
	    		document.querySelector(MPv1Ticket.selectors.mpCouponApplyed).style.display = 'none';
				document.querySelector(MPv1Ticket.selectors.mpCouponError).style.display = 'block';
				document.querySelector(MPv1Ticket.selectors.mpCouponError).innerHTML = MPv1Ticket.text.coupon_empty;
				MPv1Ticket.coupon_of_discounts.status = false;
				document.querySelector(MPv1Ticket.selectors.couponCode).style.background = null;
				document.querySelector(MPv1Ticket.selectors.applyCoupon).value = MPv1Ticket.text.apply;
				document.querySelector(MPv1.selectors.discount).value = 0;
				//MPv1.cardsHandler();
	    	} else if ( MPv1Ticket.coupon_of_discounts.status ) {
	    		// we already have a coupon set, so we remove it
	    		document.querySelector(MPv1Ticket.selectors.mpCouponApplyed).style.display = 'none';
				document.querySelector(MPv1Ticket.selectors.mpCouponError).style.display = 'none';
				MPv1Ticket.coupon_of_discounts.status = false;
				document.querySelector(MPv1Ticket.selectors.applyCoupon).style.background = null;
	    		document.querySelector(MPv1Ticket.selectors.applyCoupon).value = MPv1Ticket.text.apply;
	    		document.querySelector(MPv1Ticket.selectors.couponCode).value = "";
	    		document.querySelector(MPv1Ticket.selectors.couponCode).style.background = null;
	    		document.querySelector(MPv1.selectors.discount).value = 0;
	    		//MPv1.cardsHandler();
	    	} else {
				// set loading
				document.querySelector(MPv1Ticket.selectors.mpCouponApplyed).style.display = 'none';
				document.querySelector(MPv1Ticket.selectors.mpCouponError).style.display = 'none';
				document.querySelector(MPv1Ticket.selectors.couponCode).style.background = "url("+MPv1Ticket.paths.loading+") 98% 50% no-repeat #fff";
				document.querySelector(MPv1Ticket.selectors.applyCoupon).disabled = true;
				var request = new XMLHttpRequest();
				request.open(
					'GET',
					MPv1Ticket.coupon_of_discounts.discount_action_url +
						"&coupon_id=" + document.querySelector(MPv1Ticket.selectors.couponCode).value +
						"&amount=" + document.querySelector(MPv1Ticket.selectors.amount).value +
						"&payer=" + document.getElementById("billing_email").value,
					true
				);
				request.onreadystatechange = function() {
					if (request.readyState == 4) {
						if (request.status == 200) {
							var response = JSON.parse(request.responseText);
							if (response.status == 200) {
								document.querySelector(MPv1Ticket.selectors.mpCouponApplyed).style.display = 'block';
								document.querySelector(MPv1Ticket.selectors.discount).value = response.response.coupon_amount;
								document.querySelector(MPv1Ticket.selectors.mpCouponApplyed).innerHTML =
									"<div style='border-style: solid; border-width:thin; border-color: #009EE3; padding: 8px 8px 8px 8px; margin-top: 4px;'>" +
									MPv1Ticket.text.discount_info1 + " <strong>" + MPv1Ticket.currencyIdToCurrency(response.response.currency_id) + " " +
									Math.round(response.response.coupon_amount*100)/100 + "</strong> " + MPv1Ticket.text.discount_info2 + " " + response.response.name + ".<br>" +
									MPv1Ticket.text.discount_info3 + " <strong>" + MPv1Ticket.currencyIdToCurrency(response.response.currency_id) +
									" " + Math.round(MPv1Ticket.getAmountWithoutDiscount()*100)/100 + "</strong><br>" +
									MPv1Ticket.text.discount_info4 + " <strong>" + MPv1Ticket.currencyIdToCurrency(response.response.currency_id) +
									" " + Math.round(MPv1Ticket.getAmount()*100)/100 + "*</strong><br>" +
									"<i>" + MPv1Ticket.text.discount_info5 + "</i><br>" +
									"<a href='https://api.mercadolibre.com/campaigns/" + response.response.id + "/terms_and_conditions?format_type=html' target='_blank'>" +
									MPv1Ticket.text.discount_info6 + "</a></div>";
								document.querySelector(MPv1Ticket.selectors.mpCouponError).style.display = 'none';
								MPv1Ticket.coupon_of_discounts.status = true;
								document.querySelector(MPv1Ticket.selectors.couponCode).style.background = null;
								document.querySelector(MPv1Ticket.selectors.couponCode).style.background = "url("+MPv1Ticket.paths.check+") 98% 50% no-repeat #fff";
								document.querySelector(MPv1Ticket.selectors.applyCoupon).value = MPv1Ticket.text.remove;
								//MPv1.cardsHandler();
								document.querySelector(MPv1Ticket.selectors.campaign_id).value = response.response.id;
								document.querySelector(MPv1Ticket.selectors.campaign).value = response.response.name;
							} else if (response.status == 400 || response.status == 404) {
								document.querySelector(MPv1Ticket.selectors.mpCouponApplyed).style.display = 'none';
								document.querySelector(MPv1Ticket.selectors.mpCouponError).style.display = 'block';
								document.querySelector(MPv1Ticket.selectors.mpCouponError).innerHTML = response.response.message;
								MPv1Ticket.coupon_of_discounts.status = false;
								document.querySelector(MPv1Ticket.selectors.couponCode).style.background = null;
								document.querySelector(MPv1Ticket.selectors.couponCode).style.background = "url("+MPv1Ticket.paths.error+") 98% 50% no-repeat #fff";
								document.querySelector(MPv1Ticket.selectors.applyCoupon).value = MPv1Ticket.text.apply;
								document.querySelector(MPv1.selectors.discount).value = 0;
								//MPv1.cardsHandler();
							}
						} else {
							// request failed
							document.querySelector(MPv1Ticket.selectors.mpCouponApplyed).style.display = 'none';
							document.querySelector(MPv1Ticket.selectors.mpCouponError).style.display = 'none';
							MPv1Ticket.coupon_of_discounts.status = false;
							document.querySelector(MPv1Ticket.selectors.applyCoupon).style.background = null;
				    		document.querySelector(MPv1Ticket.selectors.applyCoupon).value = MPv1Ticket.text.apply;
				    		document.querySelector(MPv1Ticket.selectors.couponCode).value = "";
				    		document.querySelector(MPv1Ticket.selectors.couponCode).style.background = null;
				    		document.querySelector(MPv1.selectors.discount).value = 0;
				    		//MPv1.cardsHandler();
						}
						document.querySelector(MPv1Ticket.selectors.applyCoupon).disabled = false;
					}
				};
				request.send(null);
			}
		}

	    /*
	     *
	     *
	     * Initialization function
	     *
	     */

	    MPv1Ticket.addListenerEvent = function(el, eventName, handler) {
	        if (el.addEventListener) {
	            el.addEventListener(eventName, handler);
	        } else {
	            el.attachEvent('on' + eventName, function() {
	                handler.call(el);
	            });
	        }
	    };

	    MPv1Ticket.Initialize = function(site_id, coupon_mode, discount_action_url) {

	        //sets
	        MPv1Ticket.site_id = site_id
			MPv1Ticket.coupon_of_discounts.default = coupon_mode
			MPv1Ticket.coupon_of_discounts.discount_action_url = discount_action_url

	        // flow coupon of discounts
			if (MPv1Ticket.coupon_of_discounts.default) {
				MPv1Ticket.addListenerEvent(document.querySelector(MPv1Ticket.selectors.applyCoupon), 'click', MPv1Ticket.checkCouponEligibility);
			} else {
				document.querySelector(MPv1Ticket.selectors.formCoupon).style.display = 'none';
			}

	        return;
	    }

	    this.MPv1Ticket = MPv1Ticket;

	}).call();

	// === Instantiation

    var mercadopago_site_id = '<?php echo $site_id; ?>';
    var mercadopago_coupon_mode = '<?php echo $coupon_mode; ?>';
    var mercadopago_discount_action_url = '<?php echo $discount_action_url; ?>';

    MPv1Ticket.text.discount_info1 = '<?php echo $form_labels["form"]["discount_info1"]; ?>';
    MPv1Ticket.text.discount_info2 = '<?php echo $form_labels["form"]["discount_info2"]; ?>';
    MPv1Ticket.text.discount_info3 = '<?php echo $form_labels["form"]["discount_info3"]; ?>';
    MPv1Ticket.text.discount_info4 = '<?php echo $form_labels["form"]["discount_info4"]; ?>';
    MPv1Ticket.text.discount_info5 = '<?php echo $form_labels["form"]["discount_info5"]; ?>';
    MPv1Ticket.text.discount_info6 = '<?php echo $form_labels["form"]["discount_info6"]; ?>';
    MPv1Ticket.text.apply = '<?php echo $form_labels["form"]["apply"]; ?>';
    MPv1Ticket.text.remove = '<?php echo $form_labels["form"]["remove"]; ?>';
    MPv1Ticket.text.coupon_empty = '<?php echo $form_labels["form"]["coupon_empty"]; ?>';
    MPv1Ticket.paths.loading = '<?php echo ( $images_path . "loading.gif" ); ?>';
    MPv1Ticket.paths.check = '<?php echo ( $images_path . "check.png" ); ?>';
    MPv1Ticket.paths.error = '<?php echo ( $images_path . "error.png" ); ?>';

    MPv1Ticket.getAmount = function() {
        return document.querySelector(MPv1Ticket.selectors.amount).value - document.querySelector(MPv1Ticket.selectors.discount).value;
    }

    MPv1Ticket.getAmountWithoutDiscount = function() {
        return document.querySelector(MPv1Ticket.selectors.amount).value;
    }

    MPv1Ticket.Initialize(
    	mercadopago_site_id,
    	mercadopago_coupon_mode == 'yes',
    	mercadopago_discount_action_url);

</script>
