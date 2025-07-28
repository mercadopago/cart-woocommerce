import { test } from '@playwright/test';
import { mlc } from '../../../../data/meli_sites';
import { modalRejectAndChangeMethodTest, modalSuccessfulPaymentTest, modalSuccessfulPendingPaymentTest, successfulPaymentTest } from '../../../../flows/chopro';

const { shop_url, credit_card_scenarios, guestUserDefault } = mlc;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test successful payment guest with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: APPROVED.master,
    form: APPROVED.form
  });
});

test('test rejected payment guest with chopro, payment must be rejected and decline message must be shown', async ({ page }) => {
  await modalRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: REJECTED.master,
    form: REJECTED.form
  });
});

test('test pending payment guest with chopro, payment must be pending and the payment processing message must be shown', async ({ page }) => {
  await modalSuccessfulPendingPaymentTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form,
  });
});
