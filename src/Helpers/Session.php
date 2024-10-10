<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

final class Session
{
    /**
     * Get session
     *
     * @param string $key
     *
     * @return array|string|null
     */
    public function getSession(string $key)
    {
        if ($this->isAvailable()) {
            return WC()->session->get($key) ?? null;
        }

        return null;
    }

    /**
     * Set session
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     */
    public function setSession(string $key, $value): void
    {
        if ($this->isAvailable()) {
            WC()->session->set($key, $value) ?? null;
        }
    }

    /**
     * Delete session
     *
     * @param string $key
     *
     * @return void
     */
    public function deleteSession(string $key): void
    {
        if ($this->isAvailable()) {
            $this->setSession($key, null);
        }
    }

    /**
     * Verify if WC_Session exists and is available
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return WC()->session !== null;
    }
}
