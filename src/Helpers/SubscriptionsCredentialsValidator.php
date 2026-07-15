<?php

namespace MercadoPago\Woocommerce\Helpers;

use Exception;
use MercadoPago\Woocommerce\Libraries\Logs\Logs;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @package MercadoPago\Woocommerce\Helpers
 */
class SubscriptionsCredentialsValidator
{
    public const LOG_SOURCE = 'mercadopago-subscriptions';

    /**
     * @var Requester
     */
    private $requester;

    /**
     * @var Logs
     */
    private $logs;

    public function __construct(Requester $requester, Logs $logs)
    {
        $this->requester = $requester;
        $this->logs      = $logs;
    }

    /**
     * Validates a Pre-approval access token.
     *
     * On success returns: `['valid' => true, 'reason' => 'ok', 'app_id' => string,
     *                       'app_name' => string|null, 'site_id' => string|null]`
     *
     * On failure returns: `['valid' => false, 'reason' => string]`. Callers map
     * `reason` to a localized message (see CustomGateway::translatedValidationMessage).
     *
     * @param string $accessToken
     * @return array<string, mixed>
     */
    public function validate(string $accessToken): array
    {
        $appId = $this->extractApplicationId($accessToken);
        if ($appId === null) {
            return [
                'valid'        => false,
                'reason'       => 'malformed_token',
            ];
        }

        $url     = '/applications/' . $appId;
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Accept'        => 'application/json',
        ];

        try {
            $response = $this->requester->get($url, $headers);
        } catch (Exception $e) {
            $this->logs->file->error(
                'op=validate-credential reason=exception message=' . $e->getMessage(),
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );

            return [
                'valid'        => false,
                'reason'       => 'service_unavailable',
            ];
        }

        return $this->processResponse($response, $appId);
    }

    /**
     * Extracts the `application_id` from an MP access token.
     *
     * Token format: `{PREFIX}-{application_id}-{date}-{secret}-{user_id}`
     * Prefixes: `APP_USR` (prod) or `TEST` (sandbox).
     */
    public function extractApplicationId(string $accessToken): ?string
    {
        $parts = explode('-', $accessToken);
        if (count($parts) < 5) {
            return null;
        }

        if ($parts[0] !== 'APP_USR' && $parts[0] !== 'TEST') {
            return null;
        }

        if (!ctype_digit($parts[1])) {
            return null;
        }

        return $parts[1];
    }

    private function processResponse($response, string $appId): array
    {
        $httpStatus = $response->getStatus();

        if ($httpStatus === 401) {
            $this->logs->file->error(
                'op=validate-credential http_status=401 reason=invalid_token',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'invalid_token',
            ];
        }

        if ($httpStatus === 403) {
            $this->logs->file->error(
                'op=validate-credential http_status=403 reason=token_app_mismatch',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'token_app_mismatch',
            ];
        }

        if ($httpStatus === 404) {
            $this->logs->file->error(
                'op=validate-credential http_status=404 reason=application_not_found',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'application_not_found',
            ];
        }

        if ($httpStatus >= 500) {
            $this->logs->file->error(
                'op=validate-credential http_status=' . $httpStatus . ' reason=service_unavailable',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'service_unavailable',
            ];
        }

        if ($httpStatus !== 200) {
            $this->logs->file->warning(
                'op=validate-credential http_status=' . $httpStatus . ' reason=unexpected_response',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'unexpected_response',
            ];
        }

        return $this->parseSuccessBody($response->getData(), $appId);
    }

    /**
     * Extracts only the 6 necessary fields from the 200 response body and
     * discards the rest (including `access_token` and `test_access_token`).
     *
     * @param mixed $data
     * @return array<string, mixed>
     */
    private function parseSuccessBody($data, string $appId): array
    {
        $body = (array) $data;

        $scopes   = $body['scopes']   ?? null;
        $appName  = $body['name']     ?? null;
        $siteId   = $body['site_id']  ?? null;
        $active   = $body['active']   ?? null;
        $blocked  = $body['blocked']  ?? null;
        $disabled = $body['disabled'] ?? null;

        // Discard the rest of the payload — response contains access_token / test_access_token.
        $body = null;

        if (!is_array($scopes)) {
            $this->logs->file->error(
                'op=validate-credential http_status=200 reason=scope_field_missing',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'scope_field_missing',
            ];
        }

        if (!in_array('preapproval', $scopes, true)) {
            $this->logs->file->warning(
                'op=validate-credential http_status=200 reason=missing_scope',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'missing_scope',
            ];
        }

        if ($active === false) {
            $this->logs->file->error(
                'op=validate-credential http_status=200 reason=application_inactive',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'application_inactive',
            ];
        }

        if ($blocked === true) {
            $this->logs->file->error(
                'op=validate-credential http_status=200 reason=application_blocked',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'application_blocked',
            ];
        }

        if ($disabled === true) {
            $this->logs->file->error(
                'op=validate-credential http_status=200 reason=application_disabled',
                self::LOG_SOURCE,
                ['app_id' => $appId]
            );
            return [
                'valid'        => false,
                'reason'       => 'application_disabled',
            ];
        }

        $this->logs->file->info(
            'op=validate-credential http_status=200 reason=ok',
            self::LOG_SOURCE,
            ['app_id' => $appId, 'site_id' => $siteId ?? '']
        );

        return [
            'valid'    => true,
            'reason'   => 'ok',
            'app_id'   => $appId,
            'app_name' => $appName,
            'site_id'  => $siteId,
        ];
    }
}
