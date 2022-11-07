<?php

namespace MercadoPago\PP\Sdk\HttpClient\Requester;

use MercadoPago\PP\Sdk\HttpClient\Response;

/**
 * Class RequesterInterface
 *
 * @package MercadoPago\PP\Sdk\HttpClient\Curl;
 */
interface RequesterInterface
{
    /**
     * @param string|AbstractEntity|AbstractCollection|null $body
     *
     * @return \CurlHandle|resource
     */
    public function createRequest(string $method, string $uri, array $headers = [], $body = null);

    /**
     * @param \CurlHandle|resource $request
     */
    public function sendRequest($request): Response;
}
