import { test } from "@playwright/test";
import { mco } from "../../../../data/meli_sites";
import { redirectCancelOrderTest, redirectRejectAndChangeMethodTest } from "../../../../flows/chopro";

const { shop_url, credit_card_scenarios, guestUserDefault } = mco;
const { PENDING } = credit_card_scenarios;

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show the decline message', async ({page}) => {
  await redirectCancelOrderTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})

test('Given a guest user, When their payment with chopro is pending and binary is on, Should show other payment options', async ({page}) => {
  await redirectRejectAndChangeMethodTest({
    page,
    url: shop_url,
    user: guestUserDefault,
    card: PENDING.master,
    form: PENDING.form
  });
})
