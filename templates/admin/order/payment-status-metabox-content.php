<?php

/**
 * @var string $tip
 * @var string $title
 * @var string $value
 *
 * @see \MercadoPago\Woocommerce\Hooks\Order
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div id="mp-payment-status-container">
    <p style="font-family: 'Lato', sans-serif; font-size: 14px;">
        <?php echo esc_html($card_title); ?>
    </p>

    <div id="mp-payment-status-content" class="mp-status-sync-metabox-content" style="border-left: 4px solid <?php echo esc_html($border_left_color); ?>; min-height: 70px;">
        <div class="mp-status-sync-metabox-icon" style="width: 0 !important; padding: 0 10px;">
            <img
                alt="alert"
                src="<?php echo esc_url($img_src); ?>"
                class="mp-status-sync-metabox-circle-img"
            />
        </div>

        <div class="mp-status-sync-metabox-text">
            <h2 class="mp-status-sync-metabox-title" style="font-weight: 700; padding: 12px 0 0 0; font-family: 'Lato', sans-serif; font-size: 16px">
                <?php echo esc_html($alert_title); ?>
            </h2>

            <p class="mp-status-sync-metabox-description" style="font-family: 'Lato', sans-serif;">
                <?php echo esc_html($alert_description); ?>
            </p>

            <p style="margin: 12px 0 4px; display: flex; align-items: center; justify-content: flex-start;">

                <button type="button" id="mp-sync-payment-status-button" class="mp-status-sync-metabox-button primary">
                    <span><?php echo esc_html($sync_button_text); ?></span>
                    <div class="mp-status-sync-metabox-small-loader" style="display: none"></div>
                </button>

                <a
                    href="<?php echo esc_url($link); ?>"
                    target="__blank"
                    class="mp-status-sync-metabox-link"
                >
                    <?php echo esc_html($link_description); ?>
                </a>
            </p>
        </div>
    </div>
</div>
