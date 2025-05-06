<?php

use MercadoPago\Woocommerce\Helpers;
use MercadoPago\Woocommerce\Helpers\I18n;

/**
 * @var bool $switch_account
 * @var string $next
 * @var string $current_site_id
 *
 * @see \MercadoPago\Woocommerce\Admin\Settings
 */

if (!defined('ABSPATH')) {
    exit;
}

$i18n = I18n::get('admin.credentialsLinkComponents.select_country');

$switch_account ??= false;
$current_site_id ??= null;

?>

<div id="mp-settings-credentials-country" class="<?= $switch_account ? 'mp-hidden' : '' ?>">
    <div class="mp-settings-auto-credentials auto-credentials-create-link mp-block">
        <div class="mp-settings-country-img mp-settings-auto-img-size">
        </div>
        <div class="mp-settings-auto-credentials-create">
            <h1 class="mp-settings-auto-title">
                <?= $i18n['title'] ?>
            </h1>
            <span class="mp-settings-auto-description mp-settings-subtitle-font-size">
                <?= $i18n['description'] ?>
            </span>
            <div class="mp-input-group">
                <div id="mp-credentials-country-select" class="mp-select" aria-expanded="false">
                    <input type="hidden" id="mp-credentials-country" value="<?= $current_site_id ?>">
                    <button role="button" aria-label="<?= $i18n['placeholder'] ?>">
                        <?= I18n::get('admin.countries')[$current_site_id] ?? $i18n['placeholder'] ?>
                    </button>
                    <ul role="list" class="mp-hidden" tabindex="-1">
                        <?php foreach (I18n::get('admin.countries') as $siteId => $country) : ?>
                            <li role="option" data-value="<?= $siteId ?>" aria-selected="<?= $siteId === $current_site_id ? 'true' : 'false' ?>">
                                <div class="mp-select-pipe"></div>
                                <?= $country ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="mp-error-msg" tabindex="-1" aria-label="<?= $i18n['empty_error'] ?>">
                    <img src="<?= Helpers::get('url')->getImageAsset('settings/error.svg') ?>" alt="">
                    <?= $i18n['empty_error'] ?>
                </div>
                <button type="button" id="mp-button-country" class="mp-button mp-button-large" <?= $switch_account ? 'data-switch-account="true"' : "data-next='#$next' data-success='{$i18n['success']}'" ?>>
                    <?= $i18n['continue'] ?>
                </button>

            </div>
        </div>
    </div>
</div>
