<?php

declare(strict_types=1);

namespace MercadoPago\Woocommerce\SmartPix\Adapters\Telemetry;

if (!defined('ABSPATH')) {
    exit;
}

/** Optional page context for diagnostics; never an authorization or outbound destination. */
final class SmartPixMetricPage
{
    public static function current(string $storeUrl): ?string
    {
        if ((defined('DOING_CRON') && DOING_CRON) || (defined('WP_CLI') && WP_CLI)
            || (defined('WC_API_REQUEST') && WC_API_REQUEST)) {
            return null;
        }
        return self::fromRequest(
            $_SERVER,
            $storeUrl,
            (defined('DOING_AJAX') && DOING_AJAX) || (defined('REST_REQUEST') && REST_REQUEST)
        );
    }

    public static function fromRequest(array $server, string $storeUrl, bool $endpointRequest = false): ?string
    {
        $request = $server['REQUEST_URI'] ?? null;
        if (!is_string($request) || strlen($request) > 4096 || strpos($request, '/') !== 0
            || strpos($request, '//') === 0) {
            return null;
        }
        $parts = parse_url($request);
        if (!is_array($parts)) {
            return null;
        }
        $path = rawurldecode($parts['path'] ?? '/');
        parse_str($parts['query'] ?? '', $query);
        // Callback/cron requests have no current buyer page, even if a Referer is supplied.
        if (isset($query['wc-api']) || preg_match('#/(?:wc-api(?:/|$)|wp-cron\.php$)#i', $path)) {
            return null;
        }
        $origin = self::origin($storeUrl);
        if ($origin === null) {
            return null;
        }
        $endpoint = $endpointRequest || isset($query['wc-ajax']) || isset($query['rest_route'])
            || preg_match('#/(?:wp-json(?:/|$)|admin-ajax\.php$)#i', $path);
        $page = $endpoint ? ($server['HTTP_REFERER'] ?? null) : $origin . $request;
        return self::sanitize($page, $storeUrl);
    }

    /** Same origin only. Discard query/fragment and normalize private path identifiers. */
    public static function sanitize($page, string $storeUrl): ?string
    {
        if (!is_string($page) || strlen($page) > 4096 || preg_match('/[\x00-\x20\\\\]/', $page)
            || self::origin($page) === null || self::origin($page) !== self::origin($storeUrl)) {
            return null;
        }
        $parts = parse_url($page);
        $rawPath = $parts['path'] ?? '/';
        $path = rawurldecode($rawPath);
        // Reject encoded separators, contact data and ambiguous paths rather than leaking them.
        if (preg_match('/%(?:2f|5c|25)/i', $rawPath)
            || preg_match('#\A/[A-Za-z0-9._~{}/-]*\z#D', $path) !== 1
            || strpos($path, '//') !== false) {
            return null;
        }
        $segments = explode('/', $path);
        foreach ($segments as $index => $segment) {
            if ((strpos($segment, '{') !== false || strpos($segment, '}') !== false) && $segment !== '{id}') {
                return null;
            }
            if ($segment === '.' || $segment === '..') {
                return null;
            }
            if (in_array($segment, ['order-pay', 'order-received'], true)) {
                $segments = array_slice($segments, 0, $index + 1);
                $segments[] = '';
                break;
            }
            if (preg_match('/\A(?:[0-9]+|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\z/iD', $segment)
                || preg_match('/\A(?:wc_order_|APP_USR-|TEST-)/i', $segment)
                || strlen($segment) > 64) {
                $segments[$index] = '{id}';
            }
        }
        $uri = self::origin($page) . implode('/', $segments);
        return strlen($uri) <= 180 ? $uri : null;
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || preg_match('/\A[A-Za-z0-9.\[\]:-]+\z/D', $parts['host']) !== 1) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $suffix = $port === ($scheme === 'https' ? 443 : 80) ? '' : ':' . $port;
        return $scheme . '://' . strtolower($parts['host']) . $suffix;
    }
}
