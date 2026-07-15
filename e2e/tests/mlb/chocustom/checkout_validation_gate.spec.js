import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/chocustom";
import {
  validationGateBlocksTest,
  validationGateInlineTest,
  infiniteLoadingRecoveryTest,
  infiniteLoadingRecoveryInlineTest,
} from "../../../flows/checkout_validation_gate";

/**
 * PSW-4057 — Server-side checkout pre-validation gate for the Custom Checkout
 * (card) Classic flow, run via wc_ajax_mp_validate_checkout before tokenization.
 *
 * Covers:
 *   H1 — happy path is not broken by the new gate (regression guard).
 *   C1 — a required WC field cleared -> gate returns valid:false -> blocked.
 *   C2 — a malformed value the CSS `required` check misses (bad email) ->
 *        validate_posted_data rejects it -> blocked.
 *   C3 — invalid/empty card -> checkout must recover with no stuck overlay,
 *        no order-received (guards the infinite-loading regression, whether the
 *        gate fails open or blocks).
 */

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, INVALID_CARD } = credit_card_scenarios;

// H1 — regression guard: the gate must let a fully valid checkout through.
test('H1: valid form and valid card pass the validation gate and reach order-received', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});

// C1 — a required field is cleared after being filled (mirrors a buyer who empties postcode right before paying).
// Classic: server gate (mp_validate_checkout) returns valid:false.
// Blocks: client-side validation renders the inline error before submission.
test('C1: clearing a required field (postcode) blocks checkout via the validation gate', async ({ page }) => {
  if (process.env.CHECKOUT === 'blocks') {
    await validationGateInlineTest(
      page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form,
      async (p) => { await p.locator('#shipping-postcode').fill(''); },
      '#validate-error-shipping_postcode'
    );
  } else {
    await validationGateBlocksTest(
      page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form,
      async (p) => { await p.locator('#billing_postcode').fill(''); }
    );
  }
});

// C2 — a malformed email passes the browser's `required` check but fails validation.
// Classic: WooCommerce validate_posted_data catches it server-side.
// Blocks: client-side validation renders the inline error before submission.
test('C2: a malformed email is rejected by the validation gate', async ({ page }) => {
  if (process.env.CHECKOUT === 'blocks') {
    await validationGateInlineTest(
      page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form,
      async (p) => { await p.locator('#email').fill('invalid-email-format'); },
      '#validate-error-billing_email'
    );
  } else {
    await validationGateBlocksTest(
      page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form,
      async (p) => { await p.locator('#billing_email').fill('invalid-email-format'); }
    );
  }
});

// C3 — invalid/empty card. The checkout must recover (no infinite loading) and
// never reach order-received. Same invariant in both modes; only the mechanism differs (classic waits on the server gate, blocks validates client-side).
test('C3: invalid card recovers without infinite loading and never reaches order-received', async ({ page }) => {
  if (process.env.CHECKOUT === 'blocks') {
    await infiniteLoadingRecoveryInlineTest(page, shop_url, guestUserMLB, INVALID_CARD.master, INVALID_CARD.form);
  } else {
    await infiniteLoadingRecoveryTest(page, shop_url, guestUserMLB, INVALID_CARD.master, INVALID_CARD.form);
  }
});
