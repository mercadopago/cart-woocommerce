import { expect } from "@playwright/test";
import {
  goToCustomCheckout,
  fillCustomCardForm,
  placeOrder,
} from "./chocustom";

/**
 * E2E coverage for the server-side checkout pre-validation gate
 * (wc_ajax_mp_validate_checkout) that runs in the Custom Checkout (card)
 * Classic flow before tokenization.
 *
 * Behaviour under test (assets/js/checkouts/custom/entities/event-handler.js
 * -> validateCheckoutThenContinue):
 *   - valid:false  -> show errors, do NOT tokenize, do NOT reach order-received.
 *   - valid:true   -> fail open -> tokenize -> submit.
 *   - server/network error -> fail open -> tokenize anyway (never blocks the buyer).
 *
 * The validation endpoint is the WooCommerce AJAX URL ending in
 * `wc-ajax=mp_validate_checkout`. We wait on that response (event-driven) so
 * the assertions never depend on a fixed sleep.
 *
 * NOTE: the validation gate is a server-side WC AJAX call, independent of the
 * card SDK tokenization. We deliberately do NOT assert on CORS/tokenization here
 * — the gate's behaviour must hold regardless of the (sandbox-dependent) card
 * tokenization path. Tokenization itself is covered by the chocustom specs.
 */

const VALIDATION_ENDPOINT_REGEX = /wc-ajax=mp_validate_checkout/;
const ORDER_RECEIVED_REGEX = /order-received/;

/**
 * The classic checkout overlay is blockUI's `.blockUI.blockOverlay`. A stuck
 * overlay is the signature of the infinite-loading regression this gate fixed.
 */
const CLASSIC_OVERLAY = '.woocommerce-checkout .blockUI.blockOverlay, form.checkout .blockUI.blockOverlay';

async function assertNotOrderReceived(page) {
  await expect(page).not.toHaveURL(ORDER_RECEIVED_REGEX);
  await expect(page.locator('.woocommerce-thankyou-order-received')).toHaveCount(0);
}

/**
 * Waits until the classic checkout overlay is gone (or never appeared). This is
 * the deterministic "no infinite loading" assertion — it polls the real DOM
 * state via Playwright's auto-retrying expect, not a fixed timeout.
 */
async function assertOverlayCleared(page) {
  await expect(page.locator(CLASSIC_OVERLAY)).toHaveCount(0, { timeout: 30000 });
}

/**
 * C1 / C2 — The WC form is conclusively invalid (a required field cleared, or a
 * malformed value the CSS `required` check would miss). The card fields are
 * valid. The gate must return valid:false, show the error, and never tokenize
 * nor reach order-received.
 *
 * `mutateForm(page)` performs the field mutation AFTER the card form is filled,
 * so the invalidity is introduced right before place-order (mirrors a buyer who
 * edits/clears a field at the last moment, and avoids WC AJAX re-filling it).
 *
 * @param {Function} mutateForm async (page) => void  mutation that makes the form invalid
 */
export async function validationGateBlocksTest(page, url, user, card, form, mutateForm) {
  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, card, form);

  await mutateForm(page);

  // Capture the validation AJAX verdict deterministically.
  const validationResponse = page.waitForResponse(
    (response) => VALIDATION_ENDPOINT_REGEX.test(response.url()),
    { timeout: 30000 }
  );

  await placeOrder(page);

  const response = await validationResponse;
  const body = await response.json();

  // The gate must conclusively reject the form.
  expect(body?.success).toBe(true);
  expect(body?.data?.valid).toBe(false);
  expect(Array.isArray(body?.data?.errors)).toBe(true);
  expect(body.data.errors.length).toBeGreaterThan(0);

  // The buyer is shown an error and is NOT taken to the success page.
  await expect(page.locator('.woocommerce-error')).toBeVisible({ timeout: 15000 });
  await assertNotOrderReceived(page);

  // Checkout stays usable — no stuck overlay.
  await assertOverlayCleared(page);
}

/**
 * C3 — Invalid/empty card fields. This guards the infinite-loading regression
 * the validation gate introduced and then fixed: whatever the gate verdict is,
 * the checkout must never end up stuck behind a blockUI overlay.
 *
 * Two legitimate paths reach this point and BOTH must recover cleanly:
 *   - gate returns valid:true (fail open) -> createToken() refuses the invalid
 *     card -> checkout recovers;
 *   - gate returns valid:false (a card-related required WC field is empty) ->
 *     errors are shown and tokenization never runs.
 *
 * The user-facing invariant is the same in both: overlay clears, place-order is
 * usable again, and the buyer is NOT taken to order-received. We assert that
 * invariant rather than the (environment-dependent) gate verdict.
 */
export async function infiniteLoadingRecoveryTest(page, url, user, card, form) {
  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, card, form);

  // Ensure the gate actually ran (event-driven, no fixed sleep).
  const validationResponse = page.waitForResponse(
    (response) => VALIDATION_ENDPOINT_REGEX.test(response.url()),
    { timeout: 30000 }
  );

  await placeOrder(page);

  await validationResponse;

  // The checkout must self-recover — no stuck overlay (the fixed regression).
  await assertOverlayCleared(page);

  // Never reaches the success page.
  await assertNotOrderReceived(page);

  // The place-order button is clickable again (checkout not frozen).
  await expect(page.locator('#place_order')).toBeEnabled({ timeout: 15000 });
}
