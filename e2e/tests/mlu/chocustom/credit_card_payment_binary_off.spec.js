import { test, expect } from "@playwright/test";
import { mlu } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithCard from "../../../flows/pay_with_card";

const { url, guestUser, credit_card_scenarios } = mlu;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = credit_card_scenarios;

test('Given guest user with master card, When payment is approved, Should show success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, APPROVED.master, APPROVED.form);
  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('Given guest user with visa card, When payment is approved, Should show success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, APPROVED.visa, APPROVED.form);
  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('Given guest user with amex card, When payment is approved, Should show success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, APPROVED.amex, APPROVED.form);
  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('Given guest user with master card, When payment is pending and binary is off, Should show success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, PENDING.master, PENDING.form);
  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('Given guest user with visa card, When payment is pending and binary is off, Should show success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, PENDING.visa, PENDING.form);
  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('Given guest user with amex card, When payment is pending and binary is off, Should show success page', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, PENDING.amex, PENDING.form);
  await page.waitForTimeout(3000);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('Given guest user with master card, When other fields are empty, Should show help info for card holder name, installments, and document number', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, EMPTY_FIELDS.master, EMPTY_FIELDS.form);
  await page.waitForTimeout(2000);

  const cardHolderHelper = page.locator('#mp-card-holder-div input-helper');
  const installmentsHelper = page.locator('#mp-installments-helper');

  expect(await cardHolderHelper.evaluate(element => {
    return window.getComputedStyle(element).display;
  })).not.toBe('none');

  expect(await installmentsHelper.evaluate(element => {
    return window.getComputedStyle(element).display;
  })).not.toBe('none');
});

test('Given guest user with master card, When payment is rejected, Should show decline message', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, REJECTED.master, REJECTED.form);
  await page.waitForTimeout(2000);
  await expect(page.locator('div.wc-block-components-notices .wc-block-store-notice')).toHaveText(/The card issuing bank declined the payment/i);
});

test('Given guest user with visa card, When payment is rejected, Should show decline message', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, REJECTED.visa, REJECTED.form);
  await page.waitForTimeout(2000);
  await expect(page.locator('div.wc-block-components-notices .wc-block-store-notice')).toHaveText(/The card issuing bank declined the payment/i);
});

test('Given guest user with amex card, When payment is rejected, Should show decline message', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUser);
  await payWithCard(page, REJECTED.amex, REJECTED.form);
  await page.waitForTimeout(2000);
  await expect(page.locator('div.wc-block-components-notices .wc-block-store-notice')).toHaveText(/The card issuing bank declined the payment/i);
});
