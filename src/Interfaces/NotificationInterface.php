<?php

namespace MercadoPago\Woocommerce\Interfaces;

if (!defined('ABSPATH')) {
    exit;
}

interface NotificationInterface
{
    public function handleReceivedNotification();
}
