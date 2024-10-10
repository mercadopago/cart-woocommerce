<?php

/**
 * @var array $settings
 *
 * @see \MercadoPago\Woocommerce\Gateways\AbstractGateway
 */

if (!defined('ABSPATH')) {
    exit;
}

?>


<p  class="mp-support-link-text">
   <span class="mp-support-link-bold_text"><?= esc_html($settings['bold_text']) ?></span>
   <span><?= esc_html($settings['text_before_link']) ?></span>
   <span><a href="<?= esc_html($settings['support_link']) ?>" target="_blank" class="mp-support-link-text-with-link"><?= esc_html($settings['text_with_link']) ?></a></span>
   <span><?= esc_html($settings['text_after_link']) ?></span>
</p>
