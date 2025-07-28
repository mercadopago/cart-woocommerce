import { test } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { redirectCancelOrderTest, redirectSuccessfulPaymentTest, redirectRejectAndChangeMethodTest, redirectSuccessfulPendingPaymentTest } from "../../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUserDefault } = mco;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('Given a guest user, When they complete a successful payment with chopro, Should show the success page', async ({page}) => {
  await redirectSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: APPROVED.master,
    form: APPROVED.form
  });
})

test('Given a guest user, When their payment with chopro is rejected, Should show the decline message', async ({page}) => {
  await redirectCancelOrderTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('Given a guest user, When their payment with chopro is rejected, Should show other payment options', async ({page}) => {
  await redirectRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('Given a guest user, When their payment with chopro is pending and binary is off, Should show the success page', async ({page}) => {
  await redirectSuccessfulPendingPaymentTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})
