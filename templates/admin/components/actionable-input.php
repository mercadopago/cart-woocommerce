<?php

/**
 * @var string $field_key
 * @var string $field_key_checkbox
 * @var string $field_value
 * @var string $enabled
 * @var string $custom_attributes
 * @var array $settings
 *
 * @see \MercadoPago\Woocommerce\Gateways\AbstractGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<tr valign="top">
    <th scope="row" class="titledesc mp-pb-0">
        <label for="<?php echo esc_attr($field_key); ?>">
            <?php echo esc_html($settings['title']); ?>
            <?php if (isset($settings['desc_tip'])) { ?>
                <span class="woocommerce-help-tip" data-tip="<?php echo esc_html($settings['desc_tip']); ?>"></span>
            <?php } ?>
            <?php if ($settings['description']) { ?>
                <p class="description mp-activable-input-subtitle"><?php echo wp_kses_post($settings['description']); ?></p>
            <?php } ?>
        </label>
    </th>

    <td class="forminp">
        <div>
            <fieldset>
                <input
                    class="input-text regular-input"
                    type="<?php echo esc_attr($settings['input_type']); ?>"
                    name="<?php echo esc_attr($field_key); ?>"
                    id="<?php echo esc_attr($field_key); ?>"
                    style="<?php echo esc_attr(isset($settings['css'])); ?>"
                    value="<?php echo esc_attr($field_value); ?>"
                    placeholder="<?php echo esc_attr(isset($settings['placeholder'])); ?>"
                    <?php echo($custom_attributes);?>
                />
                <br/>
                <label for="<?php echo esc_attr($field_key_checkbox); ?>">
                    <input
                        type="checkbox"
                        name="<?php echo esc_attr($field_key_checkbox); ?>"
                        id="<?php echo esc_attr($field_key_checkbox); ?>"
                        value="1"
                        <?php checked($enabled, 'yes'); ?>
                    />
                    <?php echo wp_kses_post($settings['checkbox_label']); ?>
                </label>
            </fieldset>
        </div>
    </td>
</tr>
