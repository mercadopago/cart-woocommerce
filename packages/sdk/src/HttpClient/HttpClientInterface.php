<?php

namespace MercadoPago\PP\Sdk\HttpClient;

use MercadoPago\PP\Sdk\Common\AbstractEntity;

/**
 * Interface HttpClientInterface
 *
 * @package MercadoPago\PP\Sdk\HttpClient
 */
interface HttpClientInterface
{
  /**
   * Sends a GET request.
   *
   * @param string $uri
   * @param array $headers
   * @return Response
   * @throws \Exception
   */
    public function get(string $uri, array $headers = []): Response;

  /**
   * Sends a PUT request.
   *
   * @param string $uri
   * @param array $headers
   * @param string|AbstractEntity|null $body
   *
   * @return Response
   * @throws \Exception
   */
    public function put(string $uri, array $headers = [], $body = null): Response;

  /**
   * Sends a POST request.
   *
   * @param string $uri
   * @param array $headers
   * @param string|AbstractEntity|null $body
   *
   * @return Response
   * @throws \Exception
   */
    public function post(string $uri, array $headers = [], $body = null): Response;

  /**
   * Sends a request with any HTTP method.
   *
   * @param string $method HTTP method to use
   * @param string $uri
   * @param array $headers
   * @param string|AbstractEntity|null $body
   *
   * @return Response
   * @throws \Exception
   */
    public function send(string $method, string $uri, array $headers = [], $body = null): Response;
}
