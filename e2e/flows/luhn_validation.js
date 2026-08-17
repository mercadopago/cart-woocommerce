import { expect } from "@playwright/test";
import {
  goToCustomCheckout,
  fillCustomCardForm,
  placeOrder,
} from "./chocustom";

/**
 * E2E coverage for the Luhn (checksum) validation in the Custom Checkout (card).
 *
 * The card number secure field runs `enableLuhnValidation: true` (card-form.js), so a
 * card number that passes the brand + length checks but fails the Luhn checksum is
 * rejected client-side BEFORE tokenization:
 *   - the SDK fires onValidityChange with code `invalid_value`;
 *   - the plugin flags `#form-checkout__cardNumber-container` with `mp-error` and
 *     shows the card-number input-helper (a card-SPECIFIC message, not the generic
 *     WooCommerce error), mapping the reason to `rejected_luhn`;
 *   - runPreSubmitGates() sees cardNumberHasError() === true and blocks the submit,
 *     so the order never reaches order-received and the checkout stays usable.
 *
 * Because `invalid_value` (Luhn) keeps a valid BIN, the derived fields
 * (installments dropdown + issuer) are PRESERVED — only the empty state
 * (`invalid_type`) clears them. That non-reset behavior is the installments-preservation scenario below.
 */

const ORDER_RECEIVED_REGEX = /order-received/;

// Card-number error state set by the plugin (mp-custom-page.js cardNumberHasError()).
const CARD_NUMBER_CONTAINER = '#form-checkout__cardNumber-container';
// The card-SPECIFIC helper message shown next to the field (not the generic WC error).
// The <input-helper> web component renders a light-DOM `div.mp-helper` carrying the id
// `mp-card-number-helper` (distinct from the host's `input-id` attribute); the plugin
// toggles that div display:flex when the message must be shown. Target it by id so the
// locator resolves to a SINGLE element (the host has 3 descendant divs — icon + message).
const CARD_NUMBER_HELPER = '#mp-card-number-helper';
// blockUI overlay on the classic checkout — a stuck overlay is the infinite-loading signature.
const CLASSIC_OVERLAY = '.woocommerce-checkout .blockUI.blockOverlay, form.checkout .blockUI.blockOverlay';

/**
 * Turns a valid card number into a Luhn-INVALID one while keeping the BIN, brand
 * and length identical: it bumps only the last (check) digit by 1 (mod 10).
 *
 * Exactly one check digit makes a number Luhn-valid, so `(last + 1) % 10` is
 * always Luhn-invalid. Keeping the BIN intact is what lets the SDK still identify
 * the brand and load installments/issuer — the precondition for the installments-preservation scenario.
 */
export function makeLuhnInvalid(cardNumber) {
  const digits = String(cardNumber).replace(/\D/g, '');
  if (digits.length < 2) {
    throw new Error(`[E2E] makeLuhnInvalid: card number too short to mutate: "${cardNumber}"`);
  }
  const last = Number(digits[digits.length - 1]);
  const wrongLast = (last + 1) % 10;
  return digits.slice(0, -1) + wrongLast;
}

async function assertNotOrderReceived(page) {
  await expect(page).not.toHaveURL(ORDER_RECEIVED_REGEX);
  await expect(page.locator('.woocommerce-thankyou-order-received')).toHaveCount(0);
}

/**
 * Asserts the card-number field is flagged with the card-SPECIFIC error state:
 * the `mp-error` class on the container and the visible card-number helper. This
 * is what distinguishes a Luhn block from a generic "something went wrong" form
 * error — the buyer is told the CARD is invalid, not the form.
 */
async function assertCardSpecificError(page) {
  await expect(page.locator(CARD_NUMBER_CONTAINER)).toHaveClass(/mp-error/, { timeout: 15000 });

  // The card-specific helper is shown with a non-empty message ("invalid number"),
  // proving the buyer is told the CARD is invalid — not a generic form error.
  const helper = page.locator(CARD_NUMBER_HELPER);
  await expect(helper).toBeVisible({ timeout: 15000 });
  await expect(helper).not.toBeEmpty();
}

/**
 * Classic. A Luhn-invalid number (valid brand/length, bad checksum) must be
 * blocked with the card-specific error, and the order must never be placed. The
 * checkout must stay usable (no stuck overlay, place-order clickable again).
 */
export async function luhnBlockedClassic(page, url, user, card, form) {
  const invalidCard = { ...card, number: makeLuhnInvalid(card.number) };

  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, invalidCard, form);
  await placeOrder(page);

  await assertCardSpecificError(page);
  await assertNotOrderReceived(page);

  await expect(page.locator(CLASSIC_OVERLAY)).toHaveCount(0, { timeout: 30000 });
  await expect(page.locator('#place_order')).toBeEnabled({ timeout: 15000 });
}

/**
 * Blocks. Same invariant in the Blocks checkout: the Luhn-invalid number is
 * flagged card-specifically, the order is never placed and the Blocks place-order
 * button recovers to a clickable state (no infinite spinner).
 */
export async function luhnBlockedBlocks(page, url, user, card, form) {
  const invalidCard = { ...card, number: makeLuhnInvalid(card.number) };

  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, invalidCard, form);
  await placeOrder(page);

  await assertCardSpecificError(page);
  await assertNotOrderReceived(page);

  await expect(
    page.locator('.wc-block-components-checkout-place-order-button')
  ).toBeEnabled({ timeout: 15000 });
}

/**
 * Classic (installments preserved). A Luhn failure must NOT reset the already-derived fields: with a
 * valid BIN the SDK loads installments/issuer, and mutating only the last digit to
 * fail Luhn (code `invalid_value`) keeps them — only clearing the number
 * (`invalid_type`) resets them.
 *
 * Flow: fill the VALID card (installments load + an installment gets selected),
 * capture the selected installment, then edit only the last digit inside the card
 * iframe to break Luhn. We assert the card is now flagged AND the installments
 * selection survived unchanged.
 */
export async function luhnPreservesInstallmentsClassic(page, url, user, card, form) {
  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, card, form);

  const installmentsSelect = page.locator('#form-checkout__installments');
  await expect(installmentsSelect).toBeVisible({ timeout: 20000 });
  const selectedBefore = await installmentsSelect.inputValue();
  expect(selectedBefore, 'installments should be loaded/selected before breaking Luhn').not.toBe('');

  // Break Luhn by editing ONLY the last digit in the card-number iframe: go to the
  // end, delete the check digit and type a wrong one. This never empties the field,
  // so the SDK reports `invalid_value` (Luhn) — not `invalid_type` (empty) — which
  // is the path that must preserve the derived fields.
  const cardNumberInput = page
    .frameLocator('iframe[name="cardNumber"]')
    .locator('[name="cardNumber"]');
  await cardNumberInput.click();
  await cardNumberInput.press('End');
  await cardNumberInput.press('Backspace');

  const lastDigit = Number(String(card.number).replace(/\D/g, '').slice(-1));
  await cardNumberInput.pressSequentially(String((lastDigit + 1) % 10), { delay: 50 });

  // The card is now flagged as invalid...
  await assertCardSpecificError(page);

  // ...but the installments selection must have survived (Luhn does not reset it).
  await expect(installmentsSelect).toHaveValue(selectedBefore, { timeout: 5000 });
}
