<?php

namespace MercadoPago\Woocommerce\SuperToken\Contracts;

use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

interface SuperTokenMetadataWriter
{
    public function write(WC_Order $order, array $response, PaymentMetadata $internalMetadata): void;
}
