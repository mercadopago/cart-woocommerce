<?php

/**
 * @var bool $test_mode
 * @var string $test_mode
 * @var string $test_mode_title
 * @var string $test_mode_description
 * @var string $test_mode_link_text
 * @var string $test_mode_link_src
 * @var string $wallet_button
 * @var string $wallet_button_image
 * @var string $wallet_button_title
 * @var string $wallet_button_description
 * @var string $wallet_button_button_text
 * @var string $available_payments_title_icon
 * @var string $available_payments_title
 * @var string $available_payments_image
 * @var string $available_payments_chevron_up
 * @var string $available_payments_chevron_down
 * @var string $payment_methods_items
 * @var string $payment_methods_promotion_link
 * @var string $payment_methods_promotion_text
 * @var string $site_id
 * @var string $card_form_title
 * @var string $card_number_input_label
 * @var string $card_number_input_helper
 * @var string $card_holder_name_input_label
 * @var string $card_holder_name_input_helper
 * @var string $card_expiration_input_label
 * @var string $card_expiration_input_helper
 * @var string $card_security_code_input_label
 * @var string $card_security_code_input_helper
 * @var string $card_document_input_label
 * @var string $card_document_input_helper
 * @var string $card_installments_title
 * @var string $card_issuer_input_label
 * @var string $card_installments_input_helper
 * @var string $terms_and_conditions_description
 * @var string $terms_and_conditions_link_text
 * @var string $terms_and_conditions_link_src
 * @var string $amount
 * @var string $currency_ratio
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
    <div class='mp-checkout-custom-container'>
        <?php if ($test_mode) : ?>
            <div class="mp-checkout-pro-test-mode">
                <test-mode
                    title="<?= esc_html($test_mode_title) ?>"
                    description="<?= esc_html($test_mode_description) ?>"
                    link-text="<?= esc_html($test_mode_link_text) ?>"
                    link-src="<?= esc_html($test_mode_link_src) ?>"
                >
                </test-mode>
            </div>
        <?php endif; ?>

        <?php if ($wallet_button === 'yes') : ?>
            <div class='mp-wallet-button-container'>
                <img src="<?= esc_url($wallet_button_image); ?>">

                <div class='mp-wallet-button-title'>
                    <span><?= esc_html($wallet_button_title); ?></span>
                </div>

                <div class='mp-wallet-button-description'>
                    <?= esc_html($wallet_button_description); ?>
                </div>

                <div class='mp-wallet-button-button'>
                    <button id="mp-wallet-button" onclick="submitWalletButton(event)">
                        <?= esc_html($wallet_button_button_text); ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div id="mp-custom-checkout-form-container">
            <div class='mp-checkout-custom-available-payments'>
                <div class='mp-checkout-custom-available-payments-header'>
                    <div class="mp-checkout-custom-available-payments-title">
                        <img src="<?= esc_url($available_payments_title_icon); ?>" class='mp-icon'>
                        <p class="mp-checkout-custom-available-payments-text">
                            <?= esc_html($available_payments_title); ?>
                        </p>
                    </div>

                    <img
                        src="<?= esc_url($available_payments_image); ?>"
                        class='mp-checkout-custom-available-payments-collapsible'
                    />
                </div>

                <div class='mp-checkout-custom-available-payments-content'>
                    <payment-methods methods='<?= esc_html($payment_methods_items); ?>'></payment-methods>

                    <?php if ($site_id === 'MLA') : ?>
                        <span id="mp_promotion_link"> | </span>
                        <a
                            href='<?= esc_url($payment_methods_promotion_link); ?>'
                            id="mp_checkout_link"
                            class="mp-checkout-link mp-pl-10"
                            target="_blank"
                        >
                            <?= esc_html($payment_methods_promotion_text); ?>
                        </a>
                    <?php endif; ?>
                    <hr>
                </div>
            </div>

            <div class='mp-checkout-custom-card-form'>
                <p class='mp-checkout-custom-card-form-title'>
                    <?= esc_html($card_form_title); ?>
                </p>

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
                        placeholder="Ex.: María López"
                        id="form-checkout__cardholderName"
                        name="mp-card-holder-name"
                        data-checkout="cardholderName"
                    />

                    <input-helper
                        isVisible=false
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

                        <div id="form-checkout__securityCode-container" class="mp-checkout-custom-card-input"></div>

                        <p id="mp-security-code-info" class="mp-checkout-custom-info-text"></p>

                        <input-helper
                            isVisible=false
                            message="<?= esc_html($card_security_code_input_helper); ?>"
                            input-id="mp-security-code-helper"
                        >
                        </input-helper>
                    </div>
                </div>

                <div id="mp-doc-div" class="mp-checkout-custom-input-document" style="display: none;">
                    <input-document
                        label-message="<?= esc_html($card_document_input_label); ?>"
                        helper-message="<?= esc_html($card_document_input_helper); ?>"
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

            <div id="mp-checkout-custom-installments" class="mp-checkout-custom-installments-display-none">
                <p class='mp-checkout-custom-card-form-title'>
                    <?= esc_html($card_installments_title); ?>
                </p>

                <div id="mp-checkout-custom-issuers-container" class="mp-checkout-custom-issuers-container">
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

                <input-helper
                    isVisible=false
                    message="<?= esc_html($card_installments_input_helper); ?>"
                    input-id="mp-installments-helper"
                >
                </input-helper>

                <select
                    style="display: none;"
                    data-checkout="installments"
                    name="installments"
                    id="form-checkout__installments"
                    class="mp-input-select-select"
                >
                </select>

                <div id="mp-checkout-custom-box-input-tax-cft">
                    <div id="mp-checkout-custom-box-input-tax-tea">
                        <div id="mp-checkout-custom-tax-tea-text"></div>
                    </div>
                    <div id="mp-checkout-custom-tax-cft-text"></div>
                </div>
            </div>

            <div class="mp-checkout-custom-terms-and-conditions">
                <terms-and-conditions
                    description="<?= esc_html($terms_and_conditions_description) ?>"
                    link-text="<?= esc_html($terms_and_conditions_link_text) ?>"
                    link-src="<?= esc_html($terms_and_conditions_link_src) ?>"
                >
                </terms-and-conditions>
            </div>
        </div>
    </div>
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
</div>

<script type="text/javascript">
    function submitWalletButton(event) {
        event.preventDefault();
        jQuery('#mp_checkout_type').val('wallet_button');
        jQuery('form.checkout, form#order_review').submit();
    }

    var availablePayment = document.getElementsByClassName('mp-checkout-custom-available-payments')[0];
    var collapsible = availablePayment.getElementsByClassName('mp-checkout-custom-available-payments-header')[0];

    collapsible.addEventListener("click", function() {
        const icon = collapsible.getElementsByClassName('mp-checkout-custom-available-payments-collapsible')[0];
        const content = availablePayment.getElementsByClassName('mp-checkout-custom-available-payments-content')[0];

        if (content.style.maxHeight) {
            content.style.maxHeight = null;
            content.style.padding = "0px";
            icon.src = "<?= esc_url($available_payments_chevron_down); ?>";
        } else {
            let hg = content.scrollHeight + 15 + "px";
            content.style.setProperty("max-height", hg, "important");
            content.style.setProperty("padding", "24px 0px 0px", "important");
            icon.src = "<?= esc_url($available_payments_chevron_up); ?>";
        }
    });
</script>

