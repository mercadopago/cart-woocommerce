<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Nonce
{
    /**
     * Generate wp_nonce
     *
     * @param string $id
     *
     * @return string
     */
    public function generateNonce(string $id): string
    {
        $nonce = wp_create_nonce($id);

        if (!$nonce) {
            return '';
        }

        return $nonce;
    }

    /**
     * Validate wp_nonce
     *
     * @param string $id
     * @param string $nonce
     *
     * @return void
     */
    public function validateNonce(string $id, string $nonce): void
    {
        if (!wp_verify_nonce($nonce, $id)) {
            wp_send_json_error('Forbidden', 403);
        }
    }
}
