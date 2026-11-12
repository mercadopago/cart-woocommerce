<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Contracts;

use MercadoPago\Woocommerce\SmartPix\Core\SmartPixAuthorizedPayment;

if (!defined('ABSPATH')) {
    exit;
}

/** Server-only admission immediately before the create POST, after all local validation. */
interface SmartPixCreationDispatchClient extends SmartPixOrderClient
{
    public function create(array $request, ?SmartPixAuthorizedPayment $payment = null, ?callable $onDispatch = null): array;
}
