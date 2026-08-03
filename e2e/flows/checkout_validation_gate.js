import { expect } from "@playwright/test";
import {
  goToCustomCheckout,
  fillCustomCardForm,
  placeOrder,
} from "./chocustom";

/**
 * E2E coverage for invalid-input handling in the Custom Checkout (card).
 *
 * PSW-4344 removed the MP server-side pre-validation gate
 * (wc_ajax_mp_validate_checkout) that used to run before tokenization: the card
 * now tokenizes directly and the checkout relies on WooCommerce's native submit
 * validation. These tests assert the user-facing invariants that must hold with
 * that native validation, independently of the (sandbox-dependent) tokenization:
 *   - an invalid WC form is blocked (error shown, order not placed);
 *   - an invalid card recovers with no stuck overlay and never reaches
 *     order-received.
 */

const ORDER_RECEIVED_REGEX = /order-received/;

/**
 * The classic checkout overlay is blockUI's `.blockUI.blockOverlay`. A stuck
 * overlay is the signature of the infinite-loading regression.
 */
const CLASSIC_OVERLAY = '.woocommerce-checkout .blockUI.blockOverlay, form.checkout .blockUI.blockOverlay';

async function assertNotOrderReceived(page) {
  await expect(page).not.toHaveURL(ORDER_RECEIVED_REGEX);
  await expect(page.locator('.woocommerce-thankyou-order-received')).toHaveCount(0);
}

/**
 * Waits until the classic checkout overlay is gone (or never appeared) — the
 * deterministic "no infinite loading" assertion (auto-retrying, no fixed sleep).
 */
async function assertOverlayCleared(page) {
  await expect(page.locator(CLASSIC_OVERLAY)).toHaveCount(0, { timeout: 30000 });
}

/**
 * C1 / C2 — Classic. The WC form is conclusively invalid (a required field
 * cleared, or a malformed value the CSS `required` check misses) while the card
 * fields are valid. WooCommerce's native checkout validation must reject the
 * submission: show an error and never reach order-received.
 *
 * `mutateForm(page)` performs the field mutation AFTER the card form is filled,
 * so the invalidity is introduced right before place-order (mirrors a buyer who
 * edits/clears a field at the last moment, and avoids WC AJAX re-filling it).
 *
 * @param {Function} mutateForm async (page) => void  mutation that makes the form invalid
 */
export async function invalidFormBlockedClassic(page, url, user, card, form, mutateForm) {
  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, card, form);

  await mutateForm(page);

  await placeOrder(page);

  // WooCommerce native validation rejects the form: error shown, no success page.
  await expect(page.locator('.woocommerce-error')).toBeVisible({ timeout: 15000 });
  await assertNotOrderReceived(page);

  // Checkout stays usable — no stuck overlay.
  await assertOverlayCleared(page);
}

/**
 * C1 / C2 — Blocks. WC Blocks applies client-side validation before submission
 * and renders the field error inline:
 *   <div class="wc-block-components-validation-error" role="alert">
 *     <p id="validate-error-{field}">...</p>
 *   </div>
 * We verify the inline error renders and the order is not placed.
 *
 * @param {Function} mutateForm    async (page) => void  introduces the invalidity
 * @param {string}   errorSelector CSS selector for the expected inline error element
 *                   (e.g. '#validate-error-shipping_postcode')
 */
export async function invalidFormBlockedBlocks(page, url, user, card, form, mutateForm, errorSelector) {
  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, card, form);
  await mutateForm(page);
  await placeOrder(page);

  await expect(page.locator(errorSelector)).toBeVisible({ timeout: 10000 });
  await assertNotOrderReceived(page);
  await assertOverlayCleared(page);
}

/**
 * C3 — Classic. Invalid/empty card fields. Tokenization refuses the invalid
 * card; the checkout must never end up stuck behind a blockUI overlay and must
 * never reach order-received. We assert that user-facing invariant rather than
 * the (environment-dependent) tokenization path.
 */
export async function invalidCardRecoversClassic(page, url, user, card, form) {
  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, card, form);

  await placeOrder(page);

  // The checkout must self-recover — no stuck overlay (the fixed regression).
  await assertOverlayCleared(page);

  // Never reaches the success page.
  await assertNotOrderReceived(page);

  // The place-order button is clickable again (checkout not frozen).
  await expect(page.locator('#place_order')).toBeEnabled({ timeout: 15000 });
}

/**
 * C3 — Blocks. An invalid card must not freeze the checkout: the order is never
 * placed and the Blocks place-order button recovers to a clickable state (no
 * infinite spinner).
 */
export async function invalidCardRecoversBlocks(page, url, user, card, form) {
  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, card, form);

  await placeOrder(page);

  // Never reaches the success page.
  await assertNotOrderReceived(page);

  // The Blocks place-order button recovers — not stuck in the disabled/processing state.
  await expect(
    page.locator('.wc-block-components-checkout-place-order-button')
  ).toBeEnabled({ timeout: 15000 });
}
