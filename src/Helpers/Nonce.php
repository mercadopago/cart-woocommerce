<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\Woocommerce\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

final class Nonce
{
    /**
     * @var Logs
     */
    private $logs;

    /**
     * Nonce constructor
     */
    public function __construct(Logs $logs)
    {
        $this->logs = $logs;
    }

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
            $this->logs->file->error('Security nonce ' . $id . ' creation failed.', __CLASS__);
            return '';
        }

        return $nonce;
    }

    /**
     * Retrieves or display nonce hidden field for forms
     *
     * @param string $id
     * @param string $fieldName
     *
     * @return string
     */
    public function generateNonceField(string $id, string $fieldName): string
    {
        return wp_nonce_field($id, $fieldName);
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
            $this->logs->file->error('Security nonce ' . $id . ' check failed.', __FUNCTION__);
            wp_send_json_error('Forbidden', 403);
        }
    }
}
