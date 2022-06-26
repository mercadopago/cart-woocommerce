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
<div class="mp-panel-custom-checkout">
	<?php
	// @codingStandardsIgnoreLine
	echo $checkout_alert_test_mode;
	?>
	<div class="mp-row-checkout">
		<!-- Cupom mode, creat a campaign on mercado pago -->
		<?php if ( 'yes' === $coupon_mode ) : ?>
			<div id="mercadopago-form-coupon-ticket" class="mp-col-md-12 mp-pb-15">
				<div class="frame-tarjetas mp-text-justify">
					<p class="mp-subtitle-ticket-checkout"><?php echo esc_html__( 'Enter your discount coupon', 'woocommerce-mercadopago' ); ?></p>

					<div class="mp-row-checkout mp-pt-10">
						<div class="mp-col-md-9 mp-pr-15">
							<input type="text" class="mp-form-control" id="couponCodeTicket" name="mercadopago_ticket[coupon_code]" autocomplete="off" maxlength="24" placeholder="<?php echo esc_html__( 'Enter your coupon', 'woocommerce-mercadopago' ); ?>" />
						</div>
						<div class="mp-col-md-3">
							<input type="button" class="mp-button mp-pointer" id="applyCouponTicket" value="<?php echo esc_html__( 'Apply', 'woocommerce-mercadopago' ); ?>">
						</div>
						<div class="mp-row-checkout">
							<div class="mp-discount mp-col-md-9 mp-pr-15" id="mpCouponApplyedTicket"></div>
							<span class="mp-erro_febraban" id="mpCouponErrorTicket"><?php echo esc_html__( 'The code you entered is incorrect', 'woocommerce-mercadopago' ); ?></span>
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
		<div class="mp-col-md-12">
			<div class="frame-tarjetas">
				<div id="mercadopago-form-ticket">

					<?php if ( 'mlu' === $site_id ) : ?>
						<div id="form-ticket">
							<div class="mp-row-checkout">
								<p class="mp-subtitle-custom-checkout"><?php echo esc_html__( 'Enter your document number', 'woocommerce-mercadopago' ); ?></p>
								<div class="mp-col-md-4 mp-pr-15">
									<label for="mp-docType" class="mp-label-form mp-pt-5"><?php echo esc_html__( 'Type', 'woocommerce-mercadopago' ); ?></label>
									<select id="mp-docType" class="form-control mp-form-control mp-select mp-pointer" name="mercadopago_ticket[docType]">
										<option value="CI" selected><?php echo esc_html__( 'CI', 'woocommerce-mercadopago' ); ?></option>
									</select>
								</div>
								<div class="mp-col-md-8" id="box-docnumber">
									<label for="cpfcnpj" id="mp_cpf_label" class="mp-label-form title-cpf"><?php echo esc_html__( 'Document number', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
									<input type="text" class="mp-form-control" id="mp_doc_number" data-checkout="mp_doc_number" name="mercadopago_ticket[docNumber]" onkeyup="mpMaskInput(this, mpTicketInteger);" autocomplete="off" maxlength="8">
									<span class="mp-erro_febraban" data-main="#mp_doc_number"><?php echo esc_html__( 'You must provide your document number', 'woocommerce-mercadopago' ); ?></span>
									<span class="mp_error_docnumber" id="mp_error_docnumber"><?php echo esc_html__( 'Invalid Document Number', 'woocommerce-mercadopago' ); ?></span>
								</div>
							</div>
							<div class="mp-col-md-12 mp-pt-10">
								<div class="frame-tarjetas">
									<div class="mp-row-checkout">
										<p class="mp-obrigatory"><?php echo esc_html__( 'Complete all fields, they are mandatory.', 'woocommerce-mercadopago' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					<?php endif; ?>

					<div class="mp-col-md-12">
						<div class="frame-tarjetas">
							<p class="mp-subtitle-ticket-checkout"><?php echo esc_html__( 'Select the issuer with whom you want to process the payment', 'woocommerce-mercadopago' ); ?></p>
							<div class="mp-row-checkout mp-pt-10">
								<?php $at_first = true; ?>
								<?php foreach ( $payment_methods as $payment ) : ?>
											<?php if ( isset($payment['payment_places']) ) { ?>
												<?php
												foreach ( $payment['payment_places'] as $place ) :
														$payment_place_id = ( new WC_WooMercadoPago_Composite_Id_Helper() )->generateIdFromPlace($payment['id'], $place['payment_option_id']);
													?>
													<div id="frameTicket" class="mp-col-md-6 mp-pb-15 mp-min-hg">
														<div id="paymentMethodIdTicket" class="mp-ticket-payments">
														<label for="<?php echo esc_attr( $payment_place_id ); ?>" class="mp-label-form mp-pointer">
															<input type="radio" class="mp-form-control-check" name="mercadopago_ticket[paymentMethodId]" id="<?php echo esc_attr( $payment_place_id ); ?>" value="<?php echo esc_attr( $payment_place_id ); ?>"
															<?php if ( $at_first ) : ?>
															checked="checked" <?php endif; ?> />
															<img src="<?php echo esc_attr( $place['thumbnail'] ); ?>" class="mp-img-ticket" alt="<?php echo esc_attr( $place['name'] ); ?>" />
															<br>
															<p class="mp-ticket-name"><?php echo esc_attr( $place['name'] ); ?></p>
														</label>
													</div>
												</div>
												<?php endforeach; ?>
											<?php } else { ?>
												<div id="frameTicket" class="mp-col-md-6 mp-pb-15 mp-min-hg">
													<div id="paymentMethodIdTicket" class="mp-ticket-payments">
														<label for="<?php echo esc_attr( $payment['id'] ); ?>" class="mp-label-form mp-pointer">
															<input type="radio" class="mp-form-control-check" name="mercadopago_ticket[paymentMethodId]" id="<?php echo esc_attr( $payment['id'] ); ?>" value="<?php echo esc_attr( $payment['id'] ); ?>"
																<?php if ( $at_first ) : ?>
																checked="checked" <?php endif; ?> />
															<img src="<?php echo esc_attr( $payment['secure_thumbnail'] ); ?>" class="mp-img-ticket" alt="<?php echo esc_attr( $payment['name'] ); ?>" />
														<br>
															<p class="mp-ticket-name">
																<?php echo esc_attr( ( 'Pagamento na lotérica sem boleto' === $payment['name'] ) ? $payment['name'] = esc_html__( 'Lottery', 'woocommerce-mercadopago' ) : $payment['name'] ); ?>
															</p>
														</label>
													</div>
													<?php $at_first = false; ?>
												</div>
												<?php }; ?>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<?php if ( 'mlb' === $site_id ) : ?>
						<div class="mp-col-md-12 mp-pb-15" id="box-docnumber">
							<label for="cpfcnpj" id="mp_cpf_cnpj_label" class="mp-label-form title-cpf"><?php echo esc_html__( 'CPF/CNPJ', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
							<input type="text" class="mp-form-control" value="<?php echo esc_textarea( $febraban['docNumber'] ); ?>" id="mp_doc_number" data-checkout="mp_doc_number" name="mercadopago_ticket[docNumber]" onkeyup="mpMaskInput(this, mpCpfCnpj);" maxlength="18">
							<span class="mp-erro_febraban" data-main="#mp_doc_number"><?php echo esc_html__( 'You must provide your document number', 'woocommerce-mercadopago' ); ?></span>
							<span class="mp_error_docnumber" id="mp_error_docnumber"><?php echo esc_html__( 'Invalid Document Number', 'woocommerce-mercadopago' ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!-- NOT DELETE LOADING-->
		<div id="mp-box-loading"></div>

		<!-- utilities -->
		<div id="mercadopago-utilities">
			<input type="hidden" id="site_id" value="<?php echo esc_textarea( $site_id ); ?>" name="mercadopago_ticket[site_id]" />
			<input type="hidden" id="amountTicket" value="<?php echo esc_textarea( $amount ); ?>" name="mercadopago_ticket[amount]" />
			<input type="hidden" id="currency_ratioTicket" value="<?php echo esc_textarea( $currency_ratio ); ?>" name="mercadopago_ticket[currency_ratio]" />
			<input type="hidden" id="campaign_idTicket" name="mercadopago_ticket[campaign_id]" />
			<input type="hidden" id="campaignTicket" name="mercadopago_ticket[campaign]" />
			<input type="hidden" id="discountTicket" name="mercadopago_ticket[discount]" />
		</div>

	</div>
</div>

<!-- Terms and conditions link at checkout -->
<div>
	<p class="mp-terms-and-conditions">
		<?php echo esc_html( $text_prefix ); ?>
		<a target="_blank" href="<?php echo esc_html( $link_terms_and_conditions ); ?>"> <?php echo esc_html( $text_suffix ); ?> </a>
	</p>
</div>

<script type="text/javascript">
  jQuery(document).ready(function(){
    jQuery('#mp_firstname').val(jQuery('[name="billing_first_name"]').val())
    jQuery('#mp_lastname').val(jQuery('[name="billing_last_name"]').val())
    jQuery('#mp_doc_number').val(jQuery('[name="billing_cpf"]').val() || jQuery('[name="billing_cnpj"]').val())
    jQuery('#mp_address').val(jQuery('[name="billing_address_1"]').val())
    jQuery('#mp_number').val(jQuery('[name="billing_number"]').val())
    jQuery('#mp_city').val(jQuery('[name="billing_city"]').val())
    jQuery('#mp_state').val(jQuery('[name="billing_state"]').val())
    jQuery('#mp_zipcode').val(jQuery('[name="billing_postcode"]').val())
  })
	//Card mask date input
	function mpMaskInput(o, f) {
		v_obj = o
		v_fun = f
		setTimeout("mpTicketExecmascara()", 1);
	}

	function mpTicketExecmascara() {
		v_obj.value = v_fun(v_obj.value)
	}

	function mpTicketInteger(v) {
		return v.replace(/\D/g, "")
	}

	function mpCpfCnpj(v, element) {
		v = v.replace(/\D/g, "")

		if (v.length <= 11) { //CPF
			document.getElementById('mp_cpf_cnpj_label').innerHTML = 'CPF/CNPJ <em>*</em>'

			v = v.replace(/(\d{3})(\d)/, "$1.$2")
			v = v.replace(/(\d{3})(\d)/, "$1.$2")
			v = v.replace(/(\d{3})(\d{1,2})$/, "$1-$2")

			if (v.length == 14) {
				document.getElementById('mp_cpf_cnpj_label').innerHTML = 'CPF <em>*</em>'
			}

		} else { //CNPJ
			document.getElementById('mp_cpf_cnpj_label').innerHTML = 'CNPJ <em>*</em>'

			v = v.replace(/^(\d{2})(\d)/, '$1.$2');
			v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
			v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
			v = v.replace(/(\d{4})(\d)/, '$1-$2');
		}

		return v
	}
</script>
