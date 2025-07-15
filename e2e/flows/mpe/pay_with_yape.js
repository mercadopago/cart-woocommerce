export default async function (page, { otp = '123456', phoneNumber }) {
  await page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-yape').check();

  await page.locator('.mp-yape-input').fill(phoneNumber);

  const otpChars = otp.split('');

  for (const index in otpChars) {
    await page.locator('input.mp-yape-code-input').nth(index).fill(otpChars[index]);

    await page.waitForTimeout(100);
  }

  await page.waitForTimeout(2000);

  await page.locator('.wc-block-components-checkout-place-order-button').click();
}
