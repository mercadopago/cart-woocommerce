import { fillStepsToCheckout } from "./fill_steps_to_checkout";
import { expect } from "@playwright/test";

export async function successfulPaymentTest(page, url, user, method = null) {
  await fillStepsToCheckout(page, url, user);
  await makePayment(page, user, method);

  await page.waitForURL(/order-received/, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible({ timeout: 10000 });
}

export async function rejectedPaymentTest(page, url, user, method = null) {
  await fillStepsToCheckout(page, url, user);
  await makePayment(page, user, method);

  await expect(page.locator('.woocommerce-error, .wc-block-store-notice.wc-block-components-notice-banner.is-error')).toBeVisible({ timeout: 30000 });
}

async function makePayment(page, user, method = null) {
  await page.waitForLoadState();

  // Select ticket gateway — supports both Classic and Blocks
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-ticket');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-ticket');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-ticket"]').click();
    await page.waitForTimeout(2000);
  } else {
    await blocksRadio.check();
  }

  await page.waitForLoadState();

  const invoiceForm = {
    MLB: mlbInvoiceForm,
    MLA: mlaInvoiceForm,
    MLU: mluInvoiceForm,
  }[user.siteId] ?? defaultInvoiceForm;

  await invoiceForm({ page, user, method });

  // Click place order — supports both Classic and Blocks
  const classicPlaceOrder = page.locator('#place_order');
  const blocksPlaceOrder = page.locator('.wc-block-components-checkout-place-order-button');

  if (await classicPlaceOrder.isVisible({ timeout: 3000 }).catch(() => false)) {
    await classicPlaceOrder.click();
  } else {
    await blocksPlaceOrder.click();
  }

  await page.waitForLoadState();
}

async function mlbInvoiceForm({ page, user }) {
  // When multiple payment methods exist, click Boleto in the list.
  // When only one method exists, the template renders a hidden input and no list.
  const boletoItem = page.getByText('Boleto', { exact: true });
  if (await boletoItem.isVisible({ timeout: 2000 }).catch(() => false)) {
    await boletoItem.click();
  }
  await page.waitForTimeout(1000);

  // document (use ticket-specific IDs to avoid conflicts with custom checkout fields)
  await page.locator('select[name="mercadopago_ticket\\[doc_type\\]"]').selectOption(user.documentType);
  await page.locator('input[name="mercadopago_ticket\\[doc_number\\]"]').fill(user.document);

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
  // Ticket #doc_type uses uppercase values (OTRO) while custom checkout uses capitalized (Otro).
  // Try exact value first, then uppercase fallback.
  const docTypeSelect = page.locator('#doc_type');
  try {
    await docTypeSelect.selectOption(user.documentType, { timeout: 2000 });
  } catch {
    await docTypeSelect.selectOption(user.documentType.toUpperCase());
  }
  await page.locator('input[name="mercadopago_ticket\\[doc_number\\]"]').click();
  await page.locator('input[name="mercadopago_ticket\\[doc_number\\]"]').fill(user.document);
  await page.getByText(method).click();
}

async function defaultInvoiceForm({ page }) {
  // When multiple methods exist, click the first item in the list.
  // When only one method exists, the template renders a hidden input (nothing to click).
  const item = page.locator('.mp-input-table-list .mp-input-table-item').first();
  if (await item.isVisible({ timeout: 2000 }).catch(() => false)) {
    await item.click();
  }
}
