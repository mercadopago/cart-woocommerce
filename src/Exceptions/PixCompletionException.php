<?php

namespace MercadoPago\Woocommerce\Exceptions;

use RuntimeException;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The Pix payment completion could not be confirmed as persisted. Fixed messages only
 * (they can reach HTTP responses); the persisted order state decides what a retry does.
 */
class PixCompletionException extends RuntimeException
{
}
