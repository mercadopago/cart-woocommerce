const {
  wpOption, wpGetOption, wpEval,
  enableGateway, setGatewaySetting,
  isContainerRunning, getCurrentStoreSite, resetStore,
} = require('./helpers/wp-env');
require('dotenv').config();

const PORT = process.env.PORT || '8080';
const ADMIN_URL = `http://localhost:${PORT}/wp-admin/admin.php?page=mercadopago-settings`;

// Credential environment: 'test' (default, sandbox) or 'prod' (production).
// Controlled by the MP_ENV env var. When 'prod', the setup reads MP_*_PROD_<SITE>
// credentials, writes them to the plugin's prod slots and disables test mode so
// the plugin exercises the production payment flow.
const MP_ENV = (process.env.MP_ENV || 'test').toLowerCase();
const IS_PROD = MP_ENV === 'prod';
const CRED_SUFFIX = IS_PROD ? 'PROD' : 'TEST';

function detectSite() {
  if (process.env.SITE) return process.env.SITE.toUpperCase();

  const npmScript = process.env.npm_lifecycle_event || '';
  const match = npmScript.match(/test:(\w+)/);
  if (match) return match[1].toUpperCase();

  return 'MLB';
}

function getCredentials(site) {
  const accessTokenOption = IS_PROD ? '_mp_access_token_prod' : '_mp_access_token_test';
  const publicKeyOption = IS_PROD ? '_mp_public_key_prod' : '_mp_public_key_test';

  const accessToken = process.env[`MP_ACCESS_TOKEN_${CRED_SUFFIX}_${site}`]
    || process.env[`MP_ACCESS_TOKEN_${CRED_SUFFIX}`]
    || wpGetOption(accessTokenOption);

  const publicKey = process.env[`MP_PUBLIC_KEY_${CRED_SUFFIX}_${site}`]
    || process.env[`MP_PUBLIC_KEY_${CRED_SUFFIX}`]
    || wpGetOption(publicKeyOption);

  return { accessToken, publicKey };
}

function validateCredentials(creds, site) {
  if (creds.accessToken && creds.publicKey) return;

  const missing = [];
  if (!creds.accessToken) missing.push('access_token');
  if (!creds.publicKey) missing.push('public_key');

  const tokenPrefix = IS_PROD ? 'APP_USR-' : 'TEST-';

  throw new Error(
    `[E2E] Credenciais MP (${CRED_SUFFIX}) nao configuradas para ${site} (faltando: ${missing.join(', ')}).\n\n` +
    `Opcoes para resolver:\n` +
    `  1. Adicionar no e2e/.env:\n` +
    `     MP_ACCESS_TOKEN_${CRED_SUFFIX}_${site}=${tokenPrefix}...\n` +
    `     MP_PUBLIC_KEY_${CRED_SUFFIX}_${site}=${tokenPrefix}...\n\n` +
    `  2. Ou credenciais genericas (usadas para todos os paises):\n` +
    `     MP_ACCESS_TOKEN_${CRED_SUFFIX}=${tokenPrefix}...\n` +
    `     MP_PUBLIC_KEY_${CRED_SUFFIX}=${tokenPrefix}...\n\n` +
    `  3. Ou configurar via admin do plugin:\n` +
    `     ${ADMIN_URL}\n` +
    `     (login: admin / admin)\n\n` +
    `Obtenha credenciais de teste em:\n` +
    `  https://www.mercadopago.com/developers/panel/app`
  );
}

