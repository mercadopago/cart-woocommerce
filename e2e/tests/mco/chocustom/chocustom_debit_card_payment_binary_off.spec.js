import { test } from "@playwright/test";
import { mco } from "../../../data/meli_sites";
import { rejectedPaymentTest, successfulPaymentTest, emptyFieldsPaymentTest } from "../../../flows/chocustom";

const { shop_url, debit_card_scenarios, guestUserMCO } = mco;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = debit_card_scenarios;

test('Given guest user with visa card, When payment is approved, Should show success page', async ({page}) => {
  await successfulPaymentTest(page, shop_url, guestUserMCO, APPROVED.visa, APPROVED.form);
});

test('Given guest user with visa card, When payment is pending, Should show success page', async ({page}) => {
  await successfulPaymentTest(page, shop_url, guestUserMCO, PENDING.visa, PENDING.form);
});

test('Given guest user with visa card, When other fields are empty, Should show help info for card holder name, installments, and document number', async ({page}) => {
  await emptyFieldsPaymentTest(page, shop_url, guestUserMCO, EMPTY_FIELDS.visa, EMPTY_FIELDS.form);
});

test('Given guest user with visa card, When payment is rejected, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUserMCO, REJECTED.visa, REJECTED.form);
});
