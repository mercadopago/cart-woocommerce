<?php

define('ABSPATH', __DIR__);
define('MP_SYNC_RUNTIME_CONFIG_NO_RUN', true);

$capturedOptions  = [];
$capturedPassword = null;
$currentSite      = 'MLB';
$refreshCalls     = [];
$refreshFailuresRemaining = 0;
$fakeUser         = (object) [
    'ID'         => 42,
    'user_email' => 'old@example.com',
    'user_pass'  => 'old-password',
];

class FakeSellerConfig
{
    public function updatePaymentMethods(): void
    {
        global $refreshCalls;
        $refreshCalls[] = 'credentials';
    }

    public function updatePaymentMethodsBySiteId(?string $siteId = null): void
    {
        global $capturedOptions, $refreshCalls, $refreshFailuresRemaining;
        $refreshCalls[] = "site:{$siteId}";
        if ($refreshFailuresRemaining > 0) {
            --$refreshFailuresRemaining;
            $capturedOptions['_site_id_payment_methods'] = [[], false];
            return;
        }
        $capturedOptions['_site_id_payment_methods'] = [[['id' => 'visa']], false];
    }
}

$mercadopago = (object) ['sellerConfig' => new FakeSellerConfig()];

function get_user_by($field, $value)
{
    global $fakeUser;

    return $field === 'login' && $value === 'e2e_operator' ? $fakeUser : false;
}

function is_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function wp_update_user($payload)
{
    global $fakeUser;

    $fakeUser->user_email = $payload['user_email'];

    return $payload['ID'];
}

function is_wp_error($value)
{
    return false;
}

function wp_check_password($password, $hash, $userId)
{
    return $password === $hash && $userId === 42;
}

function wp_set_password($password, $userId)
{
    global $capturedPassword;

    if ($userId === 42) {
        $capturedPassword = $password;
    }
}

function update_option($option, $value, $autoload)
{
    global $capturedOptions;

    $capturedOptions[$option] = [$value, $autoload];

    return true;
}

function get_option($option, $default = false)
{
    global $capturedOptions, $currentSite;

    if ($option === '_site_id_v1') {
        return $currentSite;
    }
    return $capturedOptions[$option][0] ?? $default;
}

require __DIR__ . '/sync-runtime-config.php';

function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

putenv('E2E_SHARED_ENVIRONMENT=1');
putenv('SITE=mlb');
putenv('WP_ADMIN_USER=e2e_operator');
putenv('WP_ADMIN_PASSWORD=valid-password-with-20-chars');
putenv('WP_ADMIN_EMAIL=e2e@example.com');
putenv('MP_ACCESS_TOKEN_TEST=TEST-token');
putenv('MP_PUBLIC_KEY_TEST=TEST-public-key');
putenv('MP_CUSTOM_DOMAIN=https://e2e-staging-mlb.ppolimpo.io');

expect(mp_sync_runtime_config() === true, 'shared sync should succeed');
expect($capturedPassword === 'valid-password-with-20-chars', 'admin password should rotate');
expect($fakeUser->user_email === 'e2e@example.com', 'admin email should update');
expect($capturedOptions['_mp_access_token_test'][0] === 'TEST-token', 'test token should update');
expect($capturedOptions['_mp_public_key_test'][0] === 'TEST-public-key', 'public key should update');
expect(
    $capturedOptions['_mp_custom_domain'][0] === 'https://e2e-staging-mlb.ppolimpo.io',
    'custom domain should update'
);
expect(
    $capturedOptions['home'][0] === 'https://e2e-staging-mlb.ppolimpo.io',
    'public home URL should update'
);
expect(
    $capturedOptions['siteurl'][0] === 'https://e2e-staging-mlb.ppolimpo.io',
    'public site URL should update'
);
expect($capturedOptions['checkbox_checkout_test_mode'][0] === 'yes', 'test mode should stay enabled');
expect($refreshCalls === ['credentials', 'site:MLB'], 'payment method caches should refresh after credentials');

putenv('MP_CUSTOM_DOMAIN=https://attacker.example');
expect(mp_sync_runtime_config() === false, 'unapproved shared domain should fail closed');

putenv('MP_CUSTOM_DOMAIN=https://e2e-staging-mla.ppolimpo.io');
expect(mp_sync_runtime_config() === false, 'domain from another site should fail closed');

putenv('MP_CUSTOM_DOMAIN=https://e2e-staging-mlb.ppolimpo.io/?source=invalid');
expect(mp_sync_runtime_config() === false, 'shared domain with a query should fail closed');

putenv('MP_CUSTOM_DOMAIN=https://user@e2e-staging-mlb.ppolimpo.io/');
expect(mp_sync_runtime_config() === false, 'shared domain with userinfo should fail closed');

foreach (['mla', 'mlb', 'mlc', 'mlm', 'mco', 'mlu', 'mpe'] as $supportedSite) {
    $currentSite = strtoupper($supportedSite);
    putenv("SITE={$supportedSite}");
    putenv("MP_CUSTOM_DOMAIN=https://e2e-homol-{$supportedSite}.ppolimpo.io");
    expect(mp_sync_runtime_config() === true, "{$supportedSite} shared domain should be supported");
}

putenv('SITE=mlb');
$currentSite = 'MLB';
putenv('MP_CUSTOM_DOMAIN=https://e2e-staging-mlb.ppolimpo.io');
putenv('MP_ACCESS_TOKEN_TEST=TEST-token');
$refreshCalls = [];
$refreshFailuresRemaining = 1;
expect(
    mp_sync_runtime_config(static function (int $seconds): void {}) === true,
    'one transient payment-method failure should be retried'
);
expect(
    $refreshCalls === ['credentials', 'site:MLB', 'credentials', 'site:MLB'],
    'payment method refresh should retry the complete operation'
);

putenv('MP_ACCESS_TOKEN_TEST');
expect(mp_sync_runtime_config() === false, 'missing shared secret should fail closed');

putenv('E2E_SHARED_ENVIRONMENT=0');
putenv('MP_ACCESS_TOKEN_TEST=must-not-overwrite-local');
$optionsBeforeLocalBoot = $capturedOptions;
expect(mp_sync_runtime_config() === true, 'local runtime sync should be a no-op');
expect($capturedOptions === $optionsBeforeLocalBoot, 'local WordPress options should be preserved');

fwrite(STDOUT, "sync-runtime-config: ok\n");
