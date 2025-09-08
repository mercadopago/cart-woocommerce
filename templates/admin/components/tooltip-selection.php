<?php

/**
 * @var array{
 *      i18n: string[],
 *      after_toggle: string,
 *      icon: string,
 *      current_tooltip_id: int
 * } $settings
 *
 * @see \MercadoPago\Woocommerce\Gateways\AbstractGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

$currentOption = $settings['i18n']["tooltip_component_option{$settings['current_tooltip_id']}"];

?>

<div class="credits-tooltip-selection-title-container">
    <label class="credits-tooltip-selection-title"><?= esc_html($settings['i18n']['tooltip_component_title']) ?></label>
    <p class="credits-tooltip-selection-desc"><?= esc_html($settings['i18n']['tooltip_component_desc']) ?></p>
</div>

<div class="credits-tooltip-selection-sample-container">
    <p><?= esc_html($settings['i18n']['tooltip_component_example']) ?></p>
    <div class="mp-tooltip-sample-image-container">
        <img alt="Mercado Pago Mini Logo" src="<?= esc_url($settings['icon']) ?>" />
        <span id="selected-option"><?= esc_html($currentOption) ?></span>
    </div>
</div>

<div class="credits-tooltip-selection-options-container">
    <input type="radio" id="option1" name="woocommerce_woo-mercado-pago-credits_tooltip_selection"
        text_value="<?= esc_html($settings['i18n']['tooltip_component_option1']) ?>" value="1"
        <?= $currentOption === $settings['i18n']['tooltip_component_option1'] ? 'checked' : '' ?>>
    <label for="option1"><?= wp_kses_post($settings['i18n']['tooltip_component_option1']) ?></label><br>

    <input type="radio" id="option2" name="woocommerce_woo-mercado-pago-credits_tooltip_selection"
        text_value="<?= esc_html($settings['i18n']['tooltip_component_option2']) ?>" value="2"
        <?= $currentOption === $settings['i18n']['tooltip_component_option2'] ? 'checked' : '' ?>>
    <label for="option2"><?= wp_kses_post($settings['i18n']['tooltip_component_option2']) ?></label><br>

    <input type="radio" id="option3" name="woocommerce_woo-mercado-pago-credits_tooltip_selection"
        text_value="<?= esc_html($settings['i18n']['tooltip_component_option3']) ?>" value="3"
        <?= $currentOption === $settings['i18n']['tooltip_component_option3'] ? 'checked' : '' ?>>
    <label for="option3"><?= wp_kses_post($settings['i18n']['tooltip_component_option3']) ?></label><br>

    <input type="radio" id="option4" name="woocommerce_woo-mercado-pago-credits_tooltip_selection"
        text_value="<?= esc_html($settings['i18n']['tooltip_component_option4']) ?>" value="4"
        <?= $currentOption === $settings['i18n']['tooltip_component_option4'] ? 'checked' : '' ?>>
    <label for="option4"><?= wp_kses_post($settings['i18n']['tooltip_component_option4']) ?></label><br>
</div>

<?= isset($settings['after_toggle']) ? wp_kses_post($settings['after_toggle']) : '' ?>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const radioButtons = document.querySelectorAll('.credits-tooltip-selection-options-container input[type="radio"]');
        const selectedOptionSpan = document.getElementById('selected-option');

        function updateSelectedOption(event) {
            const selectedValue = event.target.getAttribute('text_value');
            const dotIndex = selectedValue.indexOf('.');
            if (dotIndex !== -1) {
                const firstPart = selectedValue.substring(0, dotIndex + 1);
                const remainingPart = selectedValue.substring(dotIndex + 1);
                selectedOptionSpan.innerHTML = `${firstPart}<span class="learn-more">${remainingPart}</span>`;
            } else {
                selectedOptionSpan.textContent = selectedValue;
            }
        }

        radioButtons.forEach(radioButton => {
            radioButton.addEventListener('change', updateSelectedOption);
        });

        updateSelectedOption({
            target: document.querySelector('input[type="radio"]:checked')
        });
    });
</script>
