<?php

use MercadoPago\Woocommerce\Helpers\Template;

/**
 * @var bool $test_mode
 * @var string $test_mode_title
 * @var string $test_mode_description
 * @var string $test_mode_link_text
 * @var string $test_mode_link_src
 * @var string $wallet_button
 * @var string $wallet_button_image
 * @var string $wallet_button_title
 * @var string $wallet_button_description
 * @var string $site_id
 * @var string $card_number_input_label
 * @var string $card_number_input_helper
 * @var string $card_holder_name_input_label
 * @var string $card_holder_name_input_helper
 * @var string $placeholders_cardholder_name
 * @var string $card_expiration_input_label
 * @var string $card_expiration_input_helper
 * @var string $card_security_code_input_label
 * @var string $card_security_code_input_helper
 * @var string $card_document_input_label
 * @var string $card_input_document_helper_empty
 * @var string $card_input_document_helper_invalid
 * @var string $card_input_document_helper_wrong
 * @var string $card_issuer_input_label
 * @var string $amount
 * @var string $currency_ratio
 * @var string $message_error_amount
 * @var string $security_code_tooltip_text_3_digits
 *
 * @see \MercadoPago\Woocommerce\Gateways\CustomGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="mp-checkout-custom-load">
    <div class="spinner-card-form"></div>
</div>
<div class='mp-checkout-container'>
    <?php if ($amount === null) : ?>
        <?php Template::render('public/checkouts/alert-message', ['message' => $message_error_amount]) ?>
    <?php else : ?>
        <div class='mp-checkout-custom-container'>
            <?php if ($test_mode) : ?>
                <test-mode
                    title="<?= esc_html($test_mode_title) ?>"
                    description="<?= esc_html($test_mode_description) ?>"
                    link-text="<?= esc_html($test_mode_link_text) ?>"
                    link-src="<?= esc_html($test_mode_link_src) ?>"
                >
                </test-mode>
            <?php endif; ?>

            <?php if ($wallet_button === 'yes') : ?>
                <div class='mp-wallet-button-container'>

                    <div class='mp-wallet-button-title'>
                        <span><?= esc_html($wallet_button_title); ?></span>
                    </div>

                    <div class='mp-wallet-button-description'>
                        <?= esc_html($wallet_button_description); ?>
                    </div>

                    <div class='mp-wallet-button-button'>
                        <button id="mp-wallet-button" onclick="submitWalletButton(event)">
                            <img src="<?= esc_url($wallet_button_image); ?>">
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <div id="mp-custom-checkout-form-container">
                <div class='mp-checkout-custom-card-form'>
                    <div class='mp-checkout-custom-card-row'>
                        <input-label
                            isOptinal=false
                            message="<?= esc_html($card_number_input_label); ?>"
                            for='mp-card-number'
                        >
                        </input-label>

                        <div class="mp-checkout-custom-card-input" id="form-checkout__cardNumber-container"></div>

                        <input-helper
                            isVisible=false
                            type="error"
                            message="<?= esc_html($card_number_input_helper); ?>"
                            input-id="mp-card-number-helper"
                        >
                        </input-helper>
                    </div>

                    <div class='mp-checkout-custom-card-row' id="mp-card-holder-div">
                        <input-label
                            message="<?= esc_html($card_holder_name_input_label); ?>"
                            isOptinal=false
                        >
                        </input-label>

                        <input
                            class="mp-checkout-custom-card-input mp-card-holder-name"
                            placeholder="<?= esc_html($placeholders_cardholder_name); ?>"
                            id="form-checkout__cardholderName"
                            name="mp-card-holder-name"
                            data-checkout="cardholderName"
                        />

                        <input-helper
                            isVisible=false
                            type="error"
                            message="<?= esc_html($card_holder_name_input_helper); ?>"
                            input-id="mp-card-holder-name-helper"
                            data-main="mp-card-holder-name"
                        >
                        </input-helper>
                    </div>

                    <div class='mp-checkout-custom-card-row mp-checkout-custom-dual-column-row'>
                        <div class='mp-checkout-custom-card-column'>
                            <input-label
                                message="<?= esc_html($card_expiration_input_label); ?>"
                                isOptinal=false
                            >
                            </input-label>

                            <div
                                id="form-checkout__expirationDate-container"
                                class="mp-checkout-custom-card-input mp-checkout-custom-left-card-input"
                            >
                            </div>

                            <input-helper
                                isVisible=false
                                type="error"
                                message="<?= esc_html($card_expiration_input_helper); ?>"
                                input-id="mp-expiration-date-helper"
                            >
                            </input-helper>
                        </div>

                        <div class='mp-checkout-custom-card-column'>
                            <input-label
                                message="<?= esc_html($card_security_code_input_label); ?>"
                                isOptinal=false
                            >
                            </input-label>

                            <div class="mp-checkout-custom-security-code-container">
                                <div id="form-checkout__securityCode-container" class="mp-checkout-custom-security-code-input"></div>
                                <span
                                    id="mp-security-code-info"
                                    tabindex="0"
                                    aria-label="<?= esc_html($security_code_tooltip_text_3_digits); ?>"
                                    class="mp-checkout-custom-security-code-tooltip"
                                    role="tooltip"
                                    data-tooltip="<?= esc_html($security_code_tooltip_text_3_digits); ?>"
                                >?</span>
                            </div>

                            <input-helper
                                isVisible=false
                                type="error"
                                input-id="mp-security-code-helper"
                            >
                            </input-helper>
                        </div>
                    </div>

                    <div id="mp-doc-div" class="mp-checkout-custom-input-document" style="display: none;">
                        <input-document
                            label-message="<?= esc_html($card_document_input_label); ?>"
                            helper-invalid="<?= esc_html($card_input_document_helper_invalid); ?>"
                            helper-empty="<?= esc_html($card_input_document_helper_empty); ?>"
                            helper-wrong="<?= esc_html($card_input_document_helper_wrong); ?>"
                            input-name="identificationNumber"
                            hidden-id="form-checkout__identificationNumber"
                            input-data-checkout="doc_number"
                            select-id="form-checkout__identificationType"
                            select-name="identificationType"
                            select-data-checkout="doc_type"
                            flag-error="docNumberError"
                        >
                        </input-document>
                    </div>
                </div>

                <div id="mp-checkout-custom-installments-card" class="mp-checkout-custom-installments-display-none">
                    <div id="mp-checkout-custom-issuers-container" class="mp-checkout-custom-issuers-container-display-none">
                        <div class='mp-checkout-custom-card-row'>
                            <input-label
                                isOptinal=false
                                message="<?= esc_html($card_issuer_input_label); ?>"
                                for='mp-issuer'
                            >
                            </input-label>
                        </div>

                        <div class="mp-input-select-input">
                            <select name="issuer" id="form-checkout__issuer" class="mp-input-select-select"></select>
                        </div>
                    </div>

                    <div id="mp-checkout-custom-installments-container" class="mp-checkout-custom-installments-container"></div>

                    <select
                        style="display: none;"
                        data-checkout="installments"
                        name="installments"
                        id="form-checkout__installments"
                        class="mp-input-select-select"
                    >
                    </select>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="mercadopago-utilities" style="display:none;">
    <input type="hidden" id="mp-amount" value='<?= esc_textarea($amount); ?>' name="mercadopago_custom[amount]"/>
    <input type="hidden" id="currency_ratio" value='<?= esc_textarea($currency_ratio); ?>' name="mercadopago_custom[currency_ratio]"/>
    <input type="hidden" id="paymentMethodId" name="mercadopago_custom[payment_method_id]"/>
    <input type="hidden" id="mp_checkout_type" name="mercadopago_custom[checkout_type]" value="custom"/>
    <input type="hidden" id="cardExpirationMonth" data-checkout="cardExpirationMonth"/>
    <input type="hidden" id="cardExpirationYear" data-checkout="cardExpirationYear"/>
    <input type="hidden" id="cardTokenId" name="mercadopago_custom[token]"/>
    <input type="hidden" id="cardInstallments" name="mercadopago_custom[installments]"/>
    <input type="hidden" id="mpCardSessionId" name="mercadopago_custom[session_id]" />
    <input type="hidden" id="paymentTypeId" name="mercadopago_custom[payment_type_id]"/>
    <input type="hidden" id="payerDocNumber" name="mercadopago_custom[doc_number]" />
    <input type="hidden" id="payerDocType" name="mercadopago_custom[doc_type]" />
</div>

<script type="text/javascript">
    function submitWalletButton(event) {
        event.preventDefault();
        jQuery('#mp_checkout_type').val('wallet_button');
        jQuery('form.checkout, form#order_review').submit();
    }
</script>

