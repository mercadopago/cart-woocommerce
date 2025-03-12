export default async function(page, user) {
  const siteId = shouldUseGenericForm(user.siteId) ? 'others' : user.siteId;
  const countryForm = {
    MLB: brazillianInvoceForm,
    others: genericInvoiceForm
  }

  await page.waitForTimeout(3000);

  await countryForm[siteId](page, user);
}

function shouldUseGenericForm(siteId) {
  return siteId !== 'MLB';
}

async function brazillianInvoceForm(page, user) {
  await page.locator('div').filter({ hasText: /^Boleto$/ }).nth(1).click();

  // document
  await page.locator('.mp-document-select').selectOption(user.documentType);
  await page.locator('.mp-document').fill(user.document);

  // address
  await page.locator('#form-checkout__address_zip_code').fill(user.address.zip);
  await page.locator('#form-checkout__address_federal_unit').selectOption(user.address.state);
  await page.locator('#form-checkout__address_city').fill(user.address.city);
  await page.locator('#form-checkout__address_neighborhood').fill(user.address.neighborhood);
  await page.locator('#form-checkout__address_street_name').fill(user.address.street);
  await page.locator('#form-checkout__address_street_number').fill(user.address.number);
  await page.locator('#form-checkout__address_complement').fill(user.address.complement);
}

async function genericInvoiceForm(page) {
  await page.locator('.mp-input-table-list .mp-input-table-item').first().click();
}