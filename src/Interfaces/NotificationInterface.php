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
     * @param mixed $data
     *
     * @return void
     */
    public function handleReceivedNotification($data);
}
