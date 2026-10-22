<?php

namespace MercadoPago\Woocommerce\Helpers;

use Exception;
use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;

if (!defined('ABSPATH')) {
    exit;
}

class Requester
{
    public const BASEURL_MP = 'https://api.mercadopago.com';

    private HttpClientInterface $httpClient;

    private Datadog $datadog;

    /**
     * Requester constructor
     *
     * @param HttpClientInterface $httpClient
     */
    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->datadog = Datadog::getInstance();
    }

    /**
     * @param string $uri
     * @param array $headers
     *
     * @return HttpResponse
     * @throws Exception
     */
    public function get(string $uri, array $headers = []): HttpResponse
    {
        try {
            $response = $this->httpClient->request('GET', $uri, $headers);
            $this->checkResponseForErrors($response, $uri);
            return $response;
        } catch (Exception $e) {
            $this->sendApiErrorMetric($uri, 0, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $uri
     * @param array $headers
     * @param array $body
     *
     * @return HttpResponse
     * @throws Exception
     */
    public function post(string $uri, array $headers = [], array $body = []): HttpResponse
    {
        try {
            $headers = $this->withJsonContentType($headers);
            $response = $this->httpClient->request('POST', $uri, $headers, $this->encodeBody($body));
            $this->checkResponseForErrors($response, $uri);
            return $response;
        } catch (Exception $e) {
            $this->sendApiErrorMetric($uri, 0, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $uri
     * @param array $headers
     * @param array $body
     *
     * @return HttpResponse
     * @throws Exception
     */
    public function put(string $uri, array $headers = [], array $body = []): HttpResponse
    {
        try {
            $headers = $this->withJsonContentType($headers);
            $response = $this->httpClient->request('PUT', $uri, $headers, $this->encodeBody($body));
            $this->checkResponseForErrors($response, $uri);
            return $response;
        } catch (Exception $e) {
            $this->sendApiErrorMetric($uri, 0, $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param string $uri
     * @param array $headers
     * @param array $body
     *
     * @return HttpResponse
     * @throws Exception
     */
    public function delete(string $uri, array $headers = [], array $body = []): HttpResponse
    {
        try {
            $payload = empty($body) ? null : $this->encodeBody($body);
            if ($payload !== null) {
                $headers = $this->withJsonContentType($headers);
            }
            $response = $this->httpClient->request('DELETE', $uri, $headers, $payload);
            $this->checkResponseForErrors($response, $uri);
            return $response;
        } catch (Exception $e) {
            $this->sendApiErrorMetric($uri, 0, $e->getMessage());
            throw $e;
        }
    }

    private function checkResponseForErrors(HttpResponse $response, string $uri): void
    {
        $status = $response->getStatus();
        if ($status >= 400) {
            $this->sendApiErrorMetric($uri, $status, $this->extractErrorMessage($response));
        }
    }

    // $status = 0 is the convention for pre-response exceptions (connection/timeout); >= 400 for HTTP error responses.
    private function sendApiErrorMetric(string $uri, int $status, string $message): void
    {
        $details = MetricContext::buildBaseMetricDetails($uri);
        $this->datadog->sendEvent('mp_api_error', (string) $status, $message, null, $details);
    }

    private function extractErrorMessage(HttpResponse $response): string
    {
        $data = $response->getData();

        if (is_array($data) && isset($data['message'])) {
            return (string) $data['message'];
        }

        if (is_object($data) && isset($data->message)) {
            return (string) $data->message;
        }

        return 'HTTP ' . $response->getStatus();
    }

    private function encodeBody(array $body): string
    {
        $encodedBody = wp_json_encode($body);
        if ($encodedBody === false) {
            throw new Exception('Unable to encode Mercado Pago API request.');
        }

        return $encodedBody;
    }

    private function withJsonContentType(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $name = strstr((string) $value, ':', true);
            }

            if (is_string($name) && strtolower(trim($name)) === 'content-type') {
                return $headers;
            }
        }

        $headers['Content-Type'] = 'application/json';
        return $headers;
    }
}
