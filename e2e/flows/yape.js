import { placeOrder } from "./place_order.helper";

export default async function (page, { otp = '123456', phoneNumber }) {
  // Select Yape — supports both Classic and Blocks
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-yape');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-yape');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-yape"]').click();
  } else {
    await blocksRadio.check();
  }

  await page.waitForTimeout(1000);
  await page.locator('.mp-yape-input').fill(phoneNumber);

  const otpChars = otp.split('');

  for (const index in otpChars) {
    await page.locator('input.mp-yape-code-input').nth(index).fill(otpChars[index]);
    await page.waitForTimeout(100);
  }

  await page.waitForTimeout(2000);

  // Click place order (Classic single-click / Blocks two-phase submit)
  await placeOrder(page);
}