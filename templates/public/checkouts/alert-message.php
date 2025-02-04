<?php

/**
 * @var string $message
 */

if (!defined('ABSPATH')) {
    exit;
}

?>

<p class="alert-message">
    <?= esc_html($message) ?>
</p>
