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
			<div  id="mercadopago-form-coupon-ticket" class="mp-col-md-12 mp-pb-20">
			<div class="frame-tarjetas mp-text-justify">
				<p class="mp-subtitle-ticket-checkout"><?php echo esc_html__( 'Enter your discount coupon', 'woocommerce-mercadopago' ); ?></p>

				<div class="mp-row-checkout mp-pt-10">
					<div class="mp-col-md-9 mp-pr-15">
						<input type="text" class="mp-form-control" id="couponCodeTicket" name="mercadopago_ticket[coupon_code]" autocomplete="off" maxlength="24" placeholder="<?php echo esc_html__( 'Enter your coupon', 'woocommerce-mercadopago' ); ?>" />
					</div>

					<div class="mp-col-md-3">
						<input type="button" class="mp-button mp-pointer" id="applyCouponTicket" value="<?php echo esc_html__( 'Apply', 'woocommerce-mercadopago' ); ?>">
					</div>
					<div class="mp-discount mp-col-md-9 mp-pr-15" id="mpCouponApplyedTicket"></div>
					<span class="mp-erro_febraban" id="mpCouponErrorTicket"><?php echo esc_html__( 'The code you entered is incorrect', 'woocommerce-mercadopago' ); ?></span>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<div class="mp-col-md-12">
			<div class="frame-tarjetas">
				<div id="mercadopago-form-ticket">

					<?php if ( 'MLU' === $site_id ) : ?>
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

					<?php if ( 'MLB' === $site_id ) : ?>
					<div id="form-ticket">
						<div class="mp-row-checkout">
							<div class="mp-col-md-6">
								<label for="mp_cpf_doc_type" class="mp-label-form-check mp-pointer">
									<input type="radio" name="mercadopago_ticket[docType]" class="mp-form-control-check" id="mp_cpf_doc_type" value="CPF" checked="checked" />
									<?php echo esc_html__( 'Physical person', 'woocommerce-mercadopago' ); ?>
								</label>
							</div>

							<div class="mp-col-md-6">
								<label for="mp_cnpj_doc_type" class="mp-label-form-check mp-pointer">
									<input type="radio" name="mercadopago_ticket[docType]" class="mp-form-control-check" id="mp_cnpj_doc_type" value="CNPJ">
									<?php echo esc_html__( 'Legal person', 'woocommerce-mercadopago' ); ?>
								</label>
							</div>
						</div>

						<div class="mp-row-checkout mp-pt-10">
							<div class="mp-col-md-4 mp-pr-15" id="mp_box_firstname">
								<label for="firstname" id="mp_firstname_label" class="mp-label-form title-name"><?php echo esc_html__( 'Name', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<label for="firstname" id="mp_socialname_label" class="title-razao-social mp-label-form"><?php echo esc_html__( 'Social reason', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<input type="text" class="mp-form-control" value="<?php echo esc_textarea( $febraban['firstname'] ); ?>" id="mp_firstname" data-checkout="mp_firstname" name="mercadopago_ticket[firstname]">
								<span class="mp-erro_febraban" data-main="#mp_firstname" id="error_firstname"><?php echo esc_html__( 'You must inform your name', 'woocommerce-mercadopago' ); ?></span>
							</div>

							<div class="mp-col-md-4 mp-pr-15" id="mp_box_lastname">
								<label for="lastname" class="mp-label-form title-name"><?php echo esc_html__( 'Surname', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<input type="text" class="mp-form-control" value="<?php echo esc_textarea( $febraban['lastname'] ); ?>" id="mp_lastname" data-checkout="mp_lastname" name="mercadopago_ticket[lastname]">
								<span class="mp-erro_febraban" data-main="#mp_lastname" id="error_lastname"><?php echo esc_html__( 'You must inform your last name', 'woocommerce-mercadopago' ); ?></span>
							</div>

							<div class="mp-col-md-4" id="box-docnumber">
								<label for="cpfcnpj" id="mp_cpf_label" class="mp-label-form title-cpf"><?php echo esc_html__( 'CPF', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<label for="cpfcnpj" id="mp_cnpj_label" class="title-cnpj mp-label-form"><?php echo esc_html__( 'CNPJ', 'woocommerce-mercadopago' ); ?><em>*</em></label>
								<input type="text" class="mp-form-control" value="<?php echo esc_textarea( $febraban['docNumber'] ); ?>" id="mp_doc_number" data-checkout="mp_doc_number" name="mercadopago_ticket[docNumber]" onkeyup="mpMaskInput(this, mpCpf);" maxlength="14">
								<span class="mp-erro_febraban" data-main="#mp_doc_number"><?php echo esc_html__( 'You must provide your document number', 'woocommerce-mercadopago' ); ?></span>
								<span class="mp_error_docnumber" id="mp_error_docnumber"><?php echo esc_html__( 'Invalid Document Number', 'woocommerce-mercadopago' ); ?></span>
							</div>
						</div>

						<div class="mp-row-checkout mp-pt-10">
							<div class="mp-col-md-8 mp-pr-15" id="box-firstname">
								<label for="address" class="mp-label-form"><?php echo esc_html__( 'Address', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<input type="text" value="<?php echo esc_textarea( $febraban['address'] ); ?>" class="mp-form-control" id="mp_address" data-checkout="mp_address" name="mercadopago_ticket[address]">
								<span class="mp-erro_febraban" data-main="#mp_address" id="error_address"><?php echo esc_html__( 'You must inform your address', 'woocommerce-mercadopago' ); ?></span>
							</div>

							<div class="mp-col-md-4">
								<label for="number" class="mp-label-form"><?php echo esc_html__( 'Number', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<input type="text" value="<?php echo esc_textarea( $febraban['number'] ); ?>" class="mp-form-control" id="mp_number" data-checkout="mp_number" name="mercadopago_ticket[number]" onkeyup="mpMaskInput(this, mpTicketInteger);">
								<span class="mp-erro_febraban" data-main="#mp_number" id="error_number"><?php echo esc_html__( 'You must provide your address number', 'woocommerce-mercadopago' ); ?></span>
							</div>
						</div>

						<div class="mp-row-checkout mp-pt-10">
							<div class="mp-col-md-4 mp-pr-15">
								<label for="city" class="mp-label-form"><?php echo esc_html__( 'City', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<input type="text" value="<?php echo esc_textarea( $febraban['city'] ); ?>" class="mp-form-control" id="mp_city" data-checkout="mp_city" name="mercadopago_ticket[city]">
								<span class="mp-erro_febraban" data-main="#mp_city" id="error_city"><?php echo esc_html__( 'You must inform your city', 'woocommerce-mercadopago' ); ?></span>
							</div>

							<div class="mp-col-md-4 mp-pr-15">
								<label for="state" class="mp-label-form"><?php echo esc_html__( 'State', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<select name="mercadopago_ticket[state]" id="mp_state" data-checkout="mp_state" class="mp-form-control mp-pointer">
									<option value=""
									<?php
									if ( '' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>
										<?php echo esc_html__( 'Select state"', 'woocommerce-mercadopago' ); ?>
									</option>
									<option value="AC"
									<?php
									if ( 'AC' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Acre</option>
									<option value="AL"
									<?php
									if ( 'AL' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Alagoas</option>
									<option value="AP"
									<?php
									if ( 'AP' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Amapá</option>
									<option value="AM"
									<?php
									if ( 'AM' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Amazonas</option>
									<option value="BA"
									<?php
									if ( 'BA' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Bahia</option>
									<option value="CE"
									<?php
									if ( 'CE' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Ceará</option>
									<option value="DF"
									<?php
									if ( 'DF' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Distrito Federal</option>
									<option value="ES"
									<?php
									if ( 'ES' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Espírito Santo</option>
									<option value="GO"
									<?php
									if ( 'GO' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Goiás</option>
									<option value="MA"
									<?php
									if ( 'MA' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Maranhão</option>
									<option value="MT"
									<?php
									if ( 'MT' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Mato Grosso</option>
									<option value="MS"
									<?php
									if ( 'MS' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Mato Grosso do Sul</option>
									<option value="MG"
									<?php
									if ( 'MG' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Minas Gerais</option>
									<option value="PA"
									<?php
									if ( 'PA' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Pará</option>
									<option value="PB"
									<?php
									if ( 'PB' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Paraíba</option>
									<option value="PR"
									<?php
									if ( 'PR' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Paraná</option>
									<option value="PE"
									<?php
									if ( 'PE' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Pernambuco</option>
									<option value="PI"
									<?php
									if ( 'PI' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Piauí</option>
									<option value="RJ"
									<?php
									if ( 'RJ' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Rio de Janeiro</option>
									<option value="RN"
									<?php
									if ( 'RN' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Rio Grande do Norte</option>
									<option value="RS"
									<?php
									if ( 'RS' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Rio Grande do Sul</option>
									<option value="RO"
									<?php
									if ( 'RO' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Rondônia</option>
									<option value="RA"
									<?php
									if ( 'RA' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Roraima</option>
									<option value="SC"
									<?php
									if ( 'SC' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Santa Catarina</option>
									<option value="SP"
									<?php
									if ( 'SP' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>São Paulo</option>
									<option value="SE"
									<?php
									if ( 'SE' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Sergipe</option>
									<option value="TO"
									<?php
									if ( 'TO' === $febraban['state'] ) {
										echo 'selected="selected"'; }
									?>
									>Tocantins</option>
								</select>
								<span class="mp-erro_febraban" data-main="#mp_state" id="error_state"><?php echo esc_html__( 'You must inform your status', 'woocommerce-mercadopago' ); ?></span>
							</div>

							<div class="mp-col-md-4">
								<label for="zipcode" class="mp-label-form"><?php echo esc_html__( 'Postal Code', 'woocommerce-mercadopago' ); ?> <em>*</em></label>
								<input type="text" value="<?php echo esc_textarea( $febraban['zipcode'] ); ?>" id="mp_zipcode" data-checkout="mp_zipcode" class="mp-form-control" name="mercadopago_ticket[zipcode]" maxlength="9" onkeyup="mpMaskInput(this, mpCep);">
								<span class="mp-erro_febraban" data-main="#mp_zipcode" id="error_zipcode"><?php echo esc_html__( 'You must provide your zip code', 'woocommerce-mercadopago' ); ?></span>
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

					<div class="mp-col-md-12 <?php echo ( 'MLB' === $site_id ) ? 'mp-pt-20' : ''; ?>">
						<div class="frame-tarjetas">
							<p class="mp-subtitle-ticket-checkout"><?php echo esc_html__( 'Select the issuer with whom you want to process the payment', 'woocommerce-mercadopago' ); ?></p>

							<div class="mp-row-checkout mp-pt-10">
								<?php $at_first = true; ?>
								<?php foreach ( $payment_methods as $payment ) : ?>
								<div id="frameTicket" class="mp-col-md-6 mp-pb-15 mp-min-hg">
										<div id="paymentMethodIdTicket" class="mp-ticket-payments">
											<label for="<?php echo esc_attr( $payment['id'] ); ?>" class="mp-label-form mp-pointer">
												<input type="radio" class="mp-form-control-check" name="mercadopago_ticket[paymentMethodId]" id="<?php echo esc_attr( $payment['id'] ); ?>" value="<?php echo esc_attr( $payment['id'] ); ?>"
																																							<?php
																																							if ( $at_first ) :
																																								?>
													checked="checked" <?php endif; ?> />
												<img src="<?php echo esc_attr( $payment['secure_thumbnail'] ); ?>" class="mp-img-ticket" alt="<?php echo esc_attr( $payment['name'] ); ?>" />
												<span class="mp-ticket-name"><?php echo esc_attr( $payment['name'] ); ?></span>
											</label>
										</div>
										<?php $at_first = false; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
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
		<?php echo esc_html($text_prefix); ?> 		
		<a target="_blank" href="<?php echo esc_html($link_terms_and_conditions); ?>">  <?php echo esc_html($text_suffix); ?> </a>
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

	function mpCpf(v){
		v=v.replace(/\D/g,"")
		v=v.replace(/(\d{3})(\d)/,"$1.$2")
		v=v.replace(/(\d{3})(\d)/,"$1.$2")
		v=v.replace(/(\d{3})(\d{1,2})$/,"$1-$2")
		return v
	}

	function mpCnpj(v){
		v=v.replace(/\D/g,"")
		v=v.replace(/^(\d{2})(\d)/,"$1.$2")
		v=v.replace(/^(\d{2})\.(\d{3})(\d)/,"$1.$2.$3")
		v=v.replace(/\.(\d{3})(\d)/,".$1/$2")
		v=v.replace(/(\d{4})(\d)/,"$1-$2")
		return v
	}

	function mpCep(v){
		v=v.replace(/D/g,"")
		v=v.replace(/^(\d{5})(\d)/,"$1-$2")
		return v
	}
</script>
