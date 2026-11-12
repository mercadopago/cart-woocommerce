<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix;

use InvalidArgumentException;

if (!defined('ABSPATH')) {
    exit;
}

/** Distinguishes an already-issued Pix from other validation failures without parsing messages. */
final class SmartPixExistingPixException extends InvalidArgumentException
{
    public const ERROR_CODE = 'SMART_PIX_EXISTING_PIX';

    public function __construct()
    {
        parent::__construct('A conventional Pix QR of this order may still be paid.');
    }
}
