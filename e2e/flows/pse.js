import { placeOrder } from "./place_order.helper";

export default async function (page, user) {
  // Select PSE — supports both Classic and Blocks
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-pse');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-pse');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-pse"]').click();
  } else {
    await blocksRadio.check();
  }

  await page.waitForTimeout(1000);

  // PSE requires three fields (the gateway's isCheckoutValid rejects if any is empty):
  // person_type, document and bank (financial institution). The select is reached
  // differently per checkout: Blocks renders <select id="mercadopago_pse[field]">,
  // Classic renders a Narciso <input-select name="mercadopago_pse[field]"> with an
  // inner <select>. selectPse handles both.
  const selectPse = async (field, option) => {
    const byId = page.locator(`#mercadopago_pse\\[${field}\\]`);
    if (await byId.count()) {
      await byId.selectOption(option);
    } else {
      await page.locator(`input-select[name="mercadopago_pse[${field}]"] select`).selectOption(option);
    }
  };

  await selectPse('person_type', 'individual');

  // Use the Narciso input-document component's input ID — works for both Classic and Blocks.
  // Classic uses input-name="mercadopago_pse[doc_number]" (snake_case), Blocks uses
  // inputName="mercadopago_pse[docNumber]" (camelCase), but both share input-id.
  await page.locator('#mp-pse-gateway-document-input').fill(user.document);

  // Select the first real financial institution (index 0 is the placeholder).
  await selectPse('bank', { index: 1 });
  await page.waitForTimeout(2000);

  // Click place order (Classic single-click / Blocks two-phase submit)
  await placeOrder(page);
}
