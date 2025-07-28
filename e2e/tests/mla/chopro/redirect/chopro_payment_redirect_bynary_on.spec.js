import { test } from "@playwright/test";
import { mla } from "../../../../data/meli_sites";
import { redirectCancelOrderTest, redirectRejectAndChangeMethodTest } from "../../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUserDefault } = mla;
const { PENDING } = credit_card_scenarios;

test('test pending payment with chopro, binary must be on, payment must be rejected and cancelled order page must be shown', async ({ page }) => {
  await redirectCancelOrderTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})

test('test pending payment with chopro, payment must be rejected and other payment options must be shown', async ({ page }) => {
  await redirectRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})
