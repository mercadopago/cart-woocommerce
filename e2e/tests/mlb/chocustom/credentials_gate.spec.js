import { test } from "@playwright/test";
import { mlb } from "../../../data/meli_sites";
import {
  snapshotAndClearMpCredentials,
  restoreMpCredentials,
  assertCustomGatewayHidden,
} from "../../../flows/credentials_gate";

/**
 * With no Mercado Pago credentials configured, the MP Custom gateway must not appear
 * at checkout (Classic or Blocks). The credentials are cleared for the test and
 * restored in a finally block so the store is left untouched.
 *
 * @serial-store: clearing the store-wide credential options would break any other
 * checkout spec loading/submitting concurrently, so this must not run in the parallel
 * phase. run-all-report.sh runs @serial-store specs alone (workers=1).
 */

const { shop_url, guestUserMLB } = mlb;

test.describe('Mercado Pago gateway without credentials @serial-store', () => {
  test('Given a store without Mercado Pago credentials, When the checkout is loaded, Then the Mercado Pago Custom gateway is not offered', async ({ page }) => {
    test.setTimeout(120000);
    const snapshot = snapshotAndClearMpCredentials();
    try {
      await assertCustomGatewayHidden(page, shop_url, guestUserMLB);
    } finally {
      restoreMpCredentials(snapshot);
    }
  });
});
