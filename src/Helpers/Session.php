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
        //return $_SESSION[$key] ?? null;
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
        //$_SESSION[$key] = $value;
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
        //unset($_SESSION[$key]);
        $this->setSession($key, null);
    }
}
