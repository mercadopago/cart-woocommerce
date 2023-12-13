<?php

namespace MercadoPago\PP\Sdk\Common;

/**
 * Class Constants
 *
 * @package MercadoPago\PP\Sdk\Common
 */
class Constants
{
    const BASEURL_MP = 'https://api.mercadopago.com';
    const BASEURL_ML = 'https://api.mercadolibre.com';
    const THREE_DS_VALID_OPTIONS = array(self::THREE_DS_MODE_OPTIONAL,
    self::THREE_DS_MODE_MANDATORY, self::THREE_DS_MODE_NOT_SUPPORTED);
    const THREE_DS_MODE_OPTIONAL = 'optional';
    const THREE_DS_MODE_MANDATORY = 'mandatory';
    const THREE_DS_MODE_NOT_SUPPORTED = 'not_supported';
}
