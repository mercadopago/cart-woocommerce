// REQUIRES A PUBLIC DOMAIN. PSE sets callback_url = the WC order-received URL (= home URL).
// On plain localhost the MP API rejects it ("callback_url attribute must be url valid"), so
// this test only passes when the store is reachable via a public URL. Run it with:
//   bash e2e/run-pse-with-tunnel.sh
// (spins up a temporary cloudflared domain, points home/siteurl at it, runs PSE classic +
// blocks, restores localhost). PSE also depends on MP's BankTransfers service, which can
// return a transient "BankTransfers Timeout" (absorbed by retries). See docs/known-limitations.md.
import { test } from '@playwright/test';
import { mco } from '../../../data/meli_sites';
import { fillStepsToCheckout } from '../../../flows/fill_steps_to_checkout';
import payWithPse from '../../../flows/pse';
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, pseUserMCO } = mco;

test.beforeEach(() => {
  skipIfNotSite(test, 'MCO');
});

test('test pse approved payment', async ({ page }) => {
  await fillStepsToCheckout(page, shop_url, pseUserMCO);
  await payWithPse(page, pseUserMCO);
  // PSE redirects to an external bank page — plugin scope ends at form submission.
  // Verify the checkout submitted without WC errors (page navigated away from /checkout/).
  // We wait for the URL to NOT contain /checkout/ anymore, confirming navigation happened.
  await page.waitForFunction(
    () => !window.location.pathname.includes('/checkout/') || window.location.pathname.includes('/order-received/'),
    { timeout: 30000 }
  );
});
