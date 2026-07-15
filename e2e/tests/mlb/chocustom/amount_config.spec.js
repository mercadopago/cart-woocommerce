import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import { orderIdFromReceivedUrl } from "../../../flows/refund";
import { successfulPaymentTest, goToCustomCheckout } from "../../../flows/chocustom";
import {
  snapshotGatewaySettings,
  restoreGatewaySettings,
  setDiscount,
  disableDiscount,
  setCommission,
  disableCommission,
  setDiscountAmount,
  setCurrencyConversion,
  createCoupon,
  deleteCoupon,
  payWithCustomCardAndCoupon,
  assertPluginFees,
  assertCouponApplied,
  assertCouponThenDiscount,
  assertCurrencyConverted,
  assertFeeLinesVisible,
} from "../../../flows/amount_config";

const { wpOption, wpGetOption } = require("../../../helpers/wp-env");
const { skipIfNotSite } = require("../../../helpers/site-guard");

const { shop_url, credit_card_scenarios, guestUserMLB } = mlb;
const { APPROVED } = credit_card_scenarios;

const CUSTOM_GATEWAY = "woo-mercado-pago-custom";

skipIfNotSite(test, "MLB");

// Serial so a scenario that leaves discount/commission/currency set never bleeds into
// the next payment before afterEach reverts it.
test.describe.configure({ mode: "serial" });

// Every describe carries the @serial-store tag: these scenarios mutate store-wide options
// (gateway discount/commission and especially woocommerce_currency=USD). mode:"serial" only
// orders tests inside THIS file; cross-file isolation comes from run-all-report.sh running
// MLB in two phases: non-@serial-store first (workers=2), then @serial-store alone (workers=1).
// Direct `npx playwright test tests/mlb/` invocations get workers=2 — use
// --grep-invert /@serial-store/ or be aware that @serial-store may run concurrently.

let gatewaySnapshot = null;
let originalCurrency = null;
let createdCouponCode = null;

test.beforeAll(() => {
  gatewaySnapshot = snapshotGatewaySettings(CUSTOM_GATEWAY);
  originalCurrency = wpGetOption("woocommerce_currency");
});

test.afterEach(() => {
  restoreGatewaySettings(CUSTOM_GATEWAY, gatewaySnapshot);
  if (originalCurrency) {
    wpOption("woocommerce_currency", originalCurrency);
  }
  if (createdCouponCode) {
    deleteCoupon(createdCouponCode);
    createdCouponCode = null;
  }
});

test.describe("order total reflects plugin discount and commission @serial-store", () => {
  test("Given a 10% gateway discount is active, When the buyer pays with an approved card, Should subtract the discount from the order total", async ({ page }) => {
    setDiscount(CUSTOM_GATEWAY, 10);

    await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
    const orderId = orderIdFromReceivedUrl(page);

    assertPluginFees(orderId, { discountPct: 10 });
  });

  test("Given a 5% commission is active, When the buyer pays with an approved card, Should add the commission to the order total", async ({ page }) => {
    setCommission(CUSTOM_GATEWAY, 5);

    await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
    const orderId = orderIdFromReceivedUrl(page);

    assertPluginFees(orderId, { commissionPct: 5 });
  });

  test("Given a 10% discount and a 5% commission are active, When the buyer pays with an approved card, Should net both against the order total", async ({ page }) => {
    setDiscount(CUSTOM_GATEWAY, 10);
    setCommission(CUSTOM_GATEWAY, 5);

    await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
    const orderId = orderIdFromReceivedUrl(page);

    assertPluginFees(orderId, { discountPct: 10, commissionPct: 5 });
  });

  test("Given a discount amount is set but its checkbox is OFF, When the buyer pays with an approved card, Should leave the order total unchanged", async ({ page }) => {
    // RN-1 exception: getActionableValue() returns 0 while the checkbox is off, even with a value set.
    setDiscountAmount(CUSTOM_GATEWAY, 10);
    disableDiscount(CUSTOM_GATEWAY);
    disableCommission(CUSTOM_GATEWAY);

    await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
    const orderId = orderIdFromReceivedUrl(page);

    assertPluginFees(orderId, {});
  });
});

// Regression guard for fractional percentages. AbstractGateway::$discount/$commission were
// once typed `int` (with an explicit (int) cast in CustomGateway), which truncated values
// like 0.5 to 0 — silently dropping the fee. They are now `float` (AbstractGateway.php:39,41;
// all gateways cast (float)), so a fractional percentage is applied correctly. These tests
// fail if anyone regresses the type back to int.
test.describe("fractional discount and commission @serial-store", () => {
  test("Given a fractional 0.5% gateway discount, When the buyer pays with an approved card, Should apply 0.5% of the subtotal", async ({ page }) => {
    setDiscount(CUSTOM_GATEWAY, 0.5);

    await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
    const orderId = orderIdFromReceivedUrl(page);

    assertPluginFees(orderId, { discountPct: 0.5 });
  });

  test("Given a fractional 0.5% commission, When the buyer pays with an approved card, Should apply 0.5% of the subtotal", async ({ page }) => {
    setCommission(CUSTOM_GATEWAY, 0.5);

    await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
    const orderId = orderIdFromReceivedUrl(page);

    assertPluginFees(orderId, { commissionPct: 0.5 });
  });
});

test.describe("checkout shows the plugin fee lines @serial-store", () => {
  test("Given a 10% discount and a 5% commission are active, When the buyer reaches the Custom checkout, Should display both fee lines in the order review", async ({ page }) => {
    setDiscount(CUSTOM_GATEWAY, 10);
    setCommission(CUSTOM_GATEWAY, 5);

    await goToCustomCheckout(page, shop_url, guestUserMLB);

    await assertFeeLinesVisible(page, 2);
  });
});

test.describe("WooCommerce coupon @serial-store", () => {
  test("Given a 20% percent coupon, When the buyer applies it and pays with an approved card, Should reduce the order total by the coupon amount", async ({ page }) => {
    createdCouponCode = `e2e-amount-${Date.now()}`;
    createCoupon(createdCouponCode, "percent", 20);

    await payWithCustomCardAndCoupon(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form, createdCouponCode);
    const orderId = orderIdFromReceivedUrl(page);

    assertCouponApplied(orderId, 20);
  });

  test("Given a 20% coupon and a 10% gateway discount, When the buyer applies the coupon and pays, Should apply the gateway discount on the post-coupon amount", async ({ page }) => {
    setDiscount(CUSTOM_GATEWAY, 10);
    createdCouponCode = `e2e-amount-${Date.now()}`;
    createCoupon(createdCouponCode, "percent", 20);

    await payWithCustomCardAndCoupon(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form, createdCouponCode);
    const orderId = orderIdFromReceivedUrl(page);

    assertCouponThenDiscount(orderId, { couponPct: 20, discountPct: 10 });
  });
});

test.describe("currency conversion @serial-store", () => {
  test("Given the store currency differs from the MP account and conversion is on, When the buyer pays with an approved card, Should apply the live API ratio to the order", async ({ page }) => {
    // MLB account settles in BRL; switching the store to USD forces a real conversion.
    wpOption("woocommerce_currency", "USD");
    setCurrencyConversion(CUSTOM_GATEWAY, true);

    await successfulPaymentTest(page, shop_url, guestUserMLB, APPROVED.master, APPROVED.form);
    const orderId = orderIdFromReceivedUrl(page);

    assertCurrencyConverted(orderId);
  });
});
