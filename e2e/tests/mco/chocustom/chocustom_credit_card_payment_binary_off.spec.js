import { test } from "@playwright/test";
import { mco } from "../../../data/meli_sites";
import { rejectedPaymentTest, successfulPaymentTest } from "../../../flows/chocustom";

const { shop_url, credit_card_scenarios, guestUserMCO } = mco;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = credit_card_scenarios;

test('Given guest user with master card, When payment is approved, Should show success page', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMCO, APPROVED.masterMCO, APPROVED.form);
});

test('Given guest user with amex card, When payment is approved, Should show success page', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMCO, APPROVED.amexMCO, APPROVED.form);
});

test('Given guest user with master card, When payment is pending and binary is off, Should show success page', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMCO, PENDING.masterMCO, PENDING.form);
});

test('Given guest user with amex card, When payment is pending and binary is off, Should show success page', async ({ page }) => {
  await successfulPaymentTest(page, shop_url, guestUserMCO, PENDING.amexMCO, PENDING.form);
});

test('Given guest user with master card, When other fields are empty, Should show help info for card holder name, installments, and document number', async ({ page }) => {
  await emptyFieldsPaymentTest(page, shop_url, guestUserMCO, EMPTY_FIELDS.masterMCO, EMPTY_FIELDS.form);
});

test('Given guest user with amex card, When payment is rejected, Should show decline message', async ({ page }) => {
  await rejectedPaymentTest(page, shop_url, guestUserMCO, REJECTED.amexMCO, REJECTED.form);
});
