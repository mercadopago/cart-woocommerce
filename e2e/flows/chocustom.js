import { fillStepsToCheckout } from "./fill_steps_to_checkout";
import { expect, test } from "@playwright/test";

const CORS_MESSAGE = [
  '[CORS] Falha na tokenizacao de cartao no ambiente Sandbox.',
  '',
  'Motivo provavel: Devido a migracao de ambientes PCI (Sandbox), houve uma',
  'alteracao nas politicas de CORS. Requisicoes originadas do secure-fields.mercadopago.com',
  'usando test_keys estao falhando no pre-flight para o endpoint /v1/card_tokens',
  'devido a ausencia do header Access-Control-Allow-Origin.',
  '',
  'Impacto: Bloqueio de tokenizacao de cartoes em ambiente de teste.',
  'Integradores nao conseguem tokenizar cartoes, impedindo testes E2E de pagamento.',
].join('\n');

export function trackCorsErrors(page) {
  const errors = [];
  page.on('console', msg => {
    if (msg.text().includes('CORS') || msg.text().includes('net::ERR_FAILED')) {
      errors.push(msg.text());
    }
  });
  return errors;
}

export function assertNoCors(corsErrors) {
  if (corsErrors.length > 0) {
    throw new Error(CORS_MESSAGE + '\n\nErros capturados no console:\n' + corsErrors.join('\n'));
  }
}

export async function successfulPaymentTest(page, url, user, card, form) {
  test.setTimeout(120000);
  const corsErrors = trackCorsErrors(page);
  await makePayment(page, url, user, card, form);

  // Fast-fail on CORS: check after 5s instead of waiting the full 60s timeout
  const result = await Promise.race([
    page.waitForURL(/order-received/, { waitUntil: 'domcontentloaded', timeout: 80000 }).then(() => 'ok'),
    page.waitForTimeout(5000).then(() => corsErrors.length > 0 ? 'cors' : null),
  ]);

  if (result === 'cors') {
    assertNoCors(corsErrors);
  }

  await expect(page.locator('.woocommerce-thankyou-order-received')).toBeVisible({ timeout: 80000 });
}

export async function rejectedPaymentTest(page, url, user, card, form) {
  const corsErrors = trackCorsErrors(page);
  await makePayment(page, url, user, card, form);

  const result = await Promise.race([
    expect(page.locator('.woocommerce-error, .wc-block-store-notice.wc-block-components-notice-banner.is-error')).toBeVisible({ timeout: 30000 }).then(() => 'ok'),
    page.waitForTimeout(5000).then(() => corsErrors.length > 0 ? 'cors' : null),
  ]);

  if (result === 'cors') {
    assertNoCors(corsErrors);
  }
}

export async function emptyFieldsPaymentTest(page, url, user, card, form) {
  await makePayment(page, url, user, card, form);

  for (const helper of [
    '#mp-card-holder-div input-helper[input-id="mp-card-holder-name-helper"]',
    'input-helper[input-id="mp-installments-error"]',
    '#mp-doc-div input-helper'
  ]) {
    expect(
      await page
        .locator(helper)
        .evaluate(element => window.getComputedStyle(element).display)
    ).not.toBe('none');
  }
}

/**
 * Navigates from shop to checkout, fills billing data and selects the
 * Mercado Pago Custom (credit/debit card) payment method. Supports both
 * Classic and Blocks checkout.
 */
export async function goToCustomCheckout(page, url, user) {
  await fillStepsToCheckout(page, url, user);
  await page.waitForLoadState();

  // Select custom checkout — supports both Classic and Blocks
  const classicRadio = page.locator('#payment_method_woo-mercado-pago-custom');
  const blocksRadio = page.locator('#radio-control-wc-payment-method-options-woo-mercado-pago-custom');

  if (await classicRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
    await page.locator('label[for="payment_method_woo-mercado-pago-custom"]').click();
    await page.waitForTimeout(1000);
  } else {
    await blocksRadio.check();
    // Blocks sends a Store API request after payment method selection to recalculate the
    // cart (adds MP discount/commission fees via session key). waitForLoadState() resolves
    // before that async response is processed, so give the Store API time to settle.
    await page.waitForTimeout(3000);
  }

  await page.waitForLoadState();
}

/**
 * Fills the Mercado Pago SDK card fields (inside iframes) and the document /
 * cardholder / installments fields. Uses pressSequentially so the SDK fires its
 * input events (fill() does not trigger them). When `card.number` is an invalid
 * value, the SDK will refuse to produce a token at place-order time — this is
 * intentional for the infinite-loading recovery scenario.
 */
