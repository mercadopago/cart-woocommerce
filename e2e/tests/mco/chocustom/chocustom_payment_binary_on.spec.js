import { test } from "@playwright/test";
import { mco } from "../../../data/meli_sites";
import { rejectedPaymentTest } from "../../../flows/chocustom";

const { shop_url, credit_card_scenarios, debit_card_scenarios, guestUserMCO } = mco;
const { PENDING: CREDIT_PENDING } = credit_card_scenarios;
const { PENDING: DEBIT_PENDING } = debit_card_scenarios;

test('Given guest user with master credit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUserMCO, CREDIT_PENDING.masterMCO, CREDIT_PENDING.form);
});

test('Given guest user with visa debit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUserMCO, DEBIT_PENDING.visaMCO, DEBIT_PENDING.form);
});
