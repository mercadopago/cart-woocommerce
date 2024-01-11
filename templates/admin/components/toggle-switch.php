<?php

/**
 * @var string $field_key
 * @var string $field_value
 * @var array  $settings
 *
 * @see \MercadoPago\Woocommerce\Gateways\AbstractGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="<?= esc_attr($field_key); ?>">
            <?= esc_html($settings['title']) ?>

            <?php if (isset($settings['desc_tip'])) : ?>
                <span class="woocommerce-help-tip" data-tip="<?= esc_html($settings['desc_tip']) ?>"></span>
            <?php endif; ?>

            <?php if (isset($settings['title_badge'])) : ?>
                <span class="woocommerce-help-tip" data-tip="<?= esc_html($settings['title_badge']) ?>"></span>
            <?php endif; ?>

            <?php if ($settings['subtitle']) : ?>
                <p class="description mp-toggle-subtitle"><?= wp_kses_post($settings['subtitle']) ?></p>
            <?php endif; ?>
        </label>
    </th>

    <td class="forminp">
        <div class="mp-component-card">
            <label class="mp-toggle">
                <input
                    id="<?= esc_attr($field_key) ?>"
                    name="<?= esc_attr($field_key) ?>"
                    class="mp-toggle-checkbox"
                    type="checkbox"
                    value="yes"
                    <?= checked($field_value, 'yes') ?>
                />

                <div class="mp-toggle-switch"></div>

                <div class="mp-toggle-label">
                    <span class="mp-toggle-label-enabled"><?= wp_kses($settings['descriptions']['enabled'], 'b') ?></span>
                    <span class="mp-toggle-label-disabled"><?= wp_kses($settings['descriptions']['disabled'], 'b') ?></span>
                </div>
            </label>
        </div>

        <?php
        if (isset($settings['after_toggle'])) {
            echo wp_kses_post($settings['after_toggle']);
        }
        ?>
    </td>
</tr>
