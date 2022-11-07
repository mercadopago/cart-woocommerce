<?php

namespace MercadoPago\PP\Sdk\Common;

use MercadoPago\PP\Sdk\HttpClient;
use Exception;

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
     * @param Config              $config
     */
    public function __construct($client, $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Unifies method call that makes request to any HTTP method.
     *
     * @param object|AbstractEntity|null $entity
     * @param string|UriInterface        $uri
     * @param string                     $method
     * @param array                      $headers
     *
     * @return mixed
     */
    public function execute($entity, $uri, $method = 'get', $headers = [])
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
     * @param object|AbstractEntity|null $entity
     * @param string                     $method
     * @param array                      $params
     *
     * @return mixed
     * @throws Exception
     */
    public function getEntityUri($entity, $method, $params = [])
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
    public function getDefaultHeader()
    {
        $headers = [
            'Authorization: Bearer ' . $this->config->__get('access_token'),
            'x-platform-id: ' . $this->config->__get('platform_id'),
            'x-product-id: ' . $this->config->__get('product_id'),
            'x-integrator-id: ' . $this->config->__get('integrator_id')
        ];

        return $headers;
    }

    /**
     * Get header
     * @param array $customHeaders
     *
     * @return array
     */
    public function getHeader($customHeaders = [])
    {
        $defaultHeaders = $this->getDefaultHeader();
        $headers = array_merge($defaultHeaders, $customHeaders);

        return $headers;
    }

    /**
     * Handle response
     *
     * @param Response $response
     * @param $method
     *
     * @return mixed
     * @throws Exception
     */
    public function handleResponse($response, $method, $entity = null)
    {
        if ($response->getStatus() == "200" || $response->getStatus() == "201") {
            if ($entity && $method == 'get') {
                $entity->setEntity($response->getData());
                return $entity;
            }
            return $response->getData();
        } elseif (intval($response->getStatus()) >= 400 && intval($response->getStatus()) < 500) {
            throw new Exception($response->getData()['message']);
        } else {
            throw new Exception("Internal API Error");
        }
    }
}
