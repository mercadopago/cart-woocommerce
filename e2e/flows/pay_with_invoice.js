export default async function (page, user, method = null) {
  await page.waitForLoadState();
  await page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-ticket').check();

  await page.waitForLoadState();

  const invoiceForm = {
    MLB: mlbInvoiceForm,
    MLA: mlaInvoiceForm,
    MLU: mluInvoiceForm,
  }[user.siteId] ?? defaultInvoiceForm;

  await invoiceForm({ page, user, method });

  await page.locator('.wc-block-components-checkout-place-order-button').click();
  await page.waitForLoadState();
}

async function mlbInvoiceForm({ page, user }) {
  await page.getByText('Boleto').click();

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

async function mlaInvoiceForm({ page, method }) {
  await page.getByText(method).click();
}

async function mluInvoiceForm({ page, user, method }) {
  await page.locator('#doc_type').selectOption(user.documentType);
  await page.locator('input[name="mercadopago_ticket\\[doc_number\\]"]').click();
  await page.locator('input[name="mercadopago_ticket\\[doc_number\\]"]').fill(user.document);
  await page.getByText(method).click();
}

async function defaultInvoiceForm({ page }) {
  await page.locator('.mp-input-table-list .mp-input-table-item').first().click();
}
