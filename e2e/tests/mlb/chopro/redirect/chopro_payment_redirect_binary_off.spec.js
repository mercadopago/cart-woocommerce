import { test } from "@playwright/test";
import { mlb } from "../../../../data/meli_sites";
import { redirectCancelOrderTest, redirectSuccessfulPaymentTest, redirectSuccessfulPendingPaymentTest } from "../../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED, REJECTED, PENDING } = credit_card_scenarios;

test('test sucessfull payment with chopro, payment must be approved and success page must be shown', async ({ page }) => {
  await redirectSuccessfulPaymentTest({
    page,
    url: shop_url,
    user: guestUserMLB,
    card: APPROVED.master,
    form: APPROVED.form
  });
})

test('test rejected payment with chopro, payment must be rejected and cancelled order page must be shown', async ({ page }) => {
  await redirectCancelOrderTest({
    page,
    url: shop_url,
    user: guestUserMLB,
    card: REJECTED.master,
    form: REJECTED.form
  });
})

test('test pending payment with chopro, binary must be off, payment must be approved and success page must be shown', async ({ page }) => {
  await redirectSuccessfulPendingPaymentTest({
    page,
    url: shop_url,
    user: guestUserMLB,
    card: PENDING.master,
    form: PENDING.form
  });
})
