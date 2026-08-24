import { test } from "@playwright/test";
import { guestUserMLB } from "../../data/buyer_data";
import { mlb } from "../../data/meli_sites";
import { setupSubscriptionsEnvironment, cleanupSubscriptionProduct } from "../../helpers/subscriptions-env";
import {
    trackCorsErrors,
    addSubscriptionToCartAndCheckout,
    fillClassicBillingForm,
    selectMpCustomPaymentMethod,
    applySubscriptionCardEntryPatches,
    fillMpSecureFields,
    fillInstallmentsForm,
    placeOrder,
    assertOrderReceived,
} from "../../flows/subscriptions";

const { credit_card_scenarios } = mlb;
const { APPROVED } = credit_card_scenarios;

const origin     = new URL(process.env.SHOP_URL).origin;
const productUrl = origin + process.env.SUBSCRIPTION_PRODUCT_URL;

test.beforeAll(setupSubscriptionsEnvironment);
test.afterAll(cleanupSubscriptionProduct);

test("test successful subscription payment as guest with visa, payment must be approved and subscription must be activated", async ({ page }) => {
    // Subscription checkout is slower: WCS triggers extra update_order_review cycles
    // and MP SDK reinit that are not present for regular products.
    test.setTimeout(90000);

    // Unique email per run — WCS creates a WP account on the first subscription
    // purchase, so reusing the same email triggers "account already exists" errors.
    const uniqueEmail = `test_user_${Date.now()}@testuser.com`;

    const corsErrors = trackCorsErrors(page);

    await addSubscriptionToCartAndCheckout(page, productUrl);
    await fillClassicBillingForm(page, guestUserMLB, uniqueEmail);
    await selectMpCustomPaymentMethod(page);
    await applySubscriptionCardEntryPatches(page);
    await fillMpSecureFields(page, APPROVED.visa);
    await fillInstallmentsForm(page, APPROVED.form);
    await placeOrder(page);
    await assertOrderReceived(page, corsErrors);
});
