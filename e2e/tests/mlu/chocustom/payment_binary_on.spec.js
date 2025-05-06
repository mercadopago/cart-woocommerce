import { test, expect } from "@playwright/test";
import { mlu } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithCard from "../../../flows/pay_with_card";

const { url, credit_card_scenarios, debit_card_scenarios, guestUser } = mlu;
const { PENDING: CREDIT_PENDING } = credit_card_scenarios;
const { PENDING: DEBIT_PENDING } = debit_card_scenarios;

test('Given guest user with master credit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, CREDIT_PENDING.master, CREDIT_PENDING.form);
  await page.waitForTimeout(2000);
  await expect(page.locator('div.wc-block-components-notices .wc-block-store-notice')).toHaveText(/The card issuing bank declined the payment/i);
});

test('Given guest user with amex credit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, CREDIT_PENDING.amex, CREDIT_PENDING.form);
  await page.waitForTimeout(2000);
  await expect(page.locator('div.wc-block-components-notices .wc-block-store-notice')).toHaveText(/The card issuing bank declined the payment/i);
});

test('Given guest user with master debit card, When payment is pending and binary is on, Should show decline message', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, DEBIT_PENDING.master, DEBIT_PENDING.form);
  await page.waitForTimeout(2000);
  await expect(page.locator('div.wc-block-components-notices .wc-block-store-notice')).toHaveText(/The card issuing bank declined the payment/i);
});
