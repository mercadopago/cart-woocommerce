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

<tr>
    <td class="label">
        <?= wc_help_tip($tip) ?>
        <?= esc_html($title) ?>
    </td>

    <td></td>

    <td class="total">
        <span class="amount">
            <?= wp_kses_post($value) ?>
        </span>
    </td>
</tr>
