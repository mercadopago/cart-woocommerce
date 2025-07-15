import { test } from "@playwright/test";
import { mla } from "../../../data/meli_sites";
import { rejectedPaymentTest, successfulPaymentTest, emptyFieldsPaymentTest } from "../../../flows/chocustom";

const { shop_url, credit_card_scenarios, guestUserMLA } = mla;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = credit_card_scenarios;

test('Given guest user with master card, When payment is approved, Should show success page', async ({page}) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA, APPROVED.master, APPROVED.form);
});

test('Given guest user with amex card, When payment is approved, Should show success page', async ({page}) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA, APPROVED.amex, APPROVED.form);
});

test('Given guest user with master card, When payment is pending and binary is off, Should show success page', async ({page}) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA, PENDING.master, PENDING.form);
});

test('Given guest user with amex card, When payment is pending and binary is off, Should show success page', async ({page}) => {
  await successfulPaymentTest(page, shop_url, guestUserMLA, PENDING.amex, PENDING.form);
});

test('Given guest user with master card, When other fields are empty, Should show help info for card holder name, installments, and document number', async ({page}) => {
  await emptyFieldsPaymentTest(page, shop_url, guestUserMLA, EMPTY_FIELDS.master, EMPTY_FIELDS.form);
});

test('Given guest user with amex card, When payment is rejected, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUserMLA, REJECTED.amex, REJECTED.form);
});
