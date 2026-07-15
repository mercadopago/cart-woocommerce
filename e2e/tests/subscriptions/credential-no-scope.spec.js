import { test, expect } from "@playwright/test";
import { setupSubscriptionsEnvironment } from "../../helpers/subscriptions-env";
import { loginToWpAdmin } from "../../flows/subscriptions";

const SETTINGS_URL = (base) =>
    `${base}/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woo-mercado-pago-custom`;

// The checkbox is hidden inside a custom toggle — interact via the switch div.
const TOGGLE        = "#woocommerce_woo-mercado-pago-custom_subscriptions_enabled";
const TOGGLE_SWITCH = `label.mp-toggle:has(${TOGGLE}) div.mp-toggle-switch`;
const TOKEN    = "#woocommerce_woo-mercado-pago-custom_subscriptions_access_token_test";
const SAVE_BTN = "button.woocommerce-save-button";

test.beforeAll(setupSubscriptionsEnvironment);

test("test subscriptions toggle cannot be enabled without Pre-approval scope, it must be blocked and show scope error", async ({ page }) => {
    test.setTimeout(60000);

    const base        = new URL(process.env.SHOP_URL).origin;
    const noScopeToken = process.env.MP_SUBSCRIPTIONS_NO_SCOPE_TOKEN_TEST;
    const validToken   = process.env.MP_SUBSCRIPTIONS_ACCESS_TOKEN_TEST;

    if (!noScopeToken || !validToken) {
        test.skip(true, "MP_SUBSCRIPTIONS_NO_SCOPE_TOKEN_TEST ou MP_SUBSCRIPTIONS_ACCESS_TOKEN_TEST não definido — skipping E2E-5");
    }

    await loginToWpAdmin(page);

    // ── Step 1: toggle ON with a token that lacks Pre-approval scope ─────────
    await page.goto(SETTINGS_URL(base));

    const checkbox     = page.locator(TOGGLE);
    const toggleSwitch = page.locator(TOGGLE_SWITCH);

    if (!await checkbox.isChecked()) {
        await toggleSwitch.click();
    }
    await page.locator(TOKEN).fill(noScopeToken);
    await page.locator(SAVE_BTN).click();
    await page.waitForLoadState();

    // Plugin forces toggle OFF and shows the scope error notice
    await expect(
        page.locator("div.notice-error p").filter({ hasText: "Pre-approval" })
    ).toBeVisible({ timeout: 10000 });
    await expect(checkbox).not.toBeChecked();

    // ── Step 2: toggle ON with a valid Pre-approval token ────────────────────
    await toggleSwitch.click();
    await page.locator(TOKEN).fill(validToken);
    await page.locator(SAVE_BTN).click();
    await page.waitForLoadState();

    // The success notice text is localized — assert the notice is visible
    // rather than matching a specific language string.
    await expect(page.locator("div.notice-success p")).toBeVisible({ timeout: 10000 });
    await expect(checkbox).toBeChecked();
});
