<?php
if (!defined('ABSPATH')) {
    exit;
}

$currentOption = !empty($settings['tooltip_component_current_option']) ? $settings['tooltip_component_current_option'] : '';

if (empty($currentOption)) {
    $defaultOption = $settings['tooltip_component_option1'];
    $checkedOption = $settings['tooltip_component_option1'];
} else {
    $defaultOption = $currentOption;
    $checkedOption = $currentOption;
}
?>

<div class="credits-tooltip-selection-title-container">
    <label class="credits-tooltip-selection-title"><?= esc_html($settings['tooltip_component_title']) ?></label>
    <p class="credits-tooltip-selection-desc"><?= esc_html($settings['tooltip_component_desc']) ?></p>
</div>

<div class="credits-tooltip-selection-sample-container">
    <p><?= esc_html($settings['tooltip_component_example']) ?></p>
    <div class="mp-tooltip-sample-image-container">
        <img alt="Mercado Pago Mini Logo" src="<?php echo esc_html(plugins_url('../../assets/images/products/credits/tooltip-logo.svg', plugin_dir_path(__FILE__))); ?>" />
        <span id="selected-option"><?= esc_html($defaultOption) ?></span>
    </div>
</div>

<div class="credits-tooltip-selection-options-container">
    <input type="radio" id="option1" name="woocommerce_woo-mercado-pago-credits_tooltip_selection" text_value="<?= esc_html($settings['tooltip_component_option1']) ?>" value="1" <?= $checkedOption === $settings['tooltip_component_option1'] ? 'checked' : '' ?>>
    <label for="option1"><?= wp_kses_post($settings['tooltip_component_option1']) ?></label><br>

    <input type="radio" id="option2" name="woocommerce_woo-mercado-pago-credits_tooltip_selection" text_value="<?= esc_html($settings['tooltip_component_option2']) ?>" value="2" <?= $checkedOption === $settings['tooltip_component_option2'] ? 'checked' : '' ?>>
    <label for="option2"><?= wp_kses_post($settings['tooltip_component_option2']) ?></label><br>

    <input type="radio" id="option3" name="woocommerce_woo-mercado-pago-credits_tooltip_selection" text_value="<?= esc_html($settings['tooltip_component_option3']) ?>" value="3" <?= $checkedOption === $settings['tooltip_component_option3'] ? 'checked' : '' ?>>
    <label for="option3"><?= wp_kses_post($settings['tooltip_component_option3']) ?></label><br>

    <input type="radio" id="option4" name="woocommerce_woo-mercado-pago-credits_tooltip_selection" text_value="<?= esc_html($settings['tooltip_component_option4']) ?>" value="4" <?= $checkedOption === $settings['tooltip_component_option4'] ? 'checked' : '' ?>>
    <label for="option4"><?= wp_kses_post($settings['tooltip_component_option4']) ?></label><br>
</div>

<?php
if (isset($settings['after_toggle'])) {
    echo wp_kses_post($settings['after_toggle']);
}
?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const radioButtons = document.querySelectorAll('.credits-tooltip-selection-options-container input[type="radio"]');
        const selectedOptionSpan = document.getElementById('selected-option');

        function updateSelectedOption(event) {
            const selectedValue = event.target.getAttribute('text_value');
            const dotIndex = selectedValue.indexOf('.');
            if (dotIndex !== -1) {
                const firstPart = selectedValue.substring(0, dotIndex + 1);
                const remainingPart = selectedValue.substring(dotIndex + 1);
                selectedOptionSpan.innerHTML = `${firstPart}<span style="color: #009EE3">${remainingPart}</span>`;
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
