<?php

namespace MercadoPago\Woocommerce\Helpers;

use MercadoPago\PP\Sdk\HttpClient\HttpClient;
use MercadoPago\PP\Sdk\HttpClient\HttpClientInterface;
use MercadoPago\PP\Sdk\HttpClient\Requester\CurlRequester;
use MercadoPago\PP\Sdk\HttpClient\Requester\RequesterInterface;
use MercadoPago\PP\Sdk\HttpClient\Response;

if (!defined('ABSPATH')) {
    exit;
}

final class Requester
{
    private const BASEURL_MP = 'https://api.mercadopago.com';

    /**
     * @var RequesterInterface
     */
    protected $requester;

    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * @var Requester
     */
    private static $instance = null;

    private function __construct()
    {
        $this->requester  = new CurlRequester();
        $this->httpClient = new HttpClient(self::BASEURL_MP, $this->requester);
    }

    /**
     * Get Requester instance
     *
     * @return Requester
     */
    public static function getInstance(): Requester
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param string $uri
     * @param array  $headers
     *
     * @return Response
     */
    public function get(string $uri, array $headers = []): Response
    {
        return $this->httpClient->get($uri, $headers);
    }

    /**
     * @param string $uri
     * @param array  $headers
     * @param array  $body
     *
     * @return Response
     */
    public function post(string $uri, array $headers = [], array $body = []): Response
    {
        return $this->httpClient->post($uri, $headers, json_encode($body));
    }

    /**
     * @param string $uri
     * @param array  $headers
     * @param array  $body
     *
     * @return Response
     */
    public function put(string $uri, array $headers = [], array $body = []): Response
    {
        return $this->httpClient->put($uri, $headers, json_encode($body));
    }
}
