/**
 * WooCommerce Subscriptions environment setup helper.
 *
 * Called via test.beforeAll() in each subscription spec. Runs silently as a
 * "Before Hooks" step in the test report — not as a visible test scenario.
 *
 * Every step is idempotent: it checks the current state before acting so
 * repeated runs (e.g. after a container restart) are fast and safe.
 *
 * Prerequisites (must be in place before running subscription tests):
 *   1. WooCommerce Subscriptions plugin installed in the WP instance.
 *   2. MP_ACCESS_TOKEN_TEST set in e2e/.env (or WP admin) for payment credentials.
 *   3. AP mock server running on http://mock-ap-v2:4010 (see docker-flexible-environment/README).
 */
const { execSync } = require('child_process');
const { wpCli, wpEval, CONTAINER, INSIDE_CONTAINER } = require('./wp-env');

/**
 * Ensures the WooCommerce Subscriptions test environment is ready.
 * Pass directly to test.beforeAll():
 *
 *   const { setupSubscriptionsEnvironment } = require('../../helpers/subscriptions-env');
 *   test.beforeAll(setupSubscriptionsEnvironment);
 */
async function setupSubscriptionsEnvironment() {
    ensureWcsActive();
    ensureSubscriptionProductExists();
    ensureGatewaySettings();
}

// ── WooCommerce Subscriptions plugin ─────────────────────────────────────────

// Path to the WCS ZIP inside the container — required via WCS_ZIP_PATH in e2e/.env.
const WCS_ZIP_CONTAINER_PATH = process.env.WCS_ZIP_PATH;

function ensureWcsActive() {
    const isActive = wpEval(
        `echo is_plugin_active("woocommerce-subscriptions/woocommerce-subscriptions.php") ? "yes" : "no";`
    );

    if (isActive === 'yes') return;

    // Plugin might be installed but inactive — try activating first
    const activated = wpCli('plugin activate woocommerce-subscriptions');
    if (activated !== null) return;

    // Not installed — try installing from WCS_ZIP_PATH (ZIP or extracted directory)
    if (!WCS_ZIP_CONTAINER_PATH) {
        throw new Error(
            '[subscriptions-env] WooCommerce Subscriptions is not installed and WCS_ZIP_PATH is not set.\n\n' +
            'Add WCS_ZIP_PATH to e2e/.env pointing to the plugin ZIP or extracted directory\n' +
            'inside the container:\n' +
            '  WCS_ZIP_PATH=/woocommerce-mercadopago/e2e/plugins/woocommerce-subscriptions.zip\n' +
            '  WCS_ZIP_PATH=/woocommerce-mercadopago/e2e/plugins/woocommerce-subscriptions'
        );
    }

    const isZip = WCS_ZIP_CONTAINER_PATH.endsWith('.zip');

    if (isZip) {
        const installed = wpCli(`plugin install ${WCS_ZIP_CONTAINER_PATH} --activate`);
        if (installed !== null) return;
    } else {
        // Directory: copy or symlink into wp-content/plugins then activate.
        //
        // WCS_ZIP_PATH may be a host path (e.g. /Users/…/woocommerce-subscriptions) or a
        // container-internal path (/woocommerce-mercadopago/…). When running on the host
        // we use `docker cp` to transfer the directory; inside the container we symlink.
        if (INSIDE_CONTAINER) {
            const linked = wpEval(
                `$src  = '${WCS_ZIP_CONTAINER_PATH}';` +
                `$dest = WP_PLUGIN_DIR . "/woocommerce-subscriptions";` +
                `if (!is_dir($src)) { echo "not_found"; return; }` +
                `if (!file_exists($dest)) { symlink($src, $dest); }` +
                `echo "ok";`
            );
            if (linked === 'not_found') {
                throw new Error(
                    `[subscriptions-env] WCS_ZIP_PATH directory not found inside the container:\n` +
                    `  ${WCS_ZIP_CONTAINER_PATH}`
                );
            }
        } else {
            // Running on the host: use docker cp to transfer the directory into the container.
            try {
                execSync(
                    `docker cp "${WCS_ZIP_CONTAINER_PATH}/." ` +
                    `${CONTAINER}:/var/www/html/wp-content/plugins/woocommerce-subscriptions`,
                    { stdio: 'pipe' }
                );
            } catch (err) {
                throw new Error(
                    `[subscriptions-env] docker cp failed for WCS_ZIP_PATH.\n\n` +
                    `Path used: ${WCS_ZIP_CONTAINER_PATH}\n` +
                    `Error: ${err.stderr?.toString().trim() || err.message}`
                );
            }
        }
        const activated = wpCli('plugin activate woocommerce-subscriptions');
        if (activated !== null) return;
    }

    throw new Error(
        `[subscriptions-env] Failed to install WooCommerce Subscriptions from WCS_ZIP_PATH.\n\n` +
        `Path used: ${WCS_ZIP_CONTAINER_PATH}\n` +
        `Type: ${isZip ? 'ZIP file' : 'directory'}\n\n` +
        'Check that the path is correct and accessible inside the container.'
    );
}

// ── Subscription test product ─────────────────────────────────────────────────

