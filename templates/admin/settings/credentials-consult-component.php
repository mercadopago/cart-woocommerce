<?php

/**
 * Credentials Component
 *
 * @var array $credentialsState
 * @var array $allowedHtmlTags
 * @var string $publicKeyProd
 * @var string $accessTokenProd
 * @var string $publicKeyTest
 * @var string $accessTokenTest
 * @var array $credentialsTranslations
 * @var array $credentialsLinkComponents
 * @var array $links
 *
 * @see \MercadoPago\Woocommerce\Admin\Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="mp-block mp-modal-credentials-container">
    <header class="mp-credentials-link">
        <section class="mp-modal-title-container">
            <h1 class="mp-modal-title">
                <?= wp_kses($credentialsLinkComponents['credentials_modal_title'], $allowedHtmlTags) ?>
            </h1>
            <button class="mp-credentials-modal-button" id="mp-credentials-close-modal" aria-label="<?= wp_kses($credentialsTranslations['close_modal'], $allowedHtmlTags) ?>" title="<?= wp_kses($credentialsTranslations['close_modal'], $allowedHtmlTags) ?>">
                <div class="mp-modal-close-button"></div>
            </button>
        </section>
        <p class="mp-settings-subtitle-font-size mp-settings-font-color">
            <?= wp_kses($credentialsLinkComponents['credentials_modal_description'], $allowedHtmlTags) ?>
        </p>
    </header>
    <section class="mp-block mp-block-link-data">
        <header class="mp-credentials-header">
            <h2 class="mp-block-title">
                <?= wp_kses($credentialsLinkComponents['credentials_modal_app_name'], $allowedHtmlTags) ?>
            </h2>
            <p class="mp-block-description mp-settings-subtitle-font-size mp-settings-font-color">
                <?= wp_kses($credentialsState['app_name'] ?? '', $allowedHtmlTags) ?>
            </p>
        </header>
        <section class="mp-credentials-data">
            <section class="mp-credentials-content">
                <h2 class="mp-settings-modal-credentials-title">
                    <?= wp_kses($credentialsLinkComponents['credentials_modal_title_prod'], $allowedHtmlTags) ?>
                </h2>
                <p class="mp-settings-modal-credentials-subtitle mp-settings-subtitle-font-size mp-font-color-secondary">
                    <?= wp_kses($credentialsLinkComponents['credentials_modal_subtitle_prod'], $allowedHtmlTags) ?>
                </p>
                <fieldset class="mp-settings-fieldset">
                    <label for="mp-public-key-prod" class="mp-settings-modal-label mp-text-small mp-font-color-secondary">
                        <?= wp_kses($credentialsTranslations['public_key'], $allowedHtmlTags) ?>
                    </label>
                    <input type="text" id="mp-public-key-prod" class="mp-settings-input mp-settings-subtitle-font-size" value="<?= wp_kses($publicKeyProd, $allowedHtmlTags) ?>" readonly tabindex="-1" />
                </fieldset>
                <fieldset>
                    <label for="mp-access-token-production" class="mp-settings-modal-label mp-text-small mp-font-color-secondary">
                        <?= wp_kses($credentialsTranslations['access_token'], $allowedHtmlTags) ?>
                        <button class="mp-credentials-modal-button" id="mp-production-token-button" aria-label="<?= wp_kses($credentialsTranslations['show_access_token'], $allowedHtmlTags) ?>">
                            <div class="mp-access-token-button-closed" id="mp-production-token-img" title="<?= wp_kses($credentialsTranslations['show_access_token'], $allowedHtmlTags) ?>" aria-hidden="true"></div>
                        </button>
                    </label>
                    <input type="password" id="mp-access-token-production" class="mp-settings-input mp-settings-subtitle-font-size" value="<?= wp_kses($accessTokenProd, $allowedHtmlTags) ?>" readonly tabindex="-1" />
                </fieldset>
            </section>
            <section class="mp-credentials-content">
                <h2 class="mp-settings-modal-credentials-title">
                    <?= wp_kses($credentialsLinkComponents['credentials_modal_title_test'], $allowedHtmlTags) ?>
                </h2>
                <?php if ($publicKeyTest && $accessTokenTest) : ?>
                    <p class="mp-settings-modal-credentials-subtitle mp-settings-subtitle-font-size mp-font-color-secondary">
                        <?= wp_kses($credentialsLinkComponents['credentials_modal_subtitle_test'], $allowedHtmlTags) ?>
                    </p>
                    <fieldset class="mp-settings-fieldset">
                        <label for="mp-public-key-test" class="mp-settings-modal-label mp-text-small mp-font-color-secondary">
                            <?= wp_kses($credentialsTranslations['public_key'], $allowedHtmlTags) ?>
                        </label>
                        <input type="text" id="mp-public-key-test" class="mp-settings-input mp-settings-subtitle-font-size" value="<?= wp_kses($publicKeyTest, $allowedHtmlTags) ?>" readonly tabindex="-1" />
                    </fieldset>
                    <fieldset>
                        <label for="mp-access-token-test" class="mp-settings-modal-label mp-text-small mp-font-color-secondary">
                            <?= wp_kses($credentialsTranslations['access_token'], $allowedHtmlTags) ?>
                            <button class="mp-credentials-modal-button" id="mp-test-token-button" aria-label="<?= wp_kses($credentialsTranslations['show_access_token'], $allowedHtmlTags) ?>">
                                <div class="mp-access-token-button-closed" id="mp-test-token-img" title="<?= wp_kses($credentialsTranslations['show_access_token'], $allowedHtmlTags) ?>" aria-hidden="true"></div>
                            </button>
                        </label>
                        <input type="password" id="mp-access-token-test" class="mp-settings-input mp-settings-subtitle-font-size" value="<?= wp_kses($accessTokenTest, $allowedHtmlTags) ?>" readonly tabindex="-1" />
                    </fieldset>
                <?php else : ?>
                    <p class="mp-settings-modal-credentials-subtitle mp-settings-subtitle-font-size mp-font-color-secondary">
                        <?= wp_kses($credentialsLinkComponents['credentials_modal_subtitle_empty_test'], $allowedHtmlTags) ?>
                        <span>
                            <a href="<?= wp_kses($links['mercadopago_credentials'], $allowedHtmlTags) ?>" id="mp-modal-dashboard-link" target="_blank">
                                <?= wp_kses($credentialsLinkComponents['credentials_modal_dashboard_link'], $allowedHtmlTags) ?>
                            </a><span aria-disabled="true">.</span>
                        </span>
                    </p>
                    <fieldset class="mp-settings-fieldset mp-test-public-key" id="mp-test-public-key">
                        <label for="mp-public-key-test" class="mp-settings-modal-label mp-text-small mp-font-color-secondary">
                            <?= wp_kses($credentialsTranslations['public_key'], $allowedHtmlTags) ?>
                        </label>
                        <input type="text" id="mp-public-key-test" class="mp-settings-empty-input mp-settings-subtitle-font-size" value="<?= wp_kses($publicKeyTest, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($credentialsTranslations['placeholder_credentials'], $allowedHtmlTags) ?>" />
                    </fieldset>
                    <fieldset class="mp-test-access-token" id="mp-test-access-token">
                        <label for="mp-access-token-test" class="mp-settings-modal-label mp-text-small mp-font-color-secondary">
                            <?= wp_kses($credentialsTranslations['access_token'], $allowedHtmlTags) ?>
                            <button class="mp-credentials-modal-button" id="mp-test-token-button" aria-label="<?= wp_kses($credentialsTranslations['show_access_token'], $allowedHtmlTags) ?>">
                                <div class="mp-access-token-button-closed" id="mp-test-token-img" title="<?= wp_kses($credentialsTranslations['show_access_token'], $allowedHtmlTags) ?>" aria-hidden="true"></div>
                            </button>
                        </label>
                        <input type="password" id="mp-access-token-test" class="mp-settings-empty-input mp-settings-subtitle-font-size" value="<?= wp_kses($accessTokenTest, $allowedHtmlTags) ?>" placeholder="<?= wp_kses($credentialsTranslations['placeholder_credentials'], $allowedHtmlTags) ?>" />
                    </fieldset>
                <?php endif; ?>
            </section>
        </section>
    </section>
    <?php if ($publicKeyTest && $accessTokenTest) : ?>
        <footer class="mp-credentials-footer">
            <p class="mp-text-small mp-font-color-secondary">
                <?= wp_kses($credentialsLinkComponents['credentials_modal_footer_text'], $allowedHtmlTags) ?>
                <span>
                    <a href="<?= wp_kses($links['mercadopago_credentials'], $allowedHtmlTags) ?>" id="mp-get-credentials-link" target="_blank">
                        <?= wp_kses($credentialsLinkComponents['credentials_modal_dashboard_link'], $allowedHtmlTags) ?>
                    </a>
                    <span aria-disabled="true">.</span>
                </span>
            </p>
        </footer>
    <?php else : ?>
        <button class="mp-button mp-button-large" id="mp-btn-update-test-credentials">
            <?= wp_kses($credentialsLinkComponents['button_store_credentials'], $allowedHtmlTags) ?>
        </button>
    <?php endif; ?>
</div>
