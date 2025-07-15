import { test } from "@playwright/test";
import { mlc } from "../../../data/meli_sites";
import { rejectedPaymentTest } from "../../../flows/chocustom";

const { shop_url, credit_card_scenarios, debit_card_scenarios, guestUser } = mlc;
const { PENDING: CREDIT_PENDING } = credit_card_scenarios;
const { PENDING: DEBIT_PENDING } = debit_card_scenarios;

test('Given guest user with master credit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUser, CREDIT_PENDING.master, CREDIT_PENDING.form);
});

test('Given guest user with visa debit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUser, DEBIT_PENDING.master, DEBIT_PENDING.form);
});
