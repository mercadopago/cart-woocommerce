<?php

namespace MercadoPago\PP\Sdk\Entity\Identification;

/**
 * Class Platform
 *
 * @property string $id
 * @property string $cpp_token
 *
 * @package MercadoPago\PP\Sdk\Entity\Identification
 */
class ResponseCreateSellerFunnelBase
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $cpp_token;


    /**
     * Identification constructor.
     *
     * @param string $id
     * @param string $cpp_token
     */
    public function __construct(string $id, string $cpp_token)
    {
        $this->id = $id;
        $this->cpp_token = $cpp_token;
    }
}
