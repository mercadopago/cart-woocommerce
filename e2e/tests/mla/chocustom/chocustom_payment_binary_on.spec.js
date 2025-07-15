import { test } from "@playwright/test";
import { mla } from "../../../data/meli_sites";
import { rejectedPaymentTest } from "../../../flows/chocustom";

const { shop_url, credit_card_scenarios, debit_card_scenarios, guestUserMLA } = mla;
const { PENDING: CREDIT_PENDING } = credit_card_scenarios;
const { PENDING: DEBIT_PENDING } = debit_card_scenarios;

test('Given guest user with master credit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUserMLA, CREDIT_PENDING.master, CREDIT_PENDING.form);
});

test('Given guest user with master debit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUserMLA, DEBIT_PENDING.elo, DEBIT_PENDING.form);
});
