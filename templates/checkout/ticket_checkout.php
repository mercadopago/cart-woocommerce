<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="mp-panel-custom-checkout">
    <div class="mp-row-checkout">

        <!-- Cupom mode, creat a campaign on mercado pago -->
        <?php if ($coupon_mode == 'yes') : ?>
            <div  id="mercadopago-form-coupon-ticket" class="mp-col-md-12 mp-pb-20">
            <div class="frame-tarjetas mp-text-justify">
                <p class="mp-subtitle-ticket-checkout"><?=__('Enter your discount coupon', 'woocommerce-mercadopago')?></p>

                <div class="mp-row-checkout mp-pt-10">
                    <div class="mp-col-md-9 mp-pr-15">
                        <input type="text" class="mp-form-control" id="couponCodeTicket" name="mercadopago_ticket[coupon_code]" autocomplete="off" maxlength="24" placeholder="<?=__('Enter your coupon', 'woocommerce-mercadopago')?>" />
                    </div>

                    <div class="mp-col-md-3">
                        <input type="button" class="mp-button mp-pointer" id="applyCouponTicket" value="<?= esc_html__('Apply', 'woocommerce-mercadopago'); ?>">
                    </div>
                    <div class="mp-discount mp-col-md-9 mp-pr-15" id="mpCouponApplyedTicket"></div>
                    <span class="mp-erro_febraban" id="mpCouponErrorTicket"><?=__('The code you entered is incorrect', 'woocommerce-mercadopago')?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="mp-col-md-12">
            <div class="frame-tarjetas">
                <div id="mercadopago-form-ticket">

                    <?php if($site_id == "MLU"): ?>
                        <div id="form-ticket">
                            <div class="mp-row-checkout">
							<p class="mp-subtitle-custom-checkout"><?= __('Enter your document number', 'woocommerce-mercadopago') ?></p>
                            <div class="mp-col-md-4 mp-pr-15">
                                    <label for="mp-docType" class="mp-label-form mp-pt-5"><?= esc_html__('Type', 'woocommerce-mercadopago'); ?></label>
                                    <select id="mp-docType" class="form-control mp-form-control mp-select mp-pointer" name="mercadopago_ticket[docType]">
                                    <option value="CI" selected><?= esc_html__('CI', 'woocommerce-mercadopago'); ?></option>
                                    </select>
							</div>
                            <div class="mp-col-md-8" id="box-docnumber">
                                    <label for="cpfcnpj" id="mp_cpf_label" class="mp-label-form title-cpf"><?= esc_html__('Document number', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                    <input type="text" class="mp-form-control" id="mp_doc_number" data-checkout="mp_doc_number" name="mercadopago_ticket[docNumber]" onkeyup="mpMaskInput(this, mpTicketInteger);" autocomplete="off" maxlength="8">
                                    <span class="mp-erro_febraban" data-main="#mp_doc_number"><?= esc_html__('You must provide your document number', 'woocommerce-mercadopago'); ?></span>
                                    <span class="mp_error_docnumber" id="mp_error_docnumber"><?= esc_html__('Invalid Document Number', 'woocommerce-mercadopago'); ?></span>
                                </div>
                            </div>
                            <div class="mp-col-md-12 mp-pt-10">
                                <div class="frame-tarjetas">
                                    <div class="mp-row-checkout">
                                        <p class="mp-obrigatory"><?= esc_html__('Complete all fields, they are mandatory.', 'woocommerce-mercadopago'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($site_id == "MLB"): ?>
                    <div id="form-ticket">
                        <div class="mp-row-checkout">
                            <div class="mp-col-md-6">
                                <label for="mp_cpf_doc_type" class="mp-label-form-check mp-pointer">
                                    <input type="radio" name="mercadopago_ticket[docType]" class="mp-form-control-check" id="mp_cpf_doc_type" value="CPF" checked="checked" />
                                    <?= esc_html__('Physical person', 'woocommerce-mercadopago'); ?>
                                </label>
                            </div>

                            <div class="mp-col-md-6">
                                <label for="mp_cnpj_doc_type" class="mp-label-form-check mp-pointer">
                                    <input type="radio" name="mercadopago_ticket[docType]" class="mp-form-control-check" id="mp_cnpj_doc_type" value="CNPJ">
                                    <?= esc_html__('Legal person', 'woocommerce-mercadopago'); ?>
                                </label>
                            </div>
                        </div>

                        <div class="mp-row-checkout mp-pt-10">
                            <div class="mp-col-md-4 mp-pr-15" id="mp_box_firstname">
                                <label for="firstname" id="mp_firstname_label" class="mp-label-form title-name"><?= esc_html__('Name', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <label for="firstname" id="mp_socialname_label" class="title-razao-social mp-label-form"><?= esc_html__('Social reason', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <input type="text" class="mp-form-control" value="<?= $febraban['firstname']; ?>" id="mp_firstname" data-checkout="mp_firstname" name="mercadopago_ticket[firstname]">
                                <span class="mp-erro_febraban" data-main="#mp_firstname" id="error_firstname"><?= esc_html__('You must inform your name', 'woocommerce-mercadopago'); ?></span>
                            </div>

                            <div class="mp-col-md-4 mp-pr-15" id="mp_box_lastname">
                                <label for="lastname" class="mp-label-form title-name"><?= esc_html__('Surname', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <input type="text" class="mp-form-control" value="<?= $febraban['lastname']; ?>" id="mp_lastname" data-checkout="mp_lastname" name="mercadopago_ticket[lastname]">
                                <span class="mp-erro_febraban" data-main="#mp_lastname" id="error_lastname"><?= esc_html__('You must inform your last name', 'woocommerce-mercadopago'); ?></span>
                            </div>

                            <div class="mp-col-md-4" id="box-docnumber">
                                <label for="cpfcnpj" id="mp_cpf_label" class="mp-label-form title-cpf"><?= esc_html__('CPF', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <label for="cpfcnpj" id="mp_cnpj_label" class="title-cnpj mp-label-form"><?= esc_html__('CNPJ', 'woocommerce-mercadopago'); ?><em>*</em></label>
                                <input type="text" class="mp-form-control" value="<?= $febraban['docNumber']; ?>" id="mp_doc_number" data-checkout="mp_doc_number" name="mercadopago_ticket[docNumber]" onkeyup="mpMaskInput(this, mpCpf);" maxlength="14">
                                <span class="mp-erro_febraban" data-main="#mp_doc_number"><?= esc_html__('You must provide your document number', 'woocommerce-mercadopago'); ?></span>
                                <span class="mp_error_docnumber" id="mp_error_docnumber"><?= esc_html__('Invalid Document Number', 'woocommerce-mercadopago'); ?></span>
                            </div>
                        </div>

                        <div class="mp-row-checkout mp-pt-10">
                            <div class="mp-col-md-8 mp-pr-15" id="box-firstname">
                                <label for="address" class="mp-label-form"><?= esc_html__('Address', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <input type="text" value="<?= $febraban['address']; ?>" class="mp-form-control" id="mp_address" data-checkout="mp_address" name="mercadopago_ticket[address]">
                                <span class="mp-erro_febraban" data-main="#mp_address" id="error_address"><?= esc_html__('You must inform your address', 'woocommerce-mercadopago'); ?></span>
                            </div>

                            <div class="mp-col-md-4">
                                <label for="number" class="mp-label-form"><?= esc_html__('Number', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <input type="text" value="<?= $febraban['number']; ?>" class="mp-form-control" id="mp_number" data-checkout="mp_number" name="mercadopago_ticket[number]" onkeyup="mpMaskInput(this, mpTicketInteger);">
                                <span class="mp-erro_febraban" data-main="#mp_number" id="error_number"><?= esc_html__('You must provide your address number', 'woocommerce-mercadopago'); ?></span>
                            </div>
                        </div>

                        <div class="mp-row-checkout mp-pt-10">
                            <div class="mp-col-md-4 mp-pr-15">
                                <label for="city" class="mp-label-form"><?= esc_html__('City', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <input type="text" value="<?= $febraban['city']; ?>" class="mp-form-control" id="mp_city" data-checkout="mp_city" name="mercadopago_ticket[city]">
                                <span class="mp-erro_febraban" data-main="#mp_city" id="error_city"><?= esc_html__('You must inform your city', 'woocommerce-mercadopago'); ?></span>
                            </div>

                            <div class="mp-col-md-4 mp-pr-15">
                                <label for="state" class="mp-label-form"><?= esc_html__('State', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <select name="mercadopago_ticket[state]" id="mp_state" data-checkout="mp_state" class="mp-form-control mp-pointer">
                                    <option value="" <?php if ($febraban['state'] == '') { echo 'selected="selected"'; } ?>>
                                        <?= esc_html__('Select state"', 'woocommerce-mercadopago'); ?>
                                    </option>
                                    <option value="AC" <?php if ($febraban['state'] == 'AC') { echo 'selected="selected"'; } ?>>Acre</option>
                                    <option value="AL" <?php if ($febraban['state'] == 'AL') { echo 'selected="selected"'; } ?>>Alagoas</option>
                                    <option value="AP" <?php if ($febraban['state'] == 'AP') { echo 'selected="selected"'; } ?>>Amapá</option>
                                    <option value="AM" <?php if ($febraban['state'] == 'AM') { echo 'selected="selected"'; } ?>>Amazonas</option>
                                    <option value="BA" <?php if ($febraban['state'] == 'BA') { echo 'selected="selected"'; } ?>>Bahia</option>
                                    <option value="CE" <?php if ($febraban['state'] == 'CE') { echo 'selected="selected"'; } ?>>Ceará</option>
                                    <option value="DF" <?php if ($febraban['state'] == 'DF') { echo 'selected="selected"'; } ?>>Distrito</option>
                                    <option value="ES" <?php if ($febraban['state'] == 'ES') { echo 'selected="selected"'; } ?>>Espírito</option>
                                    <option value="GO" <?php if ($febraban['state'] == 'GO') { echo 'selected="selected"'; } ?>>Goiás</option>
                                    <option value="MA" <?php if ($febraban['state'] == 'MA') { echo 'selected="selected"'; } ?>>Maranhão</option>
                                    <option value="MT" <?php if ($febraban['state'] == 'MT') { echo 'selected="selected"'; } ?>>Mato</option>
                                    <option value="MS" <?php if ($febraban['state'] == 'MS') { echo 'selected="selected"'; } ?>>Mato</option>
                                    <option value="MG" <?php if ($febraban['state'] == 'MG') { echo 'selected="selected"'; } ?>>Minas</option>
                                    <option value="PA" <?php if ($febraban['state'] == 'PA') { echo 'selected="selected"'; } ?>>Pará</option>
                                    <option value="PB" <?php if ($febraban['state'] == 'PB') { echo 'selected="selected"'; } ?>>Paraíba</option>
                                    <option value="PR" <?php if ($febraban['state'] == 'PR') { echo 'selected="selected"'; } ?>>Paraná</option>
                                    <option value="PE" <?php if ($febraban['state'] == 'PE') { echo 'selected="selected"'; } ?>>Pernambuco</option>
                                    <option value="PI" <?php if ($febraban['state'] == 'PI') { echo 'selected="selected"'; } ?>>Piauí</option>
                                    <option value="RJ" <?php if ($febraban['state'] == 'RJ') { echo 'selected="selected"'; } ?>>Rio de Janeiro</option>
                                    <option value="RN" <?php if ($febraban['state'] == 'RN') { echo 'selected="selected"'; } ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?php if ($febraban['state'] == 'RS') { echo 'selected="selected"'; } ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?php if ($febraban['state'] == 'RO') { echo 'selected="selected"'; } ?>>Rondônia</option>
                                    <option value="RA" <?php if ($febraban['state'] == 'RA') { echo 'selected="selected"'; } ?>>Roraima</option>
                                    <option value="SC" <?php if ($febraban['state'] == 'SC') { echo 'selected="selected"'; } ?>>Santa</option>
                                    <option value="SP" <?php if ($febraban['state'] == 'SP') { echo 'selected="selected"'; } ?>>São Paulo</option>
                                    <option value="SE" <?php if ($febraban['state'] == 'SE') { echo 'selected="selected"'; } ?>>Sergipe</option>
                                    <option value="TO" <?php if ($febraban['state'] == 'TO') { echo 'selected="selected"'; } ?>>Tocantins</option>
                                </select>
                                <span class="mp-erro_febraban" data-main="#mp_state" id="error_state"><?php echo esc_html__('You must inform your status', 'woocommerce-mercadopago'); ?></span>
                            </div>

                            <div class="mp-col-md-4">
                                <label for="zipcode" class="mp-label-form"><?= esc_html__('Postal Code', 'woocommerce-mercadopago'); ?> <em>*</em></label>
                                <input type="text" value="<?= $febraban['zipcode']; ?>" id="mp_zipcode" data-checkout="mp_zipcode" class="mp-form-control" name="mercadopago_ticket[zipcode]" maxlength="9" onkeyup="mpMaskInput(this, mpCep);">
                                <span class="mp-erro_febraban" data-main="#mp_zipcode" id="error_zipcode"><?= esc_html__('You must provide your zip code', 'woocommerce-mercadopago'); ?></span>
                            </div>
                        </div>

                        <div class="mp-col-md-12 mp-pt-10">
                            <div class="frame-tarjetas">
                                <div class="mp-row-checkout">
                                    <p class="mp-obrigatory"><?= esc_html__('Complete all fields, they are mandatory.', 'woocommerce-mercadopago'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="mp-col-md-12 <?= ($site_id == 'MLB') ? 'mp-pt-20' : ''; ?>">
                        <div class="frame-tarjetas">
                            <p class="mp-subtitle-ticket-checkout"><?=__('Select the issuer with whom you want to process the payment', 'woocommerce-mercadopago')?></p>

                            <div class="mp-row-checkout mp-pt-10">
                                <?php $atFirst = true; ?>
                                <?php foreach ($payment_methods as $payment) : ?>
                                <div id="frameTicket" class="mp-col-md-6 mp-pb-15 mp-min-hg">
                                        <div id="paymentMethodIdTicket" class="mp-ticket-payments">
                                            <label for="<?= $payment['id']; ?>" class="mp-label-form mp-pointer">
                                                <input type="radio" class="mp-form-control-check" name="mercadopago_ticket[paymentMethodId]" id="<?= $payment['id'] ?>" value="<?= $payment['id']; ?>" <?php if ($atFirst) : ?> checked="checked" <?php endif; ?> />
                                                <img src="<?= $payment['secure_thumbnail'] ?>" class="mp-img-ticket" alt="<?= $payment['name']; ?>" />
                                                <span class="mp-ticket-name"><?= $payment['name'] ?></span>
                                            </label>
                                        </div>
                                        <?php $atFirst = false; ?>
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
            <input type="hidden" id="site_id" value="<?php echo $site_id; ?>" name="mercadopago_ticket[site_id]" />
            <input type="hidden" id="amountTicket" value="<?php echo $amount; ?>" name="mercadopago_ticket[amount]" />
            <input type="hidden" id="currency_ratioTicket" value="<?php echo $currency_ratio; ?>" name="mercadopago_ticket[currency_ratio]" />
            <input type="hidden" id="campaign_idTicket" name="mercadopago_ticket[campaign_id]" />
            <input type="hidden" id="campaignTicket" name="mercadopago_ticket[campaign]" />
            <input type="hidden" id="discountTicket" name="mercadopago_ticket[discount]" />
        </div>

    </div>
</div>

<script type="text/javascript">
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
