// REQUIRES THE woocommerce_get_checkout_order_received_url FILTER TO RETURN A VALID PUBLIC HTTPS URL.
// PSE sets callback_url = WC_Order::get_checkout_order_received_url(). MP rejects localhost ("callback_url attribute must be url valid").
// run-pse.sh installs a temporary mu-plugin that hooks woocommerce_get_checkout_order_received_url and replaces
// localhost with https://e2e-test.example.com (MP validates format only — domain does not need to be reachable).
// Unlike filtering home_url() globally, this never affects WP canonical redirects. No external tunnel required.
// PSE also depends on MP's BankTransfers service — transient "BankTransfers Timeout" absorbed by retries. See docs/known-limitations.md §2.
import { test } from '@playwright/test';
import { mco } from '../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../flows/fill_steps_to_checkout';
import payWithPse from '../../../flows/pse';
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, pseUserMCO } = mco;

test.beforeEach(() => {
  skipIfNotSite(test, 'MCO');
  // Skip (não falha) quando o PSE não foi habilitado — só o run-pse.sh instala o mu-plugin
  // que torna o callback_url válido e seta E2E_PSE_ENABLED=1. Sem isso o MP rejeita o
  // callback_url localhost. Mantém o relatório limpo (⏭) sem esconder regressão real.
  test.skip(
    process.env.E2E_PSE_ENABLED !== '1',
    'PSE requer callback_url público — rode via run-pse.sh / --with-pse. Ver known-limitations.md §2 (PSW-4206).'
  );
});

test('test pse approved payment', async ({ page }) => {
  test.setTimeout(120000);
  await fillStepsToCheckout(page, shop_url, pseUserMCO);
  await payWithPse(page, pseUserMCO);
  // PSE redirects to an external bank page — plugin scope ends at form submission.
  // Verify the checkout submitted without WC errors (page navigated away from /checkout/).
  // We wait for the URL to NOT contain /checkout/ anymore, confirming navigation happened.
  // 60s: PSE payment creation + bank redirect can be slow in prod (BankTransfers service).
  await page.waitForFunction(
    () => !window.location.pathname.includes('/checkout/') || window.location.pathname.includes('/order-received/'),
    { timeout: 60000 }
  );
});
