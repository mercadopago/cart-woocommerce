<?php

namespace MercadoPago\PP\Sdk\HttpClient;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\AbstractCollection;
use MercadoPago\PP\Sdk\HttpClient\Requester\RequesterInterface;

/**
 * Class CurlHttpClient
 *
 * @package MercadoPago\PP\Sdk\HttpClient
 */
class HttpClient implements HttpClientInterface
{
    /**
     * Base Url
     *
     * @var string
     **/
    private $baseUrl = null;

    /**
     * Client implementation
     *
     * @var ImplementationInterface
     **/
    private $requester = null;

    /**
     * Class Constructor.
     *
     * @param RequesterInterface $requester
     *
     */
    public function __construct($baseUrl, $requester)
    {
        $this->baseUrl = $baseUrl;
        $this->requester = $requester;
    }

    public function get($uri, array $headers = []): Response
    {
        return $this->send('GET', $uri, $headers, null);
    }

    public function put($uri, array $headers = [], $body = null): Response
    {
        return $this->send('PUT', $uri, $headers, $body);
    }

    public function post($uri, array $headers = [], $body = null): Response
    {
        return $this->send('POST', $uri, $headers, $body);
    }

    public function send(string $method, $uri, array $headers = [], $body = null): Response
    {
        if (!is_string($uri)) {
            throw new \TypeError(
                sprintf(
                    '%s::send(): Argument #2 ($uri) must be of type string, %s given',
                    self::class,
                    gettype($uri)
                )
            );
        }

        if (null !== $body && !is_string($body) &&
            !is_subclass_of($body, AbstractEntity::class) && !is_subclass_of($body, AbstractCollection::class)
        ) {
            throw new \TypeError(
                sprintf(
                    '%s::send(): Argument #4 ($body) must be of type string|%s|%snull, %s given',
                    self::class,
                    AbstractEntity::class,
                    AbstractCollection::class,
                    gettype($body)
                )
            );
        }

        return $this->sendRequest(
            self::createRequest($method, $uri, $headers, $body)
        );
    }

    /**
     * @param string|AbstractEntity|AbstractCollection|null $body
     *
     * @return \CurlHandle|resource
     */
    private function createRequest(string $method, string $uri, array $headers = [], $body = null)
    {
        $url = $this->baseUrl . $uri;
        $request = $this->requester->createRequest($method, $url, $headers, $body);

        return $request;
    }

    /**
     * @param \CurlHandle|resource $request
     */
    public function sendRequest($request): Response
    {
        $response = $this->requester->sendRequest($request);

        return $response;
    }
}
