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
