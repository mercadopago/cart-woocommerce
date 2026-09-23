import { expect } from '@playwright/test';
import { StorePage } from '../pages/store.page';
import { CheckoutPage } from '../pages/checkout.page';
import { CustomCardPage } from '../pages/custom-card.page';
import { mlbApprovedCheckoutData, mlbRejectedCheckoutData } from './checkout-data';

async function openFilledCustomCheckout(browser, checkoutMode, data) {
  const configuredMode = (process.env.IOS_CHECKOUT || process.env.CHECKOUT || '').toLowerCase();
  if (configuredMode !== checkoutMode) {
    throw new Error(`[iOS E2E] Store is configured for ${configuredMode || 'unknown'}, expected ${checkoutMode}.`);
  }
  const store = new StorePage(browser, data.shopUrl, data.productId);
  const checkout = new CheckoutPage(browser);
  const cardPage = new CustomCardPage(browser);

  await store.addProductAndOpenCheckout();
  await checkout.fillBilling(data.user, checkoutMode);
  await checkout.selectCustomPayment(checkoutMode);
  await cardPage.fill(data.card, data.form);

  return { checkout, cardPage };
}

async function installmentsSync(browser, evidence, checkoutMode) {
  const data = mlbApprovedCheckoutData();
  const { checkout, cardPage } = await openFilledCustomCheckout(browser, checkoutMode, data);
  await cardPage.waitForIosAutoSelection();

  const before = await cardPage.installmentsState(false);
  expect(before.selectValue).toBe('1');
  expect(before.hiddenValue).not.toBe(before.selectValue);
  expect(before.placeholderDisabled).toBe(true);

  await evidence.captureFormFilled(checkoutMode);
  await evidence.writeBeforeSubmit({
    checkoutMode,
    pagePath: await checkout.pagePath(),
    installments: before,
  });

  await checkout.placeOrder(checkoutMode);
  // The submit handler must copy the untouched visible value into the hidden
  // field before its validation gate runs. Reaching order-received proves that
  // this synchronous handoff succeeded; the old page is no longer observable
  // once Safari starts navigation.
  await checkout.waitForOrderReceived();
  await evidence.captureOrderReceived();
}

export async function installmentsSyncClassic(browser, evidence) {
  await installmentsSync(browser, evidence, 'classic');
}

export async function installmentsSyncBlocks(browser, evidence) {
  await installmentsSync(browser, evidence, 'blocks');
}

export async function approvedCreditCard(browser, evidence, checkoutMode) {
  const data = mlbApprovedCheckoutData();
  const { checkout, cardPage } = await openFilledCustomCheckout(browser, checkoutMode, data);
  await cardPage.selectApprovedInstallment();
  const before = await cardPage.installmentsState(true);
  expect(before.selectValue).not.toBe('');

  await evidence.captureFormFilled(checkoutMode);
  await evidence.writeBeforeSubmit({
    checkoutMode,
    pagePath: await checkout.pagePath(),
    installments: before,
  });

  await checkout.placeOrder(checkoutMode);
  await checkout.waitForOrderReceived();
  await evidence.captureOrderReceived();
}

export async function rejectedCreditCard(browser, evidence, checkoutMode) {
  const data = mlbRejectedCheckoutData();
  const { checkout, cardPage } = await openFilledCustomCheckout(browser, checkoutMode, data);
  await cardPage.selectApprovedInstallment();
  const before = await cardPage.installmentsState(true);
  expect(before.selectValue).not.toBe('');

  await evidence.captureFormFilled(checkoutMode);
  await evidence.writeBeforeSubmit({
    checkoutMode,
    pagePath: await checkout.pagePath(),
    installments: before,
  });

  const previousNotices = await checkout.visibleCheckoutErrors();
  await checkout.placeOrder(checkoutMode);
  await checkout.waitForPaymentRejected(previousNotices);
  await evidence.capturePaymentRejected();
}
