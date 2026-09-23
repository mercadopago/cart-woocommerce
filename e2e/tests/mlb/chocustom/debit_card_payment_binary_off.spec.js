import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { rejectedPaymentTest, successfulPaymentTest, emptyFieldsPaymentTest } from "../../../flows/chocustom";

const { shop_url, debit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = debit_card_scenarios;

// SKIPPED: debit cards are NOT available in MLB's online checkout (Cho API / Custom).
// Confirmed by MeLi Payment Methods IT support (#payment-methods-it-support, 2026-05-06):
// "para MLB no tenemos disponible el medio de pago Débito en los checkouts" — the Payments
// API returns `not_result_by_params` ("payment method not allowed for this collector") for
// debit BINs in MLB. ELO/debelo debit in MLB exists only for InStore / Tap-to-Pay
// (Point/Apple MPOC), not the web checkout the plugin uses. These scenarios are unsupported
// by the platform (not plugin/test bugs). Empty-field validation is already covered by the
// MLB credit spec. Re-enable only if MP enables debit for MLB online checkouts.
test.describe.skip('MLB debit card (unsupported in MLB online checkout)', () => {
  test('test successful payment as guest with elo, payment must be approved and success page must be shown', async ({ page }) => {
    await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.elo, APPROVED.form);
  });

  test('test pending payment as guest with elo, payment must be approved and success page must be shown', async ({ page }) => {
    await successfulPaymentTest(page, shop_url, guestUserMLB, PENDING.elo, PENDING.form);
  });

  test('test with filled card but other fields empty - elo, it must show help info for fields card holder name, installments and document number', async ({ page }) => {
    await emptyFieldsPaymentTest(page, shop_url, guestUserMLB, EMPTY_FIELDS.elo, EMPTY_FIELDS.form);
  });

  test('test payment rejected by other reasons - elo, payment must be rejected and decline message must be shown', async ({ page }) => {
    await rejectedPaymentTest(page, shop_url, guestUserMLB, REJECTED.elo, REJECTED.form);
  });
});
