<?php

/**
 * @var array $headerTranslations
 * @var array $credentialsTranslations;
 *
 * @see \MercadoPago\Woocommerce\Admin\Translations
 */

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
        <p class="mp-settings-header-title"><?= $headerTranslations['title_header'] ?></p>
    </div>

    <div class="mp-settings-requirements">
        <div class="mp-container">
            <div class="mp-block mp-block-requirements mp-settings-margin-right">
                <p class="mp-settings-font-color mp-settings-title-font-size">
                    <?= $headerTranslations['title_requirements'] ?>
                </p>
                <div class="mp-inner-container">
                    <div>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size">
                            <?= $headerTranslations['ssl'] ?>
                        </p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size">
                                    <b><?= $headerTranslations['ssl'] ?></b>
                                </p>
                                <?= $headerTranslations['description_ssl'] ?>
                            </span>
                        </label>
                    </div>
                    <div>
                        <div id="mp-req-ssl" class="mp-settings-icon-success" style="filter: grayscale(1)"></div>
                    </div>
                </div>
                <hr>

                <div class="mp-inner-container">
                    <div>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size">
                            <?= $headerTranslations['gd_extension'] ?>
                        </p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size">
                                    <b><?= $headerTranslations['gd_extension'] ?></b>
                                </p>
                                <?= $headerTranslations['description_gd_extension'] ?>
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
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size">
                            <?= $headerTranslations['curl'] ?>
                        </p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size">
                                    <b><?= $headerTranslations['curl'] ?></b>
                                </p>
                                <?= $headerTranslations['description_curl'] ?>
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
                        <p class="mp-settings-font-color mp-settings-title-font-size">
                            <?= $headerTranslations['title_installments'] ?>
                        </p>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color">
                            <?= $headerTranslations['description_installments'] ?>
                        </p>
                    </div>
                    <div>
                        <a target="_blank" href="https://www.mercadopago.com.br/costs-section">
                            <button class="mp-button" id="mp-set-installments-button">
                                <?= $headerTranslations['button_installments'] ?>
                            </button>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mp-block mp-block-flex mp-block-manual mp-settings-margin-left">
                <div class="mp-inner-container-settings">
                    <div>
                        <p class="mp-settings-font-color mp-settings-title-font-size">
                            <?= $headerTranslations['title_questions'] ?>
                        </p>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color">
                            <?= $headerTranslations['description_questions'] ?>
                        </p>
                    </div>
                    <div>
                        <a target="_blank" href="https://www.mercadopago.com.br/developers/pt/guides/plugins/woocommerce/integration">
                            <button id="mp-plugin-guide-button" class="mp-button mp-button-light-blue">
                                <?= $headerTranslations['button_questions'] ?>
                            </button>
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
                <span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right">
                    <?= $credentialsTranslations['title_credentials'] ?>
                </span>
                <img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-credentials">
            </div>
            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-credentials-arrow-up">
            </div>
        </div>

        <div id="mp-step-1" class="mp-settings-block-align-top" style="display: none;">
            <div>
                <p class="mp-settings-subtitle-font-size mp-settings-title-color">
                    <?= $credentialsTranslations['subtitle_credentials'] ?>
                </p>
            </div>
            <div class="mp-message-credentials">
                <a class="mp-heading-credentials" target="_blank" href="https://www.mercadopago.com/developers/panel/credentials">
                    <button id="mp-get-credentials-button" class="mp-button mp-button-light-blue">
                        <?= $credentialsTranslations['button_link_credentials'] ?>
                    </button>
                </a>
            </div>

            <div id="msg-info-credentials"></div>

            <div class="mp-container">
                <div class="mp-block mp-block-flex mp-settings-margin-right">
                    <p class="mp-settings-title-font-size">
                        <b><?= $credentialsTranslations['title_credentials_prod'] ?></b>
                    </p>
                    <p class="mp-settings-label mp-settings-title-color mp-settings-margin-bottom">
                        <?= $credentialsTranslations['subtitle_credentials_prod'] ?>
                    </p>

                    <fieldset class="mp-settings-fieldset">
                        <label for="mp-public-key-prod" class="mp-settings-label mp-settings-font-color">
                            <?= $credentialsTranslations['public_key'] ?> <span style="color: red;">&nbsp;*</span>
                        </label>
                        <input
                            id="mp-public-key-prod"
                            class="mp-settings-input"
                            type="text"
                            value=""
                            placeholder="<?= $credentialsTranslations['placeholder_public_key'] ?>"
                        />
                    </fieldset>

                    <fieldset>
                        <label for="mp-access-token-prod" class="mp-settings-label mp-settings-font-color">
                            <?= $credentialsTranslations['access_token'] ?> <span style="color: red;">&nbsp;*</span>
                        </label>
                        <input
                            id="mp-access-token-prod"
                            class="mp-settings-input"
                            type="text"
                            value=""
                            placeholder="<?= $credentialsTranslations['placeholder_access_token'] ?>"
                        />
                    </fieldset>
                </div>

                <div class="mp-block mp-block-flex mp-settings-margin-left">
                    <p class="mp-settings-title-font-size">
                        <b><?= $credentialsTranslations['title_credentials_test'] ?></b>
                    </p>
                    <p class="mp-settings-label mp-settings-title-color mp-settings-margin-bottom">
                        <?= $credentialsTranslations['subtitle_credentials_test'] ?>
                    </p>

                    <fieldset class="mp-settings-fieldset">
                        <label for="mp-public-key-test" class="mp-settings-label mp-settings-font-color">
                            <?= $credentialsTranslations['public_key'] ?>
                        </label>
                        <input
                            id="mp-public-key-test"
                            class="mp-settings-input"
                            type="text"
                            value=""
                            placeholder="<?= $credentialsTranslations['placeholder_public_key'] ?>" />
                    </fieldset>

                    <fieldset>
                        <label for="mp-access-token-test" class="mp-settings-label mp-settings-font-color">
                            <?= $credentialsTranslations['access_token'] ?>
                        </label>
                        <input
                            id="mp-access-token-test"
                            class="mp-settings-input"
                            type="text"
                            value=""
                            placeholder="<?= $credentialsTranslations['placeholder_access_token'] ?>" />
                    </fieldset>
                </div>
            </div>

            <button class="mp-button" id="mp-btn-credentials">
                <?= $credentialsTranslations['button_credentials'] ?>
            </button>
        </div>
    </div>
</div>