module.exports = async function globalSetup() {
  const site = detectSite();

  // --- Ensure Docker store matches the target country ---
  const currentSite = getCurrentStoreSite();

  if (!isContainerRunning() || (currentSite && currentSite !== site)) {
    const reason = !isContainerRunning()
      ? 'container is not running'
      : `store is ${(currentSite || 'unknown').toUpperCase()}, need ${site}`;

    process.stdout.write(`[E2E] ${reason} — resetting store...\n`);

    const ok = resetStore(site);
    if (!ok) {
      throw new Error(
        `[E2E] Failed to reset store for ${site}.\n` +
        `Try manually: cd docker-flexible-environment && make reset SITE=${site.toLowerCase()}`
      );
    }
  }

  process.stdout.write(`[E2E] Configuring store for ${site}...\n`);

  // --- WC core settings ---
  wpOption('woocommerce_coming_soon', 'no');

  // Configure checkout mode: classic (default) or blocks
  const checkoutMode = (process.env.CHECKOUT || 'classic').toLowerCase();

  // Cart always uses classic shortcode (WC Blocks cart doesn't render
  // "Proceed to checkout" button correctly with Storefront + WC 10.x).
  // Only the checkout page switches between classic and blocks.
  const cartContent = '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->';

  // Blocks checkout requires the FULL inner block structure — the self-closing
  // <!-- wp:woocommerce/checkout /--> tag renders empty because WC's render
  // callback needs inner blocks (contact-information, shipping-address, payment, etc.)
  const blocksCheckoutContent = [
    '<!-- wp:woocommerce/checkout -->',
    '<div class="wp-block-woocommerce-checkout alignwide wc-block-checkout is-loading">',
    '<!-- wp:woocommerce/checkout-fields-block --><div class="wp-block-woocommerce-checkout-fields-block">',
    '<!-- wp:woocommerce/checkout-express-payment-block /-->',
    '<!-- wp:woocommerce/checkout-contact-information-block /-->',
    '<!-- wp:woocommerce/checkout-shipping-address-block /-->',
    '<!-- wp:woocommerce/checkout-billing-address-block /-->',
    '<!-- wp:woocommerce/checkout-shipping-methods-block /-->',
    '<!-- wp:woocommerce/checkout-payment-block /-->',
    '<!-- wp:woocommerce/checkout-additional-information-block /-->',
    '<!-- wp:woocommerce/checkout-order-note-block /-->',
    '<!-- wp:woocommerce/checkout-terms-block /-->',
    '<!-- wp:woocommerce/checkout-actions-block /-->',
    '</div><!-- /wp:woocommerce/checkout-fields-block -->',
    '<!-- wp:woocommerce/checkout-totals-block --><div class="wp-block-woocommerce-checkout-totals-block">',
    '<!-- wp:woocommerce/checkout-order-summary-block /-->',
    '</div><!-- /wp:woocommerce/checkout-totals-block -->',
    '</div><!-- /wp:woocommerce/checkout -->',
  ].join('');

  const checkoutContent = checkoutMode === 'blocks'
    ? blocksCheckoutContent
    : '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->';

  // Escape double quotes in HTML content so it survives PHP double-quoted string interpolation.
  // Without this, Blocks checkout HTML (which contains class="...") breaks the wp eval command.
  const safeCheckout = checkoutContent.replace(/"/g, '\\"');
  const safeCart = cartContent.replace(/"/g, '\\"');

  wpEval(
    '$checkout_id = wc_get_page_id("checkout"); ' +
    `wp_update_post(array("ID" => $checkout_id, "post_content" => "${safeCheckout}")); ` +
    '$cart_id = wc_get_page_id("cart"); ' +
    `wp_update_post(array("ID" => $cart_id, "post_content" => "${safeCart}"));`
  );

  // Fix wc-logs permissions (wp-cli runs as root, Apache runs as www-data)
  wpEval(
    '$dir = wp_upload_dir()["basedir"] . "/wc-logs"; ' +
    'if (is_dir($dir)) { chmod($dir, 0777); }'
  );

  // --- MP plugin settings ---
  // test mode 'yes' uses the *_test credential slots; 'no' uses the *_prod slots.
  wpOption('checkbox_checkout_test_mode', IS_PROD ? 'no' : 'yes');
  wpOption('_mp_custom_domain', 'https://e2e-test.example.com');
  wpOption('_mp_custom_domain_options', 'yes');
  wpOption('_site_id_v1', site);

  // --- Credentials ---
  const creds = getCredentials(site);
  validateCredentials(creds, site);

  if (IS_PROD) {
    wpOption('_mp_access_token_prod', creds.accessToken);
    wpOption('_mp_public_key_prod', creds.publicKey);
  } else {
    wpOption('_mp_access_token_test', creds.accessToken);
    wpOption('_mp_public_key_test', creds.publicKey);
  }

  // Extract client_id from access token (format: {TEST|APP_USR}-{client_id}-{timestamp}-{hash})
  // and set integration options required by the plugin.
  const clientId = creds.accessToken.replace(/^(TEST|APP_USR)-/, '').split('-')[0];
  if (clientId && /^\d+$/.test(clientId)) {
    wpOption('_mp_client_id', clientId);
  } else if (clientId) {
    // eslint-disable-next-line no-console
    console.warn(`[E2E] clientId extraido parece invalido: ${clientId} — pulando`);
  }

  // Sync payment methods from site-specific API and populate per-gateway caches.
  wpEval(
    'global $mercadopago; ' +
    '$mercadopago->sellerConfig->updatePaymentMethodsBySiteId(); ' +
    '$all = get_option("_site_id_payment_methods", []); ' +
    '$byType = []; ' +
    'foreach ($all as $m) { $byType[$m["payment_type_id"]][] = $m; } ' +
    'if (isset($byType["ticket"])) update_option("_all_payment_methods_ticket", $byType["ticket"]); ' +
    'if (isset($byType["bank_transfer"])) update_option("_mp_payment_methods_pix", $byType["bank_transfer"]); '
  );

  // Enable payment gateways
  const gateways = [
    'woo-mercado-pago-custom',
    'woo-mercado-pago-basic',
    'woo-mercado-pago-pix',
    'woo-mercado-pago-ticket',
    'woo-mercado-pago-credits',
  ];
  for (const gw of gateways) {
    enableGateway(gw);
  }

  setGatewaySetting('woo-mercado-pago-custom', 'binary_mode', 'no');

  process.stdout.write(`[E2E] Store configured for ${site} | checkout: ${checkoutMode} | env: ${CRED_SUFFIX} (test_mode=${IS_PROD ? 'no' : 'yes'}) | credentials: OK\n`);
};
