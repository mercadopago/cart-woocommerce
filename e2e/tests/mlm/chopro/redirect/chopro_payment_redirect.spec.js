import { test } from "@playwright/test";
import { mlm } from "../../../../data/meli_sites";
import { redirectCancelOrderTest, redirectSuccessfulPaymentTest, redirectRejectAndChangeMethodTest, redirectSuccessfulPendingPaymentTest } from "../../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUserDefault } = mlm;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test sucessfull payment with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await redirectSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: APPROVED.master,
    form: APPROVED.form
  });
})

test('test rejected payment with chopro, payment must be rejected and cancelled order page must be shown', async ({ page }) => {
  await redirectCancelOrderTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('test rejected payment with chopro, payment must be rejected and other payment options must be shown', async ({ page }) => {
  await redirectRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await redirectSuccessfulPendingPaymentTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})
