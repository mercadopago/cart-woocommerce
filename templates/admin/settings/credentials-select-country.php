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
                <?= wp_kses_post($i18n['title']) ?>
            </h1>
            <span class="mp-settings-auto-description mp-settings-subtitle-font-size">
                <?= wp_kses_post($i18n['description']) ?>
            </span>
            <div class="mp-input-group">
                <div id="mp-credentials-country-select" class="mp-select" aria-expanded="false">
                    <input type="hidden" id="mp-credentials-country" value="<?= esc_attr($current_site_id) ?>">
                    <button role="button" aria-label="<?= esc_attr($i18n['placeholder']) ?>">
                        <?= wp_kses_post(I18n::get('admin.countries')[$current_site_id] ?? $i18n['placeholder']) ?>
                    </button>
                    <ul role="list" class="mp-hidden" tabindex="-1">
                        <?php foreach (I18n::get('admin.countries') as $siteId => $country) : ?>
                            <li role="option" data-value="<?= esc_attr($siteId) ?>" aria-selected="<?= esc_attr($siteId === $current_site_id ? 'true' : 'false') ?>">
                                <div class="mp-select-pipe"></div>
                                <?= wp_kses_post($country) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="mp-error-msg" tabindex="-1" aria-label="<?= esc_attr($i18n['empty_error']) ?>">
                    <img src="<?= esc_url(Helpers::get('url')->getImageAsset('settings/error.svg')) ?>" alt="">
                    <?= wp_kses_post($i18n['empty_error']) ?>
                </div>
                <button type="button" id="mp-button-country" class="mp-button mp-button-large" <?= $switch_account ? 'data-switch-account="true"' : 'data-next="' . esc_attr('#' . $next) . '" data-success="' . esc_attr($i18n['success']) . '"' ?>>
                    <?= wp_kses_post($i18n['continue']) ?>
                </button>

            </div>
        </div>
    </div>
</div>
