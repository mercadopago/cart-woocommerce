import { test, expect } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithYape from "../../../flows/yape";

const { shop_url, guestUser } = mpe;

async function makePayment(page, form) {
  await fillStepsToCheckout(page, shop_url, guestUser);
  await payWithYape(page, form);
}

async function rejectedPaymentTest(page, form) {
  await makePayment(page, form);
  await expect(page.locator('.wc-block-store-notice.wc-block-components-notice-banner.is-error')).toBeVisible();
}

test('Given Yape payment When using phone number 111111111 Should create payment with success', async ({ page }) => {
  await makePayment(page, { phoneNumber: '111111111' });
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible();
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
