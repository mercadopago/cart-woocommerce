<?php

/**
 * @var string $input_document_label
 * @var string $input_document_helper_empty
 * @var string $input_document_helper_invalid
 * @var string $input_document_helper_wrong
 * @var array $mlb_states
 * @var string $billing_data_title
 * @var string $billing_data_checkbox_label
 * @var string $billing_data_postalcode_label
 * @var string $billing_data_postalcode_placeholder
 * @var string $billing_data_state_label
 * @var string $billing_data_state_placeholder
 * @var string $billing_data_city_label
 * @var string $billing_data_city_placeholder
 * @var string $billing_data_neighborhood_label
 * @var string $billing_data_neighborhood_placeholder
 * @var string $billing_data_address_label
 * @var string $billing_data_address_placeholder
 * @var string $billing_data_address_comp_label
 * @var string $billing_data_address_comp_placeholder
 * @var string $billing_data_number_label
 * @var string $billing_data_number_placeholder
 * @var string $billing_data_number_toggle_label
 * @see \MercadoPago\Woocommerce\Gateways\TicketGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<p class="mp-checkout-ticket-billing-text" data-cy="checkout-ticket-billing-text">
    <?= esc_html($billing_data_title); ?>
</p>

<div class="mp-checkout-ticket-billing-checkbox">
    <input id="form-checkout__address_checkbox" type="checkbox" />
    <p><?= esc_html($billing_data_checkbox_label); ?></p>
</div>

<div class="mp-checkout-ticket-input-document">
    <input-document
        label-message="<?= esc_html($input_document_label); ?> "
        helper-invalid="<?= esc_html($input_document_helper_invalid); ?>"
        helper-empty="<?= esc_html($input_document_helper_empty); ?>"
        helper-wrong="<?= esc_html($input_document_helper_wrong); ?>"
        input-name='mercadopago_ticket[doc_number]'
        select-name='mercadopago_ticket[doc_type]'
        select-id='doc_type'
        flag-error='mercadopago_ticket[docNumberError]'
        documents='["CPF","CNPJ"]'
        validate=true>
    </input-document>
</div>

<div class="mp-checkout-ticket-address-container">
    <div class="mp-checkout-ticket-billing-input-row">
        <div class="mp-checkout-ticket-billing-input-column">
            <label for="form-checkout__address_zip_code"><?= esc_html($billing_data_postalcode_label); ?> <b style="color: red;">*</b></label>
            <input
                class='mp-checkout-andes-input'
                placeholder='<?= esc_html($billing_data_postalcode_placeholder) ?>'
                id='form-checkout__address_zip_code'
                name='mercadopago_ticket[address_zip_code]'
                data-checkout='address_zip_code'
                maxlength="9"
                required
            />
            <input-helper type="error" input-id="form-checkout__address_zip_code_error" message="" isvisible="false" />
        </div>
        <div class="mp-checkout-ticket-billing-input-column">
            <label for="form-checkout__address_federal_unit"><?= esc_html($billing_data_state_label); ?> <b style="color: red;">*</b></label>
            <select
                class='mp-checkout-andes-input'
                id='form-checkout__address_federal_unit'
                name='mercadopago_ticket[address_federal_unit]'
                data-checkout='address_federal_unit'
                required
            >
                <option value="" disabled selected><?= esc_html($billing_data_state_placeholder); ?></option>
                <?php foreach ($mlb_states as $key => $state) { ?>
                    <option value="<?= esc_html($key); ?>"><?= esc_html($state); ?></option>
                <?php } ?>
            </select>
            <input-helper type="error" input-id="form-checkout__address_federal_unit_error" message="" isvisible="false" />
        </div>
    </div>
    <div class="mp-checkout-ticket-billing-input-row">
        <div class="mp-checkout-ticket-billing-input-column">
            <label for="form-checkout__address_city"><?= esc_html($billing_data_city_label); ?> <b style="color: red;">*</b></label>
            <input
                class='mp-checkout-andes-input'
                placeholder='<?= esc_html($billing_data_city_placeholder); ?>'
                id='form-checkout__address_city'
                name='mercadopago_ticket[address_city]'
                data-checkout='address_city'
                required
            />
            <input-helper type="error" input-id="form-checkout__address_city_error" message="" isvisible="false" />
        </div>
        <div class="mp-checkout-ticket-billing-input-column">
            <label for="form-checkout__address_neighborhood"><?= esc_html($billing_data_neighborhood_label); ?> <b style="color: red;">*</b></label>
            <input
                class='mp-checkout-andes-input'
                placeholder='<?= esc_html($billing_data_neighborhood_placeholder); ?>'
                id='form-checkout__address_neighborhood'
                name='mercadopago_ticket[address_neighborhood]'
                data-checkout='address_neighborhood'
                required
            />
            <input-helper type="error" input-id="form-checkout__address_neighborhood_error" message="" isvisible="false" />
        </div>
    </div>
    <div class="mp-checkout-ticket-billing-input-row">
        <div class="mp-checkout-ticket-billing-input-column">
            <label for="form-checkout__address_street_name"><?= esc_html($billing_data_address_label); ?> <b style="color: red;">*</b></label>
            <input
                class='mp-checkout-andes-input'
                placeholder='<?= esc_html($billing_data_address_placeholder); ?>'
                id='form-checkout__address_street_name'
                name='mercadopago_ticket[address_street_name]'
                data-checkout='address_street_name'
                required
            />
            <input-helper type="error" input-id="form-checkout__address_street_name_error" message="" isvisible="false" />
        </div>
    </div>
    <div class="mp-checkout-ticket-billing-input-row">
        <div class="mp-checkout-ticket-billing-input-column">
            <label for="form-checkout__address_street_number"><?= esc_html($billing_data_number_label); ?> <b style="color: red;">*</b></label>
            <div class="mp-checkout-ticket-billing-input-number">
                <input
                    placeholder='<?= esc_html($billing_data_number_placeholder); ?>'
                    id='form-checkout__address_street_number'
                    name='mercadopago_ticket[address_street_number]'
                    data-checkout='address_street_number'
                    required
                />
                <div class="mp-checkout-ticket-billing-number-toggle-checkbox">
                    <p><?= esc_html($billing_data_number_toggle_label); ?></p>
                    <input type="checkbox" id="form-checkout__address_number_toggle" />
                </div>
            </div>
            <input-helper type="error" input-id="form-checkout__address_street_number_error" message="" isvisible="false" />
        </div>
        <div class="mp-checkout-ticket-billing-input-column">
            <label for="form-checkout__address_complement"><?= esc_html($billing_data_address_comp_label); ?></label>
            <input
                class='mp-checkout-andes-input'
                placeholder='<?= esc_html($billing_data_address_comp_placeholder); ?>'
                id='form-checkout__address_complement'
                name='mercadopago_ticket[address_complement]'
                data-checkout='form-checkout__address_complement'
            />
        </div>
    </div>
</div>