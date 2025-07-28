import { test } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import { modalCancelOrderTest, modalSuccessfulPendingPaymentTest, modalSuccessfulPaymentTest, modalRejectAndChangeMethodTest } from "../../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test successful payment with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUserMLB,
    card: APPROVED.master,
    form: APPROVED.form
  });
})

test('test rejected payment with chopro, other payment options must be shown', async ({ page }) => {
  await modalRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUserMLB,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('test rejected payment with chopro modal, close button clicked, cancelled order page must be shown', async ({ page }) => {
  await modalCancelOrderTest({
    page,
    url: shop_url,
    user: guestUserMLB,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await modalSuccessfulPendingPaymentTest({
    page,
    url: shop_url,
    user: guestUserMLB,
    card: PENDING.master,
    form: PENDING.form
  });
})

