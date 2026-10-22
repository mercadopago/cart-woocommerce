<?php

namespace MercadoPago\Woocommerce\Api\Http;

if (!defined('ABSPATH')) {
    exit;
}

class WordPressHttpClient implements HttpClientInterface
{
    private const ALLOWED_HOST = 'api.mercadopago.com';

    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private const USER_AGENT = 'platform:v1-whitelabel,type:mp_sdk';

    private string $baseUrl;

    private int $timeout;

    public function __construct(string $baseUrl, int $timeout = 30)
    {
        $this->baseUrl = $this->validateBaseUrl($baseUrl);

        if ($timeout <= 0) {
            throw new \InvalidArgumentException('HTTP timeout must be greater than zero.');
        }

        $this->timeout = $timeout;
    }

    public function request(
        string $method,
        string $path,
        array $headers = [],
        ?string $body = null
    ): HttpResponse {
        $method = strtoupper($method);
        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            throw new \InvalidArgumentException('Unsupported HTTP method.');
        }

        $url = $this->buildUrl($path);
        $args = [
            'method'          => $method,
            'headers'         => $this->normalizeHeaders($headers),
            'timeout'         => $this->timeout,
            'redirection'     => 0,
            'reject_unsafe_urls' => true,
            'user-agent'      => self::USER_AGENT,
        ];

        if ($body !== null) {
            $args['body'] = $body;
            $args['data_format'] = 'body';
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            throw new TransportException((string) $response->get_error_code());
        }

        $responseBody = (string) wp_remote_retrieve_body($response);

        return new HttpResponse(
            (int) wp_remote_retrieve_response_code($response),
            $this->normalizeResponseHeaders(wp_remote_retrieve_headers($response)),
            $responseBody,
            $this->decodeBody($responseBody)
        );
    }

    private function validateBaseUrl(string $baseUrl): string
    {
        $parts = parse_url($baseUrl);
        if (
            $parts === false
            || ($parts['scheme'] ?? '') !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== self::ALLOWED_HOST
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new \InvalidArgumentException('Unsupported Mercado Pago API base URL.');
        }

        $path = $parts['path'] ?? '';
        if ($path !== '' && $path !== '/') {
            throw new \InvalidArgumentException('Mercado Pago API base URL cannot contain a path.');
        }

        return 'https://' . self::ALLOWED_HOST;
    }

    private function buildUrl(string $path): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw new \InvalidArgumentException('Mercado Pago API path must be relative.');
        }

        $decodedPath = $path;
        for ($decodePass = 0; $decodePass < 3; $decodePass++) {
            $nextPath = rawurldecode($decodedPath);
            if ($nextPath === $decodedPath) {
                break;
            }

            $decodedPath = $nextPath;
        }
        $parts = parse_url($path);

        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || preg_match('/[\x00-\x1F\x7F]/', $decodedPath) === 1
            || strpos($decodedPath, '\\') !== false
            || preg_match('#(?:^|/)\.\.(?:/|$)#', $decodedPath) === 1
        ) {
            throw new \InvalidArgumentException('Invalid Mercado Pago API path.');
        }

        return $this->baseUrl . $path;
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $separator = strpos((string) $value, ':');
                if ($separator === false) {
                    throw new \InvalidArgumentException('Invalid HTTP header.');
                }

                $name = trim(substr((string) $value, 0, $separator));
                $value = trim(substr((string) $value, $separator + 1));
            }

            if (!is_scalar($value)) {
                throw new \InvalidArgumentException('Invalid HTTP header.');
            }

            $name = (string) $name;
            $value = (string) $value;

            if (
                preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/", $name) !== 1
                || preg_match('/[\r\n]/', $value) === 1
            ) {
                throw new \InvalidArgumentException('Invalid HTTP header.');
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * @param mixed $headers
     */
    private function normalizeResponseHeaders($headers): array
    {
        if (is_object($headers) && method_exists($headers, 'getAll')) {
            return (array) $headers->getAll();
        }

        if ($headers instanceof \Traversable) {
            return iterator_to_array($headers);
        }

        return is_array($headers) ? $headers : [];
    }

    /**
     * @return mixed
     */
    private function decodeBody(string $body)
    {
        if ($body === '') {
            return null;
        }

        $data = json_decode($body, true);

        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }
}
