<?php

/**
 * @var bool $test_mode
 * @var string $test_mode_title
 * @var string $test_mode_description
 * @var string $test_mode_link_text
 * @var string $test_mode_link_src
 * @var string $input_document_label
 * @var string $input_document_helper
 * @var string $pse_text_label
 * @var string $input_table_button
 * @var string $payment_methods
 * @var string $amount
 * @var string $currency_ratio
 * @var array  $financial_institutions
 * @var string $person_type_label
 * @var string $financial_institutions_label
 * @var string $financial_institutions_helper
 * @var string $financial_placeholder
 * @var string $site_id
 * @var string $terms_and_conditions_description
 * @var string $terms_and_conditions_link_text
 * @var string $terms_and_conditions_link_src
 *
 * @see \MercadoPago\Woocommerce\Gateways\PseGateway
 */

if (! defined('ABSPATH')) {
    exit;
}
?>

<div class='mp-checkout-container'>
    <div class="mp-checkout-pse-container">
        <p class="mp-checkout-pse-text" data-cy="checkout-pse-text">
            <?= esc_html($pse_text_label); ?>
        </p>
        <div class="mp-checkout-pse-content">
            <?php if ($test_mode) : ?>
                <div class="mp-checkout-pse-test-mode">
                    <test-mode
                        title="<?= esc_html($test_mode_title); ?>"
                        description="<?= esc_html($test_mode_description); ?>"
                        link-text="<?= esc_html($test_mode_link_text); ?>"
                        link-src="<?= esc_html($test_mode_link_src); ?>">
                    </test-mode>
                </div>
            <?php endif; ?>
            <div class="mp-checkout-pse-person">
                <input-select
                    name="mercadopago_pse[person_type]"
                    label=<?= esc_html($person_type_label); ?>
                    optional="false"
                    options='[{"id":"individual", "description": "individual"},{"id":"association", "description": "asociación"}]'
                >
                </input-select>
            </div>
                <div class="mp-checkout-pse-input-document">
                    <input-document
                        label-message="<?= esc_html($input_document_label); ?>"
                        helper-message="<?= esc_html($input_document_helper); ?>"
                        input-name='mercadopago_pse[doc_number]'
                        select-name='mercadopago_pse[doc_type]'
                        select-id='doc_type'
                        flag-error='mercadopago_pse[docNumberError]'
                        documents='["CC","CE","NIT"]'
                        validate=true>
                    </input-document>
                </div>
            <div class="mp-checkout-pse-bank">
                <input-select
                    name="mercadopago_pse[bank]"
                    label="<?= esc_html($financial_institutions_label); ?>"
                    optional="false"
                    options='<?php print_r($financial_institutions); ?>'
                    hidden-id= "hidden-financial-pse"
                    helper-message="<?= esc_html($financial_institutions_helper); ?>"
                    default-option="<?= esc_html($financial_placeholder); ?>">
                </input-select>
            </div>


            </div>

            <!-- NOT DELETE LOADING-->
            <div id="mp-box-loading"></div>

            <!-- utilities -->
            <div id="mercadopago-utilities" style="display:none;">
                <input type="hidden" id="amountPse" value="<?= esc_textarea($amount); ?>" name="mercadopago_pse[amount]" />
                <input type="hidden" id="site_id" value="<?= esc_textarea($site_id); ?>" name="mercadopago_pse[site_id]" />
                <input type="hidden" id="currency_ratioPse" value="<?= esc_textarea($currency_ratio); ?>" name="mercadopago_pse[currency_ratio]" />
                <input type="hidden" id="campaign_idPse" name="mercadopago_pse[campaign_id]" />
                <input type="hidden" id="campaignPse" name="mercadopago_pse[campaign]" />
                <input type="hidden" id="discountPse" name="mercadopago_pse[discount]" />
            </div>

            <div class="mp-checkout-pse-terms-and-conditions">
                <terms-and-conditions
                    description="<?= esc_html($terms_and_conditions_description); ?>"
                    link-text="<?= esc_html($terms_and_conditions_link_text); ?>"
                    link-src="<?= esc_html($terms_and_conditions_link_src); ?>">
                </terms-and-conditions>
            </div>
        </div>
</div>
<div>
</div>
<script type="text/javascript">
    if (document.getElementById("payment_method_woo-mercado-pago-custom")) {
        jQuery("form.checkout").on("checkout_place_order_woo-mercado-pago-pse", function() {
            cardFormLoad();
        });
    }

</script>
