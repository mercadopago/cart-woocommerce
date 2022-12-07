<?php

namespace MercadoPago\PP\Sdk\HttpClient;

use MercadoPago\PP\Sdk\Common\AbstractEntity;
use MercadoPago\PP\Sdk\Common\AbstractCollection;
use MercadoPago\PP\Sdk\HttpClient\Requester\RequesterInterface;

/**
 * Class HttpClient
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
     * @var RequesterInterface
     **/
    private $requester = null;

    /**
     * HttpClient constructor.
     *
     * @param string $baseUrl
     * @param RequesterInterface $requester
     */
    public function __construct(string $baseUrl, RequesterInterface $requester)
    {
        $this->baseUrl = $baseUrl;
        $this->requester = $requester;
    }

    public function get(string $uri, array $headers = []): Response
    {
        return $this->send('GET', $uri, $headers, null);
    }

    public function put(string $uri, array $headers = [], $body = null): Response
    {
        return $this->send('PUT', $uri, $headers, $body);
    }

    public function post(string $uri, array $headers = [], $body = null): Response
    {
        return $this->send('POST', $uri, $headers, $body);
    }

    public function send(string $method, string $uri, array $headers = [], $body = null): Response
    {
        if (null !== $body && !is_string($body) &&
            !is_subclass_of($body, AbstractEntity::class) && !is_subclass_of($body, AbstractCollection::class)
        ) {
            throw new \Exception(
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
     * @return resource
     */
    private function createRequest(string $method, string $uri, array $headers = [], $body = null)
    {
        $url = $this->baseUrl . $uri;
        return $this->requester->createRequest($method, $url, $headers, $body);
    }

    /**
     * @param resource $request
     */
    public function sendRequest($request): Response
    {
        return $this->requester->sendRequest($request);
    }
}
