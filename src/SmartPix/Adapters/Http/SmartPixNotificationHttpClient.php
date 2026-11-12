<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Http;

use MercadoPago\Woocommerce\Api\Http\HttpClientInterface;
use MercadoPago\Woocommerce\Api\Http\HttpResponse;
use MercadoPago\Woocommerce\Api\Http\TransportException;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixDiagnostic;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixMetricContext;
use MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry\SmartPixTelemetry;

if (!defined('ABSPATH')) {
    exit;
}

/** Observe only the authenticated Core fetch, without changing its request or response. */
final class SmartPixNotificationHttpClient implements HttpClientInterface
{
    private HttpClientInterface $client;
    private SmartPixTelemetry $telemetry;

    public function __construct(HttpClientInterface $client, SmartPixTelemetry $telemetry)
    {
        $this->client = $client;
        $this->telemetry = $telemetry;
    }

    public function request(string $method, string $path, array $headers = [], ?string $body = null): HttpResponse
    {
        $route = 'GET /plugins-platforms/v1/notification/order/{id}';
        $observed = $method === 'GET' && preg_match('#\A/plugins-platforms/v1/notification/order/PPORD[A-Za-z0-9]+\z#D', $path) === 1;
        if ($observed) {
            $this->telemetry->observeRequest(null, $route);
        }
        try {
            $response = $this->client->request($method, $path, $headers, $body);
        } catch (TransportException $failure) {
            if ($observed) {
                $this->telemetry->error('notification', 'unavailable', 'server', 'notification', SmartPixDiagnostic::exception($failure));
            }
            throw $failure;
        }
        if ($observed) {
            $requestId = null;
            foreach ($response->getHeaders() as $name => $value) {
                if (is_string($name) && strcasecmp($name, 'x-request-id') === 0) {
                    $requestId = SmartPixMetricContext::identifier(is_string($value) ? trim($value) : null);
                    break;
                }
            }
            $this->telemetry->observeRequest($requestId, $route);
            if ($response->getStatus() >= 400) {
                $this->telemetry->error(
                    'notification',
                    $response->getStatus() >= 500 ? 'http_5xx' : 'http_4xx',
                    'server',
                    'notification',
                    SmartPixDiagnostic::response($response)
                );
            }
        }
        return $response;
    }
}
