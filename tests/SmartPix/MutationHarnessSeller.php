<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\Tests\SmartPix;

use MercadoPago\Woocommerce\Configs\Seller;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;
use MercadoPago\Woocommerce\SmartPix\Core\SmartPixMoney;
use MercadoPago\Woocommerce\SmartPix\Contracts\SmartPixOrderClient;
use RuntimeException;
use WC_Order;

if (!defined('WP_CLI') || !WP_CLI || getenv('SMART_PIX_MUTATION_HARNESS') !== '1') {
    throw new RuntimeException('Synthetic mutation fixtures require explicit WP-CLI authorization.');
}

/** Synthetic seller context; no credential is read or fabricated. */
class MutationHarnessSeller extends Seller
{
    public function __construct()
    {
    }

    public function getSiteId(): string
    {
        return 'MLB';
    }

    public function getCustIdFromAT(): ?string
    {
        return '12345';
    }
}