export async function fillCustomCardForm(page, card, form) {
  await page.locator('iframe[name="cardNumber"]').waitFor({ state: 'visible', timeout: 30000 });
  const cardNumberInput = page.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]');
  const cardDigits = card.number.replace(/\D/g, '');
  // pressSequentially intermittently drops keystrokes in the SDK's secure-field iframe
  // (worse in Blocks / for 15-digit amex), producing an incomplete number → tokenization
  // fails with "this card must have exactly N digits" → the test then burns the full
  // timeout retrying. Type and VERIFY the digit count, re-typing until complete.
  for (let attempt = 0; attempt < 3; attempt++) {
    await cardNumberInput.click({ timeout: 15000 });
    await cardNumberInput.fill('');
    await cardNumberInput.pressSequentially(cardDigits, { delay: 50 });
    const entered = (await cardNumberInput.inputValue().catch(() => '')).replace(/\D/g, '');
    if (entered.length === cardDigits.length) {
      break;
    }
  }

  const securityCodeInput = page.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]');
  await securityCodeInput.click();
  await securityCodeInput.pressSequentially(card.code, { delay: 30 });

  const expirationDateInput = page.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]');
  await expirationDateInput.click();
  await expirationDateInput.pressSequentially(card.date.replace('/', ''), { delay: 30 });

  await page.waitForTimeout(3000);

  // Fill the extra card fields, each gated on ITS OWN visibility. The MP test result
  // is driven by the cardholder NAME (APRO/OTHE/CONT), so it must always be filled
  // when the field renders — even for debit/amex cards, which don't show an
  // installments dropdown. A previous version gated the name on installments
  // visibility, which skipped the name for debit/amex (no installments) → MP rejected
  // the payment ("recusado devido a um erro"). The identification select and the
  // installments dropdown are optional (some countries hide identification, e.g. MLA;
  // debit/single-installment cards hide installments), so we only touch each when it
  // is actually visible — avoiding both the missed-name rejection and hangs on hidden
  // selects. We wait (bounded) for the cardholder field as the "card form rendered"
  // anchor; if it never renders we skip (prior behavior for checkouts without it).
  const cardholderName = page.locator('#form-checkout__cardholderName');
  const cardFormRendered = await cardholderName
    .waitFor({ state: 'visible', timeout: 12000 })
    .then(() => true)
    .catch(() => false);

  if (cardFormRendered) {
    await cardholderName.fill(form.name);

    const identificationType = page.locator('#form-checkout__identificationType');
    if (await identificationType.isVisible().catch(() => false)) {
      const docTypeAvailable = await identificationType
        .locator(`option[value="${form.docType}"]`)
        .count()
        .then((n) => n > 0)
        .catch(() => false);
      if (docTypeAvailable) {
        await identificationType.selectOption(form.docType);
      }
      await page.waitForTimeout(3000);

      if (form.docNumber != null) {
        await page.locator('[name="identificationNumber"]').fill(form.docNumber);
      }
    }

    const installments = page.locator('#mp-checkout-custom-installments-card');
    if (form.name !== '' && await installments.isVisible().catch(() => false)) {
      await page.locator('#form-checkout__installments').selectOption({ index: 1 });
    }
    await page.waitForLoadState();
  }
}

/**
 * Clicks the place-order button. Supports both Classic and Blocks checkout.
 *
 * Blocks — wait for the cart to settle before submitting:
 * When the gateway is selected, the plugin applies discount/commission via a cart extension (extensionCartUpdate). This marks the cart totals dirty and triggers background recalculations (POST /wc/store/v1/batch). If the order is submitted while one of those is still in flight, WC Blocks runs a totals recalculation instead of placing the order — the buyer stays on the checkout and never reaches order-received. Waiting for networkidle reproduces the natural pause a real shopper has between selecting the method and clicking, after which the recalculation settles.
 */
export async function placeOrder(page) {
  const classicPlaceOrder = page.locator('#place_order');

  if (await classicPlaceOrder.isVisible({ timeout: 3000 }).catch(() => false)) {
    await classicPlaceOrder.click();
  } else {
    const blocksPlaceOrder = page.locator('.wc-block-components-checkout-place-order-button');
    await expect(blocksPlaceOrder).toBeEnabled({ timeout: 10000 });
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
    await blocksPlaceOrder.click();
  }

  await page.waitForLoadState();
}

async function makePayment(page, url, user, card, form) {
  if (!card?.number) {
    throw new Error(
      `[E2E] Card number is undefined. Check that the env var for this card is set in e2e/.env.\n` +
      `Card object: ${JSON.stringify(card)}`
    );
  }

  await goToCustomCheckout(page, url, user);
  await fillCustomCardForm(page, card, form);
  await placeOrder(page);
}
