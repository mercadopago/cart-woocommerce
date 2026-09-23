<?php

define('ABSPATH', __DIR__);
define('OBJECT', 'OBJECT');

$capturedPages  = [];
$capturedOption = null;

function get_page_by_path($slug, $output, $type)
{
    if ($slug === 'checkout-classic' && $output === OBJECT && $type === 'page') {
        return (object) ['ID' => 11];
    }

    return false;
}

function wp_update_post($payload, $returnError)
{
    global $capturedPages;

    $capturedPages[$payload['post_name']] = $payload;

    return $returnError ? $payload['ID'] : 0;
}

function wp_insert_post($payload, $returnError)
{
    global $capturedPages;

    $capturedPages[$payload['post_name']] = $payload;

    return $returnError ? 22 : 0;
}

function is_wp_error($value)
{
    return false;
}

function update_option($option, $value, $autoload)
{
    global $capturedOption;

    $capturedOption = [$option, $value, $autoload];

    return true;
}

require __DIR__ . '/setup-checkout-pages.php';

if (!isset($capturedPages['checkout-classic'], $capturedPages['checkout-blocks'])) {
    throw new RuntimeException('both fixed checkout pages must be provisioned');
}
if ($capturedOption !== ['woocommerce_checkout_page_id', 22, false]) {
    throw new RuntimeException('the Blocks page must be the official WooCommerce checkout');
}

fwrite(STDOUT, "setup-checkout-pages: ok\n");
