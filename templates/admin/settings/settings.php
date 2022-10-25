<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<script>
    window.addEventListener("load", function () {
        mp_settings_screen_load();
    });
</script>

<div class="mp-settings">
    <div class="mp-settings-header">
        <div class="mp-settings-header-img"></div>
        <div class="mp-settings-header-logo"></div>
        <hr class="mp-settings-header-hr"/>
        <p class="mp-settings-header-title">Accept <b>payments on the spot</b> with <br/> the security from Mercado Pago</p>
    </div>

    <div class="mp-settings-requirements">
        <div class="mp-container">
            <div class="mp-block mp-block-requirements mp-settings-margin-right">
                <p class="mp-settings-font-color mp-settings-title-font-size">Technical requirements</p>
                <div class="mp-inner-container">
                    <div>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size">SSL</p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size"><b>SSL</b></p>
                                Implementation responsible for transmitting data to Mercado Pago in a secure and encrypted way.
                            </span>
                        </label>
                    </div>
                    <div>
                        <div id="mp-req-ssl" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
                    </div>
                </div>
                <hr>

                <div class="mp-inner-container">
                    <div>GD Extensions</p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size"><b>GD Extensions</b></p>
                                These extensions are responsible for the implementation and operation of Pix in your store.
                            </span>
                        </label>
                    </div>
                    <div>
                        <div id="mp-req-gd" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
                    </div>
                </div>
                <hr>

                <div class="mp-inner-container">
                    <div>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size">Curl</p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size"><b>Curl</b></p>
                                It is an extension responsible for making payments via requests from the plugin to Mercado Pago.
                            </span>
                        </label>
                    </div>
                    <div>
                        <div id="mp-req-curl" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
                    </div>
                </div>
            </div>

            <div class="mp-block mp-block-flex mp-settings-margin-left mp-settings-margin-right">
                <div class="mp-inner-container-settings">
                    <div>
                        <p class="mp-settings-font-color mp-settings-title-font-size">Collections and installments</p>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color">
                            Choose <b>when you want to receive the money</b> from your sales and if you want to offer <b>interest-free installments</b> to your clients.
                        </p>
                    </div>
                    <div>
                        <a target="_blank" href="https://www.mercadopago.com.br/costs-section">
                            <button class="mp-button" id="mp-set-installments-button">Set deadlines and fees</button>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mp-block mp-block-flex mp-block-manual mp-settings-margin-left">
                <div class="mp-inner-container-settings">
                    <div>
                        <p class="mp-settings-font-color mp-settings-title-font-size">Questions?</p>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color">
                            Choose <b><span>when you want to receive the money</b> from your sales and if you want to offer </span> interest-free installments.
                        </p>
                    </div>
                    <div>
                        <a target="_blank" href="https://www.mercadopago.com.br/developers/pt/guides/plugins/woocommerce/integration">
                            <button id="mp-plugin-guide-button" class="mp-button mp-button-light-blue">Plugin manual</button>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <hr class="mp-settings-hr" />

    <div class="mp-settings-credentials">
        <div id="mp-settings-step-one" class="mp-settings-title-align">
            <div class="mp-settings-title-container">
                <span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right"><?= $credentialsTranslations['title_credentials'] ?></span>
                <img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-credentials">
            </div>
            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-credentials-arrow-up">
            </div>
        </div>

        <div id="mp-step-1" class="mp-settings-block-align-top" style="display: none;">
            <div>
                <p class="mp-settings-subtitle-font-size mp-settings-title-color">
                    To enable orders, you must create and activate production credentials in your Mercado Pago Account. <b>Copy and paste the credentials below.</b>
                </p>
            </div>
            <div class="mp-message-credentials">
                <a class="mp-heading-credentials" target="_blank" href="https://www.mercadopago.com/developers/panel/credentials">
                    <button id="mp-get-credentials-button" class="mp-button mp-button-light-blue">Check credentials</button>
                </a>
            </div>

            <div id="msg-info-credentials"></div>

            <div class="mp-container">
                <div class="mp-block mp-block-flex mp-settings-margin-right">
                    <p class="mp-settings-title-font-size"><b>Production credentials</b></p>
                    <p class="mp-settings-label mp-settings-title-color mp-settings-margin-bottom">Enable Mercado Pago checkouts for test purchases in the store.</p>

                    <fieldset class="mp-settings-fieldset">
                        <label for="mp-public-key-prod" class="mp-settings-label mp-settings-font-color">Public key <span style="color: red;">&nbsp;*</span></label>
                        <input id="mp-public-key-prod" class="mp-settings-input" type="text" value="" placeholder="Paste your Public Key here" />
                    </fieldset>

                    <fieldset>
                        <label for="mp-access-token-prod" class="mp-settings-label mp-settings-font-color">Access Token <span style="color: red;">&nbsp;*</span></label>
                        <input id="mp-access-token-prod" class="mp-settings-input" type="text" value="" placeholder="Paste your Access Token here" />
                    </fieldset>
                </div>

                <div class="mp-block mp-block-flex mp-settings-margin-left">
                    <p class="mp-settings-title-font-size"><b>Test credentials</b></p>
                    <p class="mp-settings-label mp-settings-title-color mp-settings-margin-bottom">Enable Mercado Pago checkouts for test purchases in the store.</p>

                    <fieldset class="mp-settings-fieldset">
                        <label for="mp-public-key-test" class="mp-settings-label mp-settings-font-color">Public Key</label>
                        <input class="mp-settings-input" id="mp-public-key-test" type="text" value="" placeholder="Paste your Test Public Key here" />
                    </fieldset>

                    <fieldset>
                        <label for="mp-access-token-test" class="mp-settings-label mp-settings-font-color">Access Token</label>
                        <input class="mp-settings-input" id="mp-access-token-test" type="text" value="" placeholder="Paste your Test Access Token here" />
                    </fieldset>
                </div>
            </div>

            <button class="mp-button" id="mp-btn-credentials">Save and continue</button>
        </div>
    </div>
</div>
