import { test, expect } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithYape from "../../../flows/yape";
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, guestUser } = mpe;

// Yape surfaces the result (success page / rejection notice) after an external call to the MP sandbox whose timing is unstable and slower in Blocks. We wait generously and extend the per-test timeout so the wait never outlives the test budget (mirrors P1).
const YAPE_TIMEOUT = 60000;
const YAPE_TEST_TIMEOUT = 120000;

test.beforeEach(() => {
  skipIfNotSite(test, 'MPE');
});

async function makePayment(page, form) {
  await fillStepsToCheckout(page, shop_url, guestUser);
  await payWithYape(page, form);
}

async function rejectedPaymentTest(page, form) {
  test.setTimeout(YAPE_TEST_TIMEOUT);
  await makePayment(page, form);
  // The rejection surfaces as a WC error notice (classic), a Blocks store notice, or an
  // ARIA alert. Match all three (Adobe asserts on role="alert") so a class-name change in
  // either checkout doesn't silently miss the notice.
  await expect(
    page.locator('.woocommerce-error, .wc-block-components-notice-banner.is-error, [role="alert"].is-error').first()
  ).toBeVisible({ timeout: YAPE_TIMEOUT });
}

test('Given Yape payment When using phone number 111111111 Should create payment with success', async ({ page }) => {
  test.setTimeout(YAPE_TEST_TIMEOUT);
  await makePayment(page, { phoneNumber: '111111111' });
  await page.waitForURL(/order-received/, { waitUntil: 'domcontentloaded', timeout: YAPE_TIMEOUT });
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible({ timeout: YAPE_TIMEOUT });
})

test('Given Yape payment When using phone number 111111112 Should not create payment', async ({ page }) => {
  // 111111112 cc_rejected_call_for_authorize
  await rejectedPaymentTest(page, { phoneNumber: '111111112' });
})

test('Given Yape payment When using phone number 111111113 Should not create payment', async ({ page }) => {
  // 111111113 cc_rejected_insufficient_amount
  await rejectedPaymentTest(page, { phoneNumber: '111111113' });
})

test('Given Yape payment When using phone number 111111114 Should not create payment', async ({ page }) => {
  // 111111114 cc_rejected_other_reason
  await rejectedPaymentTest(page, { phoneNumber: '111111114' });
})

test('Given Yape payment When using phone number 111111115 Should not create payment', async ({ page }) => {
  // 111111115 cc_rejected_card_type_not_allowed
  await rejectedPaymentTest(page, { phoneNumber: '111111115' });
})

test('Given Yape payment When using phone number 111111116 Should not create payment', async ({ page }) => {
  // 111111116 cc_rejected_max_attempts
  await rejectedPaymentTest(page, { phoneNumber: '111111116' });
})

test('Given Yape payment When using phone number 111111117 Should not create payment', async ({ page }) => {
  // 111111117 cc_rejected_bad_filled_security_code
  await rejectedPaymentTest(page, { phoneNumber: '111111117' });
})

test('Given Yape payment When using phone number 111111118 Should not create payment', async ({ page }) => {
  // 111111118 cc_rejected_form_error
  await rejectedPaymentTest(page, { phoneNumber: '111111118' });
})
