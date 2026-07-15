<?php

namespace MercadoPago\Woocommerce\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class MetricContext
{
    public static function buildApiErrorDetails(string $apiRoute, ?object $mercadopago = null): array
    {
        $details = [
            'team'      => 'big',
            'api_route' => self::parameterizeApiRoute(strtok($apiRoute, '?')),
        ];

        $mp = $mercadopago ?? ($GLOBALS['mercadopago'] ?? null);
        if ($mp) {
            $details['site_id']     = $mp->sellerConfig->getSiteId();
            $details['environment'] = $mp->storeConfig->isTestMode() ? 'homol' : 'prod';
            $details['cust_id']     = $mp->sellerConfig->getCustIdFromAT();
        }

        return $details;
    }

    /**
     * Replaces opaque identifiers in every URL path segment with `{id}`
     * so the Datadog `api_route` tag stays low-cardinality regardless of which
     * endpoint is called.
     *
     * A segment is considered an ID when it matches any of the heuristics in
     * {@see self::isIdSegment()}. Path keywords like `subscriptions`,
     * `payment-methods`, `v2` or `intents` never match and are kept verbatim.
     *
     * Adding new endpoints to the codebase requires no changes here — the
     * heuristics are data-driven rather than hardcoded per route.
     */
    public static function parameterizeApiRoute(string $apiRoute): string
    {
        // Drop the absolute prefix if a full URL was given.
        if (preg_match('#^https?://[^/]+(/.*)$#', $apiRoute, $matches)) {
            $apiRoute = $matches[1];
        }

        return (string) preg_replace_callback(
            '#/([^/]+)#',
            static function (array $m): string {
                return '/' . (self::isIdSegment($m[1]) ? '{id}' : $m[1]);
            },
            $apiRoute
        );
    }

    /**
     * Decides whether a single path segment looks like an opaque identifier
     * rather than a static API keyword.
     *
     * Heuristics (applied in priority order):
     *   1. Purely numeric   — `12345`, `9999999999`
     *   2. UUID (any v)     — `8a1f8e1f-91dc-4d9d-9c9f-1e2d3c4b5a6f`
     *   3. Digit + separator — segment contains at least one digit AND a
     *        hyphen/underscore, AND length > 4 (e.g. `tok_abc-123`, `CPP-WSUB-abc-123`)
     *   4. Uppercase prefix — contains at least one uppercase letter AND a
     *        hyphen/underscore (e.g. `CPP-WSUB-abc`, Mercado Pago namespaced IDs)
     *
     * Anything that only has lowercase letters and optional hyphens — like
     * `subscriptions`, `payment-methods`, `intents`, `automatic-payments` — does
     * not match any heuristic and is returned verbatim.
     */
    private static function isIdSegment(string $segment): bool
    {
        // Heuristic 1: pure numeric
        if (ctype_digit($segment)) {
            return true;
        }

        // Heuristic 2: UUID (any version, case-insensitive)
        if (preg_match('/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i', $segment)) {
            return true;
        }

        // Heuristic 3: structured ID with digits + separator (e.g. CPP-WSUB-abc-123, tok_abc-123)
        // Limitation: segments like 'v1-beta' or 'oauth2-token' also match — they have a digit,
        // a separator and length > 4. No current MP AP v2 path uses this pattern, but keep it
        // in mind when adding endpoints whose static keywords fit this shape.
        if (
            strlen($segment) > 4
            && preg_match('/\d/', $segment)
            && preg_match('/[-_]/', $segment)
        ) {
            return true;
        }

        // Heuristic 4: uppercase + separator without mandatory digit (e.g. CPP-WSUB-abc)
        if (preg_match('/[A-Z]/', $segment) && preg_match('/[-_]/', $segment)) {
            return true;
        }

        return false;
    }
}
