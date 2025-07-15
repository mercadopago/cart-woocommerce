import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { rejectedPaymentTest } from "../../../flows/chocustom";

const{ shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { PENDING } = credit_card_scenarios;

test('test pending payment as guest with master, payment must be rejected and decline message must be shown', async ({page}) => {
  await rejectedPaymentTest(page, shop_url, guestUserMLB, PENDING.master, PENDING.form);
});
