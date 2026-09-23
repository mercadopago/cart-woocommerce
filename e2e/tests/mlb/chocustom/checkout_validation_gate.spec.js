import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/chocustom";
import {
  invalidFormBlockedClassic,
  invalidFormBlockedBlocks,
  invalidCardRecoversClassic,
  invalidCardRecoversBlocks,
} from "../../../flows/checkout_validation_gate";

/**
 * Invalid-input handling in the Custom Checkout (card).
 *
 * PSW-4344 removed the MP server-side pre-validation gate
 * (wc_ajax_mp_validate_checkout); the card now tokenizes directly and the
 * checkout relies on WooCommerce's native submit validation.
 *
 * Covers:
 *   H1 — happy path still reaches order-received (regression guard).
 *   C1 — a required WC field cleared -> checkout blocked (native validation in
 *        Classic; inline error in Blocks).
 *   C2 — a malformed value the CSS `required` check misses (bad email) ->
 *        checkout blocked.
 *   C3 — invalid/empty card -> checkout recovers with no stuck overlay and never
 *        reaches order-received.
 */

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, INVALID_CARD } = credit_card_scenarios;

// H1 — regression guard: a fully valid checkout still goes through.
test('H1: valid form and valid card reach order-received', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});

// C1 — a required field is cleared after being filled (mirrors a buyer who empties postcode right before paying).
// Classic: WooCommerce native validation rejects the submission.
// Blocks: client-side validation renders the inline error before submission.
test('C1: clearing a required field (postcode) blocks checkout', async ({ page }) => {
  if (process.env.CHECKOUT === 'blocks') {
    await invalidFormBlockedBlocks(
      page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form,
      async (p) => { await p.locator('#shipping-postcode').fill(''); },
      '#validate-error-shipping_postcode'
    );
  } else {
    await invalidFormBlockedClassic(
      page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form,
      async (p) => { await p.locator('#billing_postcode').fill(''); }
    );
  }
});

// C2 — a malformed email passes the browser's `required` check but fails validation.
// Classic: WooCommerce validate_posted_data catches it server-side.
// Blocks: client-side validation renders the inline error before submission.
test('C2: a malformed email blocks checkout', async ({ page }) => {
  if (process.env.CHECKOUT === 'blocks') {
    await invalidFormBlockedBlocks(
      page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form,
      async (p) => { await p.locator('#email').fill('invalid-email-format'); },
      '#validate-error-billing_email'
    );
  } else {
    await invalidFormBlockedClassic(
      page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form,
      async (p) => { await p.locator('#billing_email').fill('invalid-email-format'); }
    );
  }
});

// C3 — invalid/empty card. The checkout must recover (no infinite loading) and
// never reach order-received. Same invariant in both modes; only the mechanism differs.
test('C3: invalid card recovers without infinite loading and never reaches order-received', async ({ page }) => {
  if (process.env.CHECKOUT === 'blocks') {
    await invalidCardRecoversBlocks(page, shop_url, guestUserMLB, INVALID_CARD.master, INVALID_CARD.form);
  } else {
    await invalidCardRecoversClassic(page, shop_url, guestUserMLB, INVALID_CARD.master, INVALID_CARD.form);
  }
});
