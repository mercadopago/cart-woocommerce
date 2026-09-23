import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { emptyFieldsPaymentTest, rejectedPaymentTest, successfulPaymentTest } from "../../../flows/chocustom";

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = credit_card_scenarios;

test('test successful payment as guest with master, payment must be approved and success page must be shown', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
});

test('test successful payment as guest with amex, payment must be approved and success page must be shown', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.amex, APPROVED.form);
});

test('test pending payment as guest with master - binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLB, PENDING.master, PENDING.form);
});

test('test pending payment as guest with amex - binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMLB, PENDING.amex, PENDING.form);
});

test('test with filled card but other fields empty - master, it must show help info for fields card holder name, installments and document number', async ({ page }) => {
  await emptyFieldsPaymentTest(page, shop_url, guestUserMLB, EMPTY_FIELDS.master, EMPTY_FIELDS.form);
});

test('test payment rejected by other reasons - amex, payment must be rejected and decline message must be shown', async ({ page }) => {
  await rejectedPaymentTest(page, shop_url, guestUserMLB, REJECTED.amex, REJECTED.form);
});
