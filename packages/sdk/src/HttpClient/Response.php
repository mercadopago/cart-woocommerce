<?php

namespace MercadoPago\PP\Sdk\HttpClient;

use MercadoPago\PP\Sdk\Common\AbstractCollection;

class Response
{
    /**
     * Response status
     *
     * @var boolean
     **/
    protected $status;

    /**
     * Response data
     *
     * @var AbstractEntity|AbstractCollection
     **/
    protected $data;

    /**
     * Response constructor.
     *
     * @param null|int    $status
     * @param null|object $data
     * @param null|string $message
     */
    public function __construct(
        $status = null,
        $data = null
    ) {
        $this->setStatus($status);
        $this->setData($data);
    }

    /**
     * Return ths status of response
     *
     * @return int
     **/
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set the status of response
     *
     * @param boolean|null $status
     *
     * @return void
     **/
    public function setStatus($status)
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
