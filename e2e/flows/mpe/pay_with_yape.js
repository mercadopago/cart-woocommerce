export default async function (page, {otp, phoneNumber}) {
  const otpAsList = otp.split('');

  await page.getByLabel('Yape').check();

  await page.locator('.mp-yape-input').fill(phoneNumber);

  for (let i = 0; i < otpAsList.length; i++) {
    await page.locator('input.mp-yape-code-input').nth(i).fill(otpAsList[i]);

    await page.waitForTimeout(100);
  }

  await page.waitForTimeout(2000);

  await page.locator('.wc-block-components-button').click();
}
