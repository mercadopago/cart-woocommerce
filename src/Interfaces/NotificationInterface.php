<?php

namespace MercadoPago\Woocommerce\Interfaces;

if (!defined('ABSPATH')) {
    exit;
}

interface NotificationInterface
{
    /**
     * Handle Notification Request
     *
     * @param string $message
     *
     * @return void
     */
    public function handleReceivedNotification();
}
