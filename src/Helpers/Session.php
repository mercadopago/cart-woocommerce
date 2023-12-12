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
     * @return mixed
     */
    public function getSession(string $key)
    {
        return WC()->session->get($key) ?? null;
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
        WC()->session->set($key, $value) ?? null;
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
        $this->setSession($key, null);
    }
}
