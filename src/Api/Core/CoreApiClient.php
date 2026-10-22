<?php

namespace MercadoPago\Woocommerce\Api\Core;

use MercadoPago\Woocommerce\Api\Core\Exceptions\ApiException;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;

if (!defined('ABSPATH')) {
    exit;
}

class CoreApiClient
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function get(string $path, array $headers = []): HttpResponse
    {
        return $this->request('GET', $path, $headers);
    }

    public function post(string $path, array $headers = [], array $body = []): HttpResponse
    {
        return $this->request('POST', $path, $headers, $body);
    }

    public function put(string $path, array $headers = [], array $body = []): HttpResponse
    {
        return $this->request('PUT', $path, $headers, $body);
    }

    private function request(
        string $method,
        string $path,
        array $headers,
        ?array $body = null
    ): HttpResponse {
        $encodedBody = null;
        if ($body !== null) {
            $encodedBody = wp_json_encode($body);
            if ($encodedBody === false) {
                throw new \RuntimeException('Unable to encode Core API request.');
            }

            if (!$this->hasHeader($headers, 'content-type')) {
                $headers['Content-Type'] = 'application/json';
            }
        }

        $response = $this->httpClient->request($method, $path, $headers, $encodedBody);
        if ($response->getStatus() >= 200 && $response->getStatus() < 300) {
            return $response;
        }

        throw $this->createApiException($response);
    }

    private function hasHeader(array $headers, string $expectedName): bool
    {
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $name = strstr((string) $value, ':', true);
            }

            if (is_string($name) && strtolower(trim($name)) === $expectedName) {
                return true;
            }
        }

        return false;
    }

    private function createApiException(HttpResponse $response): ApiException
    {
        $data = is_array($response->getData()) ? $response->getData() : [];
        $message = isset($data['message']) && is_scalar($data['message'])
            ? (string) $data['message']
            : 'Mercado Pago Core API request failed.';
        $errorCode = isset($data['error']) && is_scalar($data['error'])
            ? (string) $data['error']
            : null;
        $apiStatus = isset($data['status']) && is_numeric($data['status'])
            ? (int) $data['status']
            : $response->getStatus();
        $originalMessage = isset($data['original_message']) && is_scalar($data['original_message'])
            ? (string) $data['original_message']
            : null;

        return new ApiException(
            $message,
            $errorCode,
            $apiStatus,
            $originalMessage,
            $response->getStatus()
        );
    }
}
