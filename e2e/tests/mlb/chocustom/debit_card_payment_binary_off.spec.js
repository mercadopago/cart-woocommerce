import { test, expect } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithCard from "../../../flows/pay_with_card";

const{ url, debit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = debit_card_scenarios;

test('test successful payment as guest with elo, payment must be approved and success page must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithCard(page, APPROVED.elo, APPROVED.formMLB);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('test pending payment as guest with elo, payment must be approved and success page must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithCard(page, PENDING.elo, PENDING.formMLB);
  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('test with filled card but other fields empty - elo, it must show help info for fields card holder name, installments and document number', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithCard(page, EMPTY_FIELDS.elo, EMPTY_FIELDS.formMLB);

  const cardHolderHelper = page.locator('#mp-card-holder-div input-helper');
  const installmentsHelper = page.locator('#mp-installments-helper');
  const docNumberHelper = page.locator('#mp-doc-number-helper');

  expect(await cardHolderHelper.evaluate(element => {
    return window.getComputedStyle(element).display;
  })).not.toBe('none');

  expect(await installmentsHelper.evaluate(element => {
    return window.getComputedStyle(element).display;
  })).not.toBe('none');

  expect(await docNumberHelper.evaluate(element => {
    return window.getComputedStyle(element).display;
  })).not.toBe('none');

});

test('test payment rejected by other reasons - elo, payment must be rejected and decline message must be shown', async ({page}) => {
  await fillStepsToCheckout(page, url, guestUserMLB);
  await payWithCard(page, REJECTED.elo, REJECTED.formMLB);

  await expect(page.locator('div.wc-block-components-notices .wc-block-store-notice')).toHaveText(/The card issuing bank declined the payment/i);
});
