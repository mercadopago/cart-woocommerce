<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\PP\Sdk\HttpClient\HttpClientInterface;
use MercadoPago\PP\Sdk\HttpClient\Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Requester
{
    /**
     * @const
     */
    public const BASEURL_MP = 'https://api.mercadopago.com';

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * Requester constructor
     *
     * @param HttpClientInterface $httpClient
     */
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @param string $uri
     * @param array $headers
     *
     * @return Response
     * @throws \Exception
     */
    public function get(string $uri, array $headers = []): Response
    {
        return $this->httpClient->get($uri, $headers);
    }

    /**
     * @param string $uri
     * @param array $headers
     * @param array $body
     *
     * @return Response
     * @throws \Exception
     */
    public function post(string $uri, array $headers = [], array $body = []): Response
    {
        return $this->httpClient->post($uri, $headers, json_encode($body));
    }

    /**
     * @param string $uri
     * @param array $headers
     * @param array $body
     *
     * @return Response
     * @throws \Exception
     */
    public function put(string $uri, array $headers = [], array $body = []): Response
    {
        return $this->httpClient->put($uri, $headers, json_encode($body));
    }
}
