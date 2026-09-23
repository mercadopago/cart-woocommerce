<?php

if (!defined('ABSPATH')) {
    exit;
}

$classicContent = '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->';
$blocksContent  = implode('', [
    '<!-- wp:woocommerce/checkout -->',
    '<div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading">',
    '<!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block">',
    '<!-- wp:woocommerce/checkout-express-payment-block /-->',
    '<!-- wp:woocommerce/checkout-contact-information-block /-->',
    '<!-- wp:woocommerce/checkout-shipping-address-block /-->',
    '<!-- wp:woocommerce/checkout-billing-address-block /-->',
    '<!-- wp:woocommerce/checkout-shipping-methods-block /-->',
    '<!-- wp:woocommerce/checkout-payment-block /-->',
    '<!-- wp:woocommerce/checkout-additional-information-block /-->',
    '<!-- wp:woocommerce/checkout-order-note-block /-->',
    '<!-- wp:woocommerce/checkout-terms-block /-->',
    '<!-- wp:woocommerce/checkout-actions-block /-->',
    '</div><!-- /wp:woocommerce/checkout-fields-block -->',
    '<!-- wp:woocommerce/checkout-totals-block --><div class="wp-block-woocommerce-checkout-totals-block">',
    '<!-- wp:woocommerce/checkout-order-summary-block --><div class="wp-block-woocommerce-checkout-order-summary-block">',
    '<!-- wp:woocommerce/checkout-order-summary-cart-items-block /-->',
    '<!-- wp:woocommerce/checkout-order-summary-subtotal-block /-->',
    '<!-- wp:woocommerce/checkout-order-summary-fee-block /-->',
    '<!-- wp:woocommerce/checkout-order-summary-discount-block /-->',
    '<!-- wp:woocommerce/checkout-order-summary-coupon-form-block /-->',
    '<!-- wp:woocommerce/checkout-order-summary-shipping-block /-->',
    '<!-- wp:woocommerce/checkout-order-summary-taxes-block /-->',
    '<!-- wp:woocommerce/checkout-order-summary-totals-block /-->',
    '</div><!-- /wp:woocommerce/checkout-order-summary-block -->',
    '</div><!-- /wp:woocommerce/checkout-totals-block -->',
    '</div><!-- /wp:woocommerce/checkout -->',
]);

$pages = [
    'checkout-classic' => [
        'post_title'   => 'Checkout Classic',
        'post_content' => $classicContent,
    ],
    'checkout-blocks' => [
        'post_title'   => 'Checkout Blocks',
        'post_content' => $blocksContent,
    ],
];
$pageIds = [];

foreach ($pages as $slug => $page) {
    $existing = get_page_by_path($slug, OBJECT, 'page');
    $payload  = [
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_name'    => $slug,
        'post_title'   => $page['post_title'],
        'post_content' => $page['post_content'],
    ];

    if ($existing) {
        $payload['ID'] = $existing->ID;
        $result        = wp_update_post($payload, true);
    } else {
        $result = wp_insert_post($payload, true);
    }

    if (is_wp_error($result)) {
        fwrite(STDERR, sprintf("Unable to provision %s checkout page.\n", $slug));
        exit(1);
    }

    $pageIds[$slug] = (int) $result;
}

// WooCommerce Blocks registers payment methods only for the official checkout
// page. Classic keeps working through its explicit shortcode route, so the
// stable pair can serve both modes without mutating this option during a run.
update_option('woocommerce_checkout_page_id', $pageIds['checkout-blocks'], false);
