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
                        <a target="_blank" href="<?= $links['mercadopago_costs'] ?>">
                            <button class="mp-button mp-button-small" id="mp-set-installments-button">
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
                        <a target="_blank" href="<?= $links['docs_integration_introduction'] ?>">
                            <button id="mp-plugin-guide-button" class="mp-button mp-button-small mp-button-light-blue">
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
                    <?= $credentialsTranslations['first_text_subtitle_credentials'] ?>
                    <a id="mp-get-credentials-link" class="mp-settings-blue-text" target="_blank" href="<?= $links['mercadopago_credentials'] ?>">
                        <?= $credentialsTranslations['text_link_credentials'] ?>
                    </a>
                    <?= $credentialsTranslations['second_text_subtitle_credentials'] ?>
                </p>
            </div>
            <div class="mp-message-credentials"></div>

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
                        <input type="text" id="mp-public-key-prod" class="mp-settings-input" value="<?= $publicKeyProd ?>" placeholder="<?= $credentialsTranslations['placeholder_public_key'] ?>" />
                    </fieldset>

                    <fieldset>
                        <label for="mp-access-token-prod" class="mp-settings-label mp-settings-font-color">
                            <?= $credentialsTranslations['access_token'] ?> <span style="color: red;">&nbsp;*</span>
                        </label>
                        <input type="text" id="mp-access-token-prod" class="mp-settings-input" value="<?= $accessTokenProd ?>" placeholder="<?= $credentialsTranslations['placeholder_access_token'] ?>" />
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
                        <input type="text" id="mp-public-key-test" class="mp-settings-input" value="<?= $publicKeyTest ?>" placeholder="<?= $credentialsTranslations['placeholder_public_key'] ?>" />
                    </fieldset>

                    <fieldset>
                        <label for="mp-access-token-test" class="mp-settings-label mp-settings-font-color">
                            <?= $credentialsTranslations['access_token'] ?>
                        </label>
                        <input type="text" id="mp-access-token-test" class="mp-settings-input" value="<?= $accessTokenTest ?>" placeholder="<?= $credentialsTranslations['placeholder_access_token'] ?>" />
                    </fieldset>
                </div>
            </div>

            <button class="mp-button mp-button-large" id="mp-btn-credentials">
                <?= $credentialsTranslations['button_credentials'] ?>
            </button>
        </div>
    </div>

    <hr class="mp-settings-hr" />

    <div class="mp-settings-credentials">
        <div id="mp-settings-step-two" class="mp-settings-title-align">
            <div class="mp-settings-title-container">
                <span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right"><?= $storeTranslations['title_store'] ?></span>
                <img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-store" />
            </div>
            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-store-info-arrow-up" />
            </div>
        </div>

        <div id="mp-step-2" class="mp-message-store mp-settings-block-align-top" style="display: none;">
            <p class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color">
                <?= $storeTranslations['subtitle_store'] ?>
            </p>
            <div class="mp-heading-store mp-container mp-settings-flex-start" id="block-two">
                <div class="mp-block mp-block-flex mp-settings-margin-right mp-settings-choose-mode">
                    <div>
                        <p class="mp-settings-title-font-size">
                            <b><?= $storeTranslations['title_info_store'] ?></b>
                        </p>
                    </div>
                    <div class="mp-settings-standard-margin">
                        <fieldset>
                            <label for="mp-store-identification" class="mp-settings-label mp-settings-font-color">
                                <?= $storeTranslations['subtitle_name_store'] ?>
                            </label>
                            <input type="text" id="mp-store-identification" class="mp-settings-input" value="<?= $storeName ?>" placeholder="<?= $storeTranslations['placeholder_name_store'] ?>" />
                        </fieldset>
                        <span class="mp-settings-helper"><?= $storeTranslations['helper_name_store'] ?></span>
                    </div>

                    <div class="mp-settings-standard-margin">
                        <fieldset>
                            <label for="mp-store-category-id" class="mp-settings-label mp-settings-font-color">
                                <?= $storeTranslations['subtitle_activities_store'] ?>
                            </label>
                            <input type="text" id="mp-store-category-id" class="mp-settings-input" value="<?= $storeId ?>" placeholder="<?= $storeTranslations['placeholder_activities_store'] ?>" />
                        </fieldset>
                        <span class="mp-settings-helper"><?= $storeTranslations['helper_activities_store'] ?></span>
                    </div>

                    <div class="mp-settings-standard-margin">
                        <label for="mp-store-categories" class="mp-settings-label mp-container mp-settings-font-color">
                            <?= $storeTranslations['subtitle_category_store'] ?>
                        </label>
                        <select name="<?= $storeTranslations['placeholder_category_store'] ?>" class="mp-settings-select" id="mp-store-categories">
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
                        <span class="mp-settings-helper"><?= $storeTranslations['helper_category_store'] ?></span>
                    </div>
                </div>

                <div class="mp-block mp-block-flex mp-block-manual mp-settings-margin-left">
                    <div>
                        <p class="mp-settings-title-font-size">
                            <b><?= $storeTranslations['title_advanced_store'] ?></b>
                        </p>
                    </div>
                    <p class="mp-settings-subtitle-font-size mp-settings-title-color">
                        <?= $storeTranslations['subtitle_advanced_store'] ?>
                    </p>

                    <div>
                        <p class="mp-settings-blue-text" id="mp-advanced-options">
                            <?= $storeTranslations['accordion_advanced_store_show'] ?>
                        </p>

                        <div class="mp-settings-advanced-options" style="display:none">
                            <div class="mp-settings-standard-margin">
                                <fieldset>
                                    <label for="mp-store-url-ipn" class="mp-settings-label mp-settings-font-color">
                                        <?= $storeTranslations['subtitle_url'] ?>
                                    </label>
                                    <input type="text" id="mp-store-url-ipn" class="mp-settings-input" value="<?= $customDomain ?>" placeholder="<?= $storeTranslations['placeholder_url'] ?>" />
                                    <div>
                                        <input type="checkbox" id="mp-store-url-ipn-options" <?= checked($customDomainOptions, 'yes'); ?> />
                                        <label for="mp-store-url-ipn-options" class="mp-settings-checkbox-options"><?php echo esc_html($storeTranslations['options_url']); ?></label>
                                    </div>
                                    <span class="mp-settings-helper"><?= $storeTranslations['helper_url'] ?></span>
                                </fieldset>
                            </div>

                            <div class="mp-settings-standard-margin">
                                <fieldset>
                                    <label for="mp-store-integrator-id" class="mp-settings-label mp-settings-font-color">
                                        <?= $storeTranslations['subtitle_integrator'] ?>
                                    </label>
                                    <input type="text" id="mp-store-integrator-id" class="mp-settings-input" value="<?= $integratorId ?>" placeholder="<?= $storeTranslations['placeholder_integrator'] ?>" />
                                    <span class="mp-settings-helper"><?= $storeTranslations['helper_integrator'] ?></span>
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
                                        <?= $storeTranslations['title_debug'] ?>
                                    </span>
                                    <br />
                                    <span class="mp-settings-font-color mp-settings-subtitle-font-size mp-settings-title-color mp-settings-debug">
                                        <?= $storeTranslations['subtitle_debug'] ?>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button class="mp-button mp-button-large" id="mp-store-info-save"><?= $storeTranslations['button_store'] ?></button>
        </div>
    </div>

    <hr class="mp-settings-hr" />

    <div class="mp-settings-payment">
        <div id="mp-settings-step-three" class="mp-settings-title-align">
            <div class="mp-settings-title-container">
                <span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right">
                    <?= $gatewaysTranslations['title_payments'] ?>
                </span>
                <img class="mp-settings-margin-left mp-settings-margin-right" id="mp-settings-icon-payment">
            </div>

            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-payments-arrow-up" />
            </div>
        </div>
        <div id="mp-step-3" class="mp-settings-block-align-top" style="display: none;">
            <p id="mp-payment" class="mp-settings-subtitle-font-size mp-settings-title-color">
                <?= $gatewaysTranslations['subtitle_payments'] ?>
            </p>
            <button id="mp-payment-method-continue" class="mp-button mp-button-large">
                <?= $gatewaysTranslations['button_payment'] ?>
            </button>
        </div>
    </div>

    <hr class="mp-settings-hr" />

    <div class="mp-settings-mode">
        <div id="mp-settings-step-four" class="mp-settings-title-align">
            <div class="mp-settings-title-container">
                <div class="mp-align-items-center">
                    <span class="mp-settings-font-color mp-settings-title-blocks mp-settings-margin-right">
                        <?= $testModeTranslations['title_test_mode'] ?>
                    </span>
                    <div id="mp-mode-badge" class="mp-settings-margin-left mp-settings-margin-right <?= $testMode ? 'mp-settings-test-mode-alert' : 'mp-settings-prod-mode-alert' ?>">
                        <span id="mp-mode-badge-test" style="display: <?= $testMode ? 'block' : 'none' ?>">
                            <?= $testModeTranslations['badge_test'] ?>
                        </span>
                        <span id="mp-mode-badge-prod" style="display: <?= $testMode ? 'none' : 'block' ?>">
                            <?= $testModeTranslations['badge_mode'] ?>
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
                <?= $testModeTranslations['subtitle_test_mode'] ?>
            </p>

            <div class="mp-container">
                <div class="mp-block mp-settings-choose-mode">
                    <div>
                        <p class="mp-settings-title-font-size">
                            <b><?= $testModeTranslations['title_mode'] ?></b>
                        </p>
                    </div>

                    <div class="mp-settings-mode-container">
                        <div class="mp-settings-mode-spacing">
                            <input type="radio" id="mp-settings-testmode-test" class="mp-settings-radio-button" name="mp-test-prod" value="yes" <?= checked($testMode) ?> />
                        </div>
                        <label for="mp-settings-testmode-test">
                            <span class="mp-settings-subtitle-font-size mp-settings-font-color">
                                <?= $testModeTranslations['title_test'] ?>
                            </span>
                            <br />
                            <span class="mp-settings-subtitle-font-size mp-settings-title-color">
                                <?= $testModeTranslations['subtitle_test'] ?>
                                <span>
                                    <a id="mp-test-mode-rules-link" class="mp-settings-blue-text" target="_blank" href="<?= $links['docs_integration_test'] ?>">
                                        <?= $testModeTranslations['subtitle_test_link'] ?>
                                    </a>
                        </label>
                    </div>

                    <div class="mp-settings-mode-container">
                        <div class="mp-settings-mode-spacing">
                            <input type="radio" id="mp-settings-testmode-prod" class="mp-settings-radio-button" name="mp-test-prod" value="no" <?= checked(!$testMode) ?> />
                        </div>
                        <label for="mp-settings-testmode-prod">
                            <span class="mp-settings-subtitle-font-size mp-settings-font-color">
                                <?= $testModeTranslations['title_prod'] ?>
                            </span>
                            <br />
                            <span class="mp-settings-subtitle-font-size mp-settings-title-color">
                                <?= $testModeTranslations['subtitle_prod'] ?>
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
                                        <span id="mp-text-badge"><?= $testModeTranslations['title_alert_test'] ?></span>
                                    </div>
                                    <div id="mp-helper-badge-div" class="mp-settings-alert-mode-body mp-settings-font-color">
                                        <span id="mp-helper-test-error"><?= $testModeTranslations['test_credentials_helper'] ?></span>
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
                                            <span id="mp-text-badge" class="mp-display-block"> <?= $testModeTranslations['title_message_prod'] ?></span>
                                        </span>
                                        <span id="mp-title-helper-test" style="display: <?= $testMode ? 'block' : 'none' ?>">
                                            <span id="mp-text-badge" class="mp-display-block"><?= $testModeTranslations['title_message_test'] ?></span>
                                        </span>
                                    </div>

                                    <div id="mp-helper-badge-div" class="mp-settings-alert-mode-body mp-settings-font-color">
                                        <span id="mp-helper-prod" style="display: <?= $testMode ? 'none' : 'block' ?>"><?= $testModeTranslations['subtitle_message_prod'] ?></span>
                                        <span id="mp-helper-test" style="display: <?= $testMode ? 'block' : 'none' ?>">
                                            <span><?= $testModeTranslations['subtitle_test_one'] ?></span><br />
                                            <span><?= $testModeTranslations['subtitle_test_two'] ?></span><br />
                                            <span><?= $testModeTranslations['subtitle_test_three'] ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button class="mp-button mp-button-large" id="mp-store-mode-save">
                <?= $testModeTranslations['button_test_mode'] ?>
            </button>
        </div>
    </div>

    <div class="mp-settings-support-container">
        <div id="mp-settings-support" class="mp-settings-title-align">

            <div class="mp-settings-title-container">
                <span class="mp-settings-support-title"> <?= $supportTranslations['support_title'] ?></span>
            </div>

            <div class="mp-settings-title-container mp-settings-margin-left">
                <img class="mp-settings-icon-open" id="mp-modes-arrow-up" />
            </div>
        </div>

        <div class="mp-settings-support-content" style="display: none;">
            <hr class="mp-settings-hr-support">
            <p><?= $supportTranslations['support_faqs_url'] ?></p>
            <p id="mp-settings-support-how-to"><b><?= $supportTranslations['support_how_to'] ?></b></p>
            <p><?= $supportTranslations['support_step_one'] ?></p>
            <p><?= $supportTranslations['support_step_two'] ?></p>
            <p><?= $supportTranslations['support_step_three'] ?></p>
            <table class="mp-settings-support-content-table">
                <tr>
                    <th>PHP Version</th>
                    <th>WordPress Version</th>
                </tr>
                <tr>
                    <td><?= $supportTranslations['support_version'] ?> <?= $phpVersion ?></td>
                    <td><?= $supportTranslations['support_version'] ?> <?= $wpVersion ?></td>
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
                    <td><?= $supportTranslations['support_version'] ?> <?= $wcVersion ?></td>
                    <td><?= $supportTranslations['support_version'] ?> <?= $pluginVersion ?></td>
                </tr>
                <tr>
                </tr>
            </table>
            <p><?= $supportTranslations['support_step_four'] ?></p>
        </div>

    </div>

    <div id="supportModal" class="mp-settings-support-modal">

        <!-- Modal content -->
        <div class="mp-settings-support-modal-content">
            <span class="mp-settings-close" onclick="closeSupportModal()">&times;</span>
            <p class="mp-settings-modal-title"><?= $supportTranslations['support_modal_title'] ?></p>
            <?php if (!empty($pluginLogs)) : ?>
                <p class="mp-settings-modal-desc"><?= $supportTranslations['support_modal_desc'] ?></p>
                <table class="mp-settings-modal-table">
                    <thead>
                        <tr>
                            <th class="mp-settings-modal-table-header small-cell"><input type="checkbox" id="selectAllCheckbox"></th>
                            <th class="mp-settings-modal-table-header"><?= $supportTranslations['support_modal_table_header_2'] ?></th>
                            <th class="mp-settings-modal-table-header"><?= $supportTranslations['support_modal_table_header_3'] ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pluginLogs as $logFile) : ?>
                            <tr class="mp-settings-modal-table-row">
                                <td class="mp-settings-modal-table-cell small-cell"><input type="checkbox" name="selected_files[]" value="<?php echo $logFile->fileFullName; ?>"></td>
                                <td class="mp-settings-modal-table-cell"><?php echo $logFile->fileName ?></td>
                                <td class="mp-settings-modal-table-cell"><?php echo $logFile->fileDate ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button id="downloadSelected" class="mp-button mp-button-small" disabled><?= $supportTranslations['support_modal_download_btn'] ?></button>
                <div class="mp-settings-button-group">
                    <div id="mp-pagination" class="mp-settings-pagination"></div>
                </div>
            <?php else : ?>
                <p class="mp-settings-modal-desc"><?= $supportTranslations['support_modal_no_content'] ?></p>
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
