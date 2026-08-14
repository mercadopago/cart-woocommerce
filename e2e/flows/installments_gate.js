import { expect } from "@playwright/test";
import { goToCustomCheckout, fillCustomCardForm, placeOrder } from "./chocustom";

/**
 * Desktop regression guard for the installments gate.
 *
 * The iOS installments-sync fix (syncInstallmentsFromSelect) mirrors the visible
 * <select> into the hidden #cardInstallments at submit — but it must NOT weaken the
 * installments gate on desktop: when the buyer never picks an installment, the
 * select stays on its disabled placeholder (value=''), so installmentsWasSelected()
 * is false and runPreSubmitGates blocks the submit with the installments error.
 *
 * This asserts that preserved behavior: a valid card with the installments left on
 * the placeholder is blocked (installments error shown, order never placed), so the
 * sync did not turn an empty selection into a silent 1x.
 */

const ORDER_RECEIVED_REGEX = /order-received/;
// Shown (display:flex) by setInstallmentsErrorState(true) when no installment is picked.
const INSTALLMENTS_ERROR = '#mp-installments-error';

/**
 * Fills the card (number/CVV/expiration), the cardholder name and the document, but
 * intentionally LEAVES the installments select on its placeholder — the state the
 * gate must still reject.
 */
async function fillCardLeaveInstallmentsUnselected(page, card, form) {
  await page.locator('iframe[name="cardNumber"]').waitFor({ state: 'visible', timeout: 30000 });

  const number = page.frameLocator('iframe[name="cardNumber"]').locator('[name="cardNumber"]');
  const digits = String(card.number).replace(/\D/g, '');
  for (let attempt = 0; attempt < 3; attempt++) {
    await number.click({ timeout: 15000 });
    await number.fill('');
    await number.pressSequentially(digits, { delay: 50 });
    const entered = (await number.inputValue().catch(() => '')).replace(/\D/g, '');
    if (entered.length === digits.length) break;
  }

  const cvv = page.frameLocator('iframe[name="securityCode"]').locator('[name="securityCode"]');
  await cvv.click();
  await cvv.pressSequentially(card.code, { delay: 30 });

  const exp = page.frameLocator('iframe[name="expirationDate"]').locator('[name="expirationDate"]');
  await exp.click();
  await exp.pressSequentially(String(card.date).replace('/', ''), { delay: 30 });

  await page.waitForTimeout(3000);

  const cardholderName = page.locator('#form-checkout__cardholderName');
  if (await cardholderName.waitFor({ state: 'visible', timeout: 12000 }).then(() => true).catch(() => false)) {
    await cardholderName.fill(form.name);

    const identificationType = page.locator('#form-checkout__identificationType');
    if (await identificationType.isVisible().catch(() => false)) {
      const docTypeAvailable = await identificationType
        .locator(`option[value="${form.docType}"]`)
        .count()
        .then((n) => n > 0)
        .catch(() => false);
      if (docTypeAvailable) await identificationType.selectOption(form.docType);
      await page.waitForTimeout(2000);
      if (form.docNumber != null) {
        await page.locator('[name="identificationNumber"]').fill(form.docNumber);
      }
    }
    // Deliberately DO NOT select an installment — leave the placeholder selected.
    await page.waitForLoadState();
  }
}

/**
 * Regression guard (desktop, Classic): installments left on the placeholder must
 * block the submit with the installments error; the order is never placed and the
 * checkout stays usable.
 */
export async function installmentsPlaceholderBlocksClassic(page, url, user, card, form) {
  await goToCustomCheckout(page, url, user);
  await fillCardLeaveInstallmentsUnselected(page, card, form);

  // Sanity: the installments select is loaded but sits on the empty placeholder.
  await expect(page.locator('#form-checkout__installments')).toBeVisible({ timeout: 20000 });
  await expect(page.locator('#form-checkout__installments')).toHaveValue('');

  await placeOrder(page);

  // The installments gate blocks: error shown, order never placed, checkout usable.
  await expect(page.locator(INSTALLMENTS_ERROR)).toBeVisible({ timeout: 15000 });
  await expect(page).not.toHaveURL(ORDER_RECEIVED_REGEX);
  await expect(page.locator('.woocommerce-thankyou-order-received')).toHaveCount(0);
  await expect(page.locator('#place_order')).toBeEnabled({ timeout: 15000 });
}

/**
 * Debit forces a single installment. For a debit BIN the SDK reports
 * payment_type_id 'debit_card', so shouldEnableInstallmentsComponent disables the
 * installments component and setChangeEventOnInstallments pins both the visible
 * select and the hidden #cardInstallments (the value posted to the backend) to '1'.
 *
 * No submit is needed: we assert the forced 1x invariant right after the card is
 * identified. Debit is not offered in MLB's online checkout, so this runs in a
 * debit-enabled market (e.g. MCO).
 */
export async function debitForcesSingleInstallment(page, url, user, card, form) {
  await goToCustomCheckout(page, url, user);
  // fillCustomCardForm skips the installments select for debit (the installments
  // card is not shown), so it does not interfere with the forced value.
  await fillCustomCardForm(page, card, form);

  await expect
    .poll(() => page.locator('#cardInstallments').inputValue(), { timeout: 20000 })
    .toBe('1');
  await expect(page.locator('#form-checkout__installments')).toHaveValue('1');
}
