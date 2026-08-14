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
 * Subscription-credential match on a manual-renewal store (credential-mismatch guard).
 *
 * With a Pre-approval (subscription) credential configured AND the store in manual
 * renewal + automatic payments OFF, two independent decisions must stay consistent:
 *   - Tokenization: overridePublicKeyForSubscriptionCheckout() swaps the SDK public
 *     key to the subscription one on any subscription context (resolvePublicKey ≠ '').
 *   - Processing: process_payment() falls through to a single payment when
 *     automaticPaymentsOff is true — which uses the DEFAULT access token.
 * If the token is minted with the subscription public key but charged with the
 * default access token, the credentials mismatch and the payment is rejected.
 *
 * This asserts the fixed behavior: a valid card is APPROVED (reaches order-received),
 * i.e. tokenization and processing use the same credential pair. Before the fix the
 * checkout fails with a credential/token error and never reaches order-received.
 *
 * @serial-store: the setup mutates store-wide options (WCS accept_manual_renewals /
 * turn_off_automatic_payments); restored to their prior values in afterAll.
 *
 * Non-regression for the two neighboring cases is covered elsewhere:
 *   - no subscription credential + manual renewal → single payment
 *     (manual-renewal-single-payment.spec.js);
 *   - subscription credential + automatic payments ON → recurrence flow
 *     (cit-approve.spec.js).
 */

const { credit_card_scenarios } = mlb;
const { APPROVED } = credit_card_scenarios;

const origin = new URL(mlb.shop_url).origin;
const productUrl = origin + (process.env.SUBSCRIPTION_PRODUCT_URL || "/product/subscription-test-product/");

// The Pre-approval credential is what triggers the public-key override; without it
// the scenario cannot be exercised.
const hasSubscriptionCredential =
    !!process.env.MP_SUBSCRIPTIONS_ACCESS_TOKEN_TEST && !!process.env.MP_SUBSCRIPTIONS_PUBLIC_KEY_TEST;

test.describe("Subscription credential match on manual-renewal checkout @serial-store", () => {
    // All store state this spec mutates; snapshotted BEFORE setup and restored to the
    // genuine pre-test state (absent options restored by deletion).
    const STORE_OPTIONS = [
        "woocommerce_woo-mercado-pago-custom_settings",
        "woocommerce_subscriptions_accept_manual_renewals",
        "woocommerce_subscriptions_turn_off_automatic_payments",
    ];
    let storeSnapshot = null;

    test.beforeAll(() => {
        test.skip(
            !hasSubscriptionCredential,
            "MP_SUBSCRIPTIONS_ACCESS_TOKEN_TEST / MP_SUBSCRIPTIONS_PUBLIC_KEY_TEST not set — cannot configure the Pre-approval credential"
        );

        // Snapshot BEFORE setup so afterAll restores the store's real pre-test
        // configuration (setup seeds subscriptions_enabled=yes AND the Pre-approval
        // public key / access token from the MP_SUBSCRIPTIONS_* env vars).
        storeSnapshot = snapshotOptions(STORE_OPTIONS);

        setupSubscriptionsEnvironment();

        // Keep the Pre-approval credential (do NOT clear it) and switch the store to
        // manual renewal + automatic payments off — the exact combination that makes
        // process_payment take the single-payment branch while the token uses the
        // subscription public key.
        wpOption("woocommerce_subscriptions_accept_manual_renewals", "yes");
        wpOption("woocommerce_subscriptions_turn_off_automatic_payments", "yes");

        // Sanity: the subscription credential must actually be present, otherwise the
        // public-key override never fires and the test would not exercise the mismatch.
        const credentialPresent = wpEval(
            `$s = get_option("woocommerce_woo-mercado-pago-custom_settings", []);` +
            `echo (!empty($s["subscriptions_public_key_test"]) && !empty($s["subscriptions_access_token_test"])) ? "yes" : "no";`
        );
        test.skip(String(credentialPresent).trim() !== "yes", "Pre-approval credential was not configured on the gateway");
    });

    test.afterAll(() => {
        restoreOptions(storeSnapshot);
    });

    test("Given a Pre-approval credential and manual renewals enabled, When a subscription is paid on the Classic checkout, Then it is approved without a credential mismatch", async ({ page }) => {
        test.skip(process.env.CHECKOUT === "blocks", "Classic variant — see the Blocks test below");
        test.setTimeout(90000);

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

    test("Given a Pre-approval credential and manual renewals enabled, When a subscription is paid on the Blocks checkout, Then it is approved without a credential mismatch", async ({ page }) => {
        test.skip(process.env.CHECKOUT !== "blocks", "Blocks variant — run with CHECKOUT=blocks");
        test.setTimeout(90000);

        const uniqueEmail = `test_user_${Date.now()}@testuser.com`;

        await paySubscriptionOnBlocks(page, productUrl, guestUserMLB, uniqueEmail, APPROVED.master, APPROVED.form);
    });
});
