import { expect } from "@playwright/test";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";
import { wpEval } from "../helpers/wp-env";
import { snapshotOptions, restoreOptions } from "../helpers/wp-options-snapshot";

/**
 * E2E coverage: with no Mercado Pago credentials configured, the MP gateways must
 * not be offered at checkout. AbstractGateway::is_available() returns false when
 * isMissingCredentials() is true (empty public key OR access token), so clearing the
 * credential options hides the gateway in both Classic and Blocks checkout.
 *
 * The credential options are snapshotted and restored by the caller in a finally
 * block, so the store is left exactly as it was — options that did not exist before
 * the test are removed again (not recreated empty), and global-setup's credentials
 * survive regardless of outcome.
 */

const MP_CREDENTIAL_OPTIONS = [
  '_mp_access_token_test',
  '_mp_public_key_test',
  '_mp_access_token_prod',
  '_mp_public_key_prod',
];

/**
 * Snapshots the MP credential options and clears them. Returns the snapshot to pass
 * to restoreMpCredentials().
 */
export function snapshotAndClearMpCredentials() {
  const snapshot = snapshotOptions(MP_CREDENTIAL_OPTIONS);
  wpEval(MP_CREDENTIAL_OPTIONS.map((k) => `update_option("${k}", "");`).join(''));
  return snapshot;
}

/** Restores the MP credential options from a snapshot (deleting any that were absent). */
export function restoreMpCredentials(snapshot) {
  restoreOptions(snapshot);
}

/**
 * Navigates to the checkout (adding a product and filling billing) and asserts the
 * MP Custom gateway is not offered in either checkout type. Filling billing proves
 * the checkout rendered, so the absence of the radio is meaningful (not a page that
 * simply failed to load).
 */
export async function assertCustomGatewayHidden(page, url, user) {
  await fillStepsToCheckout(page, url, user);
  await page.waitForLoadState();
  await expect(
    page.locator('form.checkout, .wp-block-woocommerce-checkout').first()
  ).toBeVisible({ timeout: 15000 });
  // Give the Blocks payment section time to render its (credential-less) state.
  await page.waitForTimeout(2000);

  await expect(page.locator('#payment_method_woo-mercado-pago-custom')).toHaveCount(0);
  await expect(
    page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-custom')
  ).toHaveCount(0);
}
