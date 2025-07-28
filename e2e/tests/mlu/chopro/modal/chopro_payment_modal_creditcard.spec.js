import { test } from '@playwright/test';
import { mlu } from '../../../../data/meli_sites';
import { modalRejectAndChangeMethodTest, modalSuccessfulPaymentTest, modalSuccessfulPendingPaymentTest } from '../../../../flows/chopro';

const { shop_url, credit_card_scenarios, guestUser } = mlu;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test successful payment guest with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUser,
    card: APPROVED.master,
    form: APPROVED.form
  });
});

test('test rejected payment guest with chopro, payment must be rejected and , other payment options must be shown', async ({ page }) => {
  await modalRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUser,
    card: REJECTED.master,
    form: REJECTED.form
  });
});

test('test pending chopro payment with guest, binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPendingPaymentTest({
    page,
    url: shop_url,
    user: guestUser,
    card: PENDING.master,
    form: PENDING.form
  });
});
