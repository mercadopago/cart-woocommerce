<?php

namespace MercadoPago\Woocommerce\SuperToken\Adapters;

use MercadoPago\Woocommerce\Entities\Metadata\PaymentMetadata;
use MercadoPago\Woocommerce\Order\OrderMetadata;
use MercadoPago\Woocommerce\SuperToken\Contracts\SuperTokenMetadataWriter;
use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class OrderMetadataSuperTokenWriter implements SuperTokenMetadataWriter
{
    private OrderMetadata $orderMetadata;

    public function __construct(OrderMetadata $orderMetadata)
    {
        $this->orderMetadata = $orderMetadata;
    }

    public function write(WC_Order $order, array $response, PaymentMetadata $internalMetadata): void
    {
        $this->orderMetadata->setSupertokenMetadata($order, $response, $internalMetadata);
    }
}
