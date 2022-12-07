<?php

namespace MercadoPago\PP\Sdk\HttpClient\Requester;

use MercadoPago\PP\Sdk\Common\AbstractCollection;
use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\HttpClient\Response;

/**
 * Interface RequesterInterface
 *
 * @package MercadoPago\PP\Sdk\HttpClient\Requester
 */
interface RequesterInterface
{
    /**
     * @param string|AbstractEntity|AbstractCollection|null $body
     *
     * @return resource
     */
    public function createRequest(string $method, string $uri, array $headers = [], $body = null);

    /**
     * @param resource $request
     */
    public function sendRequest($request): Response;
}
