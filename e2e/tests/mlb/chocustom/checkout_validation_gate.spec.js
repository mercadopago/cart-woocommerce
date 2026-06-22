import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { successfulPaymentTest } from "../../../flows/chocustom";
import {
  validationGateBlocksTest,
  infiniteLoadingRecoveryTest,
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

// C1 — a required WC field is cleared after being filled (mirrors a buyer who
// empties postcode right before paying). The gate must return valid:false.
test('C1: clearing a required field (postcode) blocks checkout via the validation gate', async ({ page }) => {
  await validationGateBlocksTest(
    page,
    shop_url,
    guestUserMLB,
    APPROVED.master,
    APPROVED.form,
    async (p) => {
      await p.locator('#billing_postcode').fill('');
    }
  );
});

// C2 — a malformed email passes the browser's `required` check but fails
// WooCommerce's validate_posted_data. The gate must catch it server-side.
test('C2: a malformed email is rejected by the validation gate', async ({ page }) => {
  await validationGateBlocksTest(
    page,
    shop_url,
    guestUserMLB,
    APPROVED.master,
    APPROVED.form,
    async (p) => {
      await p.locator('#billing_email').fill('invalid-email-format');
    }
  );
});

// C3 — invalid/empty card. Regardless of the gate verdict (fail open on a valid
// WC form, or block when a card-related required field is empty), the checkout
// must recover with no stuck overlay and must not reach order-received. Guards
// the infinite-loading regression this feature fixed.
test('C3: invalid card recovers without infinite loading and never reaches order-received', async ({ page }) => {
  await infiniteLoadingRecoveryTest(page, shop_url, guestUserMLB, INVALID_CARD.master, INVALID_CARD.form);
});
