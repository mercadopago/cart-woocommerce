export default async function(page, user) {
  await page.waitForLoadState();

  // user
  await page.waitForTimeout(2000);
  await page.locator('#email').fill(user.email);
  await page.locator('#billing-first_name').fill(user.firstName);
  await page.locator('#billing-last_name').fill(user.lastName);
  await page.waitForTimeout(400);

  // address
  await page.locator('#billing-country').selectOption(user.address.countryId);
  await page.locator('#billing-address_1').fill(user.address.street);
  await page.locator('#billing-city').fill(user.address.city);
  await page.locator('#billing-state').selectOption(user.address.state);
  await page.locator('#billing-postcode').fill(user.address.zip);

  // phone
  await page.locator('#billing-phone').fill(user.phone);
  await page.waitForTimeout(400);
}
