import { test, expect } from "@playwright/test";
import { mpe } from "../../../data/meli_sites";
import { fillStepsToCheckout } from "../../../flows/fill_steps_to_checkout";
import payWithYape from "../../../flows/mpe/pay_with_yape";

const{ url, ...user } = mpe;

test('Given Yape payment When using phone number 111111111 Should create payment with success', async ({page}) => {
  // 111111111 approved
  const psePhoneData = { otp: '123456', phoneNumber: '111111111' };
  await fillStepsToCheckout(page, url, user);
  await payWithYape(page, psePhoneData);

  await expect(page.locator('#main')).toHaveText(/Order received/i);
})

test('Given Yape payment When using phone number 111111112 Should not create payment', async ({page}) => {
  // 111111112 cc_rejected_call_for_authorize
  const psePhoneData = { otp: '123456', phoneNumber: '111111112' };
  await fillStepsToCheckout(page, url, user);
  await payWithYape(page, psePhoneData);

  await expect(page.locator('.wc-block-components-notice-banner__content')).toHaveText(/Yape declined your payment/i);
})

test('Given Yape payment When using phone number 111111113 Should not create payment', async ({page}) => {
  // 111111113 cc_rejected_insufficient_amount
  const psePhoneData = { otp: '123456', phoneNumber: '111111113' };
  await fillStepsToCheckout(page, url, user);
  await payWithYape(page, psePhoneData);

  await expect(page.locator('.wc-block-components-notice-banner__content')).toHaveText(/Yape declined your payment/i);
})

test('Given Yape payment When using phone number 111111114 Should not create payment', async ({page}) => {
  // 111111114 cc_rejected_other_reason
  const psePhoneData = { otp: '123456', phoneNumber: '111111114' };
  await fillStepsToCheckout(page, url, user);
  await payWithYape(page, psePhoneData);

  await expect(page.locator('.wc-block-components-notice-banner__content')).toHaveText(/Yape declined your payment/i);
})

test('Given Yape payment When using phone number 111111115 Should not create payment', async ({page}) => {
  // 111111115 cc_rejected_card_type_not_allowed
  const psePhoneData = { otp: '123456', phoneNumber: '111111115' };
  await fillStepsToCheckout(page, url, user);
  await payWithYape(page, psePhoneData);

  await expect(page.locator('.wc-block-components-notice-banner__content')).toHaveText(/Yape declined your payment/i);
})

test('Given Yape payment When using phone number 111111116 Should not create payment', async ({page}) => {
  // 111111116 cc_rejected_max_attempts
  const psePhoneData = { otp: '123456', phoneNumber: '111111116' };
  await fillStepsToCheckout(page, url, user);
  await payWithYape(page, psePhoneData);

  await expect(page.locator('.wc-block-components-notice-banner__content')).toHaveText(/Yape declined your payment/i);
})

test('Given Yape payment When using phone number 111111117 Should not create payment', async ({page}) => {
  // 111111117 cc_rejected_bad_filled_security_code
  const psePhoneData = { otp: '123456', phoneNumber: '111111117' };
  await fillStepsToCheckout(page, url, user);
  await payWithYape(page, psePhoneData);

  await expect(page.locator('.wc-block-components-notice-banner__content')).toHaveText(/Yape declined your payment/i);
})

test('Given Yape payment When using phone number 111111118 Should not create payment', async ({page}) => {
  // 111111118 cc_rejected_form_error
  const psePhoneData = { otp: '123456', phoneNumber: '111111118' };
  await fillStepsToCheckout(page, url, user);
  await payWithYape(page, psePhoneData);

  await expect(page.locator('.wc-block-components-notice-banner__content')).toHaveText(/Yape declined your payment/i);
})
