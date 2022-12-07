<?php

namespace MercadoPago\PP\Sdk\HttpClient;

use MercadoPago\PP\Sdk\Common\AbstractCollection;
use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Class Response
 *
 * @package MercadoPago\PP\Sdk\HttpClient
 */
class Response
{
    /**
     * Response status
     *
     * @var int
     **/
    private $status;

    /**
     * Response data
     *
     * @var AbstractEntity|AbstractCollection
     **/
    private $data;

    /**
     * Response constructor.
     */
    public function __construct()
    {
    }

    /**
     * Return ths status of response
     *
     * @return int
     **/
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Set the status of response
     *
     * @param int|null $status
     *
     * @return void
     **/
    public function setStatus(int $status)
    {
        $this->status = $status;
    }

    /**
     * Return the data of response
     *
     * @return object|null
     **/
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the data of response
     *
     * @param object $data
     *
     * @return void
     **/
    public function setData($data)
    {
        $this->data = $data;
    }
}
