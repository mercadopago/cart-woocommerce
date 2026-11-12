<?php

namespace MercadoPago\Woocommerce\Admin;

use MercadoPago\Woocommerce\Helpers\Country;
use MercadoPago\Woocommerce\Helpers\CurrentUser;
use MercadoPago\Woocommerce\Helpers\Form;
use MercadoPago\Woocommerce\Helpers\Nonce;
use MercadoPago\Woocommerce\Hooks\Endpoints;
use MercadoPago\Woocommerce\Libraries\Metrics\Datadog;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

class OnboardingNoticeMetrics
{
    public const AJAX_ACTION = 'mp_native_onboarding_notice_metric';
    public const NONCE_ACTION = 'mp_native_onboarding_notice_metrics';

    private const SCREEN_URIS = [
        'dashboard'   => 'index.php',
        'plugins'     => 'plugins.php',
        'wc_home'     => 'admin.php?page=wc-admin',
        'wc_settings' => 'admin.php?page=wc-settings',
        'mp_settings' => 'admin.php?page=mercadopago-settings',
    ];
    private const EVENTS = ['view', 'click', 'error'];
    private const VIEWPORTS = ['desktop', 'mobile'];
    private const ERROR_CODES = ['bootstrap_failed', 'native_slot_missing', 'cta_missing'];

    private Nonce $nonce;
    private CurrentUser $currentUser;
    private Datadog $datadog;

    public function __construct(Nonce $nonce, CurrentUser $currentUser, Endpoints $endpoints, Datadog $datadog)
    {
        $this->nonce = $nonce;
        $this->currentUser = $currentUser;
        $this->datadog = $datadog;

        $endpoints->registerAjaxEndpoint(self::AJAX_ACTION, [$this, 'handleMetric']);
    }

    public function handleMetric(): void
    {
        if (Form::requestMethod() !== 'POST') {
            wp_send_json_error('Method not allowed', 405);
        }

        if (!$this->currentUser->currentUserCan('manage_woocommerce')) {
            wp_send_json_error('Forbidden', 403);
        }

        $input = Form::sanitizedPostFields();
        if (!$this->isValidInput($input)) {
            wp_send_json_error('Invalid notice event', 400);
        }

        if (!$this->nonce->isValidNonce(self::NONCE_ACTION, $input['nonce'])) {
            wp_send_json_error('Forbidden', 403);
        }

        try {
            $platformUrl = $this->storeUrl();
            if ($platformUrl === '') {
                throw new \UnexpectedValueException('Invalid store URL');
            }

            $siteId = Country::countryToSiteId(Country::getWoocommerceDefaultCountry());
            $details = [
                'team'    => 'big',
                'device'  => $input['viewport'],
                'site_id' => $siteId !== '' ? $siteId : 'unknown',
            ];

            if ($input['event'] === 'error') {
                $details['reason'] = $input['reason'];
            }

            $this->datadog->sendEvent(
                'mp_woo_onboarding_notice_' . $input['event'],
                '1',
                null,
                null,
                $details,
                $platformUrl,
                $this->screenUri($input['screen'])
            );
        } catch (Throwable $e) {
            wp_send_json_error('Notice metric unavailable', 503);
        }

        wp_send_json_success(['accepted' => true]);
    }

    private function isValidInput($input): bool
    {
        if (!is_array($input) || array_diff(array_keys($input), ['action', 'nonce', 'event', 'screen', 'viewport', 'reason'])) {
            return false;
        }

        foreach (['action', 'nonce', 'event', 'screen', 'viewport'] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field])) {
                return false;
            }
        }

        if (
            $input['action'] !== self::AJAX_ACTION ||
            $input['nonce'] === '' || strlen($input['nonce']) > 256 ||
            !in_array($input['event'], self::EVENTS, true) ||
            !isset(self::SCREEN_URIS[$input['screen']]) ||
            !in_array($input['viewport'], self::VIEWPORTS, true)
        ) {
            return false;
        }

        if ($input['event'] === 'error') {
            return isset($input['reason']) && is_string($input['reason']) && in_array($input['reason'], self::ERROR_CODES, true);
        }

        return !array_key_exists('reason', $input);
    }

    private function storeUrl(): string
    {
        $parts = wp_parse_url(site_url());
        if (
            !is_array($parts) ||
            !isset($parts['scheme'], $parts['host']) ||
            !in_array(strtolower($parts['scheme']), ['http', 'https'], true) ||
            $parts['host'] === ''
        ) {
            return '';
        }

        $url = strtolower($parts['scheme']) . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }

        return $url . ($parts['path'] ?? '');
    }

    private function screenUri(string $screen): string
    {
        $suffix = self::SCREEN_URIS[$screen];
        $path = wp_parse_url(admin_url($suffix), PHP_URL_PATH);
        $query = strstr($suffix, '?');

        return $path . ($query !== false ? $query : '');
    }
}
