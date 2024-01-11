<?php

/**
 * @var bool $test_mode
 * @var string $test_mode_title
 * @var string $test_mode_description
 * @var string $test_mode_link_text
 * @var string $test_mode_link_src
 * @var string $site_id
 * @var string $input_document_label
 * @var string $input_document_helper
 * @var string $ticket_text_label
 * @var string $input_table_button
 * @var string $payment_methods
 * @var string $input_helper_label
 * @var string $amount
 * @var string $currency_ratio
 * @var string $terms_and_conditions_description
 * @var string $terms_and_conditions_link_text
 * @var string $terms_and_conditions_link_src
 *
 * @see \MercadoPago\Woocommerce\Gateways\TicketGateway
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class='mp-checkout-container'>
    <div class="mp-checkout-ticket-container">
        <div class="mp-checkout-ticket-content">
            <?php if ($test_mode) : ?>
                <div class="mp-checkout-ticket-test-mode">
                    <test-mode
                        title="<?= esc_html($test_mode_title); ?>"
                        description="<?= esc_html($test_mode_description); ?>"
                        link-text="<?= esc_html($test_mode_link_text); ?>"
                        link-src="<?= esc_html($test_mode_link_src); ?>">
                    </test-mode>
                </div>
            <?php endif; ?>

            <?php if ($site_id === 'MLU') : ?>
                <div class="mp-checkout-ticket-input-document">
                    <input-document
                        label-message="<?= esc_html($input_document_label); ?>"
                        helper-message="<?= esc_html($input_document_helper); ?>"
                        input-name='mercadopago_ticket[doc_number]'
                        select-name='mercadopago_ticket[doc_type]'
                        select-id='doc_type'
                        flag-error='mercadopago_ticket[docNumberError]'
                        documents='["CI","OTRO"]'
                        validate=true>
                    </input-document>
                </div>
            <?php endif; ?>

            <?php if ($site_id === 'MLB') : ?>
                <div class="mp-checkout-ticket-input-document">
                    <input-document
                        label-message="<?= esc_html($input_document_label); ?> "
                        helper-message="<?= esc_html($input_document_helper); ?>"
                        input-name='mercadopago_ticket[doc_number]'
                        select-name='mercadopago_ticket[doc_type]'
                        select-id='doc_type'
                        flag-error='mercadopago_ticket[docNumberError]'
                        documents='["CPF","CNPJ"]'
                        validate=true>
                    </input-document>
                </div>
            <?php endif; ?>

            <p class="mp-checkout-ticket-text" data-cy="checkout-ticket-text">
                <?= esc_html($ticket_text_label); ?>
            </p>

            <input-table
                name="mercadopago_ticket[payment_method_id]"
                button-name=<?= esc_html($input_table_button); ?>
                columns='<?= esc_attr(wp_json_encode($payment_methods)); ?>'>
            </input-table>

            <input-helper
                isVisible=false
                message="<?= esc_html($input_helper_label); ?>"
                input-id="mp-payment-method-helper"
                id="payment-method-helper">
            </input-helper>

            <!-- NOT DELETE LOADING-->
            <div id="mp-box-loading"></div>

            <!-- utilities -->
            <div id="mercadopago-utilities" style="display:none;">
                <input type="hidden" id="site_id" value="<?= esc_textarea($site_id); ?>" name="mercadopago_ticket[site_id]" />
                <input type="hidden" id="ticket_amount" value="<?= esc_textarea($amount); ?>" name="mercadopago_ticket[amount]" />
                <input type="hidden" id="ticket_currency_ratio" value="<?= esc_textarea($currency_ratio); ?>" name="mercadopago_ticket[currency_ratio]" />
                <input type="hidden" id="ticket_campaign_id" name="mercadopago_ticket[campaign_id]" />
                <input type="hidden" id="ticket_campaign" name="mercadopago_ticket[campaign]" />
                <input type="hidden" id="ticket_discount" name="mercadopago_ticket[discount]" />
            </div>
        </div>

        <div class="mp-checkout-ticket-terms-and-conditions">
            <terms-and-conditions
                description="<?= esc_html($terms_and_conditions_description); ?>"
                link-text="<?= esc_html($terms_and_conditions_link_text); ?>"
                link-src="<?= esc_html($terms_and_conditions_link_src); ?>">
            </terms-and-conditions>
        </div>
    </div>
</div>

<script type="text/javascript">
    if (document.getElementById("payment_method_woo-mercado-pago-custom")) {
        jQuery("form.checkout").on("checkout_place_order_woo-mercado-pago-ticket", function() {
            cardFormLoad();
        });
    }
</script>
