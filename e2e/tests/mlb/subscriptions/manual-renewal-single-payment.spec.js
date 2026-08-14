import { test } from "@playwright/test";
import { guestUserMLB } from "../../../data/buyer_data";
import { mlb } from "../../../data/meli_sites";
import { setupSubscriptionsEnvironment } from "../../../helpers/subscriptions-env";
import { wpEval, wpOption } from "../../../helpers/wp-env";
import { snapshotOptions, restoreOptions } from "../../../helpers/wp-options-snapshot";
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
} from "../../../flows/subscriptions";
import { paySubscriptionOnBlocks } from "../../../flows/subscription-blocks";

/**
 * WooCommerce Subscriptions "Accept Manual Renewals" bypass.
 *
 * When a store enables WCS Accept Manual Renewals AND Turn off automatic payments,
 * the MP Custom gateway appears at checkout for a subscription product WITHOUT
 * Pre-approval credentials. process_payment() must then process the order as a
 * regular single payment (falling through to the parent gateway) instead of the
 * recurrence flow, which would fail with missing_access_token and block the
 * checkout. Reaching order-received (instead of a block) is the proof.
 *
 * @serial-store: the setup mutates store-wide options (subscription toggle,
 * Pre-approval tokens, WCS accept_manual_renewals / turn_off_automatic_payments), so
 * it must not run in parallel with other subscription specs (cit-approve,
 * mit-renewal, card-change) that depend on those options. run-all-report.sh runs
 * @serial-store specs alone (workers=1) in a separate phase.
 */

const { credit_card_scenarios } = mlb;
const { APPROVED } = credit_card_scenarios;

const origin = new URL(mlb.shop_url).origin;
// Falls back to the product slug that subscriptions-env creates when
// SUBSCRIPTION_PRODUCT_URL is not set, so only WCS_ZIP_PATH is strictly required.
const productUrl = origin + (process.env.SUBSCRIPTION_PRODUCT_URL || "/product/subscription-test-product/");

test.describe("Accept Manual Renewals single payment @serial-store", () => {
    // All store state this spec mutates: the gateway settings + the two WCS global
    // options. Snapshotted BEFORE setup and restored to the genuine pre-test state
    // (absent options are restored by deletion, not recreated as "").
    const STORE_OPTIONS = [
        "woocommerce_woo-mercado-pago-custom_settings",
        "woocommerce_subscriptions_accept_manual_renewals",
        "woocommerce_subscriptions_turn_off_automatic_payments",
    ];
    let storeSnapshot = null;

    test.beforeAll(() => {
        // Snapshot BEFORE setup (which seeds subscriptions_enabled + tokens) so afterAll
        // restores the store's real pre-test configuration.
        storeSnapshot = snapshotOptions(STORE_OPTIONS);

        setupSubscriptionsEnvironment();

        // No recurring credential: strip the Pre-approval token slots and turn the
        // subscriptions toggle off, so the gateway carries NO subscription support and
        // is only offered via WCS Accept Manual Renewals.
        wpEval(
            `$s = get_option("woocommerce_woo-mercado-pago-custom_settings", []);` +
            `$s["subscriptions_enabled"] = "no";` +
            `unset($s["subscriptions_access_token_test"]);` +
            `unset($s["subscriptions_access_token_prod"]);` +
            `update_option("woocommerce_woo-mercado-pago-custom_settings", $s);`
        );

        wpOption("woocommerce_subscriptions_accept_manual_renewals", "yes");
        wpOption("woocommerce_subscriptions_turn_off_automatic_payments", "yes");
    });

    test.afterAll(() => {
        restoreOptions(storeSnapshot);
    });

    test("Given a subscription without Pre-approval credentials and manual renewals enabled, When it is paid on the Classic checkout, Then it is processed as a single payment and the order-received page is shown", async ({ page }) => {
        test.skip(process.env.CHECKOUT === "blocks", "Classic variant — see the Blocks test below");
        test.setTimeout(90000);

        // Unique email per run — WCS creates a WP account on the first subscription purchase.
        const uniqueEmail = `test_user_${Date.now()}@testuser.com`;
        const corsErrors = trackCorsErrors(page);

        await addSubscriptionToCartAndCheckout(page, productUrl);
        await fillClassicBillingForm(page, guestUserMLB, uniqueEmail);
        await selectMpCustomPaymentMethod(page);
        await applySubscriptionCardEntryPatches(page);
        await fillMpSecureFields(page, APPROVED.master);
        await fillInstallmentsForm(page, APPROVED.form);
        await placeOrder(page);

        await assertOrderReceived(page, corsErrors);
    });

    test("Given a subscription without Pre-approval credentials and manual renewals enabled, When it is paid on the Blocks checkout, Then it is processed as a single payment and the order-received page is shown", async ({ page }) => {
        test.skip(process.env.CHECKOUT !== "blocks", "Blocks variant — run with CHECKOUT=blocks");
        test.setTimeout(90000);

        const uniqueEmail = `test_user_${Date.now()}@testuser.com`;

        await paySubscriptionOnBlocks(page, productUrl, guestUserMLB, uniqueEmail, APPROVED.master, APPROVED.form);
    });
});
