<?php

namespace MercadoPago\PP\Sdk\Common;

use MercadoPago\PP\Sdk\HttpClient\HttpClientInterface;
use MercadoPago\PP\Sdk\HttpClient\Response;

/**
 * Class Manager
 *
 * @package MercadoPago\PP\Sdk\Common
 */
class Manager
{
    /**
     * @var HttpClientInterface
     */
    private $client;

    /**
     * @var Config
     */
    private $config;

    /**
     * Manager constructor.
     *
     * @param HttpClientInterface $client
     * @param Config $config
     */
    public function __construct(HttpClientInterface $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Unifies method call that makes request to any HTTP method.
     *
     * @param object|AbstractEntity|null $entity
     * @param string $uri
     * @param string $method
     * @param array $headers
     *
     * @return mixed
     */
    public function execute(AbstractEntity $entity, string $uri, string $method = 'get', array $headers = [])
    {
        if ($method == 'get') {
            return $this->client->{$method}($uri, $headers);
        }

        $body = json_encode($entity);
        return $this->client->{$method}($uri, $headers, $body);
    }

    /**
     * Get entity uri by performing assignments based on params.
     *
     * @param AbstractEntity|null $entity
     * @param string $method
     * @param array $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function getEntityUri(AbstractEntity $entity, string $method, array $params = [], array $queryStrings = [])
    {
        if (method_exists($entity, 'getUris')) {
            $uri = $entity->getUris()[$method];
            $matches = [];
            preg_match_all('/\\:\\w+/', $uri, $matches);

            foreach ($matches[0] as $match) {
                $key = substr($match, 1);

                if (array_key_exists($key, $params)) {
                    $uri = str_replace($match, $params[$key], $uri);
                } elseif (property_exists($entity, $key) && !is_null($entity->{$key})) {
                    $uri = str_replace($match, $entity->{$key}, $uri);
                } else {
                    $uri = str_replace($match, '', $uri);
                }
            }
            if (count($queryStrings) > 0) {
                $uri = $uri . '?' . http_build_query($queryStrings);
            }

            return $uri;
        } else {
            throw new \Exception('Method not available for ' . get_class($entity) . ' entity');
        }
    }

    /**
     * Get default header
     *
     * @return array
     */
    public function getDefaultHeader(): array
    {
          return [
            'Authorization' => 'Bearer ' . $this->config->__get('access_token'),
            'x-platform-id' => $this->config->__get('platform_id'),
            'x-product-id' => $this->config->__get('product_id'),
            'x-integrator-id' => $this->config->__get('integrator_id')
          ];
    }

    /**
     * Normalize headers
     *
     * @return array
     */
    public function normalizeHeaders(array $unnormalizedHeaders): array
    {
        $normalizedHeaders = [];
        foreach ($unnormalizedHeaders as $key => $value) {
            array_push($normalizedHeaders, $key . ': ' . $value);
        }
        return $normalizedHeaders;
    }

    /**
     * Get header
     * @param array $customHeaders
     *
     * @return array
     */
    public function getHeader(array $customHeaders = []): array
    {
        $defaultHeaders = $this->getDefaultHeader();
        if (count($customHeaders) > 0 && !$this->isHeadersAsKeyAndValueMap($customHeaders)) {
            $customHeaders = $this->setHeadersAsKeyAndValueMap($customHeaders);
        };
        return $this->normalizeHeaders(array_merge($defaultHeaders, $customHeaders));
    }

    /**
     * Get header
     *
     * If the format of the customHeaders passed is like `array('x-header: 123abc')`,
     * the method will convert the customHeaders to
     * follow format: `array('x-header' => '123abc')` and return the converted customHeaders.
     *
     * @param array $customHeaders
     *
     * @return array
     */
    public function setHeadersAsKeyAndValueMap(array $customHeaders = []): array
    {
        $headersAsKeyAndValueMap = [];

        foreach ($customHeaders as $header) {
            [$headerKey, $headerValue] = explode(":", $header);
            $headersAsKeyAndValueMap[trim($headerKey)] = trim($headerValue);
        }
        return $headersAsKeyAndValueMap;
    }
    
    /**
     * Checks if the header is in key and value format
     *
     * If the format of the customHeader passed is like `array('x-header: 123abc')`, the method will return false
     * Otherwise, if the format of the customHeader passed is like `array('x-header' => '123abc')`,
     * the method will return true
     *
     * @param array $customHeaders
     *
     * @return bool
     */
    public function isHeadersAsKeyAndValueMap(array $customHeaders = []): bool
    {
        foreach ($customHeaders as $i => $value) {
            if (is_int($i)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Handle response
     *
     * @param Response $response
     * @param string $method
     * @param AbstractEntity|null $entity
     *
     * @return mixed
     * @throws \Exception
     */
    public function handleResponse(Response $response, string $method, AbstractEntity $entity = null)
    {
        if ($response->getStatus() == '200' || $response->getStatus() == '201') {
            if ($entity && $method == 'get') {
                $entity->setEntity($response->getData());
                return $entity;
            }
            return $response->getData();
        } elseif (intval($response->getStatus()) >= 400 && intval($response->getStatus()) < 500) {
            $message = $response->getData()['message'] ?? 'No message for Multipayment scenario in v1!';
            throw new \Exception($message);
        } else {
            throw new \Exception("Internal API Error");
        }
    }

    /**
     * Handle response
     *
     * @param Response $response
     * @param string $method
     * @param AbstractEntity|null $entity
     *
     * @return mixed
     * @throws \Exception
     */
    public function handleResponseWithHeaders(Response $response)
    {
        if ($response->getStatus() == '200' || $response->getStatus() == '201') {
            return $response;
        } elseif (intval($response->getStatus()) >= 400 && intval($response->getStatus()) < 500) {
            $message = $response->getData()['message'] ?? 'No message for Multipayment scenario in v1!';
            throw new \Exception($message);
        } else {
            throw new \Exception("Internal API Error");
        }
    }

    /**
     * Get config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }
}
