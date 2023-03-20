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
    public function getEntityUri(AbstractEntity $entity, string $method, array $params = [])
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
    public function getDefaultHeader(): array
    {
          return [
            'Authorization: Bearer ' . $this->config->__get('access_token'),
            'x-platform-id: ' . $this->config->__get('platform_id'),
            'x-product-id: ' . $this->config->__get('product_id'),
            'x-integrator-id: ' . $this->config->__get('integrator_id')
          ];
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
        return array_merge($defaultHeaders, $customHeaders);
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
            throw new \Exception($response->getData()['message']);
        } else {
            throw new \Exception("Internal API Error");
        }
    }
}
