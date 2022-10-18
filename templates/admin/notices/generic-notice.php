<?php

if (!defined('ABSPATH')) {
    exit;
}

?>

<div id="message" class="notice <?= esc_attr($type) ?> <?= esc_attr($isDismissible) ?>">
    <div class="mp-alert-frame">
        <div class="mp-left-alert">
            <img src="<?= esc_url($minilogo) ?>" alt="Mercado Pago mini logo" />
        </div>

        <div class="mp-right-alert">
            <p><?= esc_html($message) ?></p>
        </div>
    </div>
</div>