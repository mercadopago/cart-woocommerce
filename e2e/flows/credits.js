import { expect } from "@playwright/test";
import { fillStepsToCheckout } from "./fill_steps_to_checkout";

export async function successfulPaymentTest(page, url, user) {
  await fillStepsToCheckout(page, url, user);

  await payWithCredits(page, user);

  await page.waitForURL('**/congrats/**');
  await page.waitForLoadState();
  await page.locator('.cow-button-back-to-site').click();
  await page.waitForURL('**/order-received/**');
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible();
}

async function payWithCredits(page, user) {
  await page.waitForLoadState();
  await page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-credits').check();

  await page.waitForTimeout(2000);
  await page.locator('.wc-block-components-checkout-place-order-button').click();
  await page.waitForLoadState();

  await page.waitForURL('**/checkout/v1/payment/**');
  await page.waitForURL('**/login/**');

  await page.getByTestId('user_id').fill(user.mpUserAccount);
  await page.locator('.login-form .login-form__submit:first-child').click();

  await page.getByTestId('password').fill(user.mpPasswordAccount);
  await page.getByTestId('action-complete').click();
  await page.waitForTimeout(1000);

  fillTwoFactor(page, user.twoFactor, '#sms', '.validation-form__button--verify-code');

  await page.waitForLoadState();
  await page.locator('#installments_select_credits-trigger').click();
  await page.waitForLoadState();
  await page.click('#installments_select_credits-menu-list li:first-child');
  await page.waitForLoadState();
  await page.click('#pay');
  await page.waitForLoadState();
  await page.waitForTimeout(5000);

  fillTwoFactor(page, user.twoFactor, '#channel-sms', '#enter-code-submit');
}

async function fillTwoFactor(page, twoFactor, smsLocator, submitButtonLocator) {
  if (!await page.locator(smsLocator).isVisible()) {
    return;
  }

  await page.locator(smsLocator).click();
  await page.waitForLoadState();
  await page.waitForTimeout(1000);

  const chars = twoFactor.split('');
  for (const index in chars) {
    const input = await page.locator('.andes-code-input.input-suffix-code input').nth(index);
    await input.click();
    await page.waitForTimeout(1000);
    await input.fill(chars[index]);
    await page.waitForTimeout(1000);
  }

  await page.locator(submitButtonLocator).click();
}
