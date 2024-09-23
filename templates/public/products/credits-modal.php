<?php

/**
 * @var string $tooltip_html
 * @var string $tooltip_link
 * @var string $modal_title
 * @var string $modal_step_1
 * @var string $modal_step_2
 * @var string $modal_step_3
 * @var string $modal_footer_init
 * @var string $modal_footer
 * @var string $modal_footer_help_link
 * @var string $modal_footer_link
 *
 * @see \MercadoPago\Woocommerce\Gateways\CreditsGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div id="tooltipComponent" class="mp-credits-tooltip-container">
    <img alt="mp-logo-hand-shake" class="mp-credits-tooltip-round-logo" src="<?php echo esc_html(plugins_url('../../assets/images/products/credits/tooltip-logo.svg', plugin_dir_path(__FILE__))); ?>">
    <div class="mp-credits-tooltip-text">
        <span><?php echo wp_kses_post($tooltip_html); ?></span>
        <span class="mp-credits-tooltip-link"><a id="mp-open-modal"><?php echo esc_html($tooltip_link); ?></a></span>
    </div>
</div>

<div id="mp-credits-modal">
    <div id="mp-credits-centralize" class="mp-credits-modal-content-centralize">
        <div class="mp-credits-modal-container">
            <div class="mp-credits-modal-container-content">
                <div class="mp-credits-modal-content">
                    <div class="mp-credits-modal-close-button">
                        <img id="mp-credits-modal-close-modal" src="<?php echo esc_html(plugins_url('../../assets/images/products/credits/close-icon.png', plugin_dir_path(__FILE__))); ?>">
                    </div>
                    <div class="mp-logo-img">
                        <img src="<?php echo esc_html(plugins_url('../../assets/images/products/credits/credits-modal-logo.png', plugin_dir_path(__FILE__))); ?>">
                    </div>

                    <div class="mp-credits-modal-titles">
                        <div class="mp-credits-modal-brand-title">
                            <span><?php echo esc_html($modal_title); ?></span>
                        </div>
                        <div class="mp-credits-modal-info">
                            <div class="mp-credits-modal-how-to-use">
                                <div>
                                    <div class="mp-credits-modal-step-circle"><div class="mp-step-mark">1</div></div>
                                    <span class="mp-credits-modal-step-circle-text"><?php echo esc_html($modal_step_1); ?></span>
                                </div>
                                <div>
                                <div class="mp-credits-modal-step-circle"><div class="mp-step-mark">2</div></div>
                                <span class="mp-credits-modal-step-circle-text"><?php echo esc_html($modal_step_2); ?></span>
                                </div>
                                <div>
                                <div class="mp-credits-modal-step-circle"><div class="mp-step-mark">3</div></div>
                                <span class="mp-credits-modal-step-circle-text"><?php echo esc_html($modal_step_3); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mp-credits-modal-FAQ">
                        <p>
                            <?php echo esc_html($modal_footer_init); ?>
                            <br>
                            <br>
                            <?php echo esc_html($modal_footer); ?>
                            <a id="mp-modal-footer-link" target="_blank" href="<?php echo esc_html($modal_footer_help_link); ?>"><?php echo esc_html($modal_footer_link); ?></a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" type="module">
    const tooltipComponent = document.getElementById('tooltipComponent');
    const itemDetailsDiv = document.getElementsByClassName('woocommerce-product-details__short-description')[0];

    if (itemDetailsDiv) {
        const childrenStyle = window.getComputedStyle(itemDetailsDiv.children[0]);
        tooltipComponent.style.margin = childrenStyle.margin;
    } else {
        const parentStyle = window.getComputedStyle(tooltipComponent.parentNode);

        if (parentStyle.marginTop != "0px") {
            tooltipComponent.style.marginBottom = parentStyle.marginTop;
        } else if (parentStyle.paddingTop != "0px") {
            tooltipComponent.style.paddingBottom = parentStyle.paddingTop;
        } else if (parentStyle.marginBlockStart != "0px") {
            tooltipComponent.style.marginBlockEnd = parentStyle.marginBlockStart;
        }
    }
</script>
