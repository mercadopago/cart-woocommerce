<?php

namespace MercadoPago\Woocommerce\Api\Http;

if (!defined('ABSPATH')) {
    exit;
}

class HttpResponse
{
    private int $status;

    private array $headers;

    private string $body;

    /**
     * @var mixed
     */
    private $data;

    /**
     * @param mixed $data
     */
    public function __construct(int $status = 0, array $headers = [], string $body = '', $data = null)
    {
        $this->status  = $status;
        $this->headers = $headers;
        $this->body    = $body;
        $this->data    = $data;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     */
    public function setData($data): void
    {
        $this->data = $data;
    }
}
