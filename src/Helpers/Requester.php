<?php

namespace MercadoPago\Woocommerce\Helpers;

use Exception;
use MercadoPago\PP\Sdk\HttpClient\HttpClientInterface;
use MercadoPago\PP\Sdk\HttpClient\Response;
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
     * @return Response
     * @throws Exception
     */
    public function get(string $uri, array $headers = []): Response
    {
        try {
            $response = $this->httpClient->get($uri, $headers);
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
     * @return Response
     * @throws Exception
     */
    public function post(string $uri, array $headers = [], array $body = []): Response
    {
        try {
            $response = $this->httpClient->post($uri, $headers, json_encode($body));
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
     * @return Response
     * @throws Exception
     */
    public function put(string $uri, array $headers = [], array $body = []): Response
    {
        try {
            $response = $this->httpClient->put($uri, $headers, json_encode($body));
            $this->checkResponseForErrors($response, $uri);
            return $response;
        } catch (Exception $e) {
            $this->sendApiErrorMetric($uri, 0, $e->getMessage());
            throw $e;
        }
    }

    private function checkResponseForErrors(Response $response, string $uri): void
    {
        $status = $response->getStatus();
        if ($status >= 400) {
            $this->sendApiErrorMetric($uri, $status, $this->extractErrorMessage($response));
        }
    }

    // $status = 0 is the convention for pre-response exceptions (connection/timeout); >= 400 for HTTP error responses.
    private function sendApiErrorMetric(string $uri, int $status, string $message): void
    {
        $details = MetricContext::buildApiErrorDetails($uri);
        $this->datadog->sendEvent('mp_api_error', (string) $status, $message, null, $details);
    }

    private function extractErrorMessage(Response $response): string
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
}
