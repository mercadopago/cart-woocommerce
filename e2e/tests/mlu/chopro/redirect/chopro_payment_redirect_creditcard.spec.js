import { test } from '@playwright/test';
import { mlu } from '../../../../data/meli_sites';
import { redirectCancelOrderTest, redirectSuccessfulPaymentTest, redirectSuccessfulPendingPaymentTest, successfulPaymentTest } from '../../../../flows/chopro';

const { shop_url, credit_card_scenarios, guestUser } = mlu;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test credit card payment with approved and guest user', async ({ page }) => {
  await redirectSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUser,
    card: APPROVED.master,
    form: APPROVED.form
  });
});

test('test credit card payment with rejected and guest user', async ({ page }) => {
  await redirectCancelOrderTest({
    page,
    url: shop_url,
    user: guestUser,
    card: REJECTED.master,
    form: REJECTED.form
  });
});

test('test credit card payment with pending and guest user', async ({ page }) => {
  await redirectSuccessfulPendingPaymentTest({
    page,
    url: shop_url,
    user: guestUser,
    card: PENDING.master,
    form: PENDING.form
  });
});
