import { test, expect } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import addProductToCart from "../../../flows/add_product_to_cart";
import fillBillingData from "../../../flows/fill_billing_data";
import payWithCard from "../../../flows/pay_with_card";

const{ url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, PENDING, REJECTED, EMPTY_FIELDS } = credit_card_scenarios;

async function fillStepsToCheckout(page, url, user) {
  await page.goto(url);
  await addProductToCart(page);
  await fillBillingData(page, user);
}

test('test successful payment as guest with master', async ({page}) => {
    await fillStepsToCheckout(page, url, guestUserMLB);

    await payWithCard(page, APPROVED.master, APPROVED.form);

    await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('test successful payment as guest with amex', async ({page}) => {

  await fillStepsToCheckout(page, url, guestUserMLB);

  await payWithCard(page, APPROVED.amex, APPROVED.form);

  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('test pending payment as guest with master', async ({page}) => {

  await fillStepsToCheckout(page, url, guestUserMLB);

  await payWithCard(page, PENDING.master, PENDING.form);

  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('test pending payment as guest with amex', async ({page}) => {

  await fillStepsToCheckout(page, url, guestUserMLB);

  await payWithCard(page, APPROVED.amex, APPROVED.form);

  await expect(page.locator('#main')).toHaveText(/Order received/i);
});

test('test with filled card but other fields empty - master', async ({page}) => {

  await fillStepsToCheckout(page, url, guestUserMLB);

  await payWithCard(page, EMPTY_FIELDS.master, EMPTY_FIELDS.form);

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

test('test payment rejected by other reasons - amex', async ({page}) => {

  await fillStepsToCheckout(page, url, guestUserMLB);

  await payWithCard(page, REJECTED.amex, REJECTED.form);

  await expect(page.locator('div.wc-block-components-notices .wc-block-store-notice')).toHaveText(/The card issuing bank declined the payment/i);
});
