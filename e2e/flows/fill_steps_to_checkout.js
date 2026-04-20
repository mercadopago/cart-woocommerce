export const fillStepsToCheckout = async function (page, url, user) {
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  await addProductsToCart(page);
  await fillBillingData(page, user);
}

async function addProductsToCart(page) {
  await page.locator('#main .ajax_add_to_cart').first().waitFor({ state: 'visible', timeout: 30000 });
  await page.locator('#main .ajax_add_to_cart').first().click();

  // Wait for AJAX add-to-cart to complete (the "View cart" link appears)
  await page.locator('#main .added_to_cart').waitFor({ state: 'visible', timeout: 10000 });
  await page.locator('#main .added_to_cart').click();

  await page.waitForLoadState();

  // Support both Classic and Blocks cart
  const classicProceed = page.locator('.checkout-button');
  const blocksProceed = page.locator('.wp-block-woocommerce-proceed-to-checkout-block a');

  if (await classicProceed.isVisible({ timeout: 5000 }).catch(() => false)) {
    await classicProceed.click();
  } else {
    await blocksProceed.click();
  }
}

async function fillBillingData(page, user) {
  await page.waitForLoadState();

  // Detect checkout mode: Classic uses #billing_first_name, Blocks uses #shipping-first_name or #email
  const isClassic = await page.locator('#billing_first_name').isVisible({ timeout: 5000 }).catch(() => false);

  if (isClassic) {
    await fillClassicBilling(page, user);
  } else {
    await fillBlocksBilling(page, user);
  }
}

async function fillClassicBilling(page, user) {
  await page.locator('#billing_first_name').fill(user.firstName);
  await page.locator('#billing_last_name').fill(user.lastName);
  await page.locator('#billing_country').selectOption(user.address.countryId);

  // Wait for WC AJAX update_order_review after country change
  // (hides/shows fields like postcode based on country locale)
  await page.waitForTimeout(1500);

  await page.locator('#billing_address_1').fill(user.address.street);
  await page.locator('#billing_city').fill(user.address.city);

  // State and postcode may be hidden depending on country (e.g. CL hides postcode)
  const stateField = page.locator('#billing_state');
  if (await stateField.isVisible({ timeout: 1000 }).catch(() => false)) {
    await stateField.selectOption(user.address.state);
  }

  const postcodeField = page.locator('#billing_postcode');
  if (await postcodeField.isVisible({ timeout: 1000 }).catch(() => false)) {
    await postcodeField.fill(user.address.zip);
  }

  await page.locator('#billing_phone').fill(user.phone || '11999999999');
  await page.locator('#billing_email').fill(user.email);

  // Extra Checkout Fields for Brazil (CPF/CNPJ + address number)
  const personType = page.locator('#billing_persontype');
  if (await personType.isVisible({ timeout: 1000 }).catch(() => false)) {
    await personType.selectOption('1');
    await page.waitForTimeout(500);
    await page.locator('#billing_cpf').fill(user.document || '');
  }
  const numberField = page.locator('#billing_number');
  if (await numberField.isVisible({ timeout: 1000 }).catch(() => false)) {
    await numberField.fill(user.address.number || '122');
  }

  // Wait for WC update_order_review AJAX to finish (updates shipping methods).
  // Re-fill postcode if visible — WC AJAX can clear it when recalculating.
  await page.waitForTimeout(2000);

  if (await postcodeField.isVisible({ timeout: 1000 }).catch(() => false)) {
    const currentValue = await postcodeField.inputValue();
    if (!currentValue && user.address.zip) {
      await postcodeField.fill(user.address.zip);
      await page.waitForTimeout(1000);
    }
  }
}

async function fillBlocksBilling(page, user) {
  const prefix = await page.locator('#shipping-first_name').isVisible({ timeout: 3000 })
    .then(() => 'shipping')
    .catch(() => 'billing');

  await page.waitForTimeout(2000);
  await page.locator('#email').fill(user.email);
  await page.locator(`#${prefix}-first_name`).fill(user.firstName);
  await page.locator(`#${prefix}-last_name`).fill(user.lastName);
  await page.waitForTimeout(400);

  await page.locator(`#${prefix}-country`).selectOption(user.address.countryId);
  await page.waitForTimeout(1500);
  await page.locator(`#${prefix}-address_1`).fill(user.address.street);
  await page.locator(`#${prefix}-city`).fill(user.address.city);

  const stateField = page.locator(`#${prefix}-state`);
  if (await stateField.isVisible({ timeout: 1000 }).catch(() => false)) {
    await stateField.selectOption(user.address.state);
  }

  const postcodeField = page.locator(`#${prefix}-postcode`);
  if (await postcodeField.isVisible({ timeout: 1000 }).catch(() => false)) {
    await postcodeField.fill(user.address.zip);
  }

  const phone = user.phone || '11999999999';
  await page.locator(`#${prefix}-phone`).fill(phone);
  await page.waitForTimeout(400);
}
