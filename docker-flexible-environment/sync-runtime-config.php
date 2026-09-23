<?php

if (!defined('ABSPATH')) {
    exit;
}

function mp_sync_runtime_config(?callable $sleep = null): bool
{
    $sharedEnvironment = getenv('E2E_SHARED_ENVIRONMENT') === '1';
    $requiredVariables = [
        'WP_ADMIN_USER',
        'WP_ADMIN_PASSWORD',
        'WP_ADMIN_EMAIL',
        'MP_ACCESS_TOKEN_TEST',
        'MP_PUBLIC_KEY_TEST',
    ];

    if ($sharedEnvironment) {
        foreach ($requiredVariables as $requiredVariable) {
            $value = getenv($requiredVariable);
            if ($value === false || $value === '') {
                return false;
            }
        }
    }

    // This script is invoked by the common container entrypoint, but persistent runtime sync is a
    // shared-environment contract. Local and personal stores keep the configuration chosen in the
    // WordPress admin instead of being reset from process environment values on every boot.
    if (!$sharedEnvironment) {
        return true;
    }

    $adminUser     = getenv('WP_ADMIN_USER');
    $adminPassword = getenv('WP_ADMIN_PASSWORD');
    $adminEmail    = getenv('WP_ADMIN_EMAIL');
    $customDomain  = getenv('MP_CUSTOM_DOMAIN');
    $site          = strtolower((string) getenv('SITE'));

    if ($adminUser === 'admin' || strlen($adminPassword) < 20) {
        return false;
    }

    $supportedSites = ['mla', 'mlb', 'mlc', 'mlm', 'mco', 'mlu', 'mpe'];
    $domainParts = $customDomain !== false ? parse_url($customDomain) : false;
    $hostMatches = [];
    $allowedHost = is_array($domainParts)
        && isset($domainParts['scheme'], $domainParts['host'])
        && $domainParts['scheme'] === 'https'
        && preg_match(
            '/\Ae2e-(staging|homol)-(mla|mlb|mlc|mlm|mco|mlu|mpe)\.ppolimpo\.io\z/',
            $domainParts['host'],
            $hostMatches
        ) === 1
        && in_array($site, $supportedSites, true)
        && $hostMatches[2] === $site
        && !isset($domainParts['user'])
        && !isset($domainParts['pass'])
        && !isset($domainParts['query'])
        && !isset($domainParts['fragment'])
        && (!isset($domainParts['path']) || $domainParts['path'] === '' || $domainParts['path'] === '/');

    if (!$allowedHost) {
        return false;
    }

    if ($adminUser !== false && $adminUser !== '') {
        $user = get_user_by('login', $adminUser);
        if (!$user) {
            return false;
        } elseif ($adminEmail !== false && $adminEmail !== '') {
            if (!is_email($adminEmail)) {
                return false;
            }
            if ($user->user_email !== $adminEmail) {
                $updatedUser = wp_update_user([
                    'ID'         => $user->ID,
                    'user_email' => $adminEmail,
                ]);
                if (is_wp_error($updatedUser)) {
                    return false;
                }
            }
        }

        if (
            $user
            && $adminPassword !== false
            && $adminPassword !== ''
            && !wp_check_password($adminPassword, $user->user_pass, $user->ID)
        ) {
            wp_set_password($adminPassword, $user->ID);
        }
    }

    $optionVariables = [
        '_mp_access_token_test' => 'MP_ACCESS_TOKEN_TEST',
        '_mp_public_key_test'   => 'MP_PUBLIC_KEY_TEST',
        '_mp_custom_domain'     => 'MP_CUSTOM_DOMAIN',
    ];

    foreach ($optionVariables as $option => $environmentVariable) {
        $value = getenv($environmentVariable);
        if ($value !== false && $value !== '') {
            update_option($option, $value, false);
        }
    }

    $publicUrl = rtrim($customDomain, '/');
    update_option('home', $publicUrl, false);
    update_option('siteurl', $publicUrl, false);

    update_option('checkbox_checkout_test_mode', 'yes', false);

    $expectedSite = strtoupper($site);
    if (strtoupper((string) get_option('_site_id_v1', '')) !== $expectedSite) {
        return false;
    }

    global $mercadopago;
    if (!isset($mercadopago->sellerConfig) || !is_object($mercadopago->sellerConfig)) {
        return false;
    }

    $sleep = $sleep ?? static function (int $seconds): void {
        sleep($seconds);
    };
    update_option('_site_id_payment_methods', [], false);
    for ($attempt = 1; $attempt <= 3; ++$attempt) {
        try {
            // Credentials are injected after the persistent store bootstrap. Refresh every derived
            // payment-method cache now so a recreated container cannot keep empty/stale data from
            // the previous seller or country.
            $mercadopago->sellerConfig->updatePaymentMethods();
            $mercadopago->sellerConfig->updatePaymentMethodsBySiteId($expectedSite);
        } catch (Throwable $error) {
            // A transient API failure is retried below without exposing provider diagnostics.
        }

        $sitePaymentMethods = get_option('_site_id_payment_methods', []);
        if (is_array($sitePaymentMethods) && $sitePaymentMethods !== []) {
            return true;
        }
        if ($attempt < 3) {
            $sleep(2);
        }
    }

    return false;
}

if (!defined('MP_SYNC_RUNTIME_CONFIG_NO_RUN') && !mp_sync_runtime_config()) {
    exit(1);
}
