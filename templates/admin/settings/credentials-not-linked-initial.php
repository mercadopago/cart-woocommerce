<?php

use MercadoPago\Woocommerce\Helpers\I18n;
use MercadoPago\Woocommerce\Helpers\Template;

/**
 * @var string $credentials_state
 * @var string $type
 *
 * @see \MercadoPago\Woocommerce\Admin\Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

$i18n = I18n::get('admin.credentialsLinkComponents');

$nextPanelId = 'mp-settings-credentials-start-panel';

?>

<?php Template::render('admin/settings/credentials-select-country', ['next' => $nextPanelId]); ?>

<div id="<?= $nextPanelId ?>" class="mp-hidden">
    <div class="mp-settings-auto-credentials auto-credentials-create-link mp-block">
        <div class="mp-settings-initial-img mp-settings-auto-img-size">
        </div>
        <div class="mp-settings-auto-credentials-create">
            <h1 class="mp-settings-auto-title">
                <?= $i18n['initial_title'] ?>
            </h1>
            <span class="mp-settings-auto-description mp-settings-subtitle-font-size">
                <?= $i18n['initial_description'] ?>
            </span>
            <div>
                <button class="mp-button mp-button-large mp-button-light-blue" id="mp-credentials-change-country">
                    <?= $i18n['change_country'] ?>
                </button>
                <button class="mp-button mp-button-large" id="mp-integration-auth-login">
                    <?= $i18n['initial_button'] ?>
                </button>
            </div>
        </div>
    </div>
</div>