function ensureSubscriptionProductExists() {
    const productUrl = process.env.SUBSCRIPTION_PRODUCT_URL || '/product/subscription-test-product/';
    const slug = productUrl.replace(/^\/product\//, '').replace(/\/$/, '');

    const existingId = wpEval(
        `$p = get_page_by_path("${slug}", OBJECT, "product"); echo $p ? $p->ID : 0;`
    );

    if (existingId && existingId !== '0') return;

    // wpEval wraps PHP in single quotes on the shell, so PHP string literals
    // must use double quotes (same convention as global-setup.js).
    const result = wpEval(
        `if (!class_exists("WC_Product_Subscription")) { echo "wcs_missing"; return; }` +
        `$p = new WC_Product_Subscription();` +
        `$p->set_name("Subscription Test Product");` +
        `$p->set_slug("${slug}");` +
        `$p->set_status("publish");` +
        `$p->set_regular_price("49.90");` +
        `$p->update_meta_data("_subscription_price", "49.90");` +
        `$p->update_meta_data("_subscription_period", "month");` +
        `$p->update_meta_data("_subscription_period_interval", "1");` +
        `$p->update_meta_data("_subscription_length", "0");` +
        `$id = $p->save();` +
        `echo $id ? "created:" . $id : "failed";`
    );

    if (result && result.startsWith('created:')) return;

    if (result === 'wcs_missing') {
        throw new Error(
            '[subscriptions-env] WC_Product_Subscription class not found.\n' +
            'Make sure WooCommerce Subscriptions is installed and active.'
        );
    }

    throw new Error(
        `[subscriptions-env] Could not create subscription product (slug: ${slug}). ` +
        `wp-cli response: ${result}`
    );
}

// ── MP gateway subscription settings ─────────────────────────────────────────

function ensureGatewaySettings() {
    const tokenTest  = process.env.MP_SUBSCRIPTIONS_ACCESS_TOKEN_TEST || '';
    const tokenProd  = process.env.MP_SUBSCRIPTIONS_ACCESS_TOKEN_PROD || '';
    const pubKeyTest = process.env.MP_SUBSCRIPTIONS_PUBLIC_KEY_TEST   || '';
    const pubKeyProd = process.env.MP_SUBSCRIPTIONS_PUBLIC_KEY_PROD   || '';

    wpEval(
        `$s = get_option("woocommerce_woo-mercado-pago-custom_settings", []);` +
        `$s["subscriptions_enabled"] = "yes";` +
        (tokenTest  ? `$s["subscriptions_access_token_test"] = "${tokenTest.replace(/"/g, '')}";`  : '') +
        (tokenProd  ? `$s["subscriptions_access_token_prod"] = "${tokenProd.replace(/"/g, '')}";`  : '') +
        (pubKeyTest ? `$s["subscriptions_public_key_test"]   = "${pubKeyTest.replace(/"/g, '')}";` : '') +
        (pubKeyProd ? `$s["subscriptions_public_key_prod"]   = "${pubKeyProd.replace(/"/g, '')}";` : '') +
        `update_option("woocommerce_woo-mercado-pago-custom_settings", $s);`
    );
}

/**
 * Asserts that the subscription's payment method was changed by verifying
 * _mp_active_card_id is set and different from the original CIT card.
 *
 * Uses WP-CLI (server-side) because handle3dsPayOrderFormSubmission() in the
 * MP plugin posts via jQuery.post('#') and WC responds with wp_redirect() (302).
 * jQuery follows the redirect silently, delivers HTML to .done() instead of JSON,
 * and the client falls through to window.location.reload() — making browser-side
 * URL detection unreliable. The payment method change succeeds on the server even
 * when the browser does not navigate to view-subscription.
 *
 * @param {number|string} subscriptionId
 * @param {string} [originalCardId] If provided, asserts the new card differs from it.
 */
function assertSubscriptionHasNewCard(subscriptionId, originalCardId) {
    const newCardId = wpEval(
        `$sub = wcs_get_subscription(${subscriptionId});` +
        `echo $sub ? (string) $sub->get_meta("_mp_active_card_id") : "";`
    );

    if (!newCardId) {
        throw new Error(
            `[subscriptions] Subscription ${subscriptionId} has no _mp_active_card_id — ` +
            `payment method change may have failed.`
        );
    }

    if (originalCardId && newCardId === String(originalCardId)) {
        throw new Error(
            `[subscriptions] _mp_active_card_id was not updated for subscription ${subscriptionId} ` +
            `(still ${newCardId}).`
        );
    }
}

/**
 * Returns the current _mp_active_card_id stored on a subscription.
 * Use this before a card change to capture the original card ID so that
 * assertSubscriptionHasNewCard can verify the value actually changed.
 *
 * @param {number|string} subscriptionId
 * @returns {string|null}
 */
function getSubscriptionCardId(subscriptionId) {
    return wpEval(
        `$sub = wcs_get_subscription(${subscriptionId});` +
        `echo $sub ? (string) $sub->get_meta("_mp_active_card_id") : "";`
    ) || null;
}

module.exports = { setupSubscriptionsEnvironment, assertSubscriptionHasNewCard, getSubscriptionCardId };
