<?php

/**
 * @var string $miniLogo
 * @var string $message
 * @var string $urlLink
 * @var string $textLink
 *
 * @see \MercadoPago\Woocommerce\Helpers\Notices
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<div id="message" class="notice notice-warning is-dismissible">
    <div class="mp-alert-frame">
        <div class="mp-left-alert">
            <img src="<?= esc_url($miniLogo) ?>" alt="Mercado Pago mini logo"/>
        </div>

        <div class="mp-right-alert">
            <p>
                <?= esc_html($message) ?>
                <a class="mp-mouse_pointer" href="<?= esc_html($urlLink); ?>" target="_blank">
                    <b><u> <?= esc_html($textLink); ?> </u></b>
                </a>
            </p>
        </div>
    </div>
</div>
