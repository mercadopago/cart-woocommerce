<?php

/**
 * @var array $headerTranslations
 * @var array $credentialsTranslations
 * @var array $storeTranslations
 * @var array $gatewaysTranslations
 * @var array $testModeTranslations
 * @var array $supportTranslations
 *
 * @var string $publicKeyProd
 * @var string $accessTokenProd
 * @var string $publicKeyTest
 * @var string $accessTokenTest
 * @var string $storeId
 * @var string $storeName
 * @var string $storeCategory
 * @var string $customDomain
 * @var string $customDomainOptions
 * @var string $integratorId
 * @var string $debugMode
 * @var string $cronSyncMode
 * @var string $checkboxCheckoutTestMode
 * @var string $checkboxCheckoutProductionMode
 * @var string $phpVersion
 * @var string $wcVersion
 * @var string $wpVersion
 * @var string $pluginVersion
 *
 * @var array $links
 * @var bool  $testMode
 * @var array $categories
 *
 * @var array $pluginLogs
 * @var array $allowedHtmlTags
 *
 * @see \MercadoPago\Woocommerce\Admin\Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<script>
    window.addEventListener("load", function() {
        mp_settings_screen_load();
    });
</script>

<span id='reference' value='{"mp-screen-name":"admin"}'></span>

<div class="mp-settings">
    <div class="mp-settings-header">
        <div class="mp-settings-header-img"></div>
        <div class="mp-settings-header-logo"></div>
        <hr class="mp-settings-header-hr" />
        <p class="mp-settings-header-title"><?= wp_kses($headerTranslations['title_header'], $allowedHtmlTags) ?></p>
    </div>

    <div class="mp-settings-requirements">
        <div class="mp-container">
            <div class="mp-block mp-block-requirements mp-settings-margin-right">
                <p class="mp-settings-font-color mp-settings-title-font-size">
                    <?= wp_kses($headerTranslations['title_requirements'], $allowedHtmlTags) ?>
                </p>
                <div class="mp-inner-container">
                    <div>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size">
                            <?= wp_kses($headerTranslations['ssl'], $allowedHtmlTags) ?>
                        </p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size">
                                    <b><?= wp_kses($headerTranslations['ssl'], $allowedHtmlTags) ?></b>
                                </p>
                                <?= wp_kses($headerTranslations['description_ssl'], $allowedHtmlTags) ?>
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
                            <?= wp_kses($headerTranslations['gd_extension'], $allowedHtmlTags) ?>
                        </p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size">
                                    <b><?= wp_kses($headerTranslations['gd_extension'], $allowedHtmlTags) ?></b>
                                </p>
                                <?= wp_kses($headerTranslations['description_gd_extension'], $allowedHtmlTags) ?>
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
                            <?= wp_kses($headerTranslations['curl'], $allowedHtmlTags) ?>
                        </p>
                        <label class="mp-settings-icon-info mp-settings-tooltip">
                            <span class="mp-settings-tooltip-text">
                                <p class="mp-settings-subtitle-font-size">
                                    <b><?= wp_kses($headerTranslations['curl'], $allowedHtmlTags) ?></b>
                                </p>
                                <?= wp_kses($headerTranslations['description_curl'], $allowedHtmlTags) ?>
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
                            <?= wp_kses($headerTranslations['title_installments'], $allowedHtmlTags) ?>
                        </p>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color">
                            <?= wp_kses($headerTranslations['description_installments'], $allowedHtmlTags) ?>
                        </p>
                    </div>
                    <div>
                        <a target="_blank" href="<?= wp_kses($links['mercadopago_costs'], $allowedHtmlTags) ?>">
                            <button class="mp-button mp-button-small" id="mp-set-installments-button">
                                <?= wp_kses($headerTranslations['button_installments'], $allowedHtmlTags) ?>
                            </button>
                        </a>
                    </div>
                </div>
            </div>

            <div class="mp-block mp-block-flex mp-block-manual mp-settings-margin-left">
                <div class="mp-inner-container-settings">
                    <div>
                        <p class="mp-settings-font-color mp-settings-title-font-size">
                            <?= wp_kses($headerTranslations['title_questions'], $allowedHtmlTags) ?>
                        </p>
                        <p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color">
                            <?= wp_kses($headerTranslations['description_questions'], $allowedHtmlTags) ?>
                        </p>
                    </div>
                    <div>
                        <a target="_blank" href="<?= wp_kses($links['docs_integration_introduction'], $allowedHtmlTags) ?>">
                            <button id="mp-plugin-guide-button" class="mp-button mp-button-small mp-button-light-blue">
                                <?= wp_kses($headerTranslations['button_questions'], $allowedHtmlTags) ?>
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
                    <?= wp_kses($credentialsTranslations['title_credentials'], $allowedHtmlTags) ?>
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
                    <?= wp_kses($credentialsTranslations['first_text_subtitle_credentials'], $allowedHtmlTags) ?>
                    <a id="mp-get-credentials-link" class="mp-settings-blue-text" target="_blank" href="<?= wp_kses($links['mercadopago_credentials'], $allowedHtmlTags) ?>">
                        <?= wp_kses($credentialsTranslations['text_link_credentials'], $allowedHtmlTags) ?>
                    </a>
                    <?= wp_kses($credentialsTranslations['second_text_subtitle_credentials'], $allowedHtmlTags) ?>
                </p>
            </div>
            <div class="mp-message-credentials"></div>

            <div id="msg-info-credentials"></div>

            <div class="mp-container">
                <div class="mp-block mp-block-flex mp-settings-margin-right">
                    <p class="mp-settings-title-font-size">
                        <b><?= wp_kses($credentialsTranslations['title_credentials_prod'], $allowedHtmlTags) ?></b>
                    </p>
                    <p class="mp-settings-label mp-settings-title-color mp-settings-margin-bottom">
                        <?= wp_kses($credentialsTranslations['subtitle_credentials_prod'], $allowedHtmlTags) ?>
                    </p>

                    <fieldset class="mp-settings-fieldset">
                        <label for="mp-public-key-prod" class="mp-settings-label mp-settings-font-color">
                            <?= wp_kses($credentialsTranslations['public_key'], $allowedHtmlTags) ?> <span style="color: red;">&nbsp;*</span>
                        </label>
                        <input type="text" id="mp-public-key-prod" class="mp-settings-input" value="<?= wp_kses($publicKeyProd, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($credentialsTranslations['placeholder_public_key'], $allowedHtmlTags) ?>" />
                    </fieldset>

                    <fieldset>
                        <label for="mp-access-token-prod" class="mp-settings-label mp-settings-font-color">
                            <?= wp_kses($credentialsTranslations['access_token'], $allowedHtmlTags) ?> <span style="color: red;">&nbsp;*</span>
                        </label>
                        <input type="text" id="mp-access-token-prod" class="mp-settings-input" value="<?= wp_kses($accessTokenProd, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($credentialsTranslations['placeholder_access_token'], $allowedHtmlTags) ?>" />
                    </fieldset>
                </div>

                <div class="mp-block mp-block-flex mp-settings-margin-left">
                    <p class="mp-settings-title-font-size">
                        <b><?= wp_kses($credentialsTranslations['title_credentials_test'], $allowedHtmlTags) ?></b>
                    </p>
                    <p class="mp-settings-label mp-settings-title-color mp-settings-margin-bottom">
                        <?= wp_kses($credentialsTranslations['subtitle_credentials_test'], $allowedHtmlTags) ?>
                    </p>

                    <fieldset class="mp-settings-fieldset">
                        <label for="mp-public-key-test" class="mp-settings-label mp-settings-font-color">
                            <?= wp_kses($credentialsTranslations['public_key'], $allowedHtmlTags) ?>
                        </label>
                        <input type="text" id="mp-public-key-test" class="mp-settings-input" value="<?= wp_kses($publicKeyTest, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($credentialsTranslations['placeholder_public_key'], $allowedHtmlTags) ?>" />
                    </fieldset>

                    <fieldset>
                        <label for="mp-access-token-test" class="mp-settings-label mp-settings-font-color">
                            <?= wp_kses($credentialsTranslations['access_token'], $allowedHtmlTags) ?>
                        </label>
                        <input type="text" id="mp-access-token-test" class="mp-settings-input" value="<?= wp_kses($accessTokenTest, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($credentialsTranslations['placeholder_access_token'], $allowedHtmlTags) ?>" />
                    </fieldset>
                </div>
            </div>

            <button class="mp-button mp-button-large" id="mp-btn-credentials">
                <?= wp_kses($credentialsTranslations['button_credentials'], $allowedHtmlTags) ?>
            </button>
        </div>
    </div>

    <hr class="mp-settings-hr" />

    <div class="mp-settings-credentials">
        <div id="mp-settings-step-two" class="mp-settings-title-align">
            <div class="mp-settings-title-container">
                <span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right"><?= wp_kses($storeTranslations['title_store'], $allowedHtmlTags) ?></span>
                <img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-store" />
            </div>
            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-store-info-arrow-up" />
            </div>
        </div>

        <div id="mp-step-2" class="mp-message-store mp-settings-block-align-top" style="display: none;">
            <p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color">
                <?= wp_kses($storeTranslations['subtitle_store'], $allowedHtmlTags) ?>
            </p>
            <div class="mp-heading-store mp-container mp-settings-flex-start" id="block-two">
                <div class="mp-block mp-block-flex mp-settings-margin-right mp-settings-choose-mode">
                    <div>
                        <p class="mp-settings-title-font-size">
                            <b><?= wp_kses($storeTranslations['title_info_store'], $allowedHtmlTags) ?></b>
                        </p>
                    </div>
                    <div class="mp-settings-standard-margin">
                        <fieldset>
                            <label for="mp-store-identification" class="mp-settings-label mp-settings-font-color">
                                <?= wp_kses($storeTranslations['subtitle_name_store'], $allowedHtmlTags) ?>
                            </label>
                            <input type="text" id="mp-store-identification" class="mp-settings-input" value="<?= wp_kses($storeName, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($storeTranslations['placeholder_name_store'], $allowedHtmlTags) ?>" />
                        </fieldset>
                        <span class="mp-settings-helper"><?= wp_kses($storeTranslations['helper_name_store'], $allowedHtmlTags) ?></span>
                    </div>

                    <div class="mp-settings-standard-margin">
                        <fieldset>
                            <label for="mp-store-category-id" class="mp-settings-label mp-settings-font-color">
                                <?= wp_kses($storeTranslations['subtitle_activities_store'], $allowedHtmlTags) ?>
                            </label>
                            <input type="text" id="mp-store-category-id" class="mp-settings-input" value="<?= wp_kses($storeId, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($storeTranslations['placeholder_activities_store'], $allowedHtmlTags) ?>" />
                        </fieldset>
                        <span class="mp-settings-helper"><?= wp_kses($storeTranslations['helper_activities_store'], $allowedHtmlTags) ?></span>
                    </div>

                    <div class="mp-settings-standard-margin">
                        <label for="mp-store-categories" class="mp-settings-label mp-container mp-settings-font-color">
                            <?= wp_kses($storeTranslations['subtitle_category_store'], $allowedHtmlTags) ?>
                        </label>
                        <select name="<?= wp_kses($storeTranslations['placeholder_category_store'], $allowedHtmlTags) ?>" class="mp-settings-select" id="mp-store-categories">
                            <?php
                            foreach ($categories as $category) {
                                echo ('
                                        <option value="' . esc_attr($category['id']) . '"' . (esc_attr($storeCategory) === esc_attr($category['id']) ? 'selected' : '') . '>
                                            ' . esc_attr($category['description']) . '
                                        </option>
                                    ');
                            }
                            ?>
                        </select>
                        <span class="mp-settings-helper"><?= wp_kses($storeTranslations['helper_category_store'], $allowedHtmlTags) ?></span>
                    </div>
                </div>

                <div class="mp-block mp-block-flex mp-block-manual mp-settings-margin-left">
                    <div>
                        <p class="mp-settings-title-font-size">
                            <b><?= wp_kses($storeTranslations['title_advanced_store'], $allowedHtmlTags) ?></b>
                        </p>
                    </div>
                    <p class="mp-settings-subtitle-font-size mp-settings-title-color">
                        <?= wp_kses($storeTranslations['subtitle_advanced_store'], $allowedHtmlTags) ?>
                    </p>

                    <div>
                        <p class="mp-settings-blue-text" id="mp-advanced-options">
                            <?= wp_kses($storeTranslations['accordion_advanced_store_show'], $allowedHtmlTags) ?>
                        </p>

                        <div class="mp-settings-advanced-options" style="display:none">
                            <div class="mp-settings-standard-margin">
                                <fieldset>
                                    <label for="mp-store-url-ipn" class="mp-settings-label mp-settings-font-color">
                                        <?= wp_kses($storeTranslations['subtitle_url'], $allowedHtmlTags) ?>
                                    </label>
                                    <input type="text" id="mp-store-url-ipn" class="mp-settings-input" value="<?= wp_kses($customDomain, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($storeTranslations['placeholder_url'], $allowedHtmlTags) ?>" />
                                    <div>
                                        <input type="checkbox" id="mp-store-url-ipn-options" <?= checked($customDomainOptions, 'yes'); ?> />
                                        <label for="mp-store-url-ipn-options" class="mp-settings-checkbox-options"><?php echo esc_html($storeTranslations['options_url']); ?></label>
                                    </div>
                                    <span class="mp-settings-helper"><?= wp_kses($storeTranslations['helper_url'], $allowedHtmlTags) ?></span>
                                </fieldset>
                            </div>

                            <div class="mp-settings-standard-margin">
                                <fieldset>
                                    <label for="mp-store-integrator-id" class="mp-settings-label mp-settings-font-color">
                                        <?= wp_kses($storeTranslations['subtitle_integrator'], $allowedHtmlTags) ?>
                                    </label>
                                    <input type="text" id="mp-store-integrator-id" class="mp-settings-input" value="<?= wp_kses($integratorId, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($storeTranslations['placeholder_integrator'], $allowedHtmlTags) ?>" />
                                    <span class="mp-settings-helper"><?= wp_kses($storeTranslations['helper_integrator'], $allowedHtmlTags) ?></span>
                                </fieldset>
                            </div>

                            <div class="mp-container">
                                <div>
                                    <label class="mp-settings-switch">
                                        <input id="mp-store-debug-mode" type="checkbox" value="yes" <?= checked($debugMode, 'yes'); ?> />
                                        <span class="mp-settings-slider mp-settings-round"></span>
                                    </label>
                                </div>
                                <label for="mp-store-debug-mode">
                                    <span class="mp-settings-subtitle-font-size mp-settings-debug mp-settings-font-color">
                                        <?= wp_kses($storeTranslations['title_debug'], $allowedHtmlTags) ?>
                                    </span>
                                    <br />
                                    <span class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color mp-settings-debug">
                                        <?= wp_kses($storeTranslations['subtitle_debug'], $allowedHtmlTags) ?>
                                    </span>
                                </label>
                            </div>

                            <div style="margin-top: 20px;" class="mp-container" id="mp-cron-config">
                                <div>
                                    <label class="mp-settings-switch">
                                        <input id="mp-store-cron-config" type="checkbox" value="yes" <?= checked($cronSyncMode, 'yes'); ?> />
                                        <span class="mp-settings-slider mp-settings-round"></span>
                                    </label>
                                </div>
                                <label for="mp-store-cron-config">
                                    <span class="mp-settings-subtitle-font-size mp-settings-debug mp-settings-font-color">
                                        <?= wp_kses($storeTranslations['title_cron_config'], $allowedHtmlTags) ?>
                                    </span>
                                    <br />
                                    <span class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color mp-settings-debug">
                                        <?= wp_kses($storeTranslations['subtitle_cron_config'], $allowedHtmlTags) ?>
                                    </span>
                                </label>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <button class="mp-button mp-button-large" id="mp-store-info-save"><?= wp_kses($storeTranslations['button_store'], $allowedHtmlTags) ?></button>
        </div>
    </div>

    <hr class="mp-settings-hr" />

    <div class="mp-settings-payment">
        <div id="mp-settings-step-three" class="mp-settings-title-align">
            <div class="mp-settings-title-container">
                <span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right">
                    <?= wp_kses($gatewaysTranslations['title_payments'], $allowedHtmlTags) ?>
                </span>
                <img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-payment">
            </div>

            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-payments-arrow-up" />
            </div>
        </div>
        <div id="mp-step-3" class="mp-settings-block-align-top" style="display: none;">
            <p id="mp-payment" class="mp-settings-subtitle-font-size mp-settings-title-color">
                <?= wp_kses($gatewaysTranslations['subtitle_payments'], $allowedHtmlTags) ?>
            </p>
            <button id="mp-payment-method-continue" class="mp-button mp-button-large">
                <?= wp_kses($gatewaysTranslations['button_payment'], $allowedHtmlTags) ?>
            </button>
        </div>
    </div>

    <hr class="mp-settings-hr" />

    <div class="mp-settings-mode">
        <div id="mp-settings-step-four" class="mp-settings-title-align">
            <div class="mp-settings-title-container">
                <div class="mp-align-items-center">
                    <span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right">
                        <?= wp_kses($testModeTranslations['title_test_mode'], $allowedHtmlTags) ?>
                    </span>
                    <div id="mp-mode-badge" class="mp-settings-margin-left mp-settings-margin-right <?= $testMode ? 'mp-settings-test-mode-alert' : 'mp-settings-prod-mode-alert' ?>">
                        <span id="mp-mode-badge-test" style="display: <?= $testMode ? 'block' : 'none' ?>">
                            <?= wp_kses($testModeTranslations['badge_test'], $allowedHtmlTags) ?>
                        </span>
                        <span id="mp-mode-badge-prod" style="display: <?= $testMode ? 'none' : 'block' ?>">
                            <?= wp_kses($testModeTranslations['badge_mode'], $allowedHtmlTags) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-modes-arrow-up" />
            </div>
        </div>

        <div id="mp-step-4" class="mp-message-test-mode mp-settings-block-align-top" style="display: none;">
            <p class="mp-heading-test-mode mp-settings-subtitle-font-size mp-settings-title-color">
                <?= wp_kses($testModeTranslations['subtitle_test_mode'], $allowedHtmlTags) ?>
            </p>

            <div class="mp-container">
                <div class="mp-block mp-settings-choose-mode">
                    <div>
                        <p class="mp-settings-title-font-size">
                            <b><?= wp_kses($testModeTranslations['title_mode'], $allowedHtmlTags) ?></b>
                        </p>
                    </div>

                    <div class="mp-settings-mode-container">
                        <div class="mp-settings-mode-spacing">
                            <input type="radio" id="mp-settings-testmode-test" class="mp-settings-radio-button" name="mp-test-prod" value="yes" <?= checked($testMode) ?> />
                        </div>
                        <label for="mp-settings-testmode-test">
                            <span class="mp-settings-subtitle-font-size mp-settings-font-color">
                                <?= wp_kses($testModeTranslations['title_test'], $allowedHtmlTags) ?>
                            </span>
                            <br />
                            <span class="mp-settings-subtitle-font-size mp-settings-title-color">
                                <?= wp_kses($testModeTranslations['subtitle_test'], $allowedHtmlTags) ?>
                                <span>
                                    <a id="mp-test-mode-rules-link" class="mp-settings-blue-text" target="_blank" href="<?= wp_kses($links['docs_integration_test'], $allowedHtmlTags) ?>">
                                        <?= wp_kses($testModeTranslations['subtitle_test_link'], $allowedHtmlTags) ?>
                                    </a>
                        </label>
                    </div>

                    <div class="mp-settings-mode-container">
                        <div class="mp-settings-mode-spacing">
                            <input type="radio" id="mp-settings-testmode-prod" class="mp-settings-radio-button" name="mp-test-prod" value="no" <?= checked(!$testMode) ?> />
                        </div>
                        <label for="mp-settings-testmode-prod">
                            <span class="mp-settings-subtitle-font-size mp-settings-font-color">
                                <?= wp_kses($testModeTranslations['title_prod'], $allowedHtmlTags) ?>
                            </span>
                            <br />
                            <span class="mp-settings-subtitle-font-size mp-settings-title-color">
                                <?= wp_kses($testModeTranslations['subtitle_prod'], $allowedHtmlTags) ?>
                            </span>
                        </label>
                    </div>

                    <div class="mp-settings-alert-payment-methods" style="display:none;">
                        <div id="mp-red-badge" class="mp-settings-alert-red"></div>
                        <div class="mp-settings-alert-payment-methods-gray">
                            <div class="mp-settings-margin-right mp-settings-mode-style">
                                <span id="mp-icon-badge-error" class="mp-settings-icon-warning"></span>
                            </div>

                            <div class="mp-settings-mode-warning">
                                <div class="mp-settings-margin-left">
                                    <div class="mp-settings-alert-mode-title">
                                        <span id="mp-text-badge"><?= wp_kses($testModeTranslations['title_alert_test'], $allowedHtmlTags) ?></span>
                                    </div>
                                    <div id="mp-helper-badge-div" class="mp-settings-alert-mode-body mp-settings-font-color">
                                        <span id="mp-helper-test-error"><?= wp_kses($testModeTranslations['test_credentials_helper'], $allowedHtmlTags) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mp-settings-alert-payment-methods">
                        <div id="mp-orange-badge" class="<?= $testMode ? 'mp-settings-alert-payment-methods-orange' : 'mp-settings-alert-payment-methods-green' ?>"></div>
                        <div class="mp-settings-alert-payment-methods-gray">
                            <div class="mp-settings-margin-right mp-settings-mode-style">
                                <span id="mp-icon-badge" class="<?= $testMode ? 'mp-settings-icon-warning' : 'mp-settings-icon-success' ?>"></span>
                            </div>

                            <div class="mp-settings-mode-warning">
                                <div class="mp-settings-margin-left">
                                    <div class="mp-settings-alert-mode-title">
                                        <span id="mp-title-helper-prod" style="display: <?= $testMode ? 'none' : 'block' ?>">
                                            <span id="mp-text-badge" class="mp-display-block"> <?= wp_kses($testModeTranslations['title_message_prod'], $allowedHtmlTags) ?></span>
                                        </span>
                                        <span id="mp-title-helper-test" style="display: <?= $testMode ? 'block' : 'none' ?>">
                                            <span id="mp-text-badge" class="mp-display-block"><?= wp_kses($testModeTranslations['title_message_test'], $allowedHtmlTags) ?></span>
                                        </span>
                                    </div>

                                    <div id="mp-helper-badge-div" class="mp-settings-alert-mode-body mp-settings-font-color">
                                        <span id="mp-helper-prod" style="display: <?= $testMode ? 'none' : 'block' ?>"><?= wp_kses($testModeTranslations['subtitle_message_prod'], $allowedHtmlTags) ?></span>
                                        <span id="mp-helper-test" style="display: <?= $testMode ? 'block' : 'none' ?>">
                                            <span><?= wp_kses($testModeTranslations['subtitle_test_one'], $allowedHtmlTags) ?></span><br />
                                            <span><?= wp_kses($testModeTranslations['subtitle_test_two'], $allowedHtmlTags) ?></span><br />
                                            <span><?= wp_kses($testModeTranslations['subtitle_test_three'], $allowedHtmlTags) ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button class="mp-button mp-button-large" id="mp-store-mode-save">
                <?= wp_kses($testModeTranslations['button_test_mode'], $allowedHtmlTags) ?>
            </button>
        </div>
    </div>

    <div class="mp-settings-support-container">
        <div id="mp-settings-support" class="mp-settings-title-align">

            <div class="mp-settings-title-container">
                <span class="mp-settings-support-title"> <?= wp_kses($supportTranslations['support_title'], $allowedHtmlTags) ?></span>
            </div>

            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-modes-arrow-up" />
            </div>
        </div>

        <div class="mp-settings-support-content" style="display: none;">
            <hr class="mp-settings-hr-support">
            <p><?= wp_kses($supportTranslations['support_faqs_url'], $allowedHtmlTags) ?></p>
            <p id="mp-settings-support-how-to"><b><?= wp_kses($supportTranslations['support_how_to'], $allowedHtmlTags) ?></b></p>
            <p><?= wp_kses($supportTranslations['support_step_one'], $allowedHtmlTags) ?></p>
            <p><?= wp_kses($supportTranslations['support_step_two'], $allowedHtmlTags) ?></p>
            <p><?= wp_kses($supportTranslations['support_step_three'], $allowedHtmlTags) ?></p>
            <table class="mp-settings-support-content-table">
                <tr>
                    <th>PHP Version</th>
                    <th>WordPress Version</th>
                </tr>
                <tr>
                    <td><?= wp_kses($supportTranslations['support_version'], $allowedHtmlTags) ?> <?= wp_kses($phpVersion, $allowedHtmlTags) ?></td>
                    <td><?= wp_kses($supportTranslations['support_version'], $allowedHtmlTags) ?> <?= wp_kses($wpVersion, $allowedHtmlTags) ?></td>
                </tr>
                <tr>
                </tr>
            </table>
            <table class="mp-settings-support-content-table">
                <tr>
                    <th>WooCommerce Version </th>
                    <th>Plugin Version</th>
                </tr>
                <tr>
                    <td><?= wp_kses($supportTranslations['support_version'], $allowedHtmlTags) ?> <?= wp_kses($wcVersion, $allowedHtmlTags) ?></td>
                    <td><?= wp_kses($supportTranslations['support_version'], $allowedHtmlTags) ?> <?= wp_kses($pluginVersion, $allowedHtmlTags) ?></td>
                </tr>
                <tr>
                </tr>
            </table>
            <p><?= wp_kses($supportTranslations['support_step_four'], $allowedHtmlTags) ?></p>
        </div>

    </div>

    <div id="supportModal" class="mp-settings-support-modal">

        <!-- Modal content -->
        <div class="mp-settings-support-modal-content">
            <span class="mp-settings-close" onclick="closeSupportModal()">&times;</span>
            <p class="mp-settings-modal-title"><?= wp_kses($supportTranslations['support_modal_title'], $allowedHtmlTags) ?></p>
            <?php if (!empty($pluginLogs)) : ?>
                <p class="mp-settings-modal-desc"><?= wp_kses($supportTranslations['support_modal_desc'], $allowedHtmlTags) ?></p>
                <table class="mp-settings-modal-table">
                    <thead>
                        <tr>
                            <th class="mp-settings-modal-table-header small-cell"><input type="checkbox" id="selectAllCheckbox"></th>
                            <th class="mp-settings-modal-table-header"><?= wp_kses($supportTranslations['support_modal_table_header_2'], $allowedHtmlTags) ?></th>
                            <th class="mp-settings-modal-table-header"><?= wp_kses($supportTranslations['support_modal_table_header_3'], $allowedHtmlTags) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pluginLogs as $logFile) : ?>
                            <tr class="mp-settings-modal-table-row">
                                <td class="mp-settings-modal-table-cell small-cell"><input type="checkbox" name="selected_files[]" value="<?php echo wp_kses($logFile->fileFullName, $allowedHtmlTags); ?>"></td>
                                <td class="mp-settings-modal-table-cell"><?php echo wp_kses($logFile->fileName, $allowedHtmlTags) ?></td>
                                <td class="mp-settings-modal-table-cell"><?php echo wp_kses($logFile->fileDate, $allowedHtmlTags) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button id="downloadSelected" class="mp-button mp-button-small" disabled><?= wp_kses($supportTranslations['support_modal_download_btn'], $allowedHtmlTags) ?></button>
                <div class="mp-settings-button-group">
                    <div id="mp-pagination" class="mp-settings-pagination"></div>
                </div>
            <?php else : ?>
                <p class="mp-settings-modal-desc"><?= wp_kses($supportTranslations['support_modal_no_content'], $allowedHtmlTags) ?></p>
            <?php endif; ?>
        </div>

    </div>


</div>
<script>
    const downloadBtn = document.getElementById('downloadSelected');
    const checkboxes2 = document.querySelectorAll('input[name="selected_files[]"]');
    const allCheckbox = document.getElementById('selectAllCheckbox')
    downloadBtn.addEventListener('click', function() {
        const selectedFiles = [];
        checkboxes2.forEach((cb) => {
            if (cb.checked) {
                selectedFiles.push(cb.value);
                cb.checked = false;
            }
        });
        allCheckbox.checked = false;
        if (selectedFiles.length > 0) {
            const url = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
            const params = new URLSearchParams();
            params.append('action', 'mp_download_log');
            selectedFiles.forEach((file) => {
                params.append('files[]', file);
            });
            window.location.href = url + '?' + params.toString();
            closeSupportModal()
        }
    });
</script>
