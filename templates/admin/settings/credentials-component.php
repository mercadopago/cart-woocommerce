<?php

use MercadoPago\Woocommerce\Helpers\I18n;
use MercadoPago\Woocommerce\Helpers\Template;

/**
 * Credentials Component
 *
 * @var array $credentialsState
 * @var array $allowedHtmlTags
 *
 * @see \MercadoPago\Woocommerce\Admin\Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

$i18n = I18n::get('admin.credentialsLinkComponents');

?>

<?php if ($credentialsState['credentials_state'] == 'linked') { ?>
    <div id="mp-settings-credentials-linked" class="mp-settings-auto-credentials auto-credentials-linked mp-block">
        <div class="mp-settings-auto-credentials-consult">
            <div class="mp-settings-auto-link-state">
                <h1 class="mp-settings-auto-link-state-title">
                    <?= wp_kses($credentialsState['title'], $allowedHtmlTags) ?>
                </h1>
                <span class="mp-settings-auto-link-state-description mp-settings-subtitle-font-size">
                    <?= wp_kses($credentialsState['description'], $allowedHtmlTags) ?>
                </span>
            </div>
        </div>
        <div class="mp-settings-auto-divider"></div>
        <div class="mp-settings-auto-credentials-consult">
            <section class="mp-settings-auto-linked-store <?= esc_attr($credentialsState['type'])?>">
                <?php if ($credentialsState['linked_account'] == 'error') { ?>
                    <div class="linked-store-img-warning"></div>
                <?php } else { ?>
                    <div class="linked-store-img"></div>
                <?php } ?>
                <div class="linked-store-data">
                    <span class="mp-settings-subtitle-font-size">
                        <span><?= wp_kses($credentialsState['store_name'], $allowedHtmlTags) ?></span>
                    </span>
                    <span class="linked-store-data-contact">
                        <?= wp_kses($credentialsState['store_contact'], $allowedHtmlTags) ?>
                    </span>
                    <?php if ($credentialsState['linked_account'] != 'error') { ?>
                        <div aria-labelledby="valid-text" class="linked-store-status">
                            <div id="valid-img" class="linked-store-valid-img"></div>
                            <span id="valid-text" class="mp-font-color-secondary">
                                <?= wp_kses($credentialsState['linked_account'], $allowedHtmlTags) ?>
                            </span>
                        </div>
                    <?php } ?>
                </div>
                <?php if ($credentialsState['linked_account'] == 'error') { ?>
                    <button class="mp-settings-button mp-auto-button" onclick="window.location.reload()">
                        <?= wp_kses($credentialsState['button'], $allowedHtmlTags) ?>
                    </button>
                <?php } else { ?>
                    <button id="mp-switch-account-btn" class="mp-settings-button mp-auto-button">
                        <?= wp_kses($credentialsState['button'], $allowedHtmlTags) ?>
                    </button>
                <?php } ?>
            </section>
            <?php if ($credentialsState['linked_account'] != 'error') { ?>
                <div class="mp-settings-auto-data-consult mp-text-small">
                    <span class="mp-font-color-secondary">
                        <?= wp_kses($credentialsState['more_info'], $allowedHtmlTags) ?>
                        <span>
                            <a href="#" id="mp-settings-auto-data-consult">
                                <?= wp_kses($credentialsState['linked_data'], $allowedHtmlTags) ?>
                            </a>
                            <span aria-disabled="true"><?= wp_kses($credentialsState['period'], $allowedHtmlTags) ?></span>
                        </span>
                    </span>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php Template::render('admin/settings/credentials-select-country', [
        'switch_account'  => true,
        'current_site_id' => $credentialsState['current_site_id']
    ]); ?>
<?php } else { ?>
    <?php if ($credentialsState['type'] === 'initial') : ?>
        <?php Template::render('admin/settings/credentials-not-linked-initial', $credentialsState); ?>
    <?php else : ?>
        <div class="mp-settings-auto-credentials auto-credentials-create-link mp-block">
            <div class="mp-settings-<?= wp_kses($credentialsState['type'], $allowedHtmlTags) ?>-img mp-settings-auto-img-size">
            </div>
            <div class="mp-settings-auto-credentials-create">
                <h1 class="mp-settings-auto-title">
                    <?= wp_kses($credentialsState['title'], $allowedHtmlTags) ?>
                </h1>
                <span class="mp-settings-auto-description mp-settings-subtitle-font-size">
                    <?= wp_kses($credentialsState['description'], $allowedHtmlTags) ?>
                </span>
                <div>
                    <button class="mp-button mp-button-large" id="<?php echo $credentialsState['type'] === 'update' ? "mp-integration-auth-login-update" : "mp-integration-auth-failed" ?>">
                        <?= wp_kses($credentialsState['button'], $allowedHtmlTags) ?>
                    </button>
                    <?php if (isset($credentialsState['secondary_button'])) : ?>
                        <button class="mp-button mp-button-large mp-button-light-blue"
                            id="<?php echo $credentialsState['type'] === 'unauthorized' ? "mp-integration-auth-login-update" : "" ?>">
                            <?= wp_kses($credentialsState['secondary_button'], $allowedHtmlTags) ?>
                        </button>
                    <?php endif ?>
                </div>
            </div>
        </div>
    <?php endif ?>
<?php } ?>
