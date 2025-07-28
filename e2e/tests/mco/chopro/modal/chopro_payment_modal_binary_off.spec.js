import { test } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { modalCancelOrderTest, modalRejectAndChangeMethodTest, modalSuccessfulPaymentTest, modalSuccessfulPendingPaymentTest } from "../../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUserDefault } = mco;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('Given a guest user, When they complete a successful payment with chopro, Should show the success page', async ({ page }) => {
  await modalSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: APPROVED.master,
    form: APPROVED.form
  });
})

test('Given a guest user, When their payment with chopro is rejected, Should show other payment options', async ({ page }) => {
  await modalRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('Given a guest user, When their payment with chopro is rejected and they close the modal, Should show the cancelled order message', async ({ page }) => {
  await modalCancelOrderTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('Given a guest user, When their payment with chopro is pending and binary is off, Should show the success page', async ({ page }) => {
  await modalSuccessfulPendingPaymentTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})
