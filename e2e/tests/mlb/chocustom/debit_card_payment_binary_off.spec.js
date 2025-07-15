import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { rejectedPaymentTest, successfulPaymentTest, emptyFieldsPaymentTest } from "../../../flows/chocustom";

const { shop_url, debit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = debit_card_scenarios;

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
